<?php

namespace App\Services\Ords;

/**
 * Redacts personal data from `devices.problem` before export. Personal names
 * are not pattern-detectable and are NOT removed.
 */
class ProblemTextScrubber
{
    public const EMAIL = 'email';
    public const URL_QUERY = 'url_query';
    public const PHONE = 'phone';
    public const LONG_DIGITS = 'long_digits';
    public const HTML = 'html';

    private const PLACEHOLDERS = [
        self::EMAIL => '[email removed]',
        self::PHONE => '[phone removed]',
        self::LONG_DIGITS => '[number removed]',
    ];

    /** @var array<string,int> */
    private array $counts = [];

    public function __construct()
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->counts = [
            self::HTML => 0,
            self::URL_QUERY => 0,
            self::EMAIL => 0,
            self::PHONE => 0,
            self::LONG_DIGITS => 0,
        ];
    }

    /** @return array<string,int> */
    public function counts(): array
    {
        return $this->counts;
    }

    public function totalRedactions(): int
    {
        return array_sum($this->counts);
    }

    public function scrub(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }

        $text = $this->stripHtml($text);
        // Must run before the digit/phone passes, or they chew through tracking params.
        $text = $this->stripUrlQueryStrings($text);
        $text = $this->redactEmails($text);
        $text = $this->redactPhoneNumbers($text);
        $text = $this->redactLongDigitRuns($text);

        return $this->normaliseWhitespace($text);
    }

    /**
     * Not strip_tags(): it truncates after an unterminated "<". Replaced with
     * a space, not deleted, so tags don't fuse adjacent words.
     */
    private function stripHtml(string $text): string
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $count = 0;
        $result = preg_replace('~<\s*/?\s*[a-z!][^>]*>~iu', ' ', $decoded, -1, $count);

        return $this->record($result, $count > 0 ? 1 : 0, self::HTML);
    }

    private function stripUrlQueryStrings(string $text): string
    {
        return $this->replace(
            '~(https?://[^\s<>"\']+?)[?#][^\s<>"\']*~iu',
            '$1',
            $text,
            self::URL_QUERY
        );
    }

    /**
     * Exclusion-based match, not an ASCII allow-list, so international addresses redact whole.
     */
    private function redactEmails(string $text): string
    {
        // Fullwidth (U+FF20)/small (U+FE6B) commercial-at also render as "@" and must be normalised first.
        $normalised = str_replace(["\u{FF20}", "\u{FE6B}"], '@', $text);

        return $this->replace(
            '~[^\s@<>"\'()\[\],;:]+@[^\s@<>"\'()\[\],;:]+\.[^\s@<>"\'()\[\],;:.]{2,}~u',
            self::PLACEHOLDERS[self::EMAIL],
            $normalised,
            self::EMAIL
        );
    }

    /**
     * Separator-bearing sequences only; bare runs go to the digit pass below,
     * so an IMEI isn't mislabelled as a phone. Nine-digit floor: rpm ranges,
     * part numbers, firmware versions and year ranges sit at 8 digits or
     * fewer, while a dialable number needs 9+ once an area/country code is
     * present. A 7-digit local number is missed by design -- cheaper than
     * destroying ranges and part numbers.
     */
    private function redactPhoneNumbers(string $text): string
    {
        $count = 0;

        $result = preg_replace_callback(
            // Bounded on digits only: \b would let "phone-555-123-4567" through untouched.
            '~(?<!\d)\+?\d[\d\s().-]{5,}\d(?!\d)~u',
            function (array $match) use (&$count) {
                $candidate = $match[0];

                // Prefix match ("2024-06-15 14:30" arrives as "2024-06-15 14"):
                // ":" is absent from the candidate class.
                if (preg_match('/^\d{4}-\d{2}-\d{2}(?!\d)/', $candidate)) {
                    return $candidate;
                }

                $digits = preg_match_all('/\d/u', $candidate);
                $hasSeparator = (bool) preg_match('/[\s().-]/u', $candidate);

                if ($digits < 9 || ! $hasSeparator) {
                    return $candidate;
                }

                $count++;

                return self::PLACEHOLDERS[self::PHONE];
            },
            $text
        );

        return $this->record($result, $count, self::PHONE);
    }

    private function redactLongDigitRuns(string $text): string
    {
        return $this->replace(
            '~(?<!\d)\d{8,}(?!\d)~u',
            self::PLACEHOLDERS[self::LONG_DIGITS],
            $text,
            self::LONG_DIGITS
        );
    }

    private function replace(string $pattern, string $replacement, string $text, string $countKey): string
    {
        $count = 0;
        $result = preg_replace($pattern, $replacement, $text, -1, $count);

        return $this->record($result, $count, $countKey);
    }

    /** Null result = regex engine failed; must never leak the original, so text is dropped and the failure counted. */
    private function record(?string $result, int $count, string $countKey): string
    {
        if ($result === null) {
            $this->counts[$countKey]++;

            return '';
        }

        $this->counts[$countKey] += $count;

        return $result;
    }

    private function normaliseWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
