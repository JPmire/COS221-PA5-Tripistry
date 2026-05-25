<?php
require_once 'includes/db_connect.php';

if (!isset($_GET['id'])) {
    header("Location: packages.php");
    exit;
}

$page_title = 'Package Details';
require_once 'includes/header.php';

$package_id = (int)$_GET['id'];

$error = '';
$success = '';

// Fetch Package Details
try {
    $stmt = $pdo->prepare("
        SELECT p.*, a.AgencyName, a.AverageRating AS AgencyRating, a.UserID AS AgencyID
        FROM TravelPackage p
        JOIN TravelAgency a ON p.AgencyID = a.UserID
        WHERE p.PackageID = ?
    ");
    $stmt->execute([$package_id]);
    $package = $stmt->fetch();

    if (!$package) {
        $error = "Package not found.";
    } else {
        // Fetch Components
        // 1. Destinations
        $stmtD = $pdo->prepare("
            SELECT d.* FROM Destination d 
            JOIN Package_Destination pd ON d.DestID = pd.DestID 
            WHERE pd.PackageID = ?
        ");
        $stmtD->execute([$package_id]);
        $destinations = $stmtD->fetchAll();

        // 2. Accommodations
        $stmtA = $pdo->prepare("
            SELECT ac.* FROM Accommodation ac 
            JOIN Package_Accommodation pa ON ac.AccommID = pa.AccommID 
            WHERE pa.PackageID = ?
        ");
        $stmtA->execute([$package_id]);
        $accommodations = $stmtA->fetchAll();

        // 3. Flights
        $stmtF = $pdo->prepare("
            SELECT f.* FROM Flight f 
            JOIN Package_Flight pf ON f.FlightID = pf.FlightID 
            WHERE pf.PackageID = ?
        ");
        $stmtF->execute([$package_id]);
        $flights = $stmtF->fetchAll();

        // 4. Attractions
        $stmtAttr = $pdo->prepare("
            SELECT attr.* FROM Attraction attr 
            JOIN Package_Attraction pa ON attr.AttractionID = pa.AttractionID 
            WHERE pa.PackageID = ?
        ");
        $stmtAttr->execute([$package_id]);
        $attractions = $stmtAttr->fetchAll();

        // 5. Restaurants
        $stmtRest = $pdo->prepare("
            SELECT r.* FROM Restaurant r 
            JOIN Package_Restaurant pr ON r.RestaurantID = pr.RestaurantID 
            WHERE pr.PackageID = ?
        ");
        $stmtRest->execute([$package_id]);
        $restaurants = $stmtRest->fetchAll();

        // Fetch Scheduled Dates & remaining capacities
        $stmtDates = $pdo->prepare("
            SELECT gt.TripDateID, gt.StartDate, gt.EndDate, 
                   (tp.MaxCapacity - COALESCE(SUM(b.PartySize), 0)) AS RemainingCapacity
            FROM GroupTrip gt
            JOIN TravelPackage tp ON gt.PackageID = tp.PackageID
            LEFT JOIN Booking b ON gt.PackageID = b.Trip_PackageID AND gt.TripDateID = b.Trip_TripDateID
            WHERE gt.PackageID = ? AND gt.Status = 'Scheduled'
            GROUP BY gt.TripDateID, gt.StartDate, gt.EndDate
        ");
        $stmtDates->execute([$package_id]);
        $scheduled_dates = $stmtDates->fetchAll();

        // Fetch Reviews (Join Traveller & compute averages)
        $stmtReviews = $pdo->prepare("
            SELECT r.*, t.FirstName, t.LastName 
            FROM Review r 
            JOIN Traveller t ON r.TravellerID = t.UserID 
            WHERE r.TargetPackageID = ? 
            ORDER BY r.DatePosted DESC
        ");
        $stmtReviews->execute([$package_id]);
        $reviews = $stmtReviews->fetchAll();

        // Compile all geographical coordinates for the Leaflet.js itinerary map
        $map_points = [];
        // 1. Destinations
        if (!empty($destinations)) {
            foreach ($destinations as $d) {
                if (!empty($d['Coordinates_Lat']) && !empty($d['Coordinates_Long'])) {
                    $map_points[] = [
                        'type' => 'Destination',
                        'name' => $d['Name'],
                        'lat' => (float)$d['Coordinates_Lat'],
                        'lng' => (float)$d['Coordinates_Long'],
                        'details' => htmlspecialchars($d['Country'] . ' • ' . ($d['Region'] ?? ''))
                    ];
                }
            }
        }
        // 2. Accommodations
        if (!empty($accommodations)) {
            foreach ($accommodations as $a) {
                if (!empty($a['Coordinates_Lat']) && !empty($a['Coordinates_Long'])) {
                    $map_points[] = [
                        'type' => 'Accommodation',
                        'name' => $a['Name'],
                        'lat' => (float)$a['Coordinates_Lat'],
                        'lng' => (float)$a['Coordinates_Long'],
                        'details' => htmlspecialchars(($a['Type'] ?? 'Hotel') . ' • $' . number_format($a['PricePerNight'], 0) . '/night • ' . ($a['StarRating'] ?? '5') . '★')
                    ];
                }
            }
        }
        // 3. Attractions
        if (!empty($attractions)) {
            foreach ($attractions as $attr) {
                if (!empty($attr['Coordinates_Lat']) && !empty($attr['Coordinates_Long'])) {
                    $map_points[] = [
                        'type' => 'Attraction',
                        'name' => $attr['Name'],
                        'lat' => (float)$attr['Coordinates_Lat'],
                        'lng' => (float)$attr['Coordinates_Long'],
                        'details' => htmlspecialchars(($attr['Category'] ?? 'Landmark') . ' • ' . ($attr['EntryFee'] > 0 ? '$' . number_format($attr['EntryFee'], 0) : 'Free Entry'))
                    ];
                }
            }
        }
        // 4. Restaurants
        if (!empty($restaurants)) {
            foreach ($restaurants as $r) {
                if (!empty($r['Coordinates_Lat']) && !empty($r['Coordinates_Long'])) {
                    $map_points[] = [
                        'type' => 'Restaurant',
                        'name' => $r['Name'],
                        'lat' => (float)$r['Coordinates_Lat'],
                        'lng' => (float)$r['Coordinates_Long'],
                        'details' => htmlspecialchars(($r['CuisineType'] ?? 'Local') . ' Cuisine • Avg Cost: $' . number_format($r['AverageCost'], 0))
                    ];
                }
            }
        }

        // Handle Booking Submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book') {
            if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Traveller') {
                $error = "You must be logged in as a Traveller to book a package.";
            } else {
                $partySize = (int)$_POST['partySize'];
                $tripDateId = (int)$_POST['tripDateId'];

                // Find remaining capacity for selected date
                $selected_date = null;
                foreach ($scheduled_dates as $sd) {
                    if ((int)$sd['TripDateID'] === $tripDateId) {
                        $selected_date = $sd;
                        break;
                    }
                }

                if (!$selected_date) {
                    $error = "Please select a valid scheduled group trip date.";
                } elseif ($partySize <= 0) {
                    $error = "Party size must be at least 1 traveller.";
                } elseif ($partySize > $selected_date['RemainingCapacity']) {
                    $error = "Cannot book trip. Only " . $selected_date['RemainingCapacity'] . " seats remaining for this date.";
                } else {
                    $totalAmount = $package['BasePrice'] * $partySize;
                    
                    try {
                        $stmtBook = $pdo->prepare("
                            INSERT INTO Booking (TotalAmount, PartySize, TravellerID, Trip_PackageID, Trip_TripDateID) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmtBook->execute([$totalAmount, $partySize, $_SESSION['user_id'], $package_id, $tripDateId]);
                        $success = "Booking successful! Your group trip has been confirmed.";
                    } catch (PDOException $e) {
                        $error = "Booking failed: " . $e->getMessage();
                    }
                }
            }
        }

        // Handle Review Submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'leave_review') {
            if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Traveller') {
                $error = "You must be logged in as a Traveller to leave reviews.";
            } else {
                $rating = (int)($_POST['rating'] ?? 0);
                $comment = trim($_POST['comment'] ?? '');

                if ($rating < 1 || $rating > 5) {
                    $error = "Please provide a valid rating between 1 and 5 stars.";
                } else {
                    // // Custom Lexicon Sentiment Scoring (Bonus Task 11)
                    // $posWords = ['amazing', 'wonderful', 'excellent', 'beautiful', 'perfect', 'great', 'love', 'enjoy', 'fantastic', 'best', 'friendly', 'clean', 'outstanding', 'superb', 'peaceful', 'magical', 'highly', 'recommend', 'smooth', 'easy', 'nice', 'awesome'];
                    // $negWords = ['bad', 'poor', 'dirty', 'rude', 'late', 'expensive', 'terrible', 'worst', 'hate', 'regret', 'horrible', 'noisy', 'boring', 'narrow', 'delay', 'problem', 'issue', 'difficult', 'broken', 'cancel', 'annoyed', 'disappointed'];

                    // $lowerComment = strtolower($comment);
                    // // Simple word boundary tokenizer
                    // $words = preg_split('/\W+/', $lowerComment, -1, PREG_SPLIT_NO_EMPTY);
                    
                    // $posCount = 0;
                    // $negCount = 0;
                    // foreach ($words as $w) {
                    //     if (in_array($w, $posWords)) $posCount++;
                    //     if (in_array($w, $negWords)) $negCount++;
                    // }

                    // $sentimentScore = 0.00;
                    // if (($posCount + $negCount) > 0) {
                    //     $sentimentScore = ($posCount - $negCount) / ($posCount + $negCount);
                    // }

                    $envPath = __DIR__ . '/.env';
                    $apiKey = file_exists($envPath) ? parse_ini_file($envPath)['GEMINI_API_KEY'] ?? '' : '';

                    $sentimentScore = 0.00; 

                    if (!empty($apiKey)) {
                        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
                        
                        $prompt = "Analyze the sentiment of the following travel review. Return ONLY a valid JSON object with a single key 'sentiment_score' containing a float from -1.00 (very negative) to 1.00 (very positive). Review: " . $comment;

                        $payload = json_encode([
                            "contents" => [
                                ["parts" => [["text" => $prompt]]]
                            ],
                            "generationConfig" => [
                                "response_mime_type" => "application/json"
                            ]
                        ]);

                        $ch = curl_init($endpoint);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($httpCode === 200 && $response) {
                            $responseData = json_decode($response, true);
                            if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                                $aiText = $responseData['candidates'][0]['content']['parts'][0]['text'];
                                $aiJson = json_decode($aiText, true);
                                
                                if (isset($aiJson['sentiment_score'])) {
                                    $sentimentScore = (float)$aiJson['sentiment_score'];
                                }
                            }
                        }
                    }

                    try {
                        $pdo->beginTransaction();

                        // Get next ReviewID for this Traveller
                        $stmtNextId = $pdo->prepare("SELECT COALESCE(MAX(ReviewID), 0) + 1 FROM Review WHERE TravellerID = ?");
                        $stmtNextId->execute([$_SESSION['user_id']]);
                        $nextReviewId = $stmtNextId->fetchColumn();

                        // Insert review
                        $stmtRev = $pdo->prepare("
                            INSERT INTO Review (TravellerID, ReviewID, Rating, Comment, SentimentScore, TargetPackageID) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmtRev->execute([$_SESSION['user_id'], $nextReviewId, $rating, $comment, $sentimentScore, $package_id]);

                        // Recalculate average rating of agency packages & direct agency reviews
                        $stmtAvg = $pdo->prepare("
                            SELECT AVG(Rating) 
                            FROM Review r
                            LEFT JOIN TravelPackage tp ON r.TargetPackageID = tp.PackageID
                            WHERE r.TargetAgencyID = ? OR tp.AgencyID = ?
                        ");
                        $stmtAvg->execute([$package['AgencyID'], $package['AgencyID']]);
                        $newAgencyAvg = $stmtAvg->fetchColumn() ?: 0.00;

                        // Update TravelAgency
                        $stmtUp = $pdo->prepare("UPDATE TravelAgency SET AverageRating = ? WHERE UserID = ?");
                        $stmtUp->execute([$newAgencyAvg, $package['AgencyID']]);

                        $pdo->commit();
                        $success = "Thank you! Your feedback has been recorded and sentiment analyzed.";
                        
                        // Re-fetch reviews
                        $stmtReviews->execute([$package_id]);
                        $reviews = $stmtReviews->fetchAll();

                        // Re-fetch package rating
                        $stmt->execute([$package_id]);
                        $package = $stmt->fetch();
                    } catch (PDOException $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $error = "Failed to record review: " . $e->getMessage();
                    }
                }
            }
        }
    }
} catch (PDOException $e) {
    $error = "Database query failed: " . $e->getMessage();
}
?>

<?php if ($error): ?>
    <div class="bg-red-100 text-primary p-4 rounded-md border border-red-200 mb-6 max-w-7xl mx-auto mt-6">
        <?php echo htmlspecialchars($error); ?>
        <br><a href="packages.php" class="underline font-medium mt-2 inline-block">Return to packages</a>
    </div>
<?php elseif ($success): ?>
    <div class="bg-green-100 text-green-800 p-4 rounded-md border border-green-200 mb-6 max-w-7xl mx-auto mt-6">
        <?php echo htmlspecialchars($success); ?>
        <br><a href="traveller_dashboard.php" class="underline font-medium mt-2 inline-block">View My Bookings</a>
    </div>
<?php endif; ?>

<?php if ($package && !$success): ?>
<div class="mb-6">
    <a href="packages.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-surface hover:bg-surface-container-low text-text-main hover:text-primary border border-outline-variant rounded-lg font-bold text-xs transition-all shadow-sm">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
        Back to Browse Packages
    </a>
</div>

<div class="flex flex-col lg:flex-row gap-8">
    
    <!-- LEFT SIDE: Package Itinerary & Associated M:N Components -->
    <div class="lg:w-2/3 flex flex-col gap-8">
        
        <!-- Base Package Card -->
        <div class="bg-surface rounded-card shadow-sm border border-muted/20 overflow-hidden bg-white">
            <div class="h-64 md:h-80 bg-background-light flex items-center justify-center border-b border-muted/20 relative">
                <span class="material-symbols-outlined text-6xl text-muted/30">landscape</span>
                <span class="absolute top-4 right-4 bg-primary text-white text-xs font-bold uppercase tracking-wider px-3 py-1 rounded-full shadow-sm">Featured Package</span>
            </div>
            
            <div class="p-6 md:p-8">
                <div class="flex justify-between items-start flex-wrap gap-4 mb-4">
                    <div>
                        <span class="text-sm font-semibold tracking-wider text-accent uppercase mb-1 block">Curated by <?php echo htmlspecialchars($package['AgencyName']); ?></span>
                        <h1 class="text-3xl md:text-4xl font-heading font-semibold text-text-main"><?php echo htmlspecialchars($package['Title']); ?></h1>
                    </div>
                    <div class="bg-background-light p-3 rounded-lg border border-outline-variant flex flex-col items-end">
                        <p class="text-[11px] text-muted uppercase tracking-wider font-bold mb-0.5">Base Price</p>
                        <p class="text-2xl font-semibold text-primary font-mono">$<?php echo number_format($package['BasePrice'], 2); ?></p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4 mb-6 pt-4 border-t border-muted/20">
                    <div class="flex items-center gap-2 bg-background-light px-3 py-1.5 rounded-md text-sm font-medium border border-outline-variant/40">
                        <span class="material-symbols-outlined text-[18px] text-muted">schedule</span>
                        <?php echo $package['DurationDays']; ?> Days
                    </div>
                    <div class="flex items-center gap-2 bg-background-light px-3 py-1.5 rounded-md text-sm font-medium border border-outline-variant/40">
                        <span class="material-symbols-outlined text-[18px] text-muted">group</span>
                        Max Capacity: <?php echo $package['MaxCapacity']; ?>
                    </div>
                    <div class="flex items-center gap-2 bg-background-light px-3 py-1.5 rounded-md text-sm font-medium border border-outline-variant/40">
                        <span class="material-symbols-outlined text-[18px] text-muted">star</span>
                        Rating: <?php echo number_format($package['AgencyRating'], 1); ?>/5
                    </div>
                </div>

                <div class="prose max-w-none text-text-main">
                    <h3 class="text-xl font-heading font-semibold mb-3 border-b border-outline-variant pb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">description</span> About This Package
                    </h3>
                    <p class="whitespace-pre-line text-muted leading-relaxed"><?php echo htmlspecialchars($package['Description'] ?: 'No description provided for this package yet.'); ?></p>
                </div>
            </div>
        </div>

        <!-- COMPONENT PACKAGES SECTION (M:N Associative Display) -->
        <div class="flex flex-col gap-6">
            <h2 class="text-2xl font-heading font-semibold text-text-main border-b border-outline-variant pb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">travel</span> What's Included in This Experience
            </h2>

            <!-- 1. Destinations Display -->
            <?php if (!empty($destinations)): ?>
            <div class="bg-surface rounded-xl border border-outline-variant p-6 shadow-sm bg-white">
                <h3 class="text-lg font-heading font-semibold text-text-main mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-accent">pin_drop</span> Locations & Destinations Visited
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <?php foreach ($destinations as $dest): ?>
                        <div class="p-4 rounded-lg bg-background-light border border-outline-variant/60 flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary mt-0.5">location_on</span>
                            <div>
                                <h4 class="font-bold text-sm text-text-main"><?php echo htmlspecialchars($dest['Name']); ?></h4>
                                <p class="text-xs text-secondary mt-0.5"><?php echo htmlspecialchars($dest['Country']); ?> • <?php echo htmlspecialchars($dest['Region']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Interactive Itinerary Map -->
            <div class="bg-surface rounded-xl border border-outline-variant p-6 shadow-sm bg-white">
                <h3 class="text-lg font-heading font-semibold text-text-main mb-4 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">map</span> Interactive Itinerary Map
                    </span>
                    <span class="text-xs bg-primary-fixed text-primary px-2.5 py-0.5 rounded font-bold uppercase tracking-wider">Route View</span>
                </h3>
                
                <!-- Leaflet CSS & JS inline loading -->
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
                
                <div id="itinerary-map" class="w-full h-[400px] rounded-lg border border-outline-variant/60 shadow-inner z-10"></div>
                <p class="text-xs text-secondary mt-3 italic flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[14px]">info</span>
                    Geodesic dashed lines outline your optimal itinerary path between lodging, landmarks, and culinary spots. Click any marker for deep relational info.
                </p>
            </div>

            <script>
            document.addEventListener("DOMContentLoaded", function() {
                // Coordinate Points compiled from PHP
                const points = <?php echo json_encode($map_points ?? []); ?>;
                
                if (!points || points.length === 0) {
                    document.getElementById('itinerary-map').innerHTML = `
                        <div class="flex flex-col items-center justify-center h-full text-secondary bg-background-light rounded-lg">
                            <span class="material-symbols-outlined text-4xl mb-2 text-primary">wrong_location</span>
                            <p class="font-bold text-sm">No geographic coordinates available for this package.</p>
                        </div>
                    `;
                    return;
                }
                
                // Initialize map
                const map = L.map('itinerary-map', {
                    scrollWheelZoom: false
                });
                
                // Base tile layer
                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
                    subdomains: 'abcd',
                    maxZoom: 20
                }).addTo(map);
                
                const latlngs = [];
                const markers = [];
                
                // Icon configuration
                const iconColors = {
                    'Destination': '#e63946',
                    'Accommodation': '#457b9d',
                    'Attraction': '#2a9d8f',
                    'Restaurant': '#f4a261'
                };
                
                points.forEach(pt => {
                    const lat = parseFloat(pt.lat);
                    const lng = parseFloat(pt.lng);
                    latlngs.push([lat, lng]);
                    
                    const markerColor = iconColors[pt.type] || '#b7102a';
                    const typeIcon = pt.type === 'Accommodation' ? 'hotel' : 
                                     pt.type === 'Attraction' ? 'explore' : 
                                     pt.type === 'Restaurant' ? 'dining' : 'location_on';
                                     
                    const customIcon = L.divIcon({
                        className: 'custom-map-marker',
                        html: `
                            <div style="
                                background: ${markerColor};
                                color: white;
                                width: 32px;
                                height: 32px;
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                box-shadow: 0 4px 10px rgba(0,0,0,0.3);
                                border: 2px solid white;
                                transition: transform 0.2s;
                            " class="hover:scale-110 flex justify-center items-center">
                                <span class="material-symbols-outlined" style="font-size: 16px;">${typeIcon}</span>
                            </div>
                        `,
                        iconSize: [32, 32],
                        iconAnchor: [16, 16],
                        popupAnchor: [0, -16]
                    });
                    
                    const popupContent = `
                        <div style="font-family: 'Manrope', sans-serif; padding: 4px; min-width: 150px;">
                            <div style="font-size: 9px; font-weight: bold; text-transform: uppercase; color: ${markerColor}; letter-spacing: 0.05em; margin-bottom: 2px;">
                                ${pt.type}
                            </div>
                            <div style="font-family: 'Epilogue', sans-serif; font-weight: 600; font-size: 13px; color: #1D3557; margin-bottom: 4px;">
                                ${pt.name}
                            </div>
                            <div style="font-size: 11px; color: #485f84; line-height: 1.3;">
                                ${pt.details}
                            </div>
                        </div>
                    `;
                    
                    const marker = L.marker([lat, lng], { icon: customIcon })
                        .bindPopup(popupContent)
                        .addTo(map);
                        
                    markers.push(marker);
                });
                
                // Draw connecting path
                const polyline = L.polyline(latlngs, {
                    color: '#b7102a',
                    weight: 3,
                    opacity: 0.8,
                    dashArray: '8, 8',
                    lineJoin: 'round'
                }).addTo(map);
                
                // Fit map bounds to show all markers
                map.fitBounds(polyline.getBounds(), { padding: [50, 50] });
            });
            </script>

            <!-- 2. Accommodations Display -->
            <?php if (!empty($accommodations)): ?>
            <div class="bg-surface rounded-xl border border-outline-variant p-6 shadow-sm bg-white">
                <h3 class="text-lg font-heading font-semibold text-text-main mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-accent">hotel</span> Stay & Lodging Accommodations
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($accommodations as $accomm): ?>
                        <div class="p-4 rounded-lg bg-background-light border border-outline-variant/60 flex flex-col gap-2">
                            <div class="flex justify-between items-start">
                                <h4 class="font-bold text-sm text-text-main"><?php echo htmlspecialchars($accomm['Name']); ?></h4>
                                <span class="px-2 py-0.5 bg-secondary-container text-on-secondary-container rounded text-[9px] uppercase font-bold"><?php echo htmlspecialchars($accomm['Type']); ?></span>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-secondary mt-1">
                                <span class="font-bold text-primary font-mono">$<?php echo number_format($accomm['PricePerNight'], 0); ?>/night</span>
                                <div class="flex text-amber-500">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <span class="material-symbols-outlined text-[14px] <?php echo $i <= $accomm['StarRating'] ? 'fill-1' : ''; ?>" style="font-variation-settings: 'FILL' <?php echo $i <= $accomm['StarRating'] ? '1' : '0'; ?>;">star</span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 3. Flights Display -->
            <?php if (!empty($flights)): ?>
            <div class="bg-surface rounded-xl border border-outline-variant p-6 shadow-sm bg-white">
                <h3 class="text-lg font-heading font-semibold text-text-main mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-accent">flight</span> Travel & Flight Schedules
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($flights as $flight): ?>
                        <div class="p-4 rounded-lg bg-background-light border border-outline-variant/60 flex flex-col gap-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-bold text-sm text-text-main"><?php echo htmlspecialchars($flight['Airline']); ?></h4>
                                    <p class="text-[10px] font-bold text-muted font-mono tracking-wider mt-0.5">FLIGHT #<?php echo htmlspecialchars($flight['FlightNum']); ?></p>
                                </div>
                                <span class="text-xs font-bold text-primary font-mono">$<?php echo number_format($flight['Cost'], 2); ?></span>
                            </div>
                            <div class="flex justify-between items-center text-xs border-t border-outline-variant/30 pt-2 text-secondary">
                                <div>
                                    <p class="font-bold text-text-main font-mono"><?php echo $flight['DepAirport_Code']; ?></p>
                                    <p class="text-[9px]"><?php echo date('M d, g:ia', strtotime($flight['DepTime'])); ?></p>
                                </div>
                                <span class="material-symbols-outlined text-[16px] text-muted">arrow_right_alt</span>
                                <div class="text-right">
                                    <p class="font-bold text-text-main font-mono"><?php echo $flight['ArrAirport_Code']; ?></p>
                                    <p class="text-[9px]"><?php echo date('M d, g:ia', strtotime($flight['ArrTime'])); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 4. Attractions Display -->
            <?php if (!empty($attractions)): ?>
            <div class="bg-surface rounded-xl border border-outline-variant p-6 shadow-sm bg-white">
                <h3 class="text-lg font-heading font-semibold text-text-main mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-accent">explore</span> Sightseeing & Landmarks
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($attractions as $attr): ?>
                        <div class="p-4 rounded-lg bg-background-light border border-outline-variant/60 flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary mt-0.5">landmark</span>
                            <div class="flex-grow">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-bold text-sm text-text-main"><?php echo htmlspecialchars($attr['Name']); ?></h4>
                                    <span class="text-[10px] font-bold font-mono text-primary bg-primary-fixed px-1.5 py-0.5 rounded"><?php echo $attr['EntryFee'] > 0 ? '$'.number_format($attr['EntryFee'], 0) : 'FREE'; ?></span>
                                </div>
                                <p class="text-xs text-secondary mt-0.5 uppercase tracking-widest font-semibold text-[10px]"><?php echo htmlspecialchars($attr['Category']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 5. Restaurants Display -->
            <?php if (!empty($restaurants)): ?>
            <div class="bg-surface rounded-xl border border-outline-variant p-6 shadow-sm bg-white">
                <h3 class="text-lg font-heading font-semibold text-text-main mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-accent">restaurant</span> Culinary & Dining Spots
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($restaurants as $rest): ?>
                        <div class="p-4 rounded-lg bg-background-light border border-outline-variant/60 flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary mt-0.5">dining</span>
                            <div class="flex-grow">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-bold text-sm text-text-main"><?php echo htmlspecialchars($rest['Name']); ?></h4>
                                    <span class="text-[10px] text-muted italic">Avg Cost: $<?php echo number_format($rest['AverageCost'], 0); ?></span>
                                </div>
                                <p class="text-xs text-secondary mt-0.5 uppercase tracking-widest font-semibold text-[10px]"><?php echo htmlspecialchars($rest['CuisineType']); ?> Cuisine</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- REVIEWS SECTION -->
        <div class="bg-surface rounded-xl border border-outline-variant p-6 shadow-sm bg-white flex flex-col gap-6">
            <h3 class="text-lg font-heading font-semibold text-text-main border-b border-outline-variant pb-3 flex justify-between items-center">
                <span class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">reviews</span> Customer Reviews
                </span>
                <span class="text-xs font-semibold px-2 py-0.5 bg-surface-container text-secondary rounded"><?php echo count($reviews); ?> Reviews</span>
            </h3>

            <!-- Review submission form -->
            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'Traveller'): ?>
                <div class="bg-background-light p-5 rounded-lg border border-outline-variant/80 flex flex-col gap-4">
                    <h4 class="font-bold text-sm text-text-main flex items-center gap-1.5"><span class="material-symbols-outlined text-primary text-[18px]">rate_review</span> Leave a Review & Rating</h4>
                    <form method="POST" action="package_detail.php?id=<?php echo $package_id; ?>" class="flex flex-col gap-4">
                        <input type="hidden" name="action" value="leave_review">
                        
                        <!-- Rating selection -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-bold text-muted uppercase tracking-wider">Your Rating:</label>
                            <div class="flex gap-1 text-amber-400" id="star-selector">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" required class="sr-only peer">
                                        <span class="material-symbols-outlined text-[24px] hover:scale-110 transition-transform peer-checked:fill-1 fill-0" onclick="highlightStars(<?php echo $i; ?>)" id="star-<?php echo $i; ?>">star</span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1" for="comment">Your Comment *</label>
                            <textarea id="comment" name="comment" required rows="3" class="w-full p-3 text-sm rounded border border-outline-variant bg-surface focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary placeholder:text-muted" placeholder="Share your experience... Describe the lodging, guides, sights, and flights!"></textarea>
                        </div>

                        <button type="submit" class="btn h-[40px] px-8 text-xs font-bold uppercase tracking-wider self-end mt-2">Submit Sentiment Review</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Reviews list -->
            <?php if (empty($reviews)): ?>
                <p class="text-center py-6 text-sm text-muted italic">No reviews submitted for this package yet.</p>
            <?php else: ?>
                <div class="flex flex-col gap-4">
                    <?php foreach ($reviews as $rev): ?>
                        <div class="p-4 rounded-lg border border-outline-variant bg-background-light/40 flex flex-col gap-2 shadow-sm">
                            <div class="flex justify-between items-start flex-wrap gap-2">
                                <div>
                                    <span class="font-bold text-sm text-text-main"><?php echo htmlspecialchars($rev['FirstName'] . ' ' . $rev['LastName']); ?></span>
                                    <span class="text-[10px] text-muted block">Reviewed on: <?php echo date('M d, Y', strtotime($rev['DatePosted'])); ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="flex text-amber-500">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <span class="material-symbols-outlined text-[16px] <?php echo $i <= $rev['Rating'] ? 'fill-1' : ''; ?>" style="font-variation-settings: 'FILL' <?php echo $i <= $rev['Rating'] ? '1' : '0'; ?>;">star</span>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <!-- Sentiment badge -->
                                    <?php if ($rev['SentimentScore'] !== null): ?>
                                        <?php 
                                            $sentiment = (float)$rev['SentimentScore'];
                                            if ($sentiment > 0.1) {
                                                $badgeClass = 'bg-green-100 text-green-800 border-green-200';
                                                $label = 'Positive (' . number_format($sentiment, 2) . ')';
                                            } else if ($sentiment < -0.1) {
                                                $badgeClass = 'bg-red-100 text-primary border-red-200';
                                                $label = 'Negative (' . number_format($sentiment, 2) . ')';
                                            } else {
                                                $badgeClass = 'bg-gray-100 text-gray-800 border-gray-200';
                                                $label = 'Neutral (' . number_format($sentiment, 2) . ')';
                                            }
                                        ?>
                                        <span class="px-2 py-0.5 text-[9px] font-bold uppercase rounded border <?php echo $badgeClass; ?>"><?php echo $label; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="text-xs text-text-main/80 italic mt-1 leading-relaxed">"<?php echo htmlspecialchars($rev['Comment']); ?>"</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT SIDE: Booking Sidebar -->
    <div class="lg:w-1/3">
        <div class="bg-surface rounded-card shadow-sm border border-muted/20 p-6 sticky top-24 bg-white">
            <h3 class="text-xl font-heading font-semibold mb-4 text-text-main flex items-center gap-1.5"><span class="material-symbols-outlined text-primary">shopping_bag</span> Book This Package</h3>
            
            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'Traveller'): ?>
                <?php if (empty($scheduled_dates)): ?>
                    <div class="text-center py-6 bg-background-light border border-outline-variant rounded-md p-4">
                        <p class="text-xs text-secondary italic">Currently, there are no scheduled group dates available for booking. Please check back later.</p>
                    </div>
                <?php else: ?>
                    <form method="GET" action="checkout.php" class="flex flex-col gap-4" id="booking-form">
                        <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                        
                        <!-- Select Date Range -->
                        <div>
                            <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1" for="tripDateId">Select Group Date Range *</label>
                            <select id="tripDateId" name="tripDateId" required class="input-field h-[40px] py-0 text-xs" onchange="checkCapacity()">
                                <option value="" disabled selected>-- Choose Travel Date range --</option>
                                <?php foreach ($scheduled_dates as $sd): ?>
                                    <option value="<?php echo $sd['TripDateID']; ?>" data-remaining="<?php echo $sd['RemainingCapacity']; ?>">
                                        <?php echo date('M d, Y', strtotime($sd['StartDate'])) . ' - ' . date('M d, Y', strtotime($sd['EndDate'])) . ' (' . ($sd['RemainingCapacity'] > 0 ? $sd['RemainingCapacity'].' remaining' : 'SOLD OUT') . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Select Party Size -->
                        <div>
                            <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1" for="partySize">Party Size *</label>
                            <input type="number" id="partySize" name="partySize" required min="1" class="input-field h-[40px] text-sm" value="1" oninput="checkCapacity()">
                            <p class="text-[10px] text-muted mt-1">Pricing calculated per traveller.</p>
                        </div>

                        <!-- Warning message container -->
                        <div id="capacity-warning" class="hidden bg-error-container text-error p-3 rounded text-xs font-bold border border-error"></div>
                        
                        <div class="pt-4 border-t border-muted/20 mt-2">
                            <div class="flex justify-between items-center mb-4 text-xs font-bold uppercase text-secondary">
                                <span>Total Booking cost</span>
                                <span class="text-xl font-semibold text-primary font-mono" id="total-price">$<?php echo number_format($package['BasePrice'], 2); ?></span>
                            </div>
                            <button type="submit" id="book-submit" class="btn w-full">Confirm Booking</button>
                        </div>
                    </form>
                    
                    <script>
                        const sizeInput = document.getElementById('partySize');
                        const dateSelect = document.getElementById('tripDateId');
                        const totalSpan = document.getElementById('total-price');
                        const warningDiv = document.getElementById('capacity-warning');
                        const submitBtn = document.getElementById('book-submit');
                        const basePrice = <?php echo $package['BasePrice']; ?>;

                        function checkCapacity() {
                            const size = parseInt(sizeInput.value) || 1;
                            const option = dateSelect.options[dateSelect.selectedIndex];
                            
                            // 1. Calculate price
                            totalSpan.textContent = '$' + (basePrice * size).toFixed(2);

                            // 2. Perform capacity validation
                            if (!dateSelect.value) {
                                submitBtn.disabled = true;
                                return;
                            }

                            const remaining = parseInt(option.getAttribute('data-remaining')) || 0;
                            if (size > remaining) {
                                warningDiv.textContent = `The selected date range only has ${remaining} seat(s) remaining. Please adjust your party size.`;
                                warningDiv.classList.remove('hidden');
                                submitBtn.disabled = true;
                            } else {
                                warningDiv.classList.add('hidden');
                                submitBtn.disabled = false;
                            }
                        }

                        // Initialize
                        checkCapacity();
                    </script>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-6 bg-background-light border border-outline-variant rounded-md p-4 flex flex-col gap-3">
                    <p class="text-xs text-text-main">You must be logged in as a Traveller to book packages and schedule dates.</p>
                    <a href="login.php" class="btn h-[40px] text-xs font-bold uppercase tracking-wider">Sign In / Log In</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Stars Rating Selection Highlights
    function highlightStars(rating) {
        for (let i = 1; i <= 5; i++) {
            const star = document.getElementById('star-' + i);
            if (i <= rating) {
                star.classList.add('fill-1');
                star.classList.remove('fill-0');
                star.style.fontVariationSettings = "'FILL' 1";
            } else {
                star.classList.remove('fill-1');
                star.classList.add('fill-0');
                star.style.fontVariationSettings = "'FILL' 0";
            }
        }
    }
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>