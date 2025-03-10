<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Translation\Translator as BaseTranslator;

class RobustTranslator extends BaseTranslator
{
    /**
     * Get the translation for the given key, checking site-specific translations first.
     *
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @param bool $fallback
     *
     * @return array|null|string|void
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        // Try to get site-specific translation first
        $siteTranslation = $this->getSiteTranslation($key, $replace, $locale);
        if ($siteTranslation !== null) {
            return $siteTranslation;
        }
        
        // Fall back to standard translation
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
            \Sentry\captureMessage('Translation not found for ' . $key);
            Log::warning('Translation not found for ' . $key);
        }

        return $translation;
    }
    
    /**
     * Get a site-specific translation if available
     * 
     * @param string $key The translation key
     * @param array $replace Values to replace in the translation
     * @param string|null $locale The locale to use
     * @return string|null The translated text or null if not found
     */
    protected function getSiteTranslation($key, array $replace = [], $locale = null)
    {
        // Get the current site
        $currentSite = app()->bound('current.site') ? app('current.site') : null;
        
        if (!$currentSite) {
            return null;
        }
        
        $siteNamespace = $currentSite['site'];
        
        // Split the key to get the group and item separately
        $parts = explode('.', $key);
        
        if (count($parts) !== 2) {
            return null;
        }
        
        $group = $parts[0];
        $item = $parts[1];
        
        // Try to get the translations for the whole group from the site namespace
        $siteTranslations = $this->getLoader()->load($locale ?? $this->locale, $group, $siteNamespace);
        
        // If the site-specific translations exist and contain the specific key, use it
        if ($siteTranslations && isset($siteTranslations[$item])) {
            Log::debug("Found site-specific translation for key", [
                'key' => $key,
                'site' => $siteNamespace
            ]);
            
            // Apply replacements and return the site-specific translation
            return $this->makeReplacements($siteTranslations[$item], $replace);
        }
        
        return null;
    }
}