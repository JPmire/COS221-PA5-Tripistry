<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'config.php';

class TripistryAPI {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function sendResponse($status, $data_or_message, $httpCode = 200) {
        http_response_code($httpCode); 
        $response = ["status" => $status];
        if ($status === "success") {
            $response["data"] = $data_or_message;
        } else {
            $response["message"] = $data_or_message; 
        }
        echo json_encode($response);
        exit;
    }

    public function handleRequest() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data || !isset($data['type'])) {
            $this->sendResponse("error", "Invalid or missing payload type.", 400);
        }

        switch ($data['type']) {
            case 'Login':
                $this->loginUser($data);
                break;
            case 'Register':
                $this->registerUser($data);
                break;
            case 'GetAllPackages':
                $this->getAllPackages($data);
                break;
            case 'GetPackageDetails':
                $this->getPackageDetails($data);
                break;
            case 'BookPackage':
                $this->bookPackage($data);
                break;
            default:
                $this->sendResponse("error", "Unknown API endpoint: " . $data['type'], 400);
        }
    }

    //Skeleton login functionaly

    private function loginUser($data) {

        /* 
         * I routed this endpoint so the API doesn't break, and I threw 
         * some basic logic in here when for some testing my own frontend.
         * * Feel free to completely delete this and write your own, or 
         * use it as a reference for how the PDO queries are structured
         */


        // if (empty($data['email']) || empty($data['password'])) {
        //     $this->sendResponse("error", "Missing email or password.", 400);
        // }

        // try {
        //     //  Check Base User Table
        //     $stmt = $this->pdo->prepare("SELECT UserID, PasswordHash, AccountStatus FROM User WHERE Email = ?");
        //     $stmt->execute([$data['email']]);
        //     $user = $stmt->fetch();

        //     if (!$user || !password_verify($data['password'], $user['PasswordHash'])) {
        //         $this->sendResponse("error", "Invalid credentials.", 401);
        //     }

        //     if ($user['AccountStatus'] !== 'Active') {
        //         $this->sendResponse("error", "Account is " . $user['AccountStatus'], 403);
        //     }

        //     // Determine Role based on Subclass Tables
        //     $role = 'Unknown';
        //     $name = '';

        //     $stmtTraveller = $this->pdo->prepare("SELECT FirstName, LastName FROM Traveller WHERE UserID = ?");
        //     $stmtTraveller->execute([$user['UserID']]);
        //     $traveller = $stmtTraveller->fetch();

        //     if ($traveller) {
        //         $role = 'Traveller';
        //         $name = $traveller['FirstName'] . ' ' . $traveller['LastName'];
        //     } else {
        //         $stmtAgency = $this->pdo->prepare("SELECT AgencyName FROM TravelAgency WHERE UserID = ?");
        //         $stmtAgency->execute([$user['UserID']]);
        //         $agency = $stmtAgency->fetch();
        //         if ($agency) {
        //             $role = 'Agency';
        //             $name = $agency['AgencyName'];
        //         }
        //     }

        //     // Update Last Login
        //     $this->pdo->prepare("UPDATE User SET LastLoginTime = NOW() WHERE UserID = ?")->execute([$user['UserID']]);

        //     $this->sendResponse("success", [
        //         "user_id" => $user['UserID'],
        //         "role" => $role,
        //         "name" => $name
        //     ]);

        // } catch (\PDOException $e) {
        //     $this->sendResponse("error", "Database error: " . $e->getMessage(), 500);
        // }
    }

    private function registerUser($data) {
    // Skeleton register
    //     $this->sendResponse("error", "Register endpoint under construction.", 501);
    }

    // Package browsing and filtering

    private function getAllPackages($data) {
        //Get the Agency Name and Rating
        $sql = "SELECT p.PackageID, p.Title, p.BasePrice, p.DurationDays, a.AgencyName, a.AverageRating 
                FROM TravelPackage p
                JOIN TravelAgency a ON p.AgencyID = a.UserID";
        
        $conditions = [];
        $params = [];

        // If filtering by destination, join destination tables
        if (!empty($data['search']['destination'])) {
            $sql .= " LEFT JOIN Package_Destination pd ON p.PackageID = pd.PackageID 
                      LEFT JOIN Destination d ON pd.DestID = d.DestID";
            $conditions[] = "(d.Name LIKE ? OR d.Country LIKE ?)";
            $params[] = "%" . $data['search']['destination'] . "%";
            $params[] = "%" . $data['search']['destination'] . "%";
        }

        // Apply Filters
        if (isset($data['search'])) {
            if (!empty($data['search']['title'])) {
                $conditions[] = "p.Title LIKE ?";
                $params[] = "%" . $data['search']['title'] . "%";
            }
            if (!empty($data['search']['max_price'])) {
                $conditions[] = "p.BasePrice <= ?";
                $params[] = (float)$data['search']['max_price'];
            }
            if (!empty($data['search']['max_duration'])) {
                $conditions[] = "p.DurationDays <= ?";
                $params[] = (int)$data['search']['max_duration'];
            }
            if (!empty($data['search']['min_rating'])) {
                $conditions[] = "a.AverageRating >= ?";
                $params[] = (float)$data['search']['min_rating'];
            }
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        // Group By to prevent duplicates if a package has multiple destinations
        $sql .= " GROUP BY p.PackageID";

        // Apply Sorting
        $valid_sorts = ['BasePrice', 'DurationDays', 'AverageRating'];
        if (!empty($data['sort']) && in_array($data['sort'], $valid_sorts)) {
            $order = (!empty($data['order']) && strtoupper($data['order']) === 'DESC') ? 'DESC' : 'ASC';
            $sortField = ($data['sort'] === 'AverageRating') ? "a.AverageRating" : "p." . $data['sort'];
            $sql .= " ORDER BY " . $sortField . " $order";
        } else {
            $sql .= " ORDER BY p.PackageID DESC"; //  sort
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $this->sendResponse("success", $stmt->fetchAll());
        } catch (\PDOException $e) {
            $this->sendResponse("error", "Database query failed: " . $e->getMessage(), 500);
        }
    }

    //Package View

    private function getPackageDetails($data) {
        if (empty($data['package_id'])) {
            $this->sendResponse("error", "Package ID is required.", 400);
        }
        $pkgId = (int)$data['package_id'];
        
        try {
            // Fetch Main Package & Agency Info
            $stmt = $this->pdo->prepare("SELECT p.*, a.AgencyName, a.AverageRating, a.Address_City, a.Address_Street 
                                         FROM TravelPackage p 
                                         JOIN TravelAgency a ON p.AgencyID = a.UserID 
                                         WHERE p.PackageID = ?");
            $stmt->execute([$pkgId]);
            $package = $stmt->fetch();
            
            if (!$package) $this->sendResponse("error", "Package not found.", 404);

            //Fetch Destinations 
            $stmt = $this->pdo->prepare("SELECT d.Name, d.Country, d.Region 
                                         FROM Destination d 
                                         JOIN Package_Destination pd ON d.DestID = pd.DestID 
                                         WHERE pd.PackageID = ?");
            $stmt->execute([$pkgId]);
            $package['destinations'] = $stmt->fetchAll();

            //Fetch Accommodations
            $stmt = $this->pdo->prepare("SELECT ac.Name, ac.Type, ac.StarRating 
                                         FROM Accommodation ac 
                                         JOIN Package_Accommodation pa ON ac.AccommID = pa.AccommID 
                                         WHERE pa.PackageID = ?");
            $stmt->execute([$pkgId]);
            $package['accommodations'] = $stmt->fetchAll();

            // Fetch Reviews
            $stmt = $this->pdo->prepare("SELECT r.Rating, r.Comment, r.DatePosted, t.FirstName 
                                         FROM Review r 
                                         JOIN Traveller t ON r.TravellerID = t.UserID 
                                         WHERE r.TargetPackageID = ? 
                                         ORDER BY r.DatePosted DESC");
            $stmt->execute([$pkgId]);
            $package['reviews'] = $stmt->fetchAll();

            $this->sendResponse("success", $package);

        } catch (\PDOException $e) {
            $this->sendResponse("error", "Failed to fetch package details: " . $e->getMessage(), 500);
        }
    }

    //Skeleton book

    private function bookPackage($data) {
        // $required = ['traveller_id', 'package_id', 'trip_date_id', 'party_size', 'total_amount'];
        // foreach ($required as $field) {
        //     if (empty($data[$field])) {
        //         $this->sendResponse("error", "Missing required field: $field", 400);
        //     }
        // }

        // try {
            
        //     $stmt = $this->pdo->prepare("INSERT INTO Booking (TravellerID, Trip_PackageID, Trip_TripDateID, PartySize, TotalAmount, PaymentStatus) 
        //                                  VALUES (?, ?, ?, ?, ?, 'Pending')");
        //     $stmt->execute([
        //         (int)$data['traveller_id'], 
        //         (int)$data['package_id'], 
        //         (int)$data['trip_date_id'], 
        //         (int)$data['party_size'], 
        //         (float)$data['total_amount']
        //     ]);

        //     $this->sendResponse("success", ["booking_id" => $this->pdo->lastInsertId(), "message" => "Booking successful!"]);
        // } catch (\PDOException $e) {
        //     $this->sendResponse("error", "Failed to process booking: " . $e->getMessage(), 500);
        // }
    }
}


if (isset($pdo)) {
    $api = new TripistryAPI($pdo);
    $api->handleRequest();
} else {
    echo json_encode(["status" => "error", "message" => "Database configuration missing."]);
}
?>