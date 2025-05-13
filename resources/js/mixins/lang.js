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

    getLocale() {
        return this.locale;
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

    /**
     * Gets the plural or singular form of the message specified based on an integer value.
     * @param {string} key - The translation key.
     * @param {number} count - The number of elements.
     * @param {object} [replacements={}] - Optional replacements for placeholders.
     * @returns {string} The translation message according to an integer value.
     */
    choice(key, count, replacements = {}) {
        replacements.count = count;
        const message = this._getMessage(key);
        if (message === null || message === undefined) {
            return key;
        }
        if (typeof message !== 'string') {
            return message;
        }
        const messageParts = message.split('|').map(part => part.trim());
        if (messageParts.length === 1) {
            return this._makeReplacements(messageParts[0], replacements);
        }
        const pluralForm = this._getPluralForm(count, this.locale);
        const selected = messageParts[pluralForm] !== undefined ? messageParts[pluralForm] : messageParts[0];
        return this._makeReplacements(selected, replacements);
    }

    /**
     * Returns the plural position to use for the given locale and number.
     * @param {Number} count
     * @param {String} locale
     * @return {Number}
     */
    _getPluralForm(count, locale) {
        switch (locale) {
            case 'az':
            case 'bo':
            case 'dz':
            case 'id':
            case 'ja':
            case 'jv':
            case 'ka':
            case 'km':
            case 'kn':
            case 'ko':
            case 'ms':
            case 'th':
            case 'tr':
            case 'vi':
            case 'zh':
                return 0;

            case 'af':
            case 'bn':
            case 'bg':
            case 'ca':
            case 'da':
            case 'de':
            case 'el':
            case 'en':
            case 'eo':
            case 'es':
            case 'et':
            case 'eu':
            case 'fa':
            case 'fi':
            case 'fo':
            case 'fur':
            case 'fy':
            case 'gl':
            case 'gu':
            case 'ha':
            case 'he':
            case 'hu':
            case 'is':
            case 'it':
            case 'ku':
            case 'lb':
            case 'ml':
            case 'mn':
            case 'mr':
            case 'nah':
            case 'nb':
            case 'ne':
            case 'nl':
            case 'nn':
            case 'no':
            case 'om':
            case 'or':
            case 'pa':
            case 'pap':
            case 'ps':
            case 'pt':
            case 'so':
            case 'sq':
            case 'sv':
            case 'sw':
            case 'ta':
            case 'te':
            case 'tk':
            case 'ur':
            case 'zu':
                return (count == 1)
                    ? 0
                    : 1;

            case 'am':
            case 'bh':
            case 'fil':
            case 'fr':
            case 'gun':
            case 'hi':
            case 'hy':
            case 'ln':
            case 'mg':
            case 'nso':
            case 'xbr':
            case 'ti':
            case 'wa':
                return ((count === 0) || (count === 1))
                    ? 0
                    : 1;

            case 'be':
            case 'bs':
            case 'hr':
            case 'ru':
            case 'sr':
            case 'uk':
                return ((count % 10 == 1) && (count % 100 != 11))
                    ? 0
                    : (((count % 10 >= 2) && (count % 10 <= 4) && ((count % 100 < 10) || (count % 100 >= 20)))
                        ? 1
                        : 2);

            case 'cs':
            case 'sk':
                return (count == 1)
                    ? 0
                    : (((count >= 2) && (count <= 4))
                        ? 1
                        : 2);

            case 'ga':
                return (count == 1)
                    ? 0
                    : ((count == 2)
                        ? 1
                        : 2);

            case 'lt':
                return ((count % 10 == 1) && (count % 100 != 11))
                    ? 0
                    : (((count % 10 >= 2) && ((count % 100 < 10) || (count % 100 >= 20)))
                        ? 1
                        : 2);

            case 'sl':
                return (count % 100 == 1)
                    ? 0
                    : ((count % 100 == 2)
                        ? 1
                        : (((count % 100 == 3) || (count % 100 == 4))
                            ? 2
                            : 3));

            case 'mk':
                return (count % 10 == 1)
                    ? 0
                    : 1;

            case 'mt':
                return (count == 1)
                    ? 0
                    : (((count === 0) || ((count % 100 > 1) && (count % 100 < 11)))
                        ? 1
                        : (((count % 100 > 10) && (count % 100 < 20))
                            ? 2
                            : 3));

            case 'lv':
                return (count === 0)
                    ? 0
                    : (((count % 10 == 1) && (count % 100 != 11))
                        ? 1
                        : 2);

            case 'pl':
                return (count == 1)
                    ? 0
                    : (((count % 10 >= 2) && (count % 10 <= 4) && ((count % 100 < 12) || (count % 100 > 14)))
                        ? 1
                        : 2);

            case 'cy':
                return (count == 1)
                    ? 0
                    : ((count == 2)
                        ? 1
                        : (((count == 8) || (count == 11))
                            ? 2
                            : 3));

            case 'ro':
                return (count == 1)
                    ? 0
                    : (((count === 0) || ((count % 100 > 0) && (count % 100 < 20)))
                        ? 1
                        : 2);

            case 'ar':
                return (count === 0)
                    ? 0
                    : ((count == 1)
                        ? 1
                        : ((count == 2)
                            ? 2
                            : (((count % 100 >= 3) && (count % 100 <= 10))
                                ? 3
                                : (((count % 100 >= 11) && (count % 100 <= 99))
                                    ? 4
                                    : 5))));

            default:
                return 0;
        }
    };
}

export default LangJS;