<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\TranslationServiceProvider as BaseTranslationServiceProvider;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator as BaseTranslator;

class TranslationServiceProvider extends BaseTranslationServiceProvider
{
    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader()
    {
        $this->app->singleton('translation.loader', function ($app) {
            return new InstanceAwareTranslationLoader($app['files'], $app['path.lang']);
        });
    }
    
    /**
     * Register the translator.
     *
     * @return void
     */
    protected function registerTranslator()
    {
        $this->app->singleton('translator', function ($app) {
            $loader = $app['translation.loader'];

            // When registering the translator component, we'll need to set the default
            // locale as well as the fallback locale. So we'll use the application
            // configuration to get these values while overriding them if necessary.
            $locale = $app['config']['app.locale'];

            $trans = new RobustTranslator($loader, $locale);

            $trans->setFallback($app['config']['app.fallback_locale']);

            return $trans;
        });
    }
}

/**
 * Custom translation loader that supports instance-specific translations
 */
class InstanceAwareTranslationLoader extends FileLoader
{
    /**
     * Load the messages for the given locale.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string  $namespace
     * @return array
     */
    public function load($locale, $group, $namespace = null)
    {
        // If dealing with namespaced translations, use parent method
        if ($namespace !== null && $namespace !== '*') {
            return parent::load($locale, $group, $namespace);
        }

        // Get current instance
        $instance = config('app.instance', 'base');
        
        // Load base translations
        $lines = $this->loadTranslationFile("lang/instances/base/{$locale}/{$group}.php");
        
        // Load instance-specific translations if not the base instance
        if ($instance !== 'base') {
            $instanceLines = $this->loadTranslationFile("lang/instances/{$instance}/{$locale}/{$group}.php");
            $lines = array_replace_recursive($lines, $instanceLines);
        }
        
        return $lines;
    }

    /**
     * Load a translation file and return its contents
     *
     * @param  string  $path
     * @return array
     */
    protected function loadTranslationFile($path)
    {
        $fullPath = base_path($path);
        
        if (!File::exists($fullPath)) {
            return [];
        }
        
        $lines = File::getRequire($fullPath);
        
        return is_array($lines) ? $lines : [];
    }
}

/**
 * Robust translator that detects missing translations
 */
class RobustTranslator extends BaseTranslator
{
    /**
     * Get the translation for the given key.
     *
     * @param  string  $key
     * @param  array  $replace
     * @param  string|null  $locale
     * @param  bool  $fallback
     * @return string|array
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $translation = parent::get($key, $replace, $locale, $fallback);

        // Check whether this key is something for which we expect a translation.  Exclude:
        // - Things in the JSON files, i.e. without a dot, as these do contain values which are validly the
        //   same as the key in English.
        // - Audit info, where we often don't expect a translation.
        // - Validation errors - if the translation does not exist then a default message will be generated.
        if (strpos($key, '.') !== FALSE &&
            strpos($key, 'group-audits') === FALSE &&
            strpos($key, 'event-audits') === FALSE &&
            strpos($key, 'validation.') === FALSE &&
            $translation === $key) {
            // This is very likely to be an error, where we have failed to translate something or fat-fingered the key.
            if (class_exists('\Sentry\SentrySdk')) {
                \Sentry\captureMessage('Translation not found for ' . $key);
            }
            Log::warning('Translation not found for ' . $key);
        }

        return $translation;
    }
} 