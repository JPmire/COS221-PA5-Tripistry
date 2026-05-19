<?php
// change this file to config.php
$host = '127.0.0.1'; //can change to phpmyadmin if that is what you are using but I use localhost
$db   = ''; // DB name
$user = ''; // db user      
$pass = ''; // local maridb/or whatever password         
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
    PDO::ATTR_EMULATE_PREPARES   => false,                  
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
   die(json_encode([
        "status" => "error",
        "message" => "Local Database connection failed: " . $e->getMessage()
    ]));
}
?>