<?php
function format_ts($time): string {
    if ($time === null || $time === '') {
        return '';
    }
    return date('n-j-y g:ia', strtotime($time));
}

function shorten_filename(string $filename, int $cutoff = 8): string {
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $name = pathinfo($filename, PATHINFO_FILENAME);
    if (strlen($name) <= $cutoff) {
        return $filename;
    }
    return substr($name, 0, $cutoff) . '...' . ($ext ? '.' . $ext : '');
}
