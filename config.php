<?php
// config.php


// where PHP should write its custom error logs
define('ERROR_LOG_PATH', __DIR__ . '/logs/php_errors.log');


//–– MySQL connection settings –– 
$db_host = 'localhost';         // or 127.0.0.1
$db_name = 'pos_system';        // make sure this matches your actual database name
$db_user = 'root';              // your MySQL username
$db_pass = '';                  // your MySQL password (often empty for root on local setups)

try {
    // build the DSN and set UTF-8
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    $conn = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // show the real error to help debug
    die('Connection failed: ' . $e->getMessage());
}
