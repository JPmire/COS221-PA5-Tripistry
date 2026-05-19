<?php
// login.php
session_start();
require_once 'db.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'traveller';

    if (empty($email) || empty($password)) {
        die("Please fill in all fields.");
    }

    try {
        $stmt = $pdo->prepare("SELECT UserID, PasswordHash, AccountStatus FROM User WHERE Email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['AccountStatus'] !== 'Active') {
                die("Your account is " . htmlspecialchars($user['AccountStatus']));
            }

            if ($password === $user['PasswordHash']) { 
                
                $roleTable = ($role === 'agency') ? 'TravelAgency' : 'Traveller';
                $roleStmt = $pdo->prepare("SELECT * FROM $roleTable WHERE UserID = ?");
                $roleStmt->execute([$user['UserID']]);
                $roleData = $roleStmt->fetch();

                if ($roleData) {
              
                    $_SESSION['user_id'] = $user['UserID'];
                    $_SESSION['role'] = $role;
                    
                    if ($role === 'traveller') {
                        $_SESSION['name'] = $roleData['FirstName'];
                        header("Location: dashboard.php"); 
                    } else {
                        $_SESSION['name'] = $roleData['AgencyName'];
                        header("Location: agency_dashboard.php"); 
                    }
                    exit();
                } else {
                    echo "Account exists, but not as a " . htmlspecialchars($role) . ".";
                }
            } else {
                echo "Invalid password.";
            }
        } else {
            echo "No account found with that email.";
        }
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
} else {
    header("Location: login.html");
    exit();
}
?>