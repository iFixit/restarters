<?php

use App\Http\Controllers\API;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\OutboundController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the AppServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// =============================================================================
// PUBLIC API ROUTES (No Authentication Required)
// =============================================================================

Route::prefix('')->group(function () {
    // Homepage and statistics
    Route::get('homepage_data', [ApiController::class, 'homepage_data']);
    Route::get('party/{id}/stats', [ApiController::class, 'partyStats']);
    Route::get('group/{id}/stats', [ApiController::class, 'groupStats']);
    
    // Outbound/Share information
    Route::get('outbound/info/{type}/{id}/{format?}', [OutboundController::class, 'info']);
    
    // Device data (paginated)
    Route::get('devices/{page}/{size}', [ApiController::class, 'getDevices']);
    
    // Notifications info (no auth required for count)
    Route::get('users/{id}/notifications', [API\UserController::class, 'notifications']);
    
    // Talk/Discussion topics
    Route::get('talk/topics/{tag?}', [API\DiscourseController::class, 'discussionTopics']);
    
    // Timezones
    Route::get('timezones', [ApiController::class, 'timezones'])->withoutMiddleware('customApiAuth');
    Route::get('timezone', [API\TimeZoneController::class, 'lookup']);
});

// =============================================================================
// AUTHENTICATED API ROUTES (v1 - Legacy)
// =============================================================================

Route::middleware('auth:api')->group(function () {
    // User management
    Route::prefix('users')->group(function () {
        Route::get('me', [ApiController::class, 'getUserInfo']);
        Route::get('/', [ApiController::class, 'getUserList']);
        Route::get('changes', [API\UserController::class, 'changes']); // Used by Zapier
    });

    // Network management
    Route::prefix('networks')->group(function () {
        Route::get('{network}/stats', [API\NetworkController::class, 'stats']); // Used by RepairTogether
    });

    // Group management
    Route::prefix('groups')->group(function () {
        Route::get('/', [API\GroupController::class, 'getGroupList']);
        Route::get('changes', [API\GroupController::class, 'getGroupChanges']); // Used by Zapier
        Route::get('network', [API\GroupController::class, 'getGroupsByUsersNetworks']); // Used by Repair Together
    });

    // Event management
    Route::prefix('events')->group(function () {
        Route::get('network/{date_from?}/{date_to?}', [API\EventController::class, 'getEventsByUsersNetworks']); // Used by Repair Together
        Route::get('{id}/volunteers', [API\EventController::class, 'listVolunteers']);
        Route::put('{id}/volunteers', [API\EventController::class, 'addVolunteer']);
    });

    // User-Group relationships
    Route::prefix('usersgroups')->group(function () {
        Route::get('changes', [API\UserGroupsController::class, 'changes']); // Used by Zapier
        Route::delete('{id}', [API\UserGroupsController::class, 'leave']); // Used by Vue client
    });
});

// =============================================================================
// API v2 - Modern RESTful API
// =============================================================================

Route::prefix('v2')->middleware(\App\Http\Middleware\APISetLocale::class)->group(function () {
    
    // Groups API
    Route::prefix('groups')->group(function () {
        // Public group endpoints
        Route::get('names', [API\GroupController::class, 'listNamesv2']);
        Route::get('tags', [API\GroupController::class, 'listTagsv2']);
        Route::get('{id}', [API\GroupController::class, 'getGroupv2']);
        Route::get('{id}/events', [API\GroupController::class, 'getEventsForGroupv2']);
        Route::get('{id}/volunteers', [API\GroupController::class, 'getVolunteersForGroupv2']);

        Route::post('/', [API\GroupController::class, 'createGroupv2']);

        Route::patch('{id}', [API\GroupController::class, 'updateGroupv2']);
        
        // Authenticated group endpoints
        Route::middleware(['auth:api'])->group(function () {
            Route::patch('{id}/volunteers/{iduser}', [API\GroupController::class, 'patchVolunteerForGroupv2']);
            Route::delete('{id}/volunteers/{iduser}', [API\GroupController::class, 'deleteVolunteerForGroupv2']);
        });
    });

    // Events API
    Route::prefix('events')->group(function () {
        Route::get('{id}', [API\EventController::class, 'getEventv2']);

        Route::post('/', [API\EventController::class, 'createEventv2']);

        Route::patch('{id}', [API\EventController::class, 'updateEventv2']);
    });

    // Networks API
    Route::prefix('networks')->group(function () {
        Route::get('/', [API\NetworkController::class, 'getNetworksv2']);
        Route::get('{id}', [API\NetworkController::class, 'getNetworkv2']);
        Route::get('{id}/groups', [API\NetworkController::class, 'getNetworkGroupsv2']);
        Route::get('{id}/events', [API\NetworkController::class, 'getNetworkEventsv2']);
    });

    // Devices API
    Route::prefix('devices')->group(function () {
        Route::get('{id}', [API\DeviceController::class, 'getDevicev2']);
        
        Route::post('/', [API\DeviceController::class, 'createDevicev2']);

        Route::patch('{id}', [API\DeviceController::class, 'updateDevicev2']);

        Route::delete('{id}', [API\DeviceController::class, 'deleteDevicev2']);
    });

    // Items API
    Route::get('items', [API\ItemController::class, 'listItemsv2']);

    // Alerts API
    Route::prefix('alerts')->group(function () {
        Route::get('/', [API\AlertController::class, 'listAlertsv2']);
        
        Route::put('/', [API\AlertController::class, 'addAlertv2']);

        Route::patch('{id}', [API\AlertController::class, 'updateAlertv2']);
    });

    // Moderation API
    Route::prefix('moderate')->middleware('auth:api')->group(function () {
        Route::get('groups', [API\GroupController::class, 'moderateGroupsv2']);
        Route::get('events', [API\EventController::class, 'moderateEventsv2']);
    });

    // Admin API
    Route::prefix('admin')->middleware(['auth:api', 'admin'])->group(function () {
        Route::prefix('groups')->group(function () {
            Route::get('/', [App\Http\Controllers\API\GroupsController::class, 'index']);
            Route::get('export', [App\Http\Controllers\API\GroupsController::class, 'exportGroups']);
            Route::post('import', [App\Http\Controllers\API\GroupsController::class, 'importGroups']);
            Route::post('bulk/{action}', [App\Http\Controllers\API\GroupsController::class, 'performBulkActions']);
            Route::post('{id}/{action}', [App\Http\Controllers\API\GroupsController::class, 'performSingleAction']);
        });
    });
});