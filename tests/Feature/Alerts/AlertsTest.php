<?php

namespace Tests\Feature\Alerts;

use App\Alerts;
use App\Models\Role;
use Cache;
use DB;
use Hash;
use Tests\ApiTestCase;
use Illuminate\Auth\AuthenticationException;

class AlertsTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Only clear the cache
        Cache::clear('alerts');
    }

    public function testListNonePresent(): void
    {
        // List - no alerts present.
        $response = $this->getWithAuth('/api/v2/alerts');
        $response->assertSuccessful();

        $json = json_decode($response->getContent(), true);
        self::assertEquals(0, count($json['data']));
    }

    public function testCreateGuest(): void {
        // Guest users should not be able to create alerts
        $this->withoutExceptionHandling();
        $this->expectException(AuthenticationException::class);
        
        // Clear api user to ensure we're a guest
        $this->apiUser = null;
        
        // This should throw an exception
        $this->put('/api/v2/alerts', [
            'title' => 'Test alert',
            'html' => '<p>Test alert</p>',
            'start' => '2001-01-01T00:00:00Z',
            'end' => '2038-01-01T02:00:00Z',
        ]);
    }
    
    public function testCreateRestarter(): void {
        // Restarters should not be able to create alerts
        $this->withoutExceptionHandling();
        $this->expectException(AuthenticationException::class);
        
        // Use the trait for authentication with the restarter role
        $this->setupApiAuth(Role::RESTARTER);
        
        // This should throw an exception
        $this->putWithAuth('/api/v2/alerts', [
            'title' => 'Test alert',
            'html' => '<p>Test alert</p>',
            'start' => '2001-01-01T00:00:00Z',
            'end' => '2038-01-01T02:00:00Z',
        ]);
    }
    
    public function testCreateHost(): void {
        // Hosts should not be able to create alerts
        $this->withoutExceptionHandling();
        $this->expectException(AuthenticationException::class);
        
        // Use the trait for authentication with the host role
        $this->setupApiAuth(Role::HOST);
        
        // This should throw an exception
        $this->putWithAuth('/api/v2/alerts', [
            'title' => 'Test alert',
            'html' => '<p>Test alert</p>',
            'start' => '2001-01-01T00:00:00Z',
            'end' => '2038-01-01T02:00:00Z',
        ]);
    }
    
    public function testCreateNetworkCoordinator(): void {
        // Network coordinators should not be able to create alerts
        $this->withoutExceptionHandling();
        $this->expectException(AuthenticationException::class);
        
        // Use the trait for authentication with the network coordinator role
        $this->setupApiAuth(Role::NETWORK_COORDINATOR);
        
        // This should throw an exception
        $this->putWithAuth('/api/v2/alerts', [
            'title' => 'Test alert',
            'html' => '<p>Test alert</p>',
            'start' => '2001-01-01T00:00:00Z',
            'end' => '2038-01-01T02:00:00Z',
        ]);
    }
    
    public function testCreateAdmin(): void {
        // Admins should be able to create alerts
        $this->setupApiAuth(Role::ADMINISTRATOR);
        
        $response = $this->putWithAuth('/api/v2/alerts', [
            'title' => 'Test alert',
            'html' => '<p>Test alert</p>',
            'start' => '2001-01-01T00:00:00Z',
            'end' => '2038-01-01T02:00:00Z',
        ]);
        
        $response->assertSuccessful();
        $json = json_decode($response->getContent(), true);
        $id = $json['id'];
        self::assertNotNull($id);

        // Should be able to see it in the list.
        $response = $this->getWithAuth('/api/v2/alerts');
        $response->assertSuccessful();

        $json = json_decode($response->getContent(), true);
        self::assertEquals(1, count($json['data']));
        self::assertEquals($id, $json['data'][0]['id']);

        // Should be able to edit it.
        $response = $this->patchWithAuth("/api/v2/alerts/$id", [
            'title' => 'Test alert2',
            'html' => '<p>Test alert2</p>',
            'start' => '2001-01-02T00:00:00Z',
            'end' => '2038-01-02T02:00:00Z',
        ]);

        $response = $this->getWithAuth('/api/v2/alerts');
        $response->assertSuccessful();

        $json = json_decode($response->getContent(), true);
        self::assertEquals(1, count($json['data']));
        self::assertEquals($id, $json['data'][0]['id']);
        self::assertEquals('Test alert2', $json['data'][0]['title']);
        self::assertEquals('<p>Test alert2</p>', $json['data'][0]['html']);
        self::assertEquals('2001-01-02T00:00:00+00:00', $json['data'][0]['start']);
        self::assertEquals('2038-01-02T02:00:00+00:00', $json['data'][0]['end']);
    }

    public function testArtisan(): void {
        // Use the trait for authentication
        $this->setupApiAuth(Role::ADMINISTRATOR);
        
        $this->artisan('alert:create', [
            'title' => 'Test alert',
            'html' => '<p>Test alert</p>',
            'start' => '-3 hour',
            'end' => 'tomorrow',
        ])->assertExitCode(0);

        // Make the API request with authentication
        $response = $this->getWithAuth('/api/v2/alerts');
        $response->assertSuccessful();

        $json = json_decode($response->getContent(), true);
        self::assertEquals(1, count($json['data']));
        self::assertEquals('Test alert', $json['data'][0]['title']);
        self::assertEquals('<p>Test alert</p>', $json['data'][0]['html']);
    }
}
