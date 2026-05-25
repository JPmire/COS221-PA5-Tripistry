<?php
/**
 * Tripistry Live API Search & Import Gateway
 * Serves AJAX requests for the Agency Package Hub & Traveller Explorer
 */
header('Content-Type: application/json');
require_once 'config.php';
require_once 'includes/travel_api.php';
require_once 'includes/image_service.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Please log in.']);
    exit;
}

$api = new TravelAPI();
$method = $_SERVER['REQUEST_METHOD'];

// Handle standard JSON payload or standard POST variables
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'search_city':
            $city = trim($input['city'] ?? $_GET['city'] ?? '');
            if (empty($city)) {
                throw new Exception('City name is required.');
            }

            // 1. Geolocate city
            $coords = $api->getCoordinates($city);
            
            // 2. Fetch sights/attractions, lodgings, and food spots around the coordinates
            $lat = $coords['lat'];
            $lon = $coords['lon'];

            $accommodations = $api->getPlacesByRadius($lat, $lon, 'accommodation', 12000, 8);
            $attractions = $api->getPlacesByRadius($lat, $lon, 'interesting_places', 12000, 8);
            $restaurants = $api->getPlacesByRadius($lat, $lon, 'catering', 12000, 8);

            echo json_encode([
                'status' => 'success',
                'city' => $coords['name'],
                'country' => $coords['country'],
                'lat' => $lat,
                'lon' => $lon,
                'source' => $coords['source'],
                'data' => [
                    'accommodations' => $accommodations,
                    'attractions' => $attractions,
                    'restaurants' => $restaurants
                ]
            ]);
            break;

        case 'search_flights':
            $dep = strtoupper(trim($input['dep'] ?? $_GET['dep'] ?? ''));
            $arr = strtoupper(trim($input['arr'] ?? $_GET['arr'] ?? ''));
            
            if (empty($dep) || empty($arr)) {
                throw new Exception('Departure and arrival airport codes are required.');
            }

            $flights = $api->getFlightsBetweenAirports($dep, $arr);
            echo json_encode([
                'status' => 'success',
                'flights' => $flights
            ]);
            break;

        case 'search_stock_images':
            $query = trim($input['query'] ?? $_GET['query'] ?? '');
            $results = ImageService::searchStock($query);
            echo json_encode([
                'status' => 'success',
                'data' => $results
            ]);
            break;

        case 'import_asset':
            // Verify only agencies can import assets
            if ($_SESSION['role'] !== 'TravelAgency') {
                throw new Exception('Access Denied: Only Travel Agencies can import travel assets.');
            }

            $assetType = strtolower(trim($input['asset_type'] ?? ''));
            if (empty($assetType)) {
                throw new Exception('Asset type is required for import.');
            }

            $insertedId = 0;

            switch ($assetType) {
                case 'destination':
                    $name = trim($input['name'] ?? '');
                    $country = trim($input['country'] ?? '');
                    $lat = (float)($input['lat'] ?? 0);
                    $lon = (float)($input['lon'] ?? 0);

                    if (empty($name)) throw new Exception('Destination name is required.');

                    $imageUrl = ImageService::getDestinationImage($name);

                    $stmt = $pdo->prepare("INSERT INTO Destination (Name, Country, Region, PopularityScore, Coordinates_Lat, Coordinates_Long, ImageURL) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $country, 'Region', 80, $lat, $lon, $imageUrl]);
                    $insertedId = $pdo->lastInsertId();
                    break;

                case 'accommodation':
                    $name = trim($input['name'] ?? '');
                    $type = trim($input['type'] ?? 'Hotel');
                    $price = (float)($input['price'] ?? 0);
                    $rating = (int)($input['rating'] ?? 4);
                    $address = trim($input['address'] ?? '');
                    $lat = (float)($input['lat'] ?? 0);
                    $lon = (float)($input['lon'] ?? 0);

                    if (empty($name) || $price <= 0) throw new Exception('Accommodation name and positive price per night are required.');

                    $imageUrl = ImageService::getAccommodationImage($name, $type);

                    $stmt = $pdo->prepare("INSERT INTO Accommodation (Name, Type, PricePerNight, StarRating, Address_Street, Address_City, Address_Zip, Coordinates_Lat, Coordinates_Long, ImageURL) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $type, $price, $rating, $address, 'City', '0000', $lat, $lon, $imageUrl]);
                    $insertedId = $pdo->lastInsertId();
                    break;

                case 'attraction':
                    $name = trim($input['name'] ?? '');
                    $category = trim($input['category'] ?? 'Sightseeing');
                    $fee = (float)($input['fee'] ?? 0);
                    $lat = (float)($input['lat'] ?? 0);
                    $lon = (float)($input['lon'] ?? 0);

                    if (empty($name)) throw new Exception('Attraction name is required.');

                    $imageUrl = ImageService::getAttractionImage($name, $category);

                    $stmt = $pdo->prepare("INSERT INTO Attraction (Name, Category, EntryFee, Coordinates_Lat, Coordinates_Long, ImageURL) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $category, $fee, $lat, $lon, $imageUrl]);
                    $insertedId = $pdo->lastInsertId();
                    break;

                case 'restaurant':
                    $name = trim($input['name'] ?? '');
                    $cuisine = trim($input['cuisine'] ?? 'General');
                    $cost = (float)($input['cost'] ?? 0);
                    $lat = (float)($input['lat'] ?? 0);
                    $lon = (float)($input['lon'] ?? 0);

                    if (empty($name)) throw new Exception('Restaurant name is required.');

                    $imageUrl = ImageService::getRestaurantImage($name, $cuisine);

                    $stmt = $pdo->prepare("INSERT INTO Restaurant (Name, CuisineType, AverageCost, Coordinates_Lat, Coordinates_Long, ImageURL) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $cuisine, $cost, $lat, $lon, $imageUrl]);
                    $insertedId = $pdo->lastInsertId();
                    break;

                case 'flight':
                    $airline = trim($input['airline'] ?? '');
                    $flightNum = trim($input['flightNum'] ?? '');
                    $depTime = trim($input['depTime'] ?? '');
                    $arrTime = trim($input['arrTime'] ?? '');
                    $cost = (float)($input['cost'] ?? 0);
                    $depCode = strtoupper(trim($input['depCode'] ?? ''));
                    $depName = trim($input['depName'] ?? '');
                    $arrCode = strtoupper(trim($input['arrCode'] ?? ''));
                    $arrName = trim($input['arrName'] ?? '');

                    if (empty($airline) || empty($flightNum) || $cost <= 0) {
                        throw new Exception('Flight airline, number, and cost are required.');
                    }

                    $stmt = $pdo->prepare("INSERT INTO Flight (Airline, FlightNum, DepTime, ArrTime, Cost, DepAirport_Code, DepAirport_Name, ArrAirport_Code, ArrAirport_Name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$airline, $flightNum, $depTime, $arrTime, $cost, $depCode, $depName, $arrCode, $arrName]);
                    $insertedId = $pdo->lastInsertId();
                    break;

                default:
                    throw new Exception('Invalid asset type selected.');
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Asset successfully imported & seeded into Tripistry database.',
                'id' => (int)$insertedId,
                'asset_type' => $assetType
            ]);
            break;

        default:
            throw new Exception("Unknown action parameter: '{$action}'");
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
