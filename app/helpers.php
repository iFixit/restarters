<?php

if (!function_exists('site_trans')) {
    /**
     * Get a site-specific translation
     *
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    function site_trans($key, $replace = [], $locale = null)
    {
        return app('translator')->site($key, $replace, $locale);
    }
}

if (!function_exists('__site')) {
    /**
     * Get a site-specific translation (alternative helper)
     *
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    function __site($key, $replace = [], $locale = null)
    {
        return app('translator')->site($key, $replace, $locale);
    }
} 