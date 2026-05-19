<?php
// reset_db.php - Universal Database Setup & Seeding Script

// Dynamically reference path to config relative to this file
$configPath = dirname(__DIR__) . '/includes/db_connect.php';
if (!file_exists($configPath)) {
    // Try root config as fallback if db_connect isn't used directly
    $configPath = dirname(__DIR__) . '/config.php';
}

if (!file_exists($configPath)) {
    die("ERROR: Config file not found. Please ensure you have copied config.php.example to config.php.\n");
}

require_once $configPath;

try {
    echo "Disabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // Get list of tables to clear any existing environment cleanly
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "Dropping table $table...\n";
        $pdo->exec("DROP TABLE IF EXISTS `$table` CASCADE;");
    }

    echo "Enabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Load and execute schema
    $schemaPath = dirname(__DIR__) . '/Tripistry_schema.sql';
    if (!file_exists($schemaPath)) {
        throw new Exception("Schema file not found at: $schemaPath");
    }
    echo "Creating schema from Tripistry_schema.sql...\n";
    $schemaSql = file_get_contents($schemaPath);
    $pdo->exec($schemaSql);

    // Load and execute seed data
    $seedPath = dirname(__DIR__) . '/seed.sql';
    if (!file_exists($seedPath)) {
        throw new Exception("Seed file not found at: $seedPath");
    }
    echo "Seeding database with premium mock accounts...\n";
    $seedSql = file_get_contents($seedPath);
    $pdo->exec($seedSql);

    echo "SUCCESS: Database successfully reset and seeded!\n";
} catch (Exception $e) {
    echo "DATABASE INITIALIZATION ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
