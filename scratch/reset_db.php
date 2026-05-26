<?php
// reset_db.php - Universal Database Setup & Seeding Script

// Dynamically reference path to config relative to this file
$configPath = dirname(__DIR__) . '/config.php';

if (!file_exists($configPath)) {
    die("ERROR: Config file not found. Please ensure you have copied config.php.example to config.php.\n");
}

try {
    // Read and parse config.php to connect without specifying a database first
    $configContent = file_get_contents($configPath);
    
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    if (preg_match('/\$host\s*=\s*[\'"]([^\'"]+)[\'"]/', $configContent, $matches)) {
        $host = $matches[1];
    }
    if (preg_match('/\$user\s*=\s*[\'"]([^\'"]*)[\'"]/', $configContent, $matches)) {
        $user = $matches[1];
    }
    if (preg_match('/\$pass\s*=\s*[\'"]([^\'"]*)[\'"]/', $configContent, $matches)) {
        $pass = $matches[1];
    }
    if (preg_match('/\$charset\s*=\s*[\'"]([^\'"]+)[\'"]/', $configContent, $matches)) {
        $charset = $matches[1];
    }

    $dsn = "mysql:host=$host;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Load and execute unified dump
    $dumpPath = dirname(__DIR__) . '/Database dump.sql';
    if (!file_exists($dumpPath)) {
        throw new Exception("Unified database dump file not found at: $dumpPath");
    }
    
    echo "Recreating database and seeding tables from Database dump.sql...\n";
    $dumpSql = file_get_contents($dumpPath);
    
    // Disable foreign key checks for safety during execution
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec($dumpSql);
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "SUCCESS: Database successfully reset and seeded!\n";
} catch (Exception $e) {
    echo "DATABASE INITIALIZATION ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
