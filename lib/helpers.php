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
