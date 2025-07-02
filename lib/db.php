<?php
/** Database connection helper */
require_once __DIR__.'/config.php';

function get_pdo(): PDO {
    static $pdo;
    if (!$pdo) {
        $config = get_config();
        $pdo = new PDO(
            "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset=utf8mb4",
            $config['db']['user'],
            $config['db']['pass']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}
