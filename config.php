<?php
// ============================================================
//  DWELRO — Database Configuration
//  config.php — include this at the top of every PHP file
// ============================================================

define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_NAME',    'vyjcjbwj_dwel');
define('DB_USER',    'vyjcjbwj_dwel');
define('DB_PASS',    'Alvarez.1000');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}