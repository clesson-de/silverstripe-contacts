<?php

declare(strict_types=1);

namespace Clesson\Contacts\Helpers;

use Clesson\Contacts\Models\Contact;

/**
 * Helper for generating customer numbers from a configurable template.
 *
 * Template syntax:
 * - Everything outside `{}` is output literally.
 * - `{Y}`, `{m}`, `{d}`, `{y}`, `{H}`, `{i}`, `{s}` etc. — PHP date() format characters.
 * - `{N:length}` — random numeric string of the given length (e.g. `{N:4}` → `7382`).
 * - `{A:length}` — random uppercase alphabetic string (e.g. `{A:3}` → `XQR`).
 * - `{X:length}` — random alphanumeric uppercase string (e.g. `{X:6}` → `A3K9BZ`).
 *
 * Examples:
 * - `K-{Y}-{N:3}`   → `K-2026-047`
 * - `{Y}{m}-{X:4}`  → `202603-A7K2`
 * - `{A:2}{N:4}`    → `KX0391`
 *
 * @package Clesson\Contacts
 * @subpackage Helpers
 */
class CustomerNumberHelper
{

    /** Characters used for alphabetic random parts. */
    private const ALPHA_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /** Characters used for alphanumeric random parts. */
    private const ALNUM_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /** Default template used when none is configured. */
    public const DEFAULT_TEMPLATE = 'K-{Y}-{N:3}';

    /**
     * Processes a template string and returns the generated customer number.
     *
     * @param string $template
     * @return string
     */
    public static function generate(string $template): string
    {
        if (trim($template) === '') {
            $template = self::DEFAULT_TEMPLATE;
        }

        // Match all {…} tokens
        return preg_replace_callback(
            '/\{([^}]+)}/',
            static function (array $matches): string {
                $token = $matches[1];

                // Random numeric: N:length
                if (preg_match('/^N:(\d+)$/', $token, $m)) {
                    return self::randomNumeric((int) $m[1]);
                }

                // Random alphabetic: A:length
                if (preg_match('/^A:(\d+)$/', $token, $m)) {
                    return self::randomAlpha((int) $m[1]);
                }

                // Random alphanumeric: X:length
                if (preg_match('/^X:(\d+)$/', $token, $m)) {
                    return self::randomAlNum((int) $m[1]);
                }

                // PHP date() format character (single character or known multi-char combos)
                return date($token);
            },
            $template
        ) ?? '';
    }

    /**
     * Checks whether a customer number is unique across all Customer records.
     *
     * @param string $number    The candidate customer number.
     * @param int    $excludeId ID of the record being edited (excluded from the check). 0 for new records.
     * @return bool
     */
    public static function isUnique(string $number, int $excludeId = 0): bool
    {
        $query = Contact::get()->filter('CustomerNumber', $number);

        if ($excludeId > 0) {
            $query = $query->exclude('ID', $excludeId);
        }

        return $query->count() === 0;
    }

    /**
     * Generates a customer number from the template and retries up to $maxAttempts
     * times if the result is not unique. After exhausting retries it appends a
     * numeric suffix to guarantee uniqueness.
     *
     * @param string $template
     * @param int    $excludeId  ID of the record being edited (0 for new records).
     * @param int    $maxAttempts Number of generation attempts before appending a suffix.
     * @return string A unique customer number.
     */
    public static function generateUnique(string $template, int $excludeId = 0, int $maxAttempts = 10): string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $number = self::generate($template);
            if (self::isUnique($number, $excludeId)) {
                return $number;
            }
        }

        // Fallback: append an incrementing suffix until unique
        $base   = self::generate($template);
        $suffix = 2;
        $number = $base . '-' . $suffix;

        while (!self::isUnique($number, $excludeId)) {
            $suffix++;
            $number = $base . '-' . $suffix;
        }

        return $number;
    }

    /**
     * Returns a random numeric string of the given length.
     *
     * @param int $length
     * @return string
     */
    private static function randomNumeric(int $length): string
    {
        $length = max(1, $length);
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= random_int(0, 9);
        }
        return $result;
    }

    /**
     * Returns a random uppercase alphabetic string of the given length.
     *
     * @param int $length
     * @return string
     */
    private static function randomAlpha(int $length): string
    {
        return self::randomFrom(self::ALPHA_CHARS, $length);
    }

    /**
     * Returns a random uppercase alphanumeric string of the given length.
     *
     * @param int $length
     * @return string
     */
    private static function randomAlNum(int $length): string
    {
        return self::randomFrom(self::ALNUM_CHARS, $length);
    }

    /**
     * Returns a random string of the given length from the given character pool.
     *
     * @param string $chars
     * @param int    $length
     * @return string
     */
    private static function randomFrom(string $chars, int $length): string
    {
        $length = max(1, $length);
        $max    = strlen($chars) - 1;
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }
        return $result;
    }

}

