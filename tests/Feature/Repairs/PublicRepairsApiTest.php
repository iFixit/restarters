<?php

namespace Tests\Feature\Repairs;

use App\Models\ApiClient;
use App\Models\Barrier;
use App\Models\Category;
use App\Models\Device;
use App\Models\Group;
use App\Models\Network;
use App\Models\Party;
use App\Services\Ords\OrdsRecordMapper;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * @see app/Http/Controllers/API/PublicRepairController.php
 * @see config/ords.php
 */
class PublicRepairsApiTest extends TestCase
{
    private const EVENT_START_UTC = '2024-06-15 18:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'restarters.features.public_events_api' => true,
            'restarters.features.public_repairs_api' => true,
            // Production ships with the placeholder, which the controller
            // refuses to serve under; tests need a usable namespace.
            'ords.id_prefix' => 'testinstance_',
            'ords.data_provider' => 'Test Repair Org',
            'ords.problem.include' => true,
            'ords.problem.scrub' => true,
        ]);
    }

    // ---------------------------------------------------------------- auth

    public function test_requires_bearer_token(): void
    {
        $this->get('/api/public/v2/repairs')->assertStatus(401);
    }

    public function test_ignores_query_token_auth(): void
    {
        $this->get('/api/public/v2/repairs?api_token=not_a_valid_public_key')->assertStatus(401);
    }

    public function test_events_scope_is_forbidden_on_repairs(): void
    {
        $token = $this->createPublicApiToken(['scopes' => ['events:read']]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/public/v2/repairs')
            ->assertStatus(403);
    }

    public function test_repairs_scope_is_forbidden_on_events(): void
    {
        $token = $this->createPublicApiToken(['scopes' => ['repairs:read']]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/public/v2/events')
            ->assertStatus(403);
    }

    public function test_enforces_allowed_origins_when_configured(): void
    {
        $this->seedRepair();
        $token = $this->createPublicApiToken(['allowed_origins' => ['https://allowed.example']]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Origin' => 'https://disallowed.example',
        ])->get('/api/public/v2/repairs')->assertStatus(403);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Origin' => 'https://allowed.example',
        ])->get('/api/public/v2/repairs')->assertSuccessful();
    }

    // ------------------------------------------------------- feature flags

    public function test_route_is_absent_when_repairs_flag_is_off(): void
    {
        $this->withExceptionHandling();
        config(['restarters.features.public_repairs_api' => false]);

        $token = $this->createPublicApiToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/public/v2/repairs')
            ->assertStatus(404);
    }

    public function test_events_api_still_works_when_repairs_flag_is_off(): void
    {
        // The flags are split so repairs can ship dark without taking the live
        // events API down with it.
        config(['restarters.features.public_repairs_api' => false]);

        $token = $this->createPublicApiToken(['scopes' => ['events:read']]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/public/v2/events')
            ->assertSuccessful();
    }

    public function test_events_route_is_absent_when_events_flag_is_off_but_repairs_is_on(): void
    {
        $this->withExceptionHandling();
        config(['restarters.features.public_events_api' => false]);

        $token = $this->createPublicApiToken(['scopes' => ['events:read', 'repairs:read']]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/public/v2/events')
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/public/v2/repairs')
            ->assertSuccessful();
    }

    public function test_preflight_is_absent_when_both_flags_are_off(): void
    {
        // A dark instance should not answer preflight with CORS headers.
        $this->withExceptionHandling();
        config([
            'restarters.features.public_events_api' => false,
            'restarters.features.public_repairs_api' => false,
        ]);

        $this->options('/api/public/v2/repairs')->assertStatus(404);
        $this->options('/api/public/v2/events')->assertStatus(404);
    }

    public function test_preflight_answers_when_a_scope_is_live(): void
    {
        config(['restarters.features.public_repairs_api' => false]);

        $this->options('/api/public/v2/repairs')
            ->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
    }

    // ------------------------------------------------------ id namespace

    public function test_refuses_to_serve_under_an_unassigned_id_namespace(): void
    {
        // ORDS ids are a stable key that ORA updates in place across releases,
        // and `fixitclinic_` already holds published rows overlapping our
        // auto-increment range. Guessing a prefix would overwrite real data.
        config(['ords.id_prefix' => OrdsRecordMapper::UNASSIGNED_ID_PREFIX]);

        $this->seedRepair();
        $token = $this->createPublicApiToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/public/v2/repairs');

        $response->assertStatus(503);
        $this->assertStringContainsString('id namespace', $response->json('message'));
    }

    public function test_refuses_to_serve_under_a_blank_id_namespace(): void
    {
        // An env var set to "" yields an empty string rather than falling back
        // to the config default, so the chart shipping ORDS_ID_PREFIX="" must
        // not be mistaken for a configured export.
        config(['ords.id_prefix' => '']);

        $this->seedRepair();
        $token = $this->createPublicApiToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/public/v2/repairs')
            ->assertStatus(503);
    }

    public function test_refuses_to_serve_without_a_data_provider(): void
    {
        // data_provider is the attribution carried on every published row.
        config(['ords.data_provider' => '']);

        $this->seedRepair();
        $token = $this->createPublicApiToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/public/v2/repairs');

        $response->assertStatus(503);
        $this->assertStringContainsString('data provider', $response->json('message'));
    }

    public function test_emits_ids_under_the_assigned_ora_namespace(): void
    {
        // The Open Repair Alliance assigned this instance `ifixit_`. Set in
        // deployment config, not defaulted in code, but pinned here so a change
        // to the emitted id shape cannot pass silently: ORA upserts on this id,
        // so it must stay stable once we have published under it.
        config(['ords.id_prefix' => 'ifixit_']);

        $device = $this->seedRepair();

        $this->assertEquals(
            'ifixit_' . $device->iddevices,
            $this->fetchRecords()[0]['id']
        );
    }

    public function test_a_padded_id_prefix_is_trimmed_before_emission(): void
    {
        // The config guard validates the trimmed prefix, so the emitted id has
        // to be trimmed too or " ifixit_ " passes the guard and publishes ids
        // carrying a leading space.
        config(['ords.id_prefix' => ' ifixit_ ']);

        $device = $this->seedRepair();

        $this->assertSame('ifixit_' . $device->iddevices, $this->fetchRecords()[0]['id']);
    }

    // ------------------------------------------------------------ columns

    public function test_emits_the_fourteen_ords_columns_in_spec_order(): void
    {
        $this->seedRepair();

        $record = $this->fetchRecords()[0];

        $this->assertEquals([
            'id',
            'data_provider',
            'country',
            'partner_product_category',
            'product_category',
            'product_category_id',
            'brand',
            'year_of_manufacture',
            'product_age',
            'repair_status',
            'repair_barrier_if_end_of_life',
            'group_identifier',
            'event_date',
            'problem',
        ], array_keys($record));
    }

    public function test_maps_every_column_from_the_fixture(): void
    {
        $device = $this->seedRepair([
            'brand' => 'Acme',
            'item_type' => 'Tower PC',
            'age' => 5,
            'problem' => 'Would not power on. Replaced the PSU.',
            'repair_status' => Device::REPAIR_STATUS_FIXED,
        ]);

        $record = $this->fetchRecords()[0];

        $this->assertEquals('testinstance_' . $device->iddevices, $record['id']);
        $this->assertEquals('Test Repair Org', $record['data_provider']);
        // groups.country_code is alpha-2; ORDS requires alpha-3.
        $this->assertEquals('GBR', $record['country']);
        // Restart's own published rows use "<category> ~ <item_type>".
        $this->assertEquals('Desktop computer ~ Tower PC', $record['partner_product_category']);
        $this->assertEquals('Desktop computer', $record['product_category']);
        // Our idcategories is 11; ORA publishes Desktop computer as 4.
        $this->assertEquals(4, $record['product_category_id']);
        $this->assertEquals('Acme', $record['brand']);
        // 2024 event minus a 5 year old item.
        $this->assertEquals('2019', $record['year_of_manufacture']);
        $this->assertEquals(5, $record['product_age']);
        $this->assertEquals('Fixed', $record['repair_status']);
        $this->assertNull($record['repair_barrier_if_end_of_life']);
        $this->assertEquals('ORDS Group', $record['group_identifier']);
        $this->assertEquals('2024-06-15', $record['event_date']);
        $this->assertEquals('Would not power on. Replaced the PSU.', $record['problem']);
    }

    public function test_event_date_uses_the_events_local_timezone(): void
    {
        // 2024-06-16 06:00 UTC is still 2024-06-15 in Los Angeles.
        $this->seedRepair([], ['start_utc' => '2024-06-16 06:00:00', 'timezone' => 'America/Los_Angeles']);

        $this->assertEquals('2024-06-15', $this->fetchRecords()[0]['event_date']);
    }

    public function test_repair_status_falls_back_to_unknown(): void
    {
        // `devices.repair_status` is NOT NULL DEFAULT 0, and ORDS carries
        // Unknown as a real enum value rather than a blank.
        $this->seedRepair(['repair_status' => 0]);

        $this->assertEquals('Unknown', $this->fetchRecords()[0]['repair_status']);
    }

    public function test_year_of_manufacture_and_age_are_omitted_when_age_is_not_recorded(): void
    {
        // `devices.age` is DECIMAL NOT NULL DEFAULT 0, so 0 is how "we did not
        // record an age" is stored rather than a real age of zero.
        $this->seedRepair(['age' => 0]);

        $record = $this->fetchRecords()[0];
        $this->assertNull($record['year_of_manufacture']);
        $this->assertNull($record['product_age']);
    }

    public function test_fractional_age_is_preserved(): void
    {
        $this->seedRepair(['age' => 2.5]);

        $record = $this->fetchRecords()[0];
        $this->assertEquals(2.5, $record['product_age']);
        // 2024 minus 2.5 years, rounded to a whole year.
        $this->assertEquals('2022', $record['year_of_manufacture']);
    }

    public function test_barrier_is_emitted_only_for_end_of_life_and_uses_ords_wording(): void
    {
        // We seed "No way to open the product"; ORDS publishes it without "the".
        $barrier = Barrier::where('barrier', 'No way to open the product')->firstOrFail();

        $device = $this->seedRepair(['repair_status' => Device::REPAIR_STATUS_ENDOFLIFE]);
        $device->barriers()->attach($barrier->id);

        $this->assertEquals(
            'No way to open product',
            $this->fetchRecords()[0]['repair_barrier_if_end_of_life']
        );
    }

    public function test_barrier_choice_is_stable_across_exports(): void
    {
        // ORDS has one barrier column but a device can carry several, so the
        // mapper takes the first. The record id is a stable key ORA upserts on,
        // so an unordered relation would republish a different barrier at random.
        $first = Barrier::where('barrier', 'Lack of equipment')->firstOrFail();
        $second = Barrier::where('barrier', 'Spare parts too expensive')->firstOrFail();
        [$lower, $higher] = $first->id < $second->id ? [$first, $second] : [$second, $first];

        $device = $this->seedRepair(['repair_status' => Device::REPAIR_STATUS_ENDOFLIFE]);
        // Attached highest-first so insertion order cannot be what makes this pass.
        $device->barriers()->attach($higher->id);
        $device->barriers()->attach($lower->id);

        $expected = config('ords.barriers')[$lower->barrier];

        $this->assertEquals($expected, $this->fetchRecords()[0]['repair_barrier_if_end_of_life']);
        $this->assertEquals($expected, $this->fetchRecords()[0]['repair_barrier_if_end_of_life']);
    }

    public function test_an_unmapped_barrier_is_omitted(): void
    {
        // A barrier outside the ORDS vocabulary is a mapping gap; emitting our
        // own wording would put an invalid value in a constrained column.
        $barrier = Barrier::create(['barrier' => 'Not an ORDS barrier']);

        $device = $this->seedRepair(['repair_status' => Device::REPAIR_STATUS_ENDOFLIFE]);
        $device->barriers()->attach($barrier->id);

        $this->assertNull($this->fetchRecords()[0]['repair_barrier_if_end_of_life']);
    }

    public function test_barrier_is_suppressed_when_the_item_was_repaired(): void
    {
        $barrier = Barrier::where('barrier', 'Lack of equipment')->firstOrFail();

        $device = $this->seedRepair(['repair_status' => Device::REPAIR_STATUS_FIXED]);
        $device->barriers()->attach($barrier->id);

        $this->assertNull($this->fetchRecords()[0]['repair_barrier_if_end_of_life']);
    }

    /**
     * The vocabulary maps were originally built from the 2018 seed and missed
     * the seven categories added in 2021, which then exported with a null
     * product_category_id. Reads the category names straight out of the
     * migrations so a future addition fails here rather than degrading quietly.
     */
    public function test_every_seeded_category_has_a_vocabulary_mapping(): void
    {
        $powered = array_keys(config('ords.categories_powered'));
        $unpowered = array_keys(config('ords.categories_unpowered'));

        $seeded = [];
        foreach (glob(database_path('migrations/*.php')) as $migration) {
            $source = file_get_contents($migration);

            // Names from the initialise migration's bulk INSERT.
            if (preg_match_all('/\(\d+,\s*"([^"]+)",/', $source, $m)) {
                $seeded = array_merge($seeded, $m[1]);
            }
            // Names from the later insert()/update() calls.
            if (preg_match_all("/'name'\s*=>\s*'([^']+)'/", $source, $m)) {
                $seeded = array_merge($seeded, $m[1]);
            }
        }

        // Only names that are actually category rows on a migrated database.
        $known = array_merge($powered, $unpowered);
        $categoryNames = array_intersect(array_unique($seeded), $known);

        $this->assertNotEmpty($categoryNames, 'no category names parsed from migrations');

        foreach (['Games console', 'Watch/clock', 'Sewing machine', 'Iron', 'Coffee maker'] as $late) {
            $this->assertContains($late, $powered, "{$late} is seeded but has no powered mapping");
        }

        foreach (['Jewellery', 'Hand tool'] as $late) {
            $this->assertContains($late, $unpowered, "{$late} is seeded but has no unpowered mapping");
        }
    }

    public function test_unpowered_categories_use_the_ords_unpowered_vocabulary(): void
    {
        Category::factory()->create([
            'idcategories' => 501,
            'name' => 'Clothing/textile',
            'revision' => 2,
            'aggregate' => 0,
            'powered' => 0,
        ]);

        $this->seedRepair(['category' => 501, 'category_creation' => 501, 'item_type' => null]);

        $record = $this->fetchRecords()[0];
        $this->assertEquals('Unpowered - Textile', $record['product_category']);
        // ORA's unpowered dataset carries no product_category_id.
        $this->assertNull($record['product_category_id']);
        $this->assertEquals('Clothing/textile', $record['partner_product_category']);
    }

    public function test_an_unmapped_powered_category_falls_back_to_our_own_name(): void
    {
        // A category outside the ORDS vocabulary is a mapping gap, not a data
        // error. The record stays usable and the gap is visible in the export.
        Category::factory()->create([
            'idcategories' => 504,
            'name' => '3D printer',
            'revision' => 2,
            'aggregate' => 0,
            'powered' => 1,
        ]);

        $this->seedRepair(['category' => 504, 'category_creation' => 504, 'item_type' => null]);

        $record = $this->fetchRecords()[0];
        $this->assertEquals('3D printer', $record['product_category']);
        $this->assertNull($record['product_category_id']);
        $this->assertEquals('3D printer', $record['partner_product_category']);
    }

    public function test_country_is_null_when_it_cannot_be_mapped_to_alpha3(): void
    {
        // ORDS requires alpha-3. `groups.country_code` is alpha-2 and nullable,
        // and nothing constrains it to a real code.
        $this->seedRepair([], ['country_code' => null]);
        $this->assertNull($this->fetchRecords()[0]['country']);

        $this->seedRepair([], ['group' => 'Unknown Country Group', 'country_code' => 'ZZ']);
        $countries = array_column($this->fetchRecords(), 'country');
        $this->assertEquals([null, null], $countries);
    }

    // ---------------------------------------------------------- visibility

    public function test_excludes_unapproved_events_unapproved_groups_and_soft_deletes(): void
    {
        $visible = $this->seedRepair();

        $unapprovedEventDevice = $this->seedRepair([], ['group' => 'Second Group', 'approve_event' => false]);
        $unapprovedGroupDevice = $this->seedRepair([], ['group' => 'Third Group', 'approve_group' => false]);

        $deletedEventDevice = $this->seedRepair([], ['group' => 'Fourth Group']);
        Party::findOrFail($deletedEventDevice->event)->delete();

        $deletedGroupDevice = $this->seedRepair([], ['group' => 'Fifth Group']);
        Group::findOrFail(Party::findOrFail($deletedGroupDevice->event)->group)->delete();

        $ids = array_column($this->fetchRecords(), 'id');

        $this->assertContains('testinstance_' . $visible->iddevices, $ids);
        $this->assertNotContains('testinstance_' . $unapprovedEventDevice->iddevices, $ids);
        $this->assertNotContains('testinstance_' . $unapprovedGroupDevice->iddevices, $ids);
        $this->assertNotContains('testinstance_' . $deletedEventDevice->iddevices, $ids);
        $this->assertNotContains('testinstance_' . $deletedGroupDevice->iddevices, $ids);
    }

    public function test_respects_allowed_network_restrictions(): void
    {
        $allowedDevice = $this->seedRepair([], ['group' => 'Allowed Group']);
        $blockedDevice = $this->seedRepair([], ['group' => 'Blocked Group']);

        $allowedNetwork = Network::factory()->create();
        $blockedNetwork = Network::factory()->create();
        $allowedNetwork->addGroup(Group::findOrFail(Party::findOrFail($allowedDevice->event)->group));
        $blockedNetwork->addGroup(Group::findOrFail(Party::findOrFail($blockedDevice->event)->group));

        $ids = array_column(
            $this->fetchRecords(['allowed_network_ids' => [$allowedNetwork->id]]),
            'id'
        );

        $this->assertContains('testinstance_' . $allowedDevice->iddevices, $ids);
        $this->assertNotContains('testinstance_' . $blockedDevice->iddevices, $ids);
    }

    public function test_a_group_in_several_allowed_networks_is_not_duplicated(): void
    {
        $device = $this->seedRepair();
        $group = Group::findOrFail(Party::findOrFail($device->event)->group);

        $networkOne = Network::factory()->create();
        $networkTwo = Network::factory()->create();
        $networkOne->addGroup($group);
        $networkTwo->addGroup($group);

        $records = $this->fetchRecords(['allowed_network_ids' => [$networkOne->id, $networkTwo->id]]);

        $this->assertCount(1, $records);
    }

    // ------------------------------------------------------------ filters

    public function test_powered_filter_selects_each_dataset(): void
    {
        Category::factory()->create([
            'idcategories' => 502,
            'name' => 'Bicycle',
            'revision' => 2,
            'aggregate' => 0,
            'powered' => 0,
        ]);

        $powered = $this->seedRepair();
        $unpowered = $this->seedRepair(['category' => 502, 'category_creation' => 502], ['reuse' => true]);

        $poweredIds = array_column($this->fetchRecords([], ['powered' => 1]), 'id');
        $this->assertContains('testinstance_' . $powered->iddevices, $poweredIds);
        $this->assertNotContains('testinstance_' . $unpowered->iddevices, $poweredIds);

        $unpoweredIds = array_column($this->fetchRecords([], ['powered' => 0]), 'id');
        $this->assertContains('testinstance_' . $unpowered->iddevices, $unpoweredIds);
        $this->assertNotContains('testinstance_' . $powered->iddevices, $unpoweredIds);

        // Unfiltered returns both: ORA publishes them as separate datasets, but
        // the endpoint does not silently drop half the data.
        $this->assertCount(2, $this->fetchRecords());
    }

    public function test_powered_filter_accepts_true_and_false_spellings(): void
    {
        // Laravel's `boolean` rule takes only true/false/0/1, and a rejected
        // value surfaces as a 500 here, so `?powered=true` used to break.
        Category::factory()->create([
            'idcategories' => 503,
            'name' => 'Bicycle',
            'revision' => 2,
            'aggregate' => 0,
            'powered' => 0,
        ]);

        $powered = $this->seedRepair();
        $unpowered = $this->seedRepair(['category' => 503, 'category_creation' => 503], ['reuse' => true]);

        $trueIds = array_column($this->fetchRecords([], ['powered' => 'true']), 'id');
        $this->assertContains('testinstance_' . $powered->iddevices, $trueIds);
        $this->assertNotContains('testinstance_' . $unpowered->iddevices, $trueIds);

        $falseIds = array_column($this->fetchRecords([], ['powered' => 'false']), 'id');
        $this->assertContains('testinstance_' . $unpowered->iddevices, $falseIds);
        $this->assertNotContains('testinstance_' . $powered->iddevices, $falseIds);
    }

    public function test_a_date_only_event_end_includes_that_whole_day(): void
    {
        // The fixture event runs at 18:00 on 2024-06-15. A caller asking for a
        // window ending on that date means to include it.
        $this->seedRepair();

        $this->assertCount(1, $this->fetchRecords([], ['event_end' => '2024-06-15']));
        $this->assertEmpty($this->fetchRecords([], ['event_end' => '2024-06-14']));
    }

    public function test_updated_since_filter(): void
    {
        $device = $this->seedRepair();
        $device->timestamps = false;
        $device->updated_at = '2000-01-01 00:00:00';
        $device->save();

        $this->assertEmpty($this->fetchRecords([], ['updated_since' => '2010-01-01T00:00:00+00:00']));
        $this->assertCount(1, $this->fetchRecords([], ['updated_since' => '1999-01-01T00:00:00+00:00']));
    }

    public function test_event_window_filters(): void
    {
        $device = $this->seedRepair();

        $this->assertCount(1, $this->fetchRecords([], [
            'event_start' => '2024-01-01T00:00:00+00:00',
            'event_end' => '2024-12-31T00:00:00+00:00',
        ]));

        $this->assertEmpty($this->fetchRecords([], ['event_start' => '2025-01-01T00:00:00+00:00']));
        $this->assertEmpty($this->fetchRecords([], ['event_end' => '2023-01-01T00:00:00+00:00']));

        $this->assertNotNull($device->iddevices);
    }

    /**
     * Note: this codebase surfaces a failed validation as a 500 carrying the
     * raw translation key rather than a 422 — the already-live events endpoint
     * does the same for per_page > 100. These tests assert that the input is
     * rejected rather than asserting the status, so they cover the ceiling
     * without baking in that pre-existing behaviour.
     */
    public function test_per_page_ceiling_is_one_thousand(): void
    {
        $this->withExceptionHandling();
        $this->seedRepair();
        $token = $this->createPublicApiToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/public/v2/repairs?per_page=1000')
            ->assertSuccessful()
            ->assertJsonPath('meta.per_page', 1000);

        // The sibling events endpoint caps at 100, which is too low for a bulk
        // export, but the ceiling still has to hold.
        $rejected = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/public/v2/repairs?per_page=1001');

        $this->assertFalse($rejected->isSuccessful());
        $this->assertStringContainsString('validation.max', $rejected->json('message'));
    }

    public function test_pages_cover_every_record_exactly_once(): void
    {
        // A bulk consumer walks this endpoint page by page, so the pages must
        // partition the result set: no row skipped, none served twice.
        $devices = [
            $this->seedRepair(),
            $this->seedRepair([], ['reuse' => true]),
            $this->seedRepair([], ['reuse' => true]),
        ];

        $expected = array_map(fn (Device $d) => 'testinstance_' . $d->iddevices, $devices);

        $token = $this->createPublicApiToken();

        $first = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/public/v2/repairs?per_page=2');
        $first->assertSuccessful()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);

        $second = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/public/v2/repairs?per_page=2&page=2');
        $second->assertSuccessful()->assertJsonPath('meta.page', 2);

        $firstIds = array_column($first->json('data'), 'id');
        $secondIds = array_column($second->json('data'), 'id');
        $all = array_merge($firstIds, $secondIds);

        $this->assertCount(2, $firstIds);
        $this->assertCount(1, $secondIds);
        $this->assertSame($all, array_unique($all));
        $this->assertEqualsCanonicalizing($expected, $all);
    }

    public function test_rejects_an_unknown_format(): void
    {
        $this->withExceptionHandling();
        $token = $this->createPublicApiToken();

        $rejected = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/public/v2/repairs?format=xml');

        $this->assertFalse($rejected->isSuccessful());
        $this->assertStringContainsString('validation.in', $rejected->json('message'));
    }

    public function test_reports_the_standard_and_columns_in_meta(): void
    {
        $this->seedRepair();
        $token = $this->createPublicApiToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/public/v2/repairs')
            ->assertSuccessful()
            // Pinned as a literal: this is the standard version the record
            // shape implements, so a change to the constant should fail here.
            ->assertJsonPath('meta.standard', 'Open Repair Data Standard v0.3')
            ->assertJsonPath('meta.columns', OrdsRecordMapper::COLUMNS)
            ->assertJsonStructure(['sync' => ['generated_at', 'max_updated_at']]);
    }

    // ---------------------------------------------------------------- CSV

    public function test_csv_output_matches_the_ords_column_order(): void
    {
        $device = $this->seedRepair([
            'brand' => 'Acme',
            'item_type' => 'Tower PC',
            'age' => 5,
            'repair_status' => Device::REPAIR_STATUS_FIXED,
            'problem' => 'Would not power on.',
        ]);

        $rows = $this->fetchCsvRows();

        $this->assertEquals(OrdsRecordMapper::COLUMNS, $rows[0]);
        $this->assertEquals([
            'testinstance_' . $device->iddevices,
            'Test Repair Org',
            'GBR',
            'Desktop computer ~ Tower PC',
            'Desktop computer',
            '4',
            'Acme',
            '2019',
            '5',
            'Fixed',
            '',
            'ORDS Group',
            '2024-06-15',
            'Would not power on.',
        ], $rows[1]);
    }

    public function test_csv_writes_empty_strings_for_missing_values(): void
    {
        // ORDS declares "" as the missing value for every optional column.
        $this->seedRepair(['brand' => null, 'age' => 0, 'problem' => '']);

        $row = $this->fetchCsvRows()[1];
        $columns = array_combine(OrdsRecordMapper::COLUMNS, $row);

        $this->assertSame('', $columns['brand']);
        $this->assertSame('', $columns['year_of_manufacture']);
        $this->assertSame('', $columns['product_age']);
        $this->assertSame('', $columns['problem']);
        $this->assertSame('', $columns['repair_barrier_if_end_of_life']);
    }

    public function test_csv_neutralises_spreadsheet_formulas(): void
    {
        // `problem` is volunteer free text, and Excel and Sheets execute a cell
        // opening with = + - @ as a formula the moment the file is opened.
        $this->seedRepair(['problem' => '=HYPERLINK("http://example.com","click")']);

        $row = $this->fetchCsvRows()[1];
        $columns = array_combine(OrdsRecordMapper::COLUMNS, $row);

        $this->assertSame(
            '\'=HYPERLINK("http://example.com","click")',
            $columns['problem']
        );
    }

    public function test_csv_carries_the_pagination_metadata_in_headers(): void
    {
        // CSV has no envelope for `meta`/`sync`, so without these a bulk
        // consumer cannot tell that a second page exists.
        $this->seedRepair();
        $this->seedRepair([], ['reuse' => true]);
        $this->seedRepair([], ['reuse' => true]);

        $token = $this->createPublicApiToken();
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/public/v2/repairs?format=csv&per_page=2');
        $response->assertSuccessful();

        $this->assertSame('3', $response->headers->get('X-Total-Count'));
        $this->assertSame('1', $response->headers->get('X-Page'));
        $this->assertSame('2', $response->headers->get('X-Per-Page'));
        $this->assertSame('2', $response->headers->get('X-Last-Page'));
        $this->assertNotEmpty($response->headers->get('X-Max-Updated-At'));
    }

    // ---------------------------------------------------------- redaction

    public function test_problem_text_is_scrubbed(): void
    {
        $this->seedRepair([
            'problem' => '<p>Owner jane@example.com, call 020 7946 0958.</p> '
                . 'Serial 123456789012. Part https://example.com/p?gclid=ABC123',
        ]);

        $problem = $this->fetchRecords()[0]['problem'];

        $this->assertStringNotContainsString('jane@example.com', $problem);
        $this->assertStringNotContainsString('020 7946 0958', $problem);
        $this->assertStringNotContainsString('123456789012', $problem);
        $this->assertStringNotContainsString('gclid', $problem);
        $this->assertStringNotContainsString('<p>', $problem);
        $this->assertStringContainsString('https://example.com/p', $problem);
    }

    public function test_scrubbing_can_be_disabled(): void
    {
        config(['ords.problem.scrub' => false]);
        $this->seedRepair(['problem' => 'Owner jane@example.com']);

        $this->assertEquals('Owner jane@example.com', $this->fetchRecords()[0]['problem']);
    }

    public function test_problem_can_be_withheld_entirely(): void
    {
        // Supports a structured-fields-only export with no free text at all.
        config(['ords.problem.include' => false]);
        $this->seedRepair(['problem' => 'Owner jane@example.com']);

        $record = $this->fetchRecords()[0];
        $this->assertArrayHasKey('problem', $record);
        $this->assertNull($record['problem']);
    }

    // ------------------------------------------------------------ helpers

    /**
     * @param array<string,mixed> $deviceAttributes
     * @param array<string,mixed> $context
     */
    private function seedRepair(array $deviceAttributes = [], array $context = []): Device
    {
        // TestCase::setUp truncates categories, so the fixture owns its own.
        if (!Category::find(11)) {
            Category::factory()->desktopComputer()->create();
        }

        if (!empty($context['reuse'])) {
            $eventId = Party::query()->orderBy('idevents', 'desc')->firstOrFail()->idevents;
        } else {
            // Groups and events are built straight from the factories rather
            // than through TestCase::createGroup/createEvent, which post to the
            // v2 API and geocode the location against the live Google Maps
            // service. This fixture needs an exact country_code, event start and
            // timezone anyway, so the round trip would only be overwritten.
            $group = Group::factory()->create([
                'name' => $context['group'] ?? 'ORDS Group',
                // array_key_exists, not ??, so a test can ask for a null code.
                'country_code' => array_key_exists('country_code', $context) ? $context['country_code'] : 'GB',
                'approved' => $context['approve_group'] ?? true,
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'location' => 'London',
            ]);

            $startUtc = $context['start_utc'] ?? self::EVENT_START_UTC;

            $event = Party::factory()->create([
                'group' => $group->idgroups,
                'approved' => $context['approve_event'] ?? true,
                'event_start_utc' => $startUtc,
                'event_end_utc' => Carbon::parse($startUtc)->addHours(2)->toDateTimeString(),
                'timezone' => $context['timezone'] ?? 'Europe/London',
            ]);

            $eventId = $event->idevents;
        }

        return Device::create(array_merge([
            'event' => $eventId,
            'category' => 11,
            'category_creation' => 11,
            'brand' => 'Acme',
            'item_type' => 'Tower PC',
            'age' => 5,
            'problem' => 'Would not power on.',
            'repair_status' => Device::REPAIR_STATUS_FIXED,
        ], $deviceAttributes));
    }

    /**
     * @param array<string,mixed> $clientAttributes
     * @param array<string,mixed> $query
     * @return array<int,array<string,mixed>>
     */
    private function fetchRecords(array $clientAttributes = [], array $query = []): array
    {
        $token = $this->createPublicApiToken($clientAttributes);
        $url = '/api/public/v2/repairs' . ($query ? '?' . http_build_query($query) : '');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson($url);
        $response->assertSuccessful();

        return $response->json('data');
    }

    /**
     * @return array<int,array<int,string>>
     */
    private function fetchCsvRows(): array
    {
        $token = $this->createPublicApiToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/public/v2/repairs?format=csv');
        $response->assertSuccessful();

        $body = trim($response->streamedContent());
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $body);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function createPublicApiToken(array $attributes = []): string
    {
        $token = 'public_api_token_' . uniqid();

        ApiClient::factory()->create(array_merge([
            'token_hash' => hash('sha256', $token),
            'scopes' => ['repairs:read'],
            'active' => true,
            'expires_at' => null,
        ], $attributes));

        return $token;
    }
}
