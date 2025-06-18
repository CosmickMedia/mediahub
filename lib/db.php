<?php
/** Database connection helper */
function get_pdo(): PDO {
    static $pdo;
    if (!$pdo) {
        $config = require __DIR__.'/../config.php';
        $pdo = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset=utf8mb4", $config['db']['user'], $config['db']['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}
