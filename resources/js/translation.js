/**
 * Translation helper for Vue components
 * 
 * This module provides translation functions that can be used in Vue components
 * to access translations loaded from the server.
 */

// These will be populated from the global window object
let translations = {};
let siteTranslations = {};
let locale = 'en';

/**
 * Initialize the translation module with data from the server
 */
export function initTranslations() {
    // Check if window.translations exists (should be set in the layout)
    if (window.translations) {
        translations = window.translations;
    }
    
    // Check if window.siteTranslations exists
    if (window.siteTranslations) {
        siteTranslations = window.siteTranslations;
    }
    
    // Get the current locale
    if (window.translationsLocale) {
        locale = window.translationsLocale;
    }
    
    console.log(`Translations initialized with locale: ${locale}`);
    console.log('Available translation groups:', Object.keys(translations));
    console.log('Available site-specific translation groups:', Object.keys(siteTranslations));
}

/**
 * Get a translation from the loaded translations
 * (equivalent to Laravel's __ function)
 * 
 * @param {string} key - The translation key (e.g. "dashboard.title")
 * @param {Object} replace - Replacement parameters
 * @returns {string} - The translated string
 */
export function __(key, replace = {}) {
    // Split the key into group and item
    const parts = key.split('.');
    if (parts.length !== 2) {
        console.warn(`Invalid translation key format: ${key}. Expected "group.key"`);
        return key;
    }
    
    const [group, item] = parts;
    
    // Check if the group exists in translations
    if (!translations[group]) {
        console.warn(`Translation group not found: ${group}. Available groups: ${Object.keys(translations).join(', ')}`);
        console.warn('Make sure the group is included in config/translation.php js_groups array');
        return key;
    }
    
    // Check if the item exists in the group
    if (!translations[group][item]) {
        console.warn(`Translation key not found: ${key}`);
        return key;
    }
    
    // Get the translation
    let translation = translations[group][item];
    
    // Replace parameters
    return applyReplacements(translation, replace);
}

/**
 * Get a site-specific translation
 * (equivalent to Laravel's __site function)
 * 
 * @param {string} key - The translation key (e.g. "dashboard.title")
 * @param {Object} replace - Replacement parameters
 * @returns {string} - The translated string
 */
export function __site(key, replace = {}) {
    // Split the key into group and item
    const parts = key.split('.');
    if (parts.length !== 2) {
        console.warn(`Invalid translation key format: ${key}. Expected "group.key"`);
        return __(key, replace); // Fallback to regular translation
    }
    
    const [group, item] = parts;
    
    // Check if we have site-specific translations for this group
    if (siteTranslations[group]) {
        // Check if the specific key exists in the site translations
        if (siteTranslations[group][item] !== undefined) {
            let translation = siteTranslations[group][item];
            console.debug(`Found site-specific translation for key: ${key}`);
            return applyReplacements(translation, replace);
        } else {
            console.debug(`Site translation group ${group} exists but doesn't have key '${item}', falling back to standard translations`);
        }
    } else {
        console.debug(`No site-specific translations found for group: ${group}, falling back to standard translations`);
    }
    
    // Fallback to regular translation
    return __(key, replace);
}

/**
 * Apply replacements to a translation string
 * 
 * @param {string} translation - The translation string with placeholders
 * @param {Object} replace - Replacement parameters
 * @returns {string} - The string with replacements applied
 */
function applyReplacements(translation, replace) {
    for (const key in replace) {
        translation = translation.replace(new RegExp(`:${key}`, 'g'), replace[key]);
    }
    
    return translation;
}

// Vue plugin to make translations available globally
export const TranslationPlugin = {
    install(Vue) {
        // Initialize translations
        initTranslations();
        
        // Add a $trans object to Vue prototype with our translation methods
        Vue.prototype.$trans = {
            get: __,        // Regular translations
            site: __site    // Site-specific translations
        };
        
        // Add a convenient directive for translations
        Vue.directive('trans', {
            bind(el, binding) {
                const key = binding.value;
                const method = binding.arg === 'site' ? __site : __;
                el.innerText = method(key);
            },
            update(el, binding) {
                const key = binding.value;
                const method = binding.arg === 'site' ? __site : __;
                el.innerText = method(key);
            }
        });
    }
};

export default {
    __,
    __site,
    initTranslations,
    TranslationPlugin
}; 