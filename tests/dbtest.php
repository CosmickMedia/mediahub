<?php
require_once __DIR__.'/../lib/db.php';
$pdo = get_pdo();
var_dump($pdo->query('SELECT 1')->fetch());
