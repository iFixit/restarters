// Get the initial instance from the window object (will be set by the backend)
const initialInstance = typeof window !== 'undefined' ? window.appInstance || "base" : "base";

// Get the initial locale from the HTML tag or default
const initialLocale = typeof document !== 'undefined' ? document.documentElement.lang || "en" : "en";

class LangJS {
    constructor(options = {}) {
        this.messages = options.messages || {};
        this.instance = options.instance || initialInstance;
        this.locale = options.locale || initialLocale;
        this.fallbackInstance = options.fallbackInstance || "base";
        this.fallbackLocale = options.fallbackLocale || "en";
        // For debugging, can be enabled via options:
        this.debug = options.debug || false;
    }

    setMessages(messages) {
        this.messages = messages;
        return this;
    }

    setFallbackInstance(fallbackInstance) {
        this.fallbackInstance = fallbackInstance;
        return this;
    }

    setFallbackLocale(fallbackLocale) {
        this.fallbackLocale = fallbackLocale;
        return this;
    }

    setInstance(instance) {
        this.instance = instance;
        return this;
    }

    setLocale(locale) {
        this.locale = locale;
        return this;
    }

    /**
     * Resolves a potentially nested key against the messages object for a given
     * instance and locale.
     * @param {object} messages - The entire translation messages object.
     * @param {string} key - The translation key (e.g., "title" or "login.login_title").
     * @param {string} instanceToUse - The instance to try.
     * @param {string} localeToUse - The locale to try.
     * @returns {string|object|null} The translation or null if not found.
     */
    _resolveKey(messages, key, instanceToUse, localeToUse) {
        const keyParts = key.split('.');

        /**
         * This corresponds to <group|key> in instances.<instance>.<locale>.<group|key>
         */
        const firstKeyPart = keyParts[0];
        
        const pathInMessages = `instances.${instanceToUse}.${localeToUse}.${firstKeyPart}`;
        let current = messages[pathInMessages];

        if (current === undefined) {
            return null;
        }

        // Navigate deeper if the original key was nested (e.g., "login.login_title")
        for (let i = 1; i < keyParts.length; i++) {
            if (typeof current !== 'object' || current === null || !current.hasOwnProperty(keyParts[i])) {
                return null;
            }
            current = current[keyParts[i]];
        }

        return current !== undefined ? current : null;
    }

    /**
     * @param {string} key - The translation key.
     * @returns {string|object|null} The translation or null if not found.
     */
    _getMessage(key) {
        const attempts = [];

        // 1. Current instance, current locale
        attempts.push({ inst: this.instance, loc: this.locale, desc: "current instance, current locale" });

        // 2. Fallback instance, current locale (if different from attempt 1)
        if (this.instance !== this.fallbackInstance) {
            attempts.push({ inst: this.fallbackInstance, loc: this.locale, desc: "fallback instance, current locale" });
        }

        // 3. Fallback instance, fallback locale (if different from previous attempts and fallback locale is different)
        if (this.locale !== this.fallbackLocale) {
            const fallbackAttempt = { inst: this.fallbackInstance, loc: this.fallbackLocale, desc: "fallback instance, fallback locale" };
            if (!attempts.some(att => att.inst === fallbackAttempt.inst && att.loc === fallbackAttempt.loc)) {
                attempts.push(fallbackAttempt);
            }
        }

        // Ensure all attempts are unique (e.g. if instance == fallbackInstance but locale != fallbackLocale)
        const uniqueAttempts = attempts.reduce((acc, current) => {
            if (!acc.find(item => item.inst === current.inst && item.loc === current.loc)) {
                acc.push(current);
            }
            return acc;
        }, []);
        
        for (const attempt of uniqueAttempts) {
            if (this.debug) {
                console.log(`Attempting key '${key}' with ${attempt.desc} (${attempt.inst}, ${attempt.loc})`);
            }

            const message = this._resolveKey(this.messages, key, attempt.inst, attempt.loc);
            if (message !== null) {
                if (this.debug) {
                    console.info(`Found message for '${key}' with ${attempt.desc}`);
                }

                return message;
            }
        }

        return null;
    }

    /**
     * Get a translation message.
     * @param {string} key - The translation key (e.g., "title" or "group.key").
     * @param {object} [replacements={}] - Optional replacements for placeholders.
     * @returns {string|object} The translated string, object, or the key itself if not found.
     */
    get(key, replacements = {}) {
        const message = this._getMessage(key);

        if (message === null) {
            return key; // Return the key if no translation is found
        }

        if (typeof message === "object") {
            return message; // Return the object directly if it's not a string
        }

        return this._makeReplacements(message, replacements);
    }

    /**
     * Check if a translation key exists.
     * @param {string} key - The translation key.
     * @returns {boolean} True if the key exists, false otherwise.
     */
    has(key) {
        return this._getMessage(key) !== null;
    }

    /**
     * Make replacements in a message string.
     * @param {string} message - The message string with placeholders (e.g., ":name").
     * @param {object} replacements - An object of replacements {key: value}.
     * @returns {string} The message with replacements made.
     */
    _makeReplacements(message, replacements) {
        let result = message;
        for (const [placeholder, value] of Object.entries(replacements)) {
            result = result.replace(new RegExp(`:${placeholder}`, "g"), String(value));
        }
        return result;
    }
}

export default LangJS;