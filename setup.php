<?php
/**
 * Setup script to initialize database tables and create default admin user.
 */
$config = require __DIR__.'/config.php';

try {
    $pdo = new PDO("mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset=utf8mb4", $config['db']['user'], $config['db']['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit("DB Connection failed: " . $e->getMessage());
}

$queries = [
    // Stores table
    "CREATE TABLE IF NOT EXISTS stores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        pin VARCHAR(50) NOT NULL UNIQUE,
        admin_email VARCHAR(255),
        drive_folder VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // Uploads table
    "CREATE TABLE IF NOT EXISTS uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        description TEXT,
        created_at DATETIME NOT NULL,
        ip VARCHAR(45) NOT NULL,
        mime VARCHAR(100) NOT NULL,
        size INT NOT NULL,
        drive_id VARCHAR(255),
        FOREIGN KEY (store_id) REFERENCES stores(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // Settings table
    "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // Logs table
    "CREATE TABLE IF NOT EXISTS logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT,
        action VARCHAR(50),
        message TEXT,
        created_at DATETIME NOT NULL,
        ip VARCHAR(45) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($queries as $sql) {
    $pdo->exec($sql);
}

// create default admin user
$username = 'admin';
$password = password_hash($config['admin_password'], PASSWORD_DEFAULT);
$pdo->prepare("INSERT IGNORE INTO users (username, password) VALUES (?, ?)")->execute([$username, $password]);

echo "Setup complete\n";
