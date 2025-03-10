<?php

namespace App\Providers;

use App\EventsUsers;
use App\Helpers\Geocoder;
use App\Helpers\RobustTranslator;
use App\Observers\EventsUsersObserver;
use Auth;
use Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;
use OwenIt\Auditing\Models\Audit;
use Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // The admin area is unusable without this
        if (app()->isLocal()) {
            error_reporting(E_ALL ^ E_NOTICE);
        }

        Schema::defaultStringLength(191);

        // Force HTTPS for all URLs when APP_URL is set to HTTPS
        if (parse_url(config('app.url'), PHP_URL_SCHEME) === 'https') {
            \URL::forceScheme('https');
        }

        // Don't create Audit entries when nothing that we want to audit has changed.
        // see: https://github.com/owen-it/laravel-auditing/issues/263#issuecomment-330695869
        Audit::creating(function (Audit $model) {
            if (empty($model->old_values) && empty($model->new_values)) {
                return false;
            }
        });

        \Illuminate\Pagination\Paginator::useBootstrapThree();

        EventsUsers::observe(EventsUsersObserver::class);
        
        // Add site-specific translation helper
        $this->registerSiteTranslationHelper();
        
        // Share translations with JavaScript
        $this->shareTranslationsWithJs();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Geocoder::class, function () {
            return new Geocoder();
        });

        // Override the existing translator with our own robust one.
        $this->app->extend('translator', function (Translator $translator) {
            $trans = new RobustTranslator($translator->getLoader(), $translator->getLocale());
            $trans->setFallback($translator->getFallback());
            return $trans;
        });

        $this->app->register(\L5Swagger\L5SwaggerServiceProvider::class);
    }
    
    /**
     * Register a site-specific translation helper function
     */
    protected function registerSiteTranslationHelper()
    {
        // Register a Blade directive for site-specific translations
        \Illuminate\Support\Facades\Blade::directive('site_trans', function ($expression) {
            return "<?php echo site_trans($expression); ?>";
        });
        
        // Make the site name available to all views
        $currentSite = app()->bound('current.site') ? app('current.site') : null;
        if ($currentSite) {
            view()->share('current_site', $currentSite['site']);
        }
    }
    
    /**
     * Share translations with JavaScript for Vue components
     */
    protected function shareTranslationsWithJs()
    {
        // Determine which translation groups to load
        $jsTransGroups = Config::get('translation.js_groups', ['dashboard', 'common', 'auth']);
        
        View::composer('*', function ($view) use ($jsTransGroups) {
            $locale = app()->getLocale();
            $translations = [];
            $siteTranslations = [];
            
            // Load regular translations
            foreach ($jsTransGroups as $group) {
                $translations[$group] = trans($group);
            }
            
            // Load site-specific translations if available
            if (app()->bound('current.site')) {
                $currentSite = app('current.site');
                
                foreach ($jsTransGroups as $group) {
                    // Try to get site-specific translations using our helper
                    $groupTranslations = [];
                    
                    // We'll manually build the array to avoid the key prefix in the result
                    $siteKey = "{$currentSite['site']}::{$group}";
                    $rawTranslations = app('translator')->get($siteKey, [], $locale, false);
                    
                    if (is_array($rawTranslations)) {
                        $groupTranslations = $rawTranslations;
                    }
                    
                    $siteTranslations[$group] = $groupTranslations;
                }
            }
            
            // Share with all views
            $view->with('translations', $translations);
            $view->with('siteTranslations', $siteTranslations);
            $view->with('translationsLocale', $locale);
        });
    }
}
