<?php
function format_ts($time): string {
    if ($time === null || $time === '') {
        return '';
    }
    return date('n-j-y g:ia', strtotime($time));
}

/**
 * Sanitize a chat message allowing basic formatting tags.
 */
function sanitize_message(string $msg): string {
    $msg = trim($msg);
    // Allow simple formatting tags
    $allowed = '<b><i><u><strong><em>';
    return strip_tags($msg, $allowed);
}

/**
 * Shorten a filename to prevent overly long text in tables.
 * Shows the first part of the basename followed by the extension.
 */
function shorten_filename(string $filename, int $baseLength = 8): string {
    $dotPos = strrpos($filename, '.');
    if ($dotPos === false) {
        $base = $filename;
        $ext = '';
    } else {
        $base = substr($filename, 0, $dotPos);
        $ext = substr($filename, $dotPos);
    }

    if (strlen($base) <= $baseLength) {
        return $filename;
    }

    return substr($base, 0, $baseLength) . '...' . $ext;
}

function format_mobile_number(string $number): string {
    $digits = preg_replace('/\D+/', '', $number);
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) === 10) {
        $digits = '1' . $digits;
    }
    if ($digits[0] !== '1') {
        // assume already has country code
        return '+' . $digits;
    }
    return '+' . $digits;
}

/**
 * Generate common phone number variations for Groundhogg.
 *
 * @param string $number Raw phone number from user input
 * @return array [digits, with country code, dashed]
 */
function phone_number_variations(string $number): array {
    $digits = preg_replace('/\D+/', '', $number);
    if ($digits === '') {
        return [];
    }
    $intl = $digits;
    if (strlen($intl) === 10) {
        $intl = '1' . $intl;
    }
    $intl = '+' . $intl;

    $dash = $digits;
    if (strlen($digits) === 11 && $digits[0] === '1') {
        $dash = substr($digits, 1, 3) . '-' . substr($digits, 4, 3) . '-' . substr($digits, 7);
    } elseif (strlen($digits) === 10) {
        $dash = substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6);
    }

    return [$digits, $intl, $dash];
}

/**
 * Decode a value if it's a JSON string. If decoding fails, the original
 * value is returned.
 *
 * @param mixed $val Potential JSON string
 * @return mixed Decoded value or original
 */
function maybe_json_decode($val) {
    if (is_string($val)) {
        $trim = trim($val);
        if ($trim !== '') {
            $decoded = json_decode($trim, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
    }
    return $val;
}

/**
 * Convert a value to an array of strings.
 *
 * Accepts arrays, JSON-encoded strings or comma separated lists. Whitespace
 * is trimmed from each element and empty values are removed.
 *
 * @param mixed $val Potential array representation
 * @return array Array of strings
 */
function to_string_array($val): array {
    if (is_array($val)) {
        return array_values(array_filter(array_map('trim', $val), 'strlen'));
    }

    if (is_string($val)) {
        $trim = trim($val);
        if ($trim === '') return [];

        $decoded = json_decode($trim, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return to_string_array($decoded);
        }

        return array_values(array_filter(array_map('trim', explode(',', $trim)), 'strlen'));
    }

    return [];
}
