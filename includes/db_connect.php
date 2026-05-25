<?php
// includes/db_connect.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';


// The $pdo variable is already created in config.php.
// We can include some common utility functions here if needed.

if (!isset($pdo)) {
    die(json_encode([
        "status" => "error",
        "message" => "Database connection object not found."
    ]));
}

// Automatically construct Notification table if missing to prevent manual XAMPP SQL imports
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Notification (
            NotificationID INT AUTO_INCREMENT PRIMARY KEY,
            UserID INT NOT NULL,
            Title VARCHAR(255) NOT NULL,
            Message TEXT NOT NULL,
            IsRead TINYINT(1) DEFAULT 0,
            CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (UserID) REFERENCES User(UserID) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    error_log("Notification schema generation failed: " . $e->getMessage());
}

// Automatically check and append ImageURL VARCHAR(500) column to core tables if missing
$tablesWithImages = ['TravelPackage', 'Destination', 'Accommodation', 'Attraction', 'Restaurant'];
foreach ($tablesWithImages as $table) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'ImageURL'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN ImageURL VARCHAR(500) DEFAULT NULL");
        }
    } catch (PDOException $e) {
        error_log("Failed to add ImageURL column to $table: " . $e->getMessage());
    }
}


// Function to safely execute queries
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return false;
    }
}
?>