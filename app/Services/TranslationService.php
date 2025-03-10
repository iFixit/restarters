<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class TranslationService
{
    /**
     * Get all translation groups that should be available to JavaScript
     *
     * @return array
     */
    public function getJsGroups()
    {
        // Default groups that should always be included
        $defaultGroups = [
            'dashboard',
            'common',
            'auth',
            'landing',
            'groups',
            'events',
            'nav',
            'general',
        ];
        
        // Get all PHP files from the lang/en directory
        $langPath = base_path('lang/en');
        if (!File::isDirectory($langPath)) {
            return $defaultGroups;
        }
        
        $files = File::glob($langPath . '/*.php');
        $groups = array_map(function($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }, $files);
        
        // Filter out any groups you don't want to include in JavaScript
        $excludedGroups = [
            'validation', // Usually too large and not needed in JS
            'pagination',
            'passwords',
            // Add any other groups you want to exclude
        ];
        
        $groups = array_diff($groups, $excludedGroups);
        
        // Ensure default groups are included even if they don't exist as files
        return array_unique(array_merge($defaultGroups, $groups));
    }
    
    /**
     * Load translations for all JS groups
     *
     * @param string $locale
     * @return array
     */
    public function loadJsTranslations($locale = null)
    {
        $locale = $locale ?: app()->getLocale();
        $groups = $this->getJsGroups();
        $translations = [];
        
        foreach ($groups as $group) {
            $translations[$group] = trans($group);
        }
        
        return $translations;
    }
} 