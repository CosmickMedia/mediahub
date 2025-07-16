<?php
require_once __DIR__.'/db.php';

if (!function_exists('get_setting')) {
    function get_setting(string $name) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE name=?');
        $stmt->execute([$name]);
        return $stmt->fetchColumn();
    }
}

if (!function_exists('set_setting')) {
    function set_setting(string $name, $value): void {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('INSERT INTO settings (name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)');
        $stmt->execute([$name, $value]);
    }
}
