<?php
function format_ts($time): string {
    if ($time === null || $time === '') {
        return '';
    }
    return date('n-j-y g:ia', strtotime($time));
}
