<?php
// delete_package.php
session_start();
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
    http_response_code(403);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['package_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM TravelPackage WHERE PackageID = ? AND AgencyID = ?");
        $stmt->execute([$_POST['package_id'], $_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Log error, etc.
    }
}
header("Location: agency_dashboard.php");
exit;
?>