<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Translation\Translator as BaseTranslator;

class RobustTranslator extends BaseTranslator
{
    /**
     * @param string $key
     * @param array $replace
     * @param null $locale
     * @param bool $fallback
     *
     * @return array|null|string|void
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
            \Sentry\captureMessage('Translation not found for ' . $key);
            Log::warning('Translation not found for ' . $key);
        }

        return $translation;
    }
    
    /**
     * Get a translation from a site-specific namespace
     * 
     * @param string $key The translation key
     * @param array $replace Values to replace in the translation
     * @param string|null $locale The locale to use
     * @param bool $fallback Whether to fallback to the default translation
     * @return string The translated text
     */
    public function site($key, array $replace = [], $locale = null, $fallback = true)
    {
        // Get the current site
        $currentSite = app()->bound('current.site') ? app('current.site') : null;
        
        if (!$currentSite) {
            Log::warning("No current site found when trying to translate key: {$key}");
            return parent::get($key, $replace, $locale, $fallback);
        }
        
        $siteNamespace = $currentSite['site'];
        
        // Split the key to get the group and item separately
        $parts = explode('.', $key);
        
        if (count($parts) !== 2) {
            Log::warning("Invalid translation key format: {$key}. Expected 'group.key'");
            return parent::get($key, $replace, $locale, $fallback);
        }
        
        $group = $parts[0];
        $item = $parts[1];
        
        // Try to get the translations for the whole group from the site namespace
        $siteKey = "{$siteNamespace}::{$group}";
        $siteTranslations = $this->getLoader()->load($locale ?? $this->locale, $group, $siteNamespace);
        
        // If the site-specific translations exist and contain the specific key, use it
        if ($siteTranslations && isset($siteTranslations[$item])) {
            Log::debug("Found site-specific translation for key", [
                'key' => $key,
                'site' => $siteNamespace,
                'translation' => $siteTranslations[$item]
            ]);
            
            // Apply replacements and return the site-specific translation
            return $this->makeReplacements($siteTranslations[$item], $replace);
        }
        
        // If fallback is enabled, get the standard translation for this key
        if ($fallback) {
            Log::debug("No site-specific translation found for key, falling back to standard", [
                'key' => $key,
                'site' => $siteNamespace
            ]);
            
            return parent::get($key, $replace, $locale, true);
        }
        
        // No translation found and no fallback
        return $key;
    }
}