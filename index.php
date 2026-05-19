<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'TravelAgency') {
        header("Location: agency_dashboard.php");
    } else {
        header("Location: traveller_dashboard.php");
    }
    exit;
}
header("Location: login.php");
exit;
?>