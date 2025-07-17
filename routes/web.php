<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BrandsController;
use App\Http\Controllers\CalendarEventsController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupTagsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InformationAlertCookieController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\OutboundController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SkillsController;
use App\Http\Controllers\StyleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the AppServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Helper function to handle discourse redirects for gamification routes
 */
function redirectToDiscourse($fallbackRoute = 'home') {
    return function() use ($fallbackRoute) {
        if (config('restarters.features.discourse_integration')) {
            return redirect('https://talk.restarters.net/t/our-work-on-repair-data/1150');
        }
        return redirect()->route($fallbackRoute);
    };
}

/**
 * Helper function to register gamification routes that redirect to discourse
 */
function registerGameRoutes($prefix) {
    Route::prefix($prefix)->group(function() {
        Route::get('/', redirectToDiscourse());
        Route::get('/{any}', redirectToDiscourse());
    });
}

// =============================================================================
// PUBLIC ROUTES (No Authentication Required)
// =============================================================================

Route::middleware('centralizedAuth:optional')->group(function () {
    
    // === HOME & LANDING ===
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/about', [HomeController::class, 'index']);
    
    // === AUTHENTICATION & USER REGISTRATION ===
    Route::get('login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
    Route::get('logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');
    Route::get('register', [UserController::class, 'getRegister']);

    Route::prefix('user')->group(function () {
        Route::get('/', [HomeController::class, 'index']);
        Route::get('register/{hash?}', [UserController::class, 'getRegister'])->name('registration');
        Route::post('register/check-valid-email', [UserController::class, 'postEmail']);
        Route::post('register/{hash?}', [UserController::class, 'postRegister']);
        Route::get('reset', [UserController::class, 'reset']);
        Route::post('reset', [UserController::class, 'reset']);
        Route::get('recover', [UserController::class, 'recover']);
        Route::post('recover', [UserController::class, 'recover']);
        Route::get('thumbnail', [UserController::class, 'getThumbnail']);
        Route::get('menus', [UserController::class, 'getUserMenus']);
        Route::get('forbidden', fn() => view('user.forbidden', ['title' => 'Oops']));
    });

    // === PARTY/EVENT VIEWING ===
    Route::get('party/view/{id}', [PartyController::class, 'view']);
    
    // === INVITATION ACCEPTANCE ===
    Route::get('party/invite/{code}', [PartyController::class, 'confirmCodeInvite']);
    Route::get('group/invite/{code}', [GroupController::class, 'confirmCodeInvite']);
    
    // === STATIC PAGES ===
    Route::get('about/cookie-policy', fn() => View::make('features.cookie-policy'));
    Route::get('visualisations', fn() => View::make('visualisations'));
    
    // === EXPORT ROUTES (Public access for external downloads) ===
    Route::prefix('export')->group(function() {
        Route::get('devices/event/{id}', [ExportController::class, 'devicesEvent']);
        Route::get('devices/group/{id}', [ExportController::class, 'devicesGroup']);
        Route::get('devices', [ExportController::class, 'devices']);
        Route::get('groups/{id}/events', [ExportController::class, 'groupEvents']);
        Route::get('networks/{id}/events', [ExportController::class, 'networkEvents']);
    });
    
    // === CALENDAR ROUTES (Public for external calendar apps) ===
    Route::prefix('calendar')->group(function () {
        Route::get('user/{calendar_hash}', [CalendarEventsController::class, 'allEventsByUser'])
            ->name('calendar-events-by-user');
        Route::get('group/{group}', [CalendarEventsController::class, 'allEventsByGroup'])
            ->name('calendar-events-by-group');
        Route::get('network/{network}', [CalendarEventsController::class, 'allEventsByNetwork'])
            ->name('calendar-events-by-network');
        Route::get('group-area/{area}', [CalendarEventsController::class, 'allEventsByArea'])
            ->name('calendar-events-by-area');
        Route::get('all-events/{hash_env}', [CalendarEventsController::class, 'allEvents'])
            ->name('calendar-events-all');
    });
    
    // === GAMIFICATION ROUTES (Redirect to Discourse) ===
    registerGameRoutes('FaultCat');
    registerGameRoutes('faultcat');
    registerGameRoutes('MiscCat');
    registerGameRoutes('misccat');
    registerGameRoutes('MobiFix');
    registerGameRoutes('mobifix');
    registerGameRoutes('MobiFixOra');
    registerGameRoutes('mobifixora');
    registerGameRoutes('TabiCat');
    registerGameRoutes('tabicat');
    registerGameRoutes('PrintCat');
    registerGameRoutes('printcat');
    registerGameRoutes('BattCat');
    registerGameRoutes('battcat');
    registerGameRoutes('DustUp');
    registerGameRoutes('dustup');
    
    // === IFRAME ROUTES ===
    Route::get('outbound/info/{type}/{id}/{format?}', [OutboundController::class, 'info']);
    Route::get('group/stats/{id}/{format?}', [GroupController::class, 'stats']);
    Route::get('group-tag/stats/{group_tag_id}/{format?}', [GroupController::class, 'statsByGroupTag']);
    Route::get('admin/stats/{type?}', [AdminController::class, 'stats']);
    Route::get('party/stats/{id}/wide', [PartyController::class, 'stats']);
    
    // === UTILITY ROUTES ===
    Route::get('markAsRead/{id?}', function ($id = null) {
        $notifications = auth()->user()->unReadNotifications;
        if ($id) {
            $notifications = $notifications->where('id', $id);
        }
        $notifications->markAsRead();
        return redirect()->back();
    })->name('markAsRead');
    
    Route::get('set-lang/{locale}', [LocaleController::class, 'setLang']);
    Route::post('set-cookie', InformationAlertCookieController::class);
    
    // === DEVELOPMENT & TESTING ===
    Route::get('test/check-auth', fn() => new \App\Services\CheckAuthService);
    
    // === STYLE GUIDE ===
    Route::prefix('style')->group(function () {
        Route::get('/', [StyleController::class, 'index']);
        Route::get('guide', [StyleController::class, 'guide']);
        Route::get('find', [StyleController::class, 'find']);
    });
});

// =============================================================================
// AUTHENTICATED ROUTES
// =============================================================================

Route::middleware(['centralizedAuth'])->group(function () {
    
    // === USER PROFILE & SETTINGS ===
    Route::prefix('profile')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('profile');
        Route::get('notifications', [UserController::class, 'getNotifications'])->name('notifications');
        Route::get('edit/{id?}', [UserController::class, 'getProfileEdit'])->name('edit-profile');
        Route::get('{id}', [UserController::class, 'index']);
        
        // Profile update routes
        Route::post('edit-info', [UserController::class, 'postProfileInfoEdit']);
        Route::post('edit-password', [UserController::class, 'postProfilePasswordEdit']);
        Route::post('edit-language', [UserController::class, 'storeLanguage']);
        Route::post('edit-preferences', [UserController::class, 'postProfilePreferencesEdit']);
        Route::post('edit-tags', [UserController::class, 'postProfileTagsEdit']);
        Route::post('edit-photo', [UserController::class, 'postProfilePictureEdit']);
        Route::post('edit-admin-settings', [UserController::class, 'postAdminEdit']);
        Route::post('edit-repair-directory', [UserController::class, 'postProfileRepairDirectory']);
    });
    
    // === USER MANAGEMENT ===
    Route::prefix('user')->group(function () {
        Route::post('create', [UserController::class, 'create']);
        Route::get('all', [UserController::class, 'all'])->name('users');
        Route::get('all/search', [UserController::class, 'search']);
        Route::get('edit/{id}', [UserController::class, 'getProfileEdit']);
        Route::post('edit/{id}', [UserController::class, 'edit']);
        Route::post('soft-delete', [UserController::class, 'postSoftDeleteUser']);
        Route::get('onboarding-complete', [UserController::class, 'getOnboardingComplete']);
    });
    
    // === DASHBOARD ===
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])
            ->name('dashboard')
            ->middleware('AcceptUserInvites');
        Route::get('host', [DashboardController::class, 'getHostDash']);
    });
    
    // === FIXOMETER (DEVICES) ===
    Route::prefix('fixometer')->group(function () {
        Route::get('/', [DeviceController::class, 'index'])->name('devices');
    });
    
    Route::prefix('device')->group(function () {
        Route::get('/', fn() => redirect('/fixometer'));
        Route::get('search', [DeviceController::class, 'search']);
        Route::post('image-upload/{id}', [DeviceController::class, 'imageUpload']);
        Route::get('image/delete/{iddevices}/{idxref}', [DeviceController::class, 'deleteImage']);
    });
    
    // === NETWORKS ===
    Route::resource('networks', NetworkController::class)->only(['index', 'show', 'edit', 'update']);
    Route::post('networks/{network}/groups', [NetworkController::class, 'associateGroup'])
        ->name('networks.associate-group');
    
    // === GROUPS ===
    Route::prefix('group')->group(function () {
        Route::get('/', [GroupController::class, 'mine'])->name('groups');
        Route::get('create', [GroupController::class, 'create'])->name('create-group');
        Route::post('create', [GroupController::class, 'create']);
        Route::get('edit/{id}', [GroupController::class, 'edit']);
        Route::post('edit/{id}', [GroupController::class, 'edit']);
        Route::get('view/{id}', [GroupController::class, 'view'])->name('group.show');
        Route::get('all', [GroupController::class, 'all']);
        Route::get('mine', [GroupController::class, 'mine']);
        Route::get('nearby', [GroupController::class, 'nearby']);
        Route::get('network/{id}', [GroupController::class, 'network']);
        Route::get('join/{id}', [GroupController::class, 'getJoinGroup']);
        Route::get('delete/{id}', [GroupController::class, 'delete']);
        
        // Group invitations
        Route::post('invite', [GroupController::class, 'postSendInvite']);
        Route::get('accept-invite/{id}/{hash}', [GroupController::class, 'confirmInvite']);
        Route::get('nearbyinvite/{groupId}/{userId}', [GroupController::class, 'inviteNearbyRestarter']);
        
        // Group images
        Route::post('image-upload/{id}', [GroupController::class, 'imageUpload']);
        Route::get('image/delete/{idgroups}/{id}/{path}', [GroupController::class, 'ajaxDeleteImage']);
    });
    
    // === EVENTS (PARTIES) ===
    Route::prefix('party')->group(function () {
        Route::get('/', [PartyController::class, 'index'])->name('events');
        Route::get('all', [PartyController::class, 'allUpcoming'])->name('all-upcoming-events');
        Route::get('all-past', [PartyController::class, 'allPast'])->name('all-past-events');
        Route::get('group/{group_id?}', [PartyController::class, 'index'])->name('group-events');
        Route::get('create/{group_id?}', [PartyController::class, 'create']);
        Route::get('edit/{id}', [PartyController::class, 'edit']);
        Route::post('edit/{id}', [PartyController::class, 'edit']);
        Route::get('duplicate/{id}', [PartyController::class, 'duplicate']);
        Route::post('delete/{id}', [PartyController::class, 'deleteEvent']);
        Route::get('deleteimage', [PartyController::class, 'deleteimage']);
        Route::get('join/{id}', [PartyController::class, 'getJoinEvent']);
        Route::get('contribution/{id}', [PartyController::class, 'getContributions']);
        
        // Event invitations & volunteers
        Route::post('invite', [PartyController::class, 'postSendInvite']);
        Route::get('accept-invite/{id}/{hash}', [PartyController::class, 'confirmInvite']);
        Route::get('cancel-invite/{id}', [PartyController::class, 'cancelInvite']);
        Route::post('remove-volunteer', [PartyController::class, 'removeVolunteer']);
        Route::get('get-group-emails-with-names/{event_id}', [PartyController::class, 'getGroupEmailsWithNames']);
        Route::post('update-quantity', [PartyController::class, 'updateQuantity']);
        Route::post('update-volunteerquantity', [PartyController::class, 'updateVolunteerQuantity']);
        
        // Event images
        Route::post('image-upload/{id}', [PartyController::class, 'imageUpload']);
        Route::get('image/delete/{idevents}/{id}/{path}', [PartyController::class, 'deleteImage']);
    });
    
    // === ADMINISTRATIVE ROUTES ===
    
    // Admin Dashboard
    Route::prefix('admin')->middleware(App\Http\Middleware\AdminMiddleware::class)->group(function () {
        Route::get('stats', [AdminController::class, 'stats']);
        Route::get('groups', [AdminController::class, 'groups'])->name('admin.groups');
    });
    
    // Categories
    Route::prefix('category')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('category');
        Route::get('edit/{id}', [CategoryController::class, 'getEditCategory']);
        Route::post('edit/{id}', [CategoryController::class, 'postEditCategory']);
    });
    
    // Roles
    Route::prefix('role')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('roles');
        Route::get('edit/{id}', [RoleController::class, 'edit']);
        Route::post('edit/{id}', [RoleController::class, 'edit']);
    });
    
    // Brands
    Route::prefix('brands')->group(function () {
        Route::get('/', [BrandsController::class, 'index'])->name('brands');
        Route::get('create', [BrandsController::class, 'getCreateBrand']);
        Route::post('create', [BrandsController::class, 'postCreateBrand']);
        Route::get('edit/{id}', [BrandsController::class, 'getEditBrand']);
        Route::post('edit/{id}', [BrandsController::class, 'postEditBrand']);
        Route::get('delete/{id}', [BrandsController::class, 'getDeleteBrand']);
    });
    
    // Skills
    Route::prefix('skills')->group(function () {
        Route::get('/', [SkillsController::class, 'index'])->name('skills');
        Route::post('create', [SkillsController::class, 'postCreateSkill']);
        Route::get('edit/{id}', [SkillsController::class, 'getEditSkill']);
        Route::post('edit/{id}', [SkillsController::class, 'postEditSkill']);
        Route::get('delete/{id}', [SkillsController::class, 'getDeleteSkill']);
    });
    
    // Group Tags
    Route::prefix('tags')->group(function () {
        Route::get('/', [GroupTagsController::class, 'index'])->name('tags');
        Route::post('create', [GroupTagsController::class, 'postCreateTag']);
        Route::get('edit/{id}', [GroupTagsController::class, 'getEditTag']);
        Route::post('edit/{id}', [GroupTagsController::class, 'postEditTag']);
        Route::get('delete/{id}', [GroupTagsController::class, 'getDeleteTag']);
    });
    
    // Outbound
    Route::get('outbound', [OutboundController::class, 'index']);
});

// =============================================================================
// SYSTEM ROUTES
// =============================================================================

// Health check endpoint
Route::get('healthz', function () {
    $checks = [];
    $allPassed = true;
    
    // Database check
    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (Exception $e) {
        $checks['database'] = 'failed';
        $allPassed = false;
    }
    
    // Storage check
    try {
        $checks['storage'] = is_writable(storage_path()) ? 'ok' : 'failed';
        if ($checks['storage'] === 'failed') {
            $allPassed = false;
        }
    } catch (Exception $e) {
        $checks['storage'] = 'failed';
        $allPassed = false;
    }
    
    $response = [
        'status' => $allPassed ? 'ok' : 'failed',
        'timestamp' => now()->toISOString(),
        'app' => config('app.name'),
        'checks' => $checks,
    ];
    
    return response()->json($response, $allPassed ? 200 : 503);
});

// =============================================================================
// DEVELOPMENT HELPERS
// =============================================================================

// Useful code to log all queries during development
// \DB::listen(function($sql) {
//     \Log::info($sql->sql);
//     \Log::info($sql->bindings);
//     \Log::info($sql->time);
// });