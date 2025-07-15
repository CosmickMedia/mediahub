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
