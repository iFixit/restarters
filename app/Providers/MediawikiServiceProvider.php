<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Addwiki\Mediawiki\Api\Client\Auth\UserAndPassword;
use Addwiki\Mediawiki\Api\Client\Action\ActionApi;
use Addwiki\Mediawiki\Api\MediawikiFactory;
use Addwiki\Mediawiki\Api\Service\UserCreator;

class MediawikiServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register the application services.
     * 
     * Only registers if FEATURE__WIKI_INTEGRATION is true
     */
    public function register(): void
    {
        // Do not register any services if wiki integration is disabled
        if (env('FEATURE__WIKI_INTEGRATION') !== true) {
            // Register null implementations to avoid dependency injection errors
            $this->app->bind(MediawikiFactory::class, function() {
                return null;
            });
            
            $this->app->bind(UserCreator::class, function() {
                return null;
            });
            
            $this->app->bind(ActionApi::class, function() {
                return null;
            });
            
            return;
        }
        
        $this->app->singleton(MediawikiFactory::class, function() {
            try {
                Log::debug('Connect to Mediawiki');
                $apiUrl = env('WIKI_URL').'/api.php';
                $auth = new UserAndPassword(env('WIKI_APIUSER'), env('WIKI_APIPASSWORD'));

                $api = new ActionApi($apiUrl, $auth);
                Log::debug('Connected to Mediawiki');

                return new MediawikiFactory($api);
            } catch (\Exception $ex) {
                Log::error('Failed to create ActionApi: '.$ex->getMessage());
                return null;
            }
        });

        $this->app->bind(UserCreator::class, function($app) {
            $mw = $app->make(MediawikiFactory::class);
            if ($mw) {
                return $mw->newUserCreator();
            }

            return null;
        });
        
        $this->app->bind(ActionApi::class, function() {
            try {
                $apiUrl = env('WIKI_URL').'/api.php';
                $auth = new UserAndPassword(env('WIKI_APIUSER'), env('WIKI_APIPASSWORD'));
                return new ActionApi($apiUrl, $auth);
            } catch (\Exception $ex) {
                Log::error('Failed to create ActionApi for dependency injection: '.$ex->getMessage());
                return null;
            }
        });
    }
    
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            MediawikiFactory::class,
            UserCreator::class,
            ActionApi::class,
        ];
    }
}
