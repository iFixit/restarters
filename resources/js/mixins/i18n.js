import * as Sentry from "@sentry/vue";
import translations from "../translations.js";
import LangJS from "./lang.js"; // Assuming Lang.js is in the same directory

export const Lang = new LangJS({
    messages: translations,
    // PHP returns 1 for true, 0 for false instead of true/false
    debug: window.appDebug === '1'
});

export default {
    computed: {
        $lang() {
            // Make Lang available in all components
            return Lang;
        },
    },
    methods: {
        __(key, replacements = {}) {
            const message = Lang.get(key, replacements);

            if (message === key && key !== null && typeof key === 'string') {
                const currentLocale = Lang.locale || 'unknown';
                console.error(`Missing translation for '${key}' in locale '${currentLocale}' (fallbacks checked).`);
                Sentry.captureMessage(`Missing translation for '${key}' in locale '${currentLocale}'.`);
            }
            return message;
        },
    },
};