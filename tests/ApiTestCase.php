<?php

namespace Tests;

use App\Models\Role;

/**
 * Base test case for API tests
 * Automatically handles authentication for API endpoints
 */
class ApiTestCase extends TestCase
{
    use ApiTestHelpers;
    
    /**
     * Default role used for API authentication
     */
    protected $defaultApiRole = Role::ADMINISTRATOR;
    
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup API authentication by default with the admin role
        $this->setupApiAuth($this->defaultApiRole);
    }
} 