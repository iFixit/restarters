/**
 * Translation helper for Vue components
 * 
 * This module provides translation functions that can be used in Vue components
 * to access translations loaded from the server.
 */

// These will be populated from the global window object
let translations = {};
let locale = 'en';

/**
 * Initialize the translation module with data from the server
 */
export function initTranslations() {
    // Check if window.translations exists (should be set in the layout)
    if (window.translations) {
        translations = window.translations;
    }
    
    // Get the current locale
    if (window.translationsLocale) {
        locale = window.translationsLocale;
    }
    
    console.log(`Translations initialized with locale: ${locale}`);
    console.log('Available translation groups:', Object.keys(translations));
}

/**
 * Get a translation from the loaded translations
 * Site-specific translations are already merged in the PHP side
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
        
        // Add a $translate function to Vue prototype
        Vue.prototype.$translate = __;

        // Add a convenient directive for translations
        Vue.directive('translate', {
            bind(el, binding) {
                el.innerText = __(binding.value);
            },
            update(el, binding) {
                el.innerText = __(binding.value);
            }
        });
    }
};

export default {
    __,
    initTranslations,
    TranslationPlugin
}; 