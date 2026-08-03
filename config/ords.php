<?php

use App\Models\Device;
use App\Services\Ords\OrdsRecordMapper;

// Configuration for the ORDS export. See docs/public-repairs-api.md.
//
// The vocabulary maps follow the Open Repair Alliance's published data at
// github.com/openrepair/data rather than its tableschema.json, which is stale
// in places. Where the two disagree the difference is noted below.

return [

    // Instance-specific and deliberately unusable by default: the endpoint
    // returns 503 while this is the placeholder or blank. ORDS ids are a stable
    // key the consumer upserts on across releases, so publishing under an
    // unassigned or borrowed namespace overwrites another provider's records.
    // Set it per deployment; do not guess a value for a new instance.
    'id_prefix' => env('ORDS_ID_PREFIX', OrdsRecordMapper::UNASSIGNED_ID_PREFIX),

    // Organisation name emitted on every record. Undefaulted for the same
    // reason as the prefix: a blank credits the data to nobody.
    'data_provider' => env('ORDS_DATA_PROVIDER', ''),

    // `include` drops the column for a structured-fields-only export. `scrub`
    // is the redaction pass. Personal names are not pattern-detectable and are
    // not removed.
    'problem' => [
        'include' => env('ORDS_INCLUDE_PROBLEM', true),
        'scrub' => env('ORDS_SCRUB_PROBLEM', true),
    ],

    // Higher ceiling than the events endpoint's 100: this is a bulk export.
    'pagination' => [
        'default_per_page' => 100,
        'max_per_page' => 1000,
    ],

    // Unknown is a real value in the published data, not a blank, and is what
    // the 0 default on `devices.repair_status` maps to.
    'repair_status' => [
        Device::REPAIR_STATUS_FIXED => 'Fixed',
        Device::REPAIR_STATUS_REPAIRABLE => 'Repairable',
        Device::REPAIR_STATUS_ENDOFLIFE => 'End of life',
    ],

    'repair_status_unknown' => 'Unknown',

    // Our seeded barriers match the published vocabulary except for the "the"
    // in "No way to open the product". "Item too worn out" is published but not
    // seeded here; mapped so an instance that adds it is covered.
    'barriers' => [
        'Spare parts not available' => 'Spare parts not available',
        'Spare parts too expensive' => 'Spare parts too expensive',
        'No way to open the product' => 'No way to open product',
        'No way to open product' => 'No way to open product',
        'Repair information not available' => 'Repair information not available',
        'Lack of equipment' => 'Lack of equipment',
        'Item too worn out' => 'Item too worn out',
        'Too worn out' => 'Item too worn out',
    ],

    // Keyed on `categories.name` where `categories.powered` is true.
    //
    // Names mostly pass straight through, but the ids do not line up (our
    // "Desktop computer" is idcategories 11, product_category_id 4) and the
    // standard collapses our screen-size and laptop-size splits into one
    // category each, which is confirmed against The Restart Project's own
    // published rows.
    'categories_powered' => [
        'Desktop computer' => ['Desktop computer', 4],
        'Flat screen 15-17"' => ['Flat screen', 8],
        'Flat screen 19-20"' => ['Flat screen', 8],
        'Flat screen 22-24"' => ['Flat screen', 8],
        'Flat screen 26-30"' => ['Flat screen', 8],
        'Flat screen 32-37"' => ['Flat screen', 8],
        'Laptop large' => ['Laptop', 16],
        'Laptop medium' => ['Laptop', 16],
        'Laptop small' => ['Laptop', 16],
        'Paper shredder' => ['Paper shredder', 21],
        'PC accessory' => ['PC accessory', 22],
        'Printer/scanner' => ['Printer/scanner', 25],
        'Digital compact camera' => ['Digital compact camera', 5],
        'DSLR/video camera' => ['DSLR/video camera', 6],
        'Handheld entertainment device' => ['Handheld entertainment device', 10],
        'Headphones' => ['Headphones', 11],
        'Mobile' => ['Mobile', 19],
        'Tablet' => ['Tablet', 30],
        'Hi-Fi integrated' => ['Hi-Fi integrated', 12],
        'Hi-Fi separates' => ['Hi-Fi separates', 13],
        'Musical instrument' => ['Musical instrument', 20],
        'Portable radio' => ['Portable radio', 23],
        'Projector' => ['Projector', 26],
        'TV and gaming-related accessories' => ['TV and gaming-related accessories', 33],
        'Aircon/dehumidifier' => ['Aircon/dehumidifier', 1],
        'Decorative or safety lights' => ['Decorative or safety lights', 3],
        'Fan' => ['Fan', 7],
        'Hair & beauty item' => ['Hair & beauty item', 9],
        'Kettle' => ['Kettle', 14],
        'Lamp' => ['Lamp', 15],
        'Power tool' => ['Power tool', 24],
        'Small kitchen item' => ['Small kitchen item', 29],
        'Toaster' => ['Toaster', 31],
        'Toy' => ['Toy', 32],
        'Vacuum' => ['Vacuum', 34],
        'Misc' => ['Misc', 18],

        // Added by 2021_08_13_000439_update_lca_unpowered_categories.
        'Games console' => ['Games console', 38],
        'Watch/clock' => ['Watch/clock', 35],
        'Sewing machine' => ['Sewing machine', 27],
        'Iron' => ['Iron', 40],
        'Coffee maker' => ['Coffee maker', 36],

        // Pre-2021_10_04_124708_fix_category_names aliases, for instances that
        // never ran that migration.
        'PC Accessory' => ['PC accessory', 22],
        'Digital Compact Camera' => ['Digital compact camera', 5],
        'DLSR / Video Camera' => ['DSLR/video camera', 6],
        'Aircon/Dehumidifier' => ['Aircon/dehumidifier', 1],
        'Hair & Beauty item' => ['Hair & beauty item', 9],
    ],

    // Keyed on `categories.name` where `categories.powered` is false. Unpowered
    // repairs are published as a separate four-column dataset with no
    // product_category_id, so these carry the "Unpowered - X" name and a null
    // id. Callers wanting the 14-column aggregate shape should pass powered=1.
    'categories_unpowered' => [
        'Furniture' => 'Unpowered - Furniture',
        'Bicycle' => 'Unpowered - Bicycle',
        'Clothing/textile' => 'Unpowered - Textile',
        'Jewellery' => 'Unpowered - Jewellery',
        'Misc' => 'Unpowered - Other',
        // No published equivalent; "Household" is the nearest but overstates it.
        'Hand tool' => 'Unpowered - Other',
    ],

    'categories_unpowered_fallback' => 'Unpowered - Other',

];
