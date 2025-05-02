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
     */
    public function register(): void
    {
        // Register implementations based on feature flag
        $this->registerMediawikiFactory();
        $this->registerUserCreator();
        $this->registerActionApi();
    }
    
    /**
     * Register MediawikiFactory implementation
     */
    protected function registerMediawikiFactory(): void
    {
        $this->app->singleton(MediawikiFactory::class, function() {
            if (!$this->isWikiIntegrationEnabled()) {
                return null;
            }
            
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
    }
    
    /**
     * Register UserCreator implementation
     */
    protected function registerUserCreator(): void
    {
        $this->app->bind(UserCreator::class, function($app) {
            if (!$this->isWikiIntegrationEnabled()) {
                return null;
            }
            
            $mw = $app->make(MediawikiFactory::class);
            if ($mw) {
                return $mw->newUserCreator();
            }

            return null;
        });
    }
    
    /**
     * Register ActionApi implementation
     */
    protected function registerActionApi(): void
    {
        $this->app->bind(ActionApi::class, function() {
            if (!$this->isWikiIntegrationEnabled()) {
                return null;
            }
            
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
     * Check if wiki integration is enabled
     */
    protected function isWikiIntegrationEnabled(): bool
    {
        return env('FEATURE__WIKI_INTEGRATION') === true;
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
