# External Service Authentication Implementation Guide
## Laravel + Vue.js Integration

This guide provides step-by-step instructions for implementing session-based authentication that delegates to an external service (like iFixit.com) in a Laravel + Vue.js application.

## Table of Contents

1. [Core Authentication Strategy](#core-authentication-strategy)
2. [Database Setup](#database-setup)
3. [Laravel Implementation](#laravel-implementation)
4. [Vue.js Implementation](#vuejs-implementation)
5. [Configuration](#configuration)
6. [Testing Strategy](#testing-strategy)
7. [Security Considerations](#security-considerations)

---

## Core Authentication Strategy

### Goal
Implement session-based authentication that delegates to an external service:
- **NO local user credentials** (passwords, OAuth tokens)
- Use external service's session cookies for authentication
- Validate sessions against external API
- Maintain minimal local user records for app functionality

### Authentication Flow
1. User accesses protected route
2. Check for session cookie
3. If no session → redirect to external login
4. External service authenticates user
5. External service sets session cookie and redirects back
6. Validate session against external API
7. Sync user data locally
8. Grant access to application

---

## Database Setup

### Users Table Migration

```sql
-- Create minimal users table
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL UNIQUE,
    external_user_id VARCHAR(255) NOT NULL UNIQUE,
    external_username VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index for lookups
CREATE INDEX idx_users_external_id ON users(external_user_id);
```

### Laravel Migration

```php
<?php
// database/migrations/xxxx_create_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('external_user_id')->unique();
            $table->string('external_username')->nullable();
            $table->timestamps();
            
            $table->index('external_user_id');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
```

---

## Laravel Implementation

### Step 1: External API Service

```php
<?php
// app/Services/ExternalAuthService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class ExternalAuthService
{
    private string $baseUrl;
    private string $apiUrl;
    private string $uuidNamespace;
    
    public function __construct()
    {
        $this->baseUrl = config('external_auth.base_url');
        $this->apiUrl = config('external_auth.api_url');
        $this->uuidNamespace = config('external_auth.uuid_namespace');
    }
    
    /**
     * Validate session cookie against external API
     */
    public function validateSession(string $sessionCookie): ?array
    {
        try {
            // Validate session cookie format (32 characters for iFixit-style)
            if (strlen($sessionCookie) !== 32) {
                return null;
            }
            
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Cookie' => "session={$sessionCookie}",
                'User-Agent' => 'YourApp/1.0',
            ])->get("{$this->apiUrl}/user");
            
            if ($response->successful()) {
                $userData = $response->json();
                
                // Validate required fields
                if (!isset($userData['userid']) || !isset($userData['login'])) {
                    return null;
                }
                
                return $userData;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('External API validation failed', [
                'error' => $e->getMessage(),
                'session_length' => strlen($sessionCookie)
            ]);
            return null;
        }
    }
    
    /**
     * Map external user ID to consistent UUID
     */
    public function mapExternalIdToUuid(string $externalId): string
    {
        return Uuid::uuid5($this->uuidNamespace, $externalId)->toString();
    }
    
    /**
     * Get external login URL with callback
     */
    public function getLoginUrl(string $callbackUrl): string
    {
        return "{$this->baseUrl}/login?redirect=" . urlencode($callbackUrl);
    }
    
    /**
     * Get external logout URL with callback
     */
    public function getLogoutUrl(string $callbackUrl): string
    {
        return "{$this->baseUrl}/logout?redirect=" . urlencode($callbackUrl);
    }
}
```

### Step 2: Custom Authentication Guard

```php
<?php
// app/Guards/ExternalSessionGuard.php

namespace App\Guards;

use App\Models\User;
use App\Services\ExternalAuthService;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExternalSessionGuard implements Guard
{
    use GuardHelpers;
    
    private Request $request;
    private ExternalAuthService $externalAuthService;
    
    public function __construct(Request $request, ExternalAuthService $externalAuthService)
    {
        $this->request = $request;
        $this->externalAuthService = $externalAuthService;
    }
    
    public function check(): bool
    {
        return $this->user() !== null;
    }
    
    public function user(): ?User
    {
        if ($this->user !== null) {
            return $this->user;
        }
        
        $sessionCookie = $this->getSessionCookie();
        
        if (!$sessionCookie) {
            return null;
        }
        
        $externalUserData = $this->externalAuthService->validateSession($sessionCookie);
        
        if (!$externalUserData) {
            return null;
        }
        
        // Sync user data and return User model
        $this->user = User::syncFromExternal($externalUserData);
        
        return $this->user;
    }
    
    public function validate(array $credentials = []): bool
    {
        return $this->check();
    }
    
    private function getSessionCookie(): ?string
    {
        // Try to get session cookie from request
        return $this->request->cookie('session') ?? 
               $this->request->header('Cookie') ? 
               $this->extractSessionFromCookieHeader($this->request->header('Cookie')) : 
               null;
    }
    
    private function extractSessionFromCookieHeader(string $cookieHeader): ?string
    {
        if (preg_match('/session=([^;]+)/', $cookieHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
```

### Step 3: User Model Updates

```php
<?php
// app/Models/User.php

namespace App\Models;

use App\Services\ExternalAuthService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class User extends Model
{
    protected $fillable = [
        'id',
        'email',
        'external_user_id',
        'external_username'
    ];
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = Str::uuid();
            }
        });
    }
    
    /**
     * Sync user from external service data
     */
    public static function syncFromExternal(array $externalUserData): User
    {
        $externalAuthService = app(ExternalAuthService::class);
        $uuid = $externalAuthService->mapExternalIdToUuid($externalUserData['userid']);
        
        return self::updateOrCreate(
            ['id' => $uuid],
            [
                'email' => $externalUserData['login'],
                'external_user_id' => $externalUserData['userid'],
                'external_username' => $externalUserData['username'] ?? null,
            ]
        );
    }
}
```

### Step 4: Authentication Middleware

```php
<?php
// app/Http/Middleware/ExternalAuth.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ExternalAuthService;

class ExternalAuth
{
    private ExternalAuthService $externalAuthService;
    
    public function __construct(ExternalAuthService $externalAuthService)
    {
        $this->externalAuthService = $externalAuthService;
    }
    
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            // Store the intended URL for redirect after login
            $intendedUrl = $request->fullUrl();
            
            // If this is an API request, return JSON error
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'login_url' => $this->externalAuthService->getLoginUrl($intendedUrl)
                ], 401);
            }
            
            // For web requests, redirect to login
            return redirect()->route('login', ['redirect' => $intendedUrl]);
        }
        
        return $next($request);
    }
}
```

### Step 5: Auth Configuration

```php
<?php
// config/auth.php

return [
    'defaults' => [
        'guard' => 'external',
        'passwords' => 'users',
    ],
    
    'guards' => [
        'external' => [
            'driver' => 'external_session',
        ],
    ],
    
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],
];
```

### Step 6: Service Provider

```php
<?php
// app/Providers/AuthServiceProvider.php

namespace App\Providers;

use App\Guards\ExternalSessionGuard;
use App\Services\ExternalAuthService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerPolicies();
        
        Auth::extend('external_session', function ($app, $name, array $config) {
            return new ExternalSessionGuard(
                $app['request'],
                $app[ExternalAuthService::class]
            );
        });
    }
}
```

### Step 7: Routes

```php
<?php
// routes/web.php

use App\Services\ExternalAuthService;
use Illuminate\Support\Facades\Route;

Route::get('/login', function (ExternalAuthService $externalAuthService) {
    $callbackUrl = request()->get('redirect', '/');
    $externalLoginUrl = $externalAuthService->getLoginUrl($callbackUrl);
    
    return view('auth.login', compact('externalLoginUrl'));
})->name('login');

Route::post('/logout', function (ExternalAuthService $externalAuthService) {
    $callbackUrl = url('/auth/logout-callback');
    
    return redirect($externalAuthService->getLogoutUrl($callbackUrl));
})->name('logout');

Route::get('/auth/logout-callback', function () {
    // Clear any local session data
    session()->flush();
    
    // Clear session cookie
    return redirect('/login')->withCookie(cookie()->forget('session'));
})->name('logout.callback');

// API Routes
Route::prefix('api')->group(function () {
    Route::get('/auth/check', function () {
        if (Auth::check()) {
            return response()->json([
                'isAuthenticated' => true,
                'user' => [
                    'id' => Auth::user()->id,
                    'email' => Auth::user()->email,
                    'username' => Auth::user()->external_username,
                ]
            ]);
        }
        
        return response()->json([
            'isAuthenticated' => false,
        ], 401);
    });
});

// Protected routes
Route::middleware(['external_auth'])->group(function () {
    Route::get('/', function () {
        return view('dashboard');
    });
    
    Route::get('/dashboard', function () {
        return view('dashboard');
    });
});
```

---

## Vue.js Implementation

### Step 1: Auth Store (Pinia)

```javascript
// stores/auth.js
import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isAuthenticated: false,
    isLoading: false,
    error: null
  }),
  
  actions: {
    async checkAuth() {
      this.isLoading = true
      this.error = null
      
      try {
        const response = await axios.get('/api/auth/check')
        this.user = response.data.user
        this.isAuthenticated = response.data.isAuthenticated
      } catch (error) {
        this.user = null
        this.isAuthenticated = false
        
        if (error.response?.status === 401) {
          // Handle unauthenticated state
          this.error = 'Please log in to continue'
        } else {
          this.error = 'Authentication check failed'
        }
      } finally {
        this.isLoading = false
      }
    },
    
    logout() {
      // Create a form and submit it to trigger Laravel logout
      const form = document.createElement('form')
      form.method = 'POST'
      form.action = '/logout'
      
      // Add CSRF token
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      if (csrfToken) {
        const csrfInput = document.createElement('input')
        csrfInput.type = 'hidden'
        csrfInput.name = '_token'
        csrfInput.value = csrfToken
        form.appendChild(csrfInput)
      }
      
      document.body.appendChild(form)
      form.submit()
    },
    
    redirectToLogin(redirectUrl = null) {
      const currentUrl = redirectUrl || window.location.pathname
      window.location.href = `/login?redirect=${encodeURIComponent(currentUrl)}`
    }
  }
})
```

### Step 2: Router Configuration

```javascript
// router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { requiresGuest: true }
  },
  {
    path: '/',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/profile',
    name: 'Profile',
    component: () => import('@/views/Profile.vue'),
    meta: { requiresAuth: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation guards
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  
  // Check authentication status
  if (to.meta.requiresAuth || to.meta.requiresGuest) {
    await authStore.checkAuth()
  }
  
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    // Redirect to login with current route as callback
    authStore.redirectToLogin(to.fullPath)
    return
  }
  
  if (to.meta.requiresGuest && authStore.isAuthenticated) {
    // Redirect authenticated users away from login
    next({ name: 'Dashboard' })
    return
  }
  
  next()
})

export default router
```

### Step 3: HTTP Interceptor

```javascript
// plugins/axios.js
import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

// Request interceptor
axios.interceptors.request.use(
  (config) => {
    // Add CSRF token to requests
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    if (csrfToken) {
      config.headers['X-CSRF-TOKEN'] = csrfToken
    }
    
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    const authStore = useAuthStore()
    
    if (error.response?.status === 401) {
      // Handle authentication errors
      authStore.user = null
      authStore.isAuthenticated = false
      
      // Redirect to login if not already there
      if (!window.location.pathname.includes('/login')) {
        authStore.redirectToLogin()
      }
    }
    
    return Promise.reject(error)
  }
)

export default axios
```

### Step 4: Login Component

```vue
<!-- views/Login.vue -->
<template>
  <div class="login-container">
    <div class="login-card">
      <h2 class="login-title">Sign in with External Service</h2>
      
      <div v-if="isChecking" class="loading">
        <div class="spinner"></div>
        <p>Checking login status...</p>
      </div>
      
      <div v-else-if="isLoggedIn" class="success">
        <p>✓ Already logged in! Redirecting...</p>
      </div>
      
      <div v-else class="login-form">
        <button @click="redirectToExternalLogin" class="login-btn">
          Go to External Login
        </button>
        
        <div v-if="error" class="error">
          {{ error }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { useAuthStore } from '@/stores/auth'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

export default {
  name: 'Login',
  setup() {
    const authStore = useAuthStore()
    const route = useRoute()
    const router = useRouter()
    
    const isChecking = ref(true)
    const isLoggedIn = ref(false)
    const error = ref(null)
    
    const checkAuthStatus = async () => {
      try {
        await authStore.checkAuth()
        
        if (authStore.isAuthenticated) {
          isLoggedIn.value = true
          
          // Redirect to intended page after short delay
          setTimeout(() => {
            const redirectUrl = route.query.redirect || '/'
            router.push(redirectUrl)
          }, 1500)
        }
      } catch (err) {
        error.value = 'Error checking authentication status'
        console.error(err)
      } finally {
        isChecking.value = false
      }
    }
    
    const redirectToExternalLogin = () => {
      const callbackUrl = route.query.redirect || '/'
      window.location.href = route.query.external_login_url || 
        `/login?redirect=${encodeURIComponent(callbackUrl)}`
    }
    
    onMounted(() => {
      checkAuthStatus()
    })
    
    return {
      isChecking,
      isLoggedIn,
      error,
      redirectToExternalLogin
    }
  }
}
</script>

<style scoped>
.login-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  background-color: #f5f5f5;
}

.login-card {
  background: white;
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  max-width: 400px;
  width: 100%;
}

.login-title {
  text-align: center;
  margin-bottom: 1.5rem;
  color: #333;
}

.login-btn {
  width: 100%;
  padding: 0.75rem;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 1rem;
  cursor: pointer;
  transition: background-color 0.3s;
}

.login-btn:hover {
  background-color: #0056b3;
}

.loading, .success {
  text-align: center;
  padding: 1rem;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f3f3f3;
  border-top: 4px solid #007bff;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.error {
  color: #dc3545;
  text-align: center;
  margin-top: 1rem;
  padding: 0.5rem;
  background-color: #f8d7da;
  border: 1px solid #f5c6cb;
  border-radius: 4px;
}

.success {
  color: #155724;
  background-color: #d4edda;
  border: 1px solid #c3e6cb;
  border-radius: 4px;
}
</style>
```

### Step 5: Navigation Component

```vue
<!-- components/Navigation.vue -->
<template>
  <nav class="navbar">
    <div class="nav-brand">
      <router-link to="/">Your App</router-link>
    </div>
    
    <div class="nav-links">
      <router-link to="/" class="nav-link">Dashboard</router-link>
      <router-link to="/profile" class="nav-link">Profile</router-link>
    </div>
    
    <div class="nav-user" v-if="authStore.isAuthenticated">
      <span class="user-name">{{ authStore.user?.username || authStore.user?.email }}</span>
      <button @click="handleLogout" class="logout-btn">Logout</button>
    </div>
  </nav>
</template>

<script>
import { useAuthStore } from '@/stores/auth'

export default {
  name: 'Navigation',
  setup() {
    const authStore = useAuthStore()
    
    const handleLogout = () => {
      authStore.logout()
    }
    
    return {
      authStore,
      handleLogout
    }
  }
}
</script>

<style scoped>
.navbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 2rem;
  background-color: #343a40;
  color: white;
}

.nav-brand a {
  color: white;
  text-decoration: none;
  font-weight: bold;
  font-size: 1.25rem;
}

.nav-links {
  display: flex;
  gap: 1rem;
}

.nav-link {
  color: white;
  text-decoration: none;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  transition: background-color 0.3s;
}

.nav-link:hover {
  background-color: rgba(255, 255, 255, 0.1);
}

.nav-user {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.user-name {
  font-weight: 500;
}

.logout-btn {
  background-color: #dc3545;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
  transition: background-color 0.3s;
}

.logout-btn:hover {
  background-color: #c82333;
}
</style>
```

---

## Configuration

### Step 1: Environment Variables

```env
# .env
EXTERNAL_AUTH_BASE_URL=https://external-service.com
EXTERNAL_AUTH_API_URL=https://external-service.com/api/2.0
EXTERNAL_AUTH_UUID_NAMESPACE=9c25ebd3-7dda-45e1-9c2e-b80f4818c730

# Session Configuration
SESSION_DRIVER=cookie
SESSION_LIFETIME=120
SESSION_DOMAIN=.yourdomain.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

### Step 2: External Auth Configuration

```php
<?php
// config/external_auth.php

return [
    'base_url' => env('EXTERNAL_AUTH_BASE_URL', 'https://external-service.com'),
    'api_url' => env('EXTERNAL_AUTH_API_URL', 'https://external-service.com/api/2.0'),
    'uuid_namespace' => env('EXTERNAL_AUTH_UUID_NAMESPACE', '9c25ebd3-7dda-45e1-9c2e-b80f4818c730'),
    'session_cookie_name' => 'session',
    'session_cookie_length' => 32,
];
```

### Step 3: Blade Layout

```blade
<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Your App')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app">
        @yield('content')
    </div>
</body>
</html>
```

### Step 4: Login View

```blade
<!-- resources/views/auth/login.blade.php -->
@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="login-container">
    <div class="login-card">
        <h2>Sign in with External Service</h2>
        <a href="{{ $externalLoginUrl }}" class="login-btn">
            Go to External Login
        </a>
    </div>
</div>
@endsection
```

---

## Security Considerations

### 1. Session Security
- **Cookie Domain**: Set session cookies to appropriate domain (e.g., `.yourdomain.com`)
- **HTTPS Only**: Ensure `SESSION_SECURE_COOKIE=true` in production
- **SameSite**: Use `SESSION_SAME_SITE=lax` for cross-site compatibility

### 2. API Security
- **Rate Limiting**: Implement rate limiting for external API calls
- **Timeout Handling**: Set appropriate timeouts for external API requests
- **Error Logging**: Log authentication failures and API errors

### 3. Input Validation
- **Session Cookie Format**: Validate session cookie format before API calls
- **URL Validation**: Validate redirect URLs to prevent open redirects
- **CSRF Protection**: Include CSRF tokens in all form submissions

### 4. Monitoring
- **Authentication Events**: Log all authentication attempts
- **API Failures**: Monitor external API availability and response times
- **User Synchronization**: Track user creation and updates

### 5. Error Handling
```php
// Example error handling in controller
public function protectedAction()
{
    try {
        $user = Auth::user();
        // ... action logic
    } catch (\Exception $e) {
        Log::error('Protected action failed', [
            'user_id' => Auth::id(),
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'error' => 'Action failed. Please try again.'
        ], 500);
    }
}
```

### 6. Session Management
- **Session Cleanup**: Implement session cleanup on logout
- **Cookie Expiration**: Set appropriate cookie expiration times
- **Cross-Domain Handling**: Handle session cookies across subdomains

---

## Deployment Checklist

- [ ] Configure environment variables
- [ ] Set up database with proper indexes
- [ ] Configure session cookie domain
- [ ] Enable HTTPS in production
- [ ] Set up external API monitoring
- [ ] Configure rate limiting
- [ ] Set up error logging
- [ ] Test cross-domain cookie handling
- [ ] Verify logout flow clears all sessions
- [ ] Test redirect URLs and callbacks
- [ ] Set up user synchronization monitoring
- [ ] Configure CSRF protection
- [ ] Test API error handling
- [ ] Verify UUID generation consistency
- [ ] Set up authentication event logging

---

This implementation provides a robust foundation for external service authentication that can be adapted to work with any external authentication provider that uses session-based authentication. 