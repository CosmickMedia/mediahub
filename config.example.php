<?php
// Copy this file to config.php and fill in your settings
return [
    'db' => [
        'host' => 'localhost',
        'dbname' => 'storeuploads',
        'user' => 'dbuser',
        'pass' => 'dbpass',
    ],
    'admin_password' => 'changeme',
    'service_account_json' => __DIR__.'/service-account.json',
    'drive_base_folder' => '',
    'notification_email' => 'admin@example.com',
];
