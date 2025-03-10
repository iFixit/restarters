<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class TranslationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Maps domains to their corresponding translation site folders
     */
    protected $siteTranslations = [];
    
    /**
     * Create a new service provider instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function __construct($app)
    {
        parent::__construct($app);
        
        // Load site translations from config
        $this->siteTranslations = Config::get('translation.sites', []);
    }

    public function register()
    {
        $this->registerLoader();

        $this->app->singleton('translator', function ($app) {
            $loader = $app['translation.loader'];
            $locale = $app->getLocale();
            $trans = new Translator($loader, $locale);
            $trans->setFallback($app->getFallbackLocale());
            return $trans;
        });
    }

    protected function registerLoader()
    {
        $this->app->singleton('translation.loader', function ($app) {
            $loader = new FileLoader($app['files'], base_path('lang'));
            
            // Register all site namespaces
            foreach ($this->siteTranslations as $site) {
                $sitePath = base_path("lang/{$site['site']}");
                if (File::isDirectory($sitePath)) {
                    $loader->addNamespace($site['site'], $sitePath);
                    
                    if (Config::get('translation.debug', false)) {
                        Log::debug("Registered translation namespace: {$site['site']}", ['path' => $sitePath]);
                    }
                }
            }
            
            return $loader;
        });
    }

    public function provides()
    {
        return ['translator', 'translation.loader'];
    }

    public function boot()
    {
        // Use the site determined by the LanguageSwitcher middleware if available
        $site = app()->bound('current.site') ? app('current.site') : $this->getCurrentSite();
        
        if (!$site) {
            return;
        }

        $locale = app()->getLocale();
        $sitePath = base_path("lang/{$site['site']}");
        
        if (!File::isDirectory($sitePath)) {
            return;
        }
        
        $translator = app('translator');
        
        // First check for locale-specific files in site directory
        $localeSpecificPath = "{$sitePath}/{$locale}";
        if (File::isDirectory($localeSpecificPath)) {
            $this->loadTranslationFiles($localeSpecificPath, $locale, $translator);
        }
        
        // Then check for direct files in site directory (flat structure)
        $this->loadTranslationFiles($sitePath, $locale, $translator, true);
    }

    /**
     * Load and merge translation files from a specific directory
     * 
     * @param string $path The directory path to load files from
     * @param string $locale The current locale
     * @param \Illuminate\Translation\Translator $translator The translator instance
     * @param bool $isFlat Whether this is a flat structure (not locale-nested)
     */
    protected function loadTranslationFiles($path, $locale, $translator, $isFlat = false)
    {
        // Check if directory exists
        if (!File::isDirectory($path)) {
            return;
        }
        
        $translationFiles = File::glob("{$path}/*.php");
        $debug = Config::get('translation.debug', false);
        
        if ($debug) {
            Log::info('Found translation files', ['path' => $path, 'count' => count($translationFiles)]);
        }
        
        // Load and merge all override files
        foreach ($translationFiles as $file) {
            $group = basename($file, '.php');
            
            try {
                // Get base translations
                $baseTranslations = $translator->getLoader()->load($locale, $group) ?: [];
                
                // Get override translations
                $overrideTranslations = require $file;
                
                // Merge translations, keeping the override values where they exist
                // but preserving base translations for keys that don't exist in the override
                $mergedTranslations = array_merge($baseTranslations, $overrideTranslations);
                
                // Add the translations to the translator with the correct group prefix
                $translator->addLines(
                    collect($mergedTranslations)
                        ->mapWithKeys(fn($value, $key) => ["{$group}.{$key}" => $value])
                        ->toArray(),
                    $locale
                );
                
                if ($debug) {
                    Log::debug('Translation data', [
                        'group' => $group,
                        'base_count' => count($baseTranslations),
                        'override_count' => count($overrideTranslations),
                        'merged_count' => count($mergedTranslations)
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Error loading translations for {$group}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Get the current site configuration based on the domain
     * 
     * @return array|null The site configuration or null if no match
     */
    protected function getCurrentSite()
    {
        $currentDomain = request()->getHost();
        
        foreach ($this->siteTranslations as $site) {
            if ($site['domain'] === $currentDomain) {
                return $site;
            }
        }
        
        return null;
    }
} 