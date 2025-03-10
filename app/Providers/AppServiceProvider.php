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
use App\Services\TranslationService;

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

        // Register the TranslationService
        $this->app->singleton(TranslationService::class, function ($app) {
            return new TranslationService();
        });

        $this->app->register(\L5Swagger\L5SwaggerServiceProvider::class);
    }
    
    /**
     * Share translations with JavaScript for Vue components
     */
    protected function shareTranslationsWithJs()
    {
        // Use the TranslationService to get JS translations
        $translationService = app(TranslationService::class);
        $jsTransGroups = $translationService->getJsGroups();
        
        View::composer('*', function ($view) use ($jsTransGroups) {
            $locale = app()->getLocale();
            $translations = [];
            
            // Load translations for each group
            foreach ($jsTransGroups as $group) {
                // Get translations for this group
                // The RobustTranslator::get method will automatically check for site-specific translations first
                $translations[$group] = trans($group);
            }
            
            // Share with all views
            $view->with('translations', $translations);
            $view->with('translationsLocale', $locale);
            
            // Make the site name available to views
            $currentSite = app()->bound('current.site') ? app('current.site') : null;
            if ($currentSite) {
                $view->with('current_site', $currentSite['site']);
            }
        });
    }
}
