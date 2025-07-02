<?php
/**
 * Configuration loader. Attempts to load config.php and falls back to
 * config.example.php if the real configuration file is missing. This
 * avoids fatal errors during initial setup.
 */
function get_config(): array {
    static $cfg;
    if ($cfg !== null) {
        return $cfg;
    }
    $primary = __DIR__ . '/../config.php';
    if (file_exists($primary)) {
        $cfg = require $primary;
    } else {
        $example = __DIR__ . '/../config.example.php';
        if (file_exists($example)) {
            $cfg = require $example;
        } else {
            throw new Exception('No configuration file found');
        }
    }
    return $cfg;
}
