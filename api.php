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

require_once 'includes/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
            case 'CreateDestination':
                $this->createDestination($data);
                break;
            case 'CreateFlight':
                $this->createFlight($data);
                break;
            case 'CreateAccommodation':
                $this->createAccommodation($data);
                break;
            case 'CreateAttraction':
                $this->createAttraction($data);
                break;
            case 'CreateRestaurant':
                $this->createRestaurant($data);
                break;
            case 'UpdateAgencyProfile':
                $this->updateAgencyProfile($data);
                break;
            case 'UpdateTravellerProfile':
                $this->updateTravellerProfile($data);
                break;
            case 'UpdateBookingStatus':
                $this->updateBookingStatus($data);
                break;
            case 'GetNotifications':
                $this->getNotifications($data);
                break;
            case 'MarkNotificationsRead':
                $this->markNotificationsRead($data);
                break;
            default:
                $this->sendResponse("error", "Unknown API endpoint: " . $data['type'], 400);
        }
    }

    private function logSecurityEvent($level, $message, $userId = null) {
        if (!file_exists('logs')) {
            @mkdir('logs', 0777, true);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $timestamp = date('Y-m-d H:i:s');
        $logMsg = "[$timestamp] [$level] [IP: $ip] [UserID: " . ($userId ?: 'GUEST') . "] $message\n";
        @file_put_contents('logs/security_audit.log', $logMsg, FILE_APPEND);
    }

    private function loginUser($data) {
        if (empty($data['email']) || empty($data['password']) || empty($data['role'])) {
            $this->sendResponse("error", "Missing email, password, or role.", 400);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!isset($_SESSION['login_failures'])) {
            $_SESSION['login_failures'] = [];
        }
        if (!isset($_SESSION['login_failures'][$ip])) {
            $_SESSION['login_failures'][$ip] = 0;
        }

        if ($_SESSION['login_failures'][$ip] >= 5) {
            $this->logSecurityEvent('CRITICAL', "Login rate-limit blocked attempt for IP: $ip, Email: " . $data['email']);
            $this->sendResponse("error", "Too many failed login attempts. Please try again in a few minutes.", 429);
        }

        try {
            // Check Base User Table
            $stmt = $this->pdo->prepare("SELECT UserID, PasswordHash, AccountStatus FROM User WHERE Email = ?");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch();

            if (!$user) {
                $_SESSION['login_failures'][$ip]++;
                $this->logSecurityEvent('WARNING', "Invalid email login attempt for email: " . $data['email']);
                $this->sendResponse("error", "Invalid credentials.", 401);
            }

            // Strict password verification using secure bcrypt hashes
            if (!password_verify($data['password'], $user['PasswordHash'])) {
                $_SESSION['login_failures'][$ip]++;
                $this->logSecurityEvent('WARNING', "Invalid password login attempt for email: " . $data['email'], $user['UserID']);
                $this->sendResponse("error", "Invalid credentials.", 401);
            }

            if ($user['AccountStatus'] !== 'Active') {
                $this->logSecurityEvent('WARNING', "Blocked user login attempt for email: " . $data['email'] . " Status: " . $user['AccountStatus'], $user['UserID']);
                $this->sendResponse("error", "Account is " . $user['AccountStatus'], 403);
            }

            // Check specific role table
            $role = $data['role'];
            if ($role === 'Traveller') {
                $stmtRole = $this->pdo->prepare("SELECT FirstName, LastName FROM Traveller WHERE UserID = ?");
                $stmtRole->execute([$user['UserID']]);
                $traveller = $stmtRole->fetch();
                if (!$traveller) {
                    $this->logSecurityEvent('WARNING', "Role mismatch login attempt (not a Traveller) for email: " . $data['email'], $user['UserID']);
                    $this->sendResponse("error", "User is not a Traveller.", 403);
                }
                $_SESSION['name'] = $traveller['FirstName'] . ' ' . $traveller['LastName'];
            } else if ($role === 'TravelAgency') {
                $stmtRole = $this->pdo->prepare("SELECT AgencyName FROM TravelAgency WHERE UserID = ?");
                $stmtRole->execute([$user['UserID']]);
                $agency = $stmtRole->fetch();
                if (!$agency) {
                    $this->logSecurityEvent('WARNING', "Role mismatch login attempt (not a Travel Agency) for email: " . $data['email'], $user['UserID']);
                    $this->sendResponse("error", "User is not a Travel Agency.", 403);
                }
                $_SESSION['name'] = $agency['AgencyName'];
            } else {
                $this->sendResponse("error", "Invalid role specified.", 400);
            }

            // Reset login failures on success
            $_SESSION['login_failures'][$ip] = 0;

            // Setup session
            $_SESSION['user_id'] = $user['UserID'];
            $_SESSION['role'] = $role;

            // Update Last Login
            $this->pdo->prepare("UPDATE User SET LastLoginTime = NOW() WHERE UserID = ?")->execute([$user['UserID']]);

            $this->logSecurityEvent('INFO', "Successful login for UserID: " . $user['UserID'], $user['UserID']);

            $this->sendResponse("success", [
                "redirect" => ($role === 'TravelAgency') ? 'agency_dashboard.php' : 'traveller_dashboard.php'
            ]);

        } catch (\PDOException $e) {
            $this->logSecurityEvent('ERROR', "Database error during login: " . $e->getMessage());
            $this->sendResponse("error", "Database error: " . $e->getMessage(), 500);
        }
    }

    private function registerUser($data) {
        if (empty($data['email']) || empty($data['password']) || empty($data['role'])) {
            $this->sendResponse("error", "Missing email, password, or role.", 400);
        }

        $email = trim($data['email']);
        $password = $data['password'];
        $role = $data['role'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse("error", "Invalid email format.", 400);
        }

        if (strlen($password) < 6) {
            $this->sendResponse("error", "Password must be at least 6 characters long.", 400);
        }

        if ($role !== 'Traveller' && $role !== 'TravelAgency') {
            $this->sendResponse("error", "Invalid role specified.", 400);
        }

        // Validate dynamic fields based on role
        if ($role === 'Traveller') {
            if (empty($data['firstName']) || empty($data['lastName']) || empty($data['dob'])) {
                $this->sendResponse("error", "Missing required fields for Traveller registration.", 400);
            }
            $firstName = trim($data['firstName']);
            $lastName = trim($data['lastName']);
            $dob = $data['dob'];
            $preferences = isset($data['preferences']) ? trim($data['preferences']) : '';
        } else {
            if (empty($data['agencyName']) || empty($data['registrationNumber']) || empty($data['contacts']) ||
                empty($data['addressStreet']) || empty($data['addressCity']) || empty($data['addressZip'])) {
                $this->sendResponse("error", "Missing required fields for Travel Agency registration.", 400);
            }
            $agencyName = trim($data['agencyName']);
            $registrationNumber = trim($data['registrationNumber']);
            $contacts = trim($data['contacts']);
            $addressStreet = trim($data['addressStreet']);
            $addressCity = trim($data['addressCity']);
            $addressZip = trim($data['addressZip']);
        }

        try {
            // Check if email already exists
            $stmt = $this->pdo->prepare("SELECT UserID FROM User WHERE Email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $this->sendResponse("error", "Email address is already registered.", 400);
            }

            // If agency, check if registration number already exists
            if ($role === 'TravelAgency') {
                $stmtReg = $this->pdo->prepare("SELECT UserID FROM TravelAgency WHERE RegistrationNumber = ?");
                $stmtReg->execute([$registrationNumber]);
                if ($stmtReg->fetch()) {
                    $this->sendResponse("error", "Registration number is already registered.", 400);
                }
            }

            // Start Transaction to guarantee multi-table consistency
            $this->pdo->beginTransaction();

            // Hash the password securely
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            // 1. Insert into base User table
            $stmtUser = $this->pdo->prepare("INSERT INTO User (Email, PasswordHash, DateJoined, AccountStatus) VALUES (?, ?, CURDATE(), 'Active')");
            $stmtUser->execute([$email, $passwordHash]);
            $userId = (int)$this->pdo->lastInsertId();

            if ($role === 'Traveller') {
                // 2. Insert into Traveller subclass table
                $stmtTraveller = $this->pdo->prepare("INSERT INTO Traveller (UserID, FirstName, LastName, DOB) VALUES (?, ?, ?, ?)");
                $stmtTraveller->execute([$userId, $firstName, $lastName, $dob]);

                // 3. Insert into Traveller_Preferences multivalued table
                if (!empty($preferences)) {
                    $prefArray = array_unique(array_filter(array_map('trim', explode(',', $preferences))));
                    $stmtPref = $this->pdo->prepare("INSERT INTO Traveller_Preferences (UserID, Preference) VALUES (?, ?)");
                    foreach ($prefArray as $pref) {
                        $stmtPref->execute([$userId, $pref]);
                    }
                }
                
                $_SESSION['name'] = $firstName . ' ' . $lastName;
            } else {
                // 2. Insert into TravelAgency subclass table
                $stmtAgency = $this->pdo->prepare("INSERT INTO TravelAgency (UserID, AgencyName, RegistrationNumber, Address_Street, Address_City, Address_Zip) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtAgency->execute([$userId, $agencyName, $registrationNumber, $addressStreet, $addressCity, $addressZip]);

                // 3. Insert into TravelAgency_Contacts multivalued table
                if (!empty($contacts)) {
                    $contactArray = array_unique(array_filter(array_map('trim', explode(',', $contacts))));
                    $stmtContact = $this->pdo->prepare("INSERT INTO TravelAgency_Contacts (UserID, ContactNumber) VALUES (?, ?)");
                    foreach ($contactArray as $contact) {
                        $stmtContact->execute([$userId, $contact]);
                    }
                }

                $_SESSION['name'] = $agencyName;
            }

            // Commit Transaction
            $this->pdo->commit();

            // Setup session for instant authentication
            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = $role;

            $this->sendResponse("success", [
                "redirect" => ($role === 'TravelAgency') ? 'agency_dashboard.php' : 'traveller_dashboard.php'
            ]);

        } catch (\PDOException $e) {
            // Roll back transaction on error to prevent partial writes
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->sendResponse("error", "Database transaction failed: " . $e->getMessage(), 500);
        }
    }

    // Package browsing and filtering

    private function getAllPackages($data) {
        //Get the Agency Name and Rating
        $sql = "SELECT p.PackageID, p.Title, p.BasePrice, p.DurationDays, p.ImageURL, a.AgencyName, a.AverageRating 
                FROM TravelPackage p
                JOIN TravelAgency a ON p.AgencyID = a.UserID";
        
        $conditions = [];
        $params = [];

        // If filtering by destination or global keyword, join destination tables
        if (!empty($data['search']['destination']) || !empty($data['search']['global_query'])) {
            $sql .= " LEFT JOIN Package_Destination pd ON p.PackageID = pd.PackageID 
                      LEFT JOIN Destination d ON pd.DestID = d.DestID";
        }

        // Apply Filters
        if (isset($data['search'])) {
            if (!empty($data['search']['destination'])) {
                $conditions[] = "(d.Name LIKE ? OR d.Country LIKE ?)";
                $params[] = "%" . $data['search']['destination'] . "%";
                $params[] = "%" . $data['search']['destination'] . "%";
            }
            if (!empty($data['search']['global_query'])) {
                $conditions[] = "(p.Title LIKE ? OR d.Name LIKE ? OR d.Country LIKE ? OR d.Region LIKE ? OR a.AgencyName LIKE ?)";
                $q = "%" . $data['search']['global_query'] . "%";
                $params[] = $q;
                $params[] = $q;
                $params[] = $q;
                $params[] = $q;
                $params[] = $q;
            }
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
            $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Auto-resolve cover images on-the-fly if empty
            require_once 'includes/image_service.php';
            foreach ($packages as &$pkg) {
                if (empty($pkg['ImageURL'])) {
                    $pkg['ImageURL'] = ImageService::getPackageImage($pkg['Title']);
                }
            }

            $this->sendResponse("success", $packages);
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

            // Auto-resolve cover image if empty
            if (empty($package['ImageURL'])) {
                require_once 'includes/image_service.php';
                $package['ImageURL'] = ImageService::getPackageImage($package['Title'], $package['Description']);
            }

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

    private function createDestination($data) {
        if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
            $this->sendResponse("error", "Unauthorized.", 403);
        }
        if (empty($data['name']) || empty($data['country'])) {
            $this->sendResponse("error", "Missing destination name or country.", 400);
        }
        try {
            $stmt = $this->pdo->prepare("INSERT INTO Destination (Name, Country, Region) VALUES (?, ?, ?)");
            $stmt->execute([trim($data['name']), trim($data['country']), trim($data['region'] ?? '')]);
            $this->sendResponse("success", ["id" => $this->pdo->lastInsertId(), "message" => "Destination added!"]);
        } catch (\PDOException $e) {
            $this->sendResponse("error", "Failed to add destination: " . $e->getMessage(), 500);
        }
    }

    private function createFlight($data) {
        if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
            $this->sendResponse("error", "Unauthorized.", 403);
        }
        if (empty($data['airline']) || empty($data['flightNum']) || empty($data['depTime']) || empty($data['arrTime']) || !isset($data['cost']) || empty($data['depAirportCode']) || empty($data['arrAirportCode'])) {
            $this->sendResponse("error", "Missing required flight details.", 400);
        }
        try {
            $stmt = $this->pdo->prepare("INSERT INTO Flight (Airline, FlightNum, DepTime, ArrTime, Cost, DepAirport_Code, DepAirport_Name, ArrAirport_Code, ArrAirport_Name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($data['airline']),
                trim($data['flightNum']),
                $data['depTime'],
                $data['arrTime'],
                (float)$data['cost'],
                trim($data['depAirportCode']),
                trim($data['depAirportName'] ?? ''),
                trim($data['arrAirportCode']),
                trim($data['arrAirportName'] ?? '')
            ]);
            $this->sendResponse("success", ["id" => $this->pdo->lastInsertId(), "message" => "Flight added!"]);
        } catch (\PDOException $e) {
            $this->sendResponse("error", "Failed to add flight: " . $e->getMessage(), 500);
        }
    }

    private function createAccommodation($data) {
        if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
            $this->sendResponse("error", "Unauthorized.", 403);
        }
        $accommType = trim($data['accommType'] ?? ($data['type'] !== 'CreateAccommodation' ? $data['type'] : 'Hotel'));
        if (empty($data['name']) || empty($accommType) || !isset($data['pricePerNight'])) {
            $this->sendResponse("error", "Missing required accommodation details.", 400);
        }
        try {
            $stmt = $this->pdo->prepare("INSERT INTO Accommodation (Name, Type, PricePerNight, StarRating, Address_Street, Address_City, Address_Zip) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                trim($data['name']),
                $accommType,
                (float)$data['pricePerNight'],
                !empty($data['starRating']) ? (int)$data['starRating'] : null,
                trim($data['addressStreet'] ?? ''),
                trim($data['addressCity'] ?? ''),
                trim($data['addressZip'] ?? '')
            ]);
            $this->sendResponse("success", ["id" => $this->pdo->lastInsertId(), "message" => "Accommodation added!"]);
        } catch (\PDOException $e) {
            $this->sendResponse("error", "Failed to add accommodation: " . $e->getMessage(), 500);
        }
    }

    private function createAttraction($data) {
        if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
            $this->sendResponse("error", "Unauthorized.", 403);
        }
        if (empty($data['name'])) {
            $this->sendResponse("error", "Missing attraction name.", 400);
        }
        try {
            $stmt = $this->pdo->prepare("INSERT INTO Attraction (Name, Category, EntryFee) VALUES (?, ?, ?)");
            $stmt->execute([
                trim($data['name']),
                trim($data['category'] ?? ''),
                !empty($data['entryFee']) ? (float)$data['entryFee'] : 0.00
            ]);
            $this->sendResponse("success", ["id" => $this->pdo->lastInsertId(), "message" => "Attraction added!"]);
        } catch (\PDOException $e) {
            $this->sendResponse("error", "Failed to add attraction: " . $e->getMessage(), 500);
        }
    }

    private function createRestaurant($data) {
        if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
            $this->sendResponse("error", "Unauthorized.", 403);
        }
        if (empty($data['name'])) {
            $this->sendResponse("error", "Missing restaurant name.", 400);
        }
        try {
            $stmt = $this->pdo->prepare("INSERT INTO Restaurant (Name, CuisineType, AverageCost) VALUES (?, ?, ?)");
            $stmt->execute([
                trim($data['name']),
                trim($data['cuisineType'] ?? ''),
                !empty($data['averageCost']) ? (float)$data['averageCost'] : 0.00
            ]);
            $this->sendResponse("success", ["id" => $this->pdo->lastInsertId(), "message" => "Restaurant added!"]);
        } catch (\PDOException $e) {
            $this->sendResponse("error", "Failed to add restaurant: " . $e->getMessage(), 500);
        }
    }

    private function updateAgencyProfile($data) {
        if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
            $this->sendResponse("error", "Unauthorized.", 403);
        }
        $agency_id = $_SESSION['user_id'];
        $agency_name = trim($data['agencyName'] ?? '');
        $address_street = trim($data['addressStreet'] ?? '');
        $address_city = trim($data['addressCity'] ?? '');
        $address_zip = trim($data['addressZip'] ?? '');
        $phones = $data['phones'] ?? [];

        if ($agency_name === '') {
            $this->sendResponse("error", "Agency Name cannot be left blank.", 400);
        }
        if ($address_street === '' || $address_city === '' || $address_zip === '') {
            $this->sendResponse("error", "All address fields are required.", 400);
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                UPDATE TravelAgency 
                SET AgencyName = ?, Address_Street = ?, Address_City = ?, Address_Zip = ?
                WHERE UserID = ?
            ");
            $stmt->execute([$agency_name, $address_street, $address_city, $address_zip, $agency_id]);

            $stmt = $this->pdo->prepare("DELETE FROM TravelAgency_Contacts WHERE UserID = ?");
            $stmt->execute([$agency_id]);

            if (is_array($phones)) {
                $stmt = $this->pdo->prepare("INSERT INTO TravelAgency_Contacts (UserID, ContactNumber) VALUES (?, ?)");
                foreach ($phones as $phone) {
                    $phone = trim($phone);
                    if ($phone !== '') {
                        $stmt->execute([$agency_id, $phone]);
                    }
                }
            }

            $this->pdo->commit();
            $_SESSION['name'] = $agency_name;
            $this->sendResponse("success", ["message" => "Profile updated successfully!"]);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->sendResponse("error", "Failed to update profile: " . $e->getMessage(), 500);
        }
    }

    private function updateTravellerProfile($data) {
        if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'Traveller') {
            $this->sendResponse("error", "Unauthorized.", 403);
        }
        $traveller_id = $_SESSION['user_id'];
        $first_name = trim($data['firstName'] ?? '');
        $last_name = trim($data['lastName'] ?? '');
        $dob = trim($data['dob'] ?? '');
        $budget = (float)($data['budget'] ?? 0.00);
        $preferences = $data['preferences'] ?? [];

        if ($first_name === '' || $last_name === '') {
            $this->sendResponse("error", "First Name and Last Name cannot be left blank.", 400);
        }
        if ($dob === '') {
            $this->sendResponse("error", "Date of Birth is required.", 400);
        }
        if ($budget < 0) {
            $this->sendResponse("error", "Solo Budget cannot be negative.", 400);
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                UPDATE Traveller 
                SET FirstName = ?, LastName = ?, DOB = ?, SoloBudget = ?
                WHERE UserID = ?
            ");
            $stmt->execute([$first_name, $last_name, $dob, $budget, $traveller_id]);

            $stmt = $this->pdo->prepare("DELETE FROM Traveller_Preferences WHERE UserID = ?");
            $stmt->execute([$traveller_id]);

            if (is_array($preferences)) {
                $stmt = $this->pdo->prepare("INSERT INTO Traveller_Preferences (UserID, Preference) VALUES (?, ?)");
                foreach ($preferences as $pref) {
                    $pref = trim($pref);
                    if ($pref !== '') {
                        $stmt->execute([$traveller_id, $pref]);
                    }
                }
            }

            $this->pdo->commit();
            $_SESSION['name'] = $first_name . ' ' . $last_name;
            $this->sendResponse("success", ["message" => "Profile updated successfully!"]);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->sendResponse("error", "Failed to update profile: " . $e->getMessage(), 500);
        }
    }

    private function updateBookingStatus($data) {
        if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
            $this->sendResponse("error", "Unauthorized.", 403);
        }
        $agency_id = $_SESSION['user_id'];
        $booking_id = (int)($data['bookingId'] ?? 0);
        $new_status = trim($data['paymentStatus'] ?? '');

        $valid_statuses = ['Pending', 'Paid', 'Failed', 'Refunded'];
        if (!in_array($new_status, $valid_statuses)) {
            $this->sendResponse("error", "Invalid payment status.", 400);
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT b.*, tp.AgencyID, tp.MaxCapacity, tp.Title AS PackageTitle, gt.StartDate, gt.EndDate
                FROM Booking b
                JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
                JOIN GroupTrip gt ON (b.Trip_PackageID = gt.PackageID AND b.Trip_TripDateID = gt.TripDateID)
                WHERE b.BookingID = ?
            ");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch();

            if (!$booking) {
                $this->sendResponse("error", "Booking not found.", 404);
            }

            if ($booking['AgencyID'] !== $agency_id) {
                $this->sendResponse("error", "Unauthorized to update this booking.", 403);
            }

            if ($new_status === 'Paid' && $booking['PaymentStatus'] !== 'Paid') {
                $stmt = $this->pdo->prepare("
                    SELECT COALESCE(SUM(PartySize), 0)
                    FROM Booking
                    WHERE Trip_PackageID = ? AND Trip_TripDateID = ? AND PaymentStatus = 'Paid' AND BookingID != ?
                ");
                $stmt->execute([$booking['Trip_PackageID'], $booking['Trip_TripDateID'], $booking_id]);
                $current_paid_guests = (int)$stmt->fetchColumn();

                $max_capacity = (int)$booking['MaxCapacity'];
                $party_size = (int)$booking['PartySize'];

                if ($current_paid_guests + $party_size > $max_capacity) {
                    $this->sendResponse("error", "Capacity Exceeded! Only " . ($max_capacity - $current_paid_guests) . " slots remaining, but party size is {$party_size}.", 400);
                }
            }

            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("UPDATE Booking SET PaymentStatus = ? WHERE BookingID = ?");
            $stmt->execute([$new_status, $booking_id]);

            // Notify Traveller
            $traveller_id = (int)$booking['TravellerID'];
            $package_title = $booking['PackageTitle'];
            $notifyTitle = "Booking Status Updated!";
            $notifyMsg = "Your booking for package \"" . htmlspecialchars($package_title) . "\" has been updated to \"" . $new_status . "\".";
            
            $stmtNotify = $this->pdo->prepare("INSERT INTO Notification (UserID, Title, Message, IsRead) VALUES (?, ?, ?, 0)");
            $stmtNotify->execute([$traveller_id, $notifyTitle, $notifyMsg]);

            $this->pdo->commit();

            $this->sendResponse("success", ["message" => "Booking status updated to {$new_status}."]);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->sendResponse("error", "Database error: " . $e->getMessage(), 500);
        }
    }

    private function getNotifications($data) {
        if (empty($_SESSION['user_id'])) {
            $this->sendResponse("error", "Unauthorized.", 403);
        }
        $user_id = $_SESSION['user_id'];
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM Notification 
                WHERE UserID = ? 
                ORDER BY CreatedAt DESC LIMIT 10
            ");
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtUnread = $this->pdo->prepare("
                SELECT COUNT(*) FROM Notification 
                WHERE UserID = ? AND IsRead = 0
            ");
            $stmtUnread->execute([$user_id]);
            $unread_count = (int)$stmtUnread->fetchColumn();

            $this->sendResponse("success", [
                "notifications" => $notifications,
                "unread_count" => $unread_count
            ]);
        } catch (\PDOException $e) {
            $this->sendResponse("error", "Database error: " . $e->getMessage(), 500);
        }
    }

    private function markNotificationsRead($data) {
        if (empty($_SESSION['user_id'])) {
            $this->sendResponse("error", "Unauthorized.", 403);
        }
        $user_id = $_SESSION['user_id'];
        try {
            $stmt = $this->pdo->prepare("
                UPDATE Notification SET IsRead = 1 
                WHERE UserID = ?
            ");
            $stmt->execute([$user_id]);
            $this->sendResponse("success", ["message" => "All notifications marked as read."]);
        } catch (\PDOException $e) {
            $this->sendResponse("error", "Database error: " . $e->getMessage(), 500);
        }
    }
}


if (isset($pdo)) {
    $api = new TripistryAPI($pdo);
    $api->handleRequest();
} else {
    echo json_encode(["status" => "error", "message" => "Database configuration missing."]);
}
?>