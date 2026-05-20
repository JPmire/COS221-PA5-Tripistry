<?php
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: agency_dashboard.php");
    exit;
}

$page_title = 'Edit Package';
require_once 'includes/header.php';

$agency_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

$package_id = (int)$_GET['id'];


// 1. Fetch Package Details & Verify Ownership
try {
    $stmt = $pdo->prepare("SELECT * FROM TravelPackage WHERE PackageID = ? AND AgencyID = ?");
    $stmt->execute([$package_id, $agency_id]);
    $package = $stmt->fetch();

    if (!$package) {
        $error_message = "Package not found or you do not have permission to edit it.";
    }
} catch (\PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// 2. Handle Base Package & M:N Component Updates Form Submission
if ($package && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_package'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $basePrice = (float)($_POST['basePrice'] ?? 0);
    $durationDays = (int)($_POST['durationDays'] ?? 0);
    $maxCapacity = (int)($_POST['maxCapacity'] ?? 0);

    if (empty($title) || $basePrice <= 0 || $durationDays <= 0 || $maxCapacity <= 0) {
        $error_message = "Please fill in all required fields accurately.";
    } else {
        try {
            $pdo->beginTransaction();

            // A. Update base TravelPackage table
            $stmt = $pdo->prepare("
                UPDATE TravelPackage 
                SET Title = ?, Description = ?, BasePrice = ?, DurationDays = ?, MaxCapacity = ? 
                WHERE PackageID = ? AND AgencyID = ?
            ");
            $stmt->execute([$title, $description, $basePrice, $durationDays, $maxCapacity, $package_id, $agency_id]);

            // B. Clear and rebuild Destination links
            $pdo->prepare("DELETE FROM Package_Destination WHERE PackageID = ?")->execute([$package_id]);
            if (!empty($_POST['destinations']) && is_array($_POST['destinations'])) {
                $stmtPD = $pdo->prepare("INSERT INTO Package_Destination (PackageID, DestID) VALUES (?, ?)");
                foreach ($_POST['destinations'] as $destId) {
                    $stmtPD->execute([$package_id, (int)$destId]);
                }
            }

            // C. Clear and rebuild Accommodation links
            $pdo->prepare("DELETE FROM Package_Accommodation WHERE PackageID = ?")->execute([$package_id]);
            if (!empty($_POST['accommodations']) && is_array($_POST['accommodations'])) {
                $stmtPA = $pdo->prepare("INSERT INTO Package_Accommodation (PackageID, AccommID) VALUES (?, ?)");
                foreach ($_POST['accommodations'] as $accommId) {
                    $stmtPA->execute([$package_id, (int)$accommId]);
                }
            }

            // D. Clear and rebuild Flight links
            $pdo->prepare("DELETE FROM Package_Flight WHERE PackageID = ?")->execute([$package_id]);
            if (!empty($_POST['flights']) && is_array($_POST['flights'])) {
                $stmtPF = $pdo->prepare("INSERT INTO Package_Flight (PackageID, FlightID) VALUES (?, ?)");
                foreach ($_POST['flights'] as $flightId) {
                    $stmtPF->execute([$package_id, (int)$flightId]);
                }
            }

            // E. Clear and rebuild Attraction links
            $pdo->prepare("DELETE FROM Package_Attraction WHERE PackageID = ?")->execute([$package_id]);
            if (!empty($_POST['attractions']) && is_array($_POST['attractions'])) {
                $stmtPAttr = $pdo->prepare("INSERT INTO Package_Attraction (PackageID, AttractionID) VALUES (?, ?)");
                foreach ($_POST['attractions'] as $attrId) {
                    $stmtPAttr->execute([$package_id, (int)$attrId]);
                }
            }

            // F. Clear and rebuild Restaurant links
            $pdo->prepare("DELETE FROM Package_Restaurant WHERE PackageID = ?")->execute([$package_id]);
            if (!empty($_POST['restaurants']) && is_array($_POST['restaurants'])) {
                $stmtPR = $pdo->prepare("INSERT INTO Package_Restaurant (PackageID, RestaurantID) VALUES (?, ?)");
                foreach ($_POST['restaurants'] as $restId) {
                    $stmtPR->execute([$package_id, (int)$restId]);
                }
            }

            $pdo->commit();
            $success_message = "Package base details and linked components updated successfully!";
            
            // Re-fetch package so form displays updated values
            $stmt = $pdo->prepare("SELECT * FROM TravelPackage WHERE PackageID = ? AND AgencyID = ?");
            $stmt->execute([$package_id, $agency_id]);
            $package = $stmt->fetch();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "Error updating package: " . $e->getMessage();
        }
    }
}

// 3. Handle Group Trip Scheduling Form Submission
if ($package && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_trip'])) {
    $startDate = $_POST['startDate'] ?? '';
    $endDate = $_POST['endDate'] ?? '';

    if (empty($startDate) || empty($endDate) || $endDate < $startDate) {
        $error_message = "Please provide a valid start and end date range.";
    } else {
        try {
            // Find next TripDateID
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(TripDateID), 0) + 1 FROM GroupTrip WHERE PackageID = ?");
            $stmt->execute([$package_id]);
            $nextTripDateId = $stmt->fetchColumn();

            $stmtGT = $pdo->prepare("
                INSERT INTO GroupTrip (PackageID, TripDateID, StartDate, EndDate, Status) 
                VALUES (?, ?, ?, ?, 'Scheduled')
            ");
            $stmtGT->execute([$package_id, $nextTripDateId, $startDate, $endDate]);
            $success_message = "New group trip date range scheduled successfully!";
        } catch (\PDOException $e) {
            $error_message = "Failed to schedule group trip: " . $e->getMessage();
        }
    }
}

// 4. Handle Group Trip Status Update
if ($package && isset($_GET['action']) && $_GET['action'] === 'update_trip_status') {
    $tripDateId = (int)($_GET['trip_date_id'] ?? 0);
    $newStatus = $_GET['status'] ?? '';

    $validStatuses = ['Scheduled', 'Ongoing', 'Completed', 'Cancelled'];
    if ($tripDateId > 0 && in_array($newStatus, $validStatuses)) {
        try {
            $stmt = $pdo->prepare("UPDATE GroupTrip SET Status = ? WHERE PackageID = ? AND TripDateID = ?");
            $stmt->execute([$newStatus, $package_id, $tripDateId]);
            $success_message = "Group trip status updated successfully!";
        } catch (\PDOException $e) {
            $error_message = "Failed to update trip status: " . $e->getMessage();
        }
    }
}

// 5. Handle Group Trip Deletion
if ($package && isset($_GET['action']) && $_GET['action'] === 'delete_trip') {
    $tripDateId = (int)($_GET['trip_date_id'] ?? 0);

    if ($tripDateId > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM GroupTrip WHERE PackageID = ? AND TripDateID = ?");
            $stmt->execute([$package_id, $tripDateId]);
            $success_message = "Scheduled group trip date range deleted successfully!";
        } catch (\PDOException $e) {
            $error_message = "Failed to delete group trip date range: " . $e->getMessage();
        }
    }
}

// Fetch all elements & checked elements
if ($package) {
    try {
        // Fetch all available assets
        $destinations = $pdo->query("SELECT * FROM Destination ORDER BY Name ASC")->fetchAll();
        $accommodations = $pdo->query("SELECT * FROM Accommodation ORDER BY Name ASC")->fetchAll();
        $flights = $pdo->query("SELECT * FROM Flight ORDER BY Airline ASC, FlightNum ASC")->fetchAll();
        $attractions = $pdo->query("SELECT * FROM Attraction ORDER BY Name ASC")->fetchAll();
        $restaurants = $pdo->query("SELECT * FROM Restaurant ORDER BY Name ASC")->fetchAll();

        // Fetch currently linked IDs for checking boxes
        $stmt = $pdo->prepare("SELECT DestID FROM Package_Destination WHERE PackageID = ?");
        $stmt->execute([$package_id]);
        $linked_destinations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("SELECT AccommID FROM Package_Accommodation WHERE PackageID = ?");
        $stmt->execute([$package_id]);
        $linked_accommodations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("SELECT FlightID FROM Package_Flight WHERE PackageID = ?");
        $stmt->execute([$package_id]);
        $linked_flights = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("SELECT AttractionID FROM Package_Attraction WHERE PackageID = ?");
        $stmt->execute([$package_id]);
        $linked_attractions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("SELECT RestaurantID FROM Package_Restaurant WHERE PackageID = ?");
        $stmt->execute([$package_id]);
        $linked_restaurants = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Fetch existing group trips
        $stmt = $pdo->prepare("
            SELECT gt.*, COALESCE(SUM(b.PartySize), 0) AS BookedCount
            FROM GroupTrip gt
            LEFT JOIN Booking b ON gt.PackageID = b.Trip_PackageID AND gt.TripDateID = b.Trip_TripDateID
            WHERE gt.PackageID = ?
            GROUP BY gt.TripDateID, gt.StartDate, gt.EndDate, gt.Status
            ORDER BY gt.StartDate ASC
        ");
        $stmt->execute([$package_id]);
        $group_trips = $stmt->fetchAll();

    } catch (\PDOException $e) {
        $error_message = "Database fetch error: " . $e->getMessage();
    }
}
?>

<?php if ($error_message && !$package): ?>
    <div class="max-w-4xl mx-auto bg-red-100 text-primary p-4 rounded-md border border-red-200 mt-8">
        <?php echo htmlspecialchars($error_message); ?>
        <br><a href="agency_dashboard.php" class="underline font-bold mt-2 inline-block">Return to Dashboard</a>
    </div>
<?php endif; ?>

<?php if ($package): ?>
<div class="max-w-5xl mx-auto flex flex-col lg:flex-row gap-8">
    
    <!-- LEFT SIDE: Package Editing Fields (M:N linking) -->
    <div class="lg:w-2/3 flex flex-col gap-6">
        <div class="bg-surface rounded-xl border border-outline-variant p-6 md:p-8 shadow-sm">
            <div class="mb-6 flex justify-between items-center border-b border-outline-variant pb-4">
                <div>
                    <h1 class="text-2xl font-heading font-semibold text-text-main">Edit Travel Package</h1>
                    <p class="text-xs text-secondary mt-1">Modify title, itinerary description, pricing, and associated assets.</p>
                </div>
                <a href="agency_dashboard.php" class="text-text-main font-medium hover:text-primary transition-colors flex items-center gap-1 border border-outline-variant rounded-md px-3 py-1.5 hover:bg-background-light">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span> Dashboard
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-green-100 text-green-800 p-4 rounded-md mb-6 border border-green-200 font-medium">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-100 text-primary p-4 rounded-md mb-6 border border-red-200 font-medium">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit_package.php?id=<?php echo $package_id; ?>" class="flex flex-col gap-8">
                <input type="hidden" name="update_package" value="1">
                
                <!-- SECTION 1: Base details -->
                <div class="flex flex-col gap-5 bg-background-light p-5 rounded-lg border border-outline-variant">
                    <h3 class="text-lg font-heading font-semibold text-text-main flex items-center gap-2 border-b border-outline-variant pb-2">
                        <span class="material-symbols-outlined text-primary">description</span> 1. Basic Package Information
                    </h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1" for="title">Package Title <span class="text-primary">*</span></label>
                        <input type="text" id="title" name="title" required class="input-field" value="<?php echo htmlspecialchars($package['Title']); ?>" placeholder="e.g. Romantic Paris Getaway">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-text-main mb-1" for="description">Detailed Description</label>
                        <textarea id="description" name="description" rows="4" class="w-full p-4 rounded-lg border border-outline-variant bg-surface text-on-surface placeholder:text-muted focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition-colors" placeholder="Provide a detailed daily itinerary..."><?php echo htmlspecialchars($package['Description']); ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1" for="basePrice">Base Price ($) <span class="text-primary">*</span></label>
                            <input type="number" id="basePrice" name="basePrice" required min="1" step="0.01" class="input-field" value="<?php echo htmlspecialchars($package['BasePrice']); ?>" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1" for="durationDays">Duration (Days) <span class="text-primary">*</span></label>
                            <input type="number" id="durationDays" name="durationDays" required min="1" class="input-field" value="<?php echo htmlspecialchars($package['DurationDays']); ?>" placeholder="Duration in days">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-main mb-1" for="maxCapacity">Max Capacity <span class="text-primary">*</span></label>
                            <input type="number" id="maxCapacity" name="maxCapacity" required min="1" class="input-field" value="<?php echo htmlspecialchars($package['MaxCapacity']); ?>" placeholder="Maximum travellers allowed">
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: Link Travel Assets & Destinations -->
                <div class="flex flex-col gap-6">
                    <h3 class="text-lg font-heading font-semibold text-text-main flex items-center gap-2 border-b border-outline-variant pb-2">
                        <span class="material-symbols-outlined text-primary">link</span> 2. Link Travel Assets & Destinations
                    </h3>
                    <p class="text-xs text-secondary -mt-3">Link associated destinations, hotels, flights, attractions, and restaurants to this itinerary package.</p>

                    <!-- Accordion Destinations -->
                    <div class="border border-outline-variant rounded-lg overflow-hidden bg-surface">
                        <div class="px-5 py-3 border-b border-outline-variant bg-surface-container flex justify-between items-center cursor-pointer select-none" onclick="toggleAccordion('acc-dest')">
                            <span class="font-bold text-text-main flex items-center gap-2">
                                <span class="material-symbols-outlined text-accent">pin_drop</span> Destinations Visited
                            </span>
                            <div class="flex items-center gap-3">
                                <button type="button" class="px-2.5 py-1 text-xs font-bold text-primary border border-primary/30 rounded hover:bg-primary/10 flex items-center gap-1 transition-colors" onclick="event.stopPropagation(); openQuickModal('dest')">
                                    <span class="material-symbols-outlined text-[14px]">add</span> Quick Add
                                </button>
                                <span id="acc-dest-icon" class="material-symbols-outlined text-muted">expand_less</span>
                            </div>
                        </div>
                        <div id="acc-dest" class="p-5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 max-h-[250px] overflow-y-auto">
                            <?php if (empty($destinations)): ?>
                                <p class="text-sm text-muted italic col-span-full text-center py-2">No destinations defined yet.</p>
                            <?php else: ?>
                                <?php foreach ($destinations as $dest): ?>
                                    <?php $checked = in_array($dest['DestID'], $linked_destinations) ? 'checked' : ''; ?>
                                    <label class="flex items-start gap-2.5 p-2 border border-outline-variant rounded hover:bg-background-light cursor-pointer select-none <?php echo $checked ? 'border-primary/50 bg-primary/5' : ''; ?>">
                                        <input type="checkbox" name="destinations[]" value="<?php echo $dest['DestID']; ?>" <?php echo $checked; ?> class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
                                        <span class="text-xs font-medium text-text-main leading-tight"><?php echo htmlspecialchars($dest['Name']) . ', ' . htmlspecialchars($dest['Country']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Accordion Accommodations -->
                    <div class="border border-outline-variant rounded-lg overflow-hidden bg-surface">
                        <div class="px-5 py-3 border-b border-outline-variant bg-surface-container flex justify-between items-center cursor-pointer select-none" onclick="toggleAccordion('acc-accomm')">
                            <span class="font-bold text-text-main flex items-center gap-2">
                                <span class="material-symbols-outlined text-accent">hotel</span> Accommodations / Hotels
                            </span>
                            <div class="flex items-center gap-3">
                                <button type="button" class="px-2.5 py-1 text-xs font-bold text-primary border border-primary/30 rounded hover:bg-primary/10 flex items-center gap-1 transition-colors" onclick="event.stopPropagation(); openQuickModal('accomm')">
                                    <span class="material-symbols-outlined text-[14px]">add</span> Quick Add
                                </button>
                                <span id="acc-accomm-icon" class="material-symbols-outlined text-muted">expand_less</span>
                            </div>
                        </div>
                        <div id="acc-accomm" class="p-5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 max-h-[250px] overflow-y-auto">
                            <?php if (empty($accommodations)): ?>
                                <p class="text-sm text-muted italic col-span-full text-center py-2">No accommodations defined yet.</p>
                            <?php else: ?>
                                <?php foreach ($accommodations as $accomm): ?>
                                    <?php $checked = in_array($accomm['AccommID'], $linked_accommodations) ? 'checked' : ''; ?>
                                    <label class="flex items-start gap-2.5 p-2 border border-outline-variant rounded hover:bg-background-light cursor-pointer select-none <?php echo $checked ? 'border-primary/50 bg-primary/5' : ''; ?>">
                                        <input type="checkbox" name="accommodations[]" value="<?php echo $accomm['AccommID']; ?>" <?php echo $checked; ?> class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
                                        <span class="text-xs font-medium text-text-main leading-tight">
                                            <?php echo htmlspecialchars($accomm['Name']); ?>
                                            <span class="block text-[10px] text-muted font-normal uppercase mt-0.5"><?php echo htmlspecialchars($accomm['Type']); ?> • $<?php echo number_format($accomm['PricePerNight'], 0); ?>/night</span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Accordion Flights -->
                    <div class="border border-outline-variant rounded-lg overflow-hidden bg-surface">
                        <div class="px-5 py-3 border-b border-outline-variant bg-surface-container flex justify-between items-center cursor-pointer select-none" onclick="toggleAccordion('acc-flights')">
                            <span class="font-bold text-text-main flex items-center gap-2">
                                <span class="material-symbols-outlined text-accent">flight</span> Flights Included
                            </span>
                            <div class="flex items-center gap-3">
                                <button type="button" class="px-2.5 py-1 text-xs font-bold text-primary border border-primary/30 rounded hover:bg-primary/10 flex items-center gap-1 transition-colors" onclick="event.stopPropagation(); openQuickModal('flight')">
                                    <span class="material-symbols-outlined text-[14px]">add</span> Quick Add
                                </button>
                                <span id="acc-flights-icon" class="material-symbols-outlined text-muted">expand_less</span>
                            </div>
                        </div>
                        <div id="acc-flights" class="p-5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 max-h-[250px] overflow-y-auto">
                            <?php if (empty($flights)): ?>
                                <p class="text-sm text-muted italic col-span-full text-center py-2">No flights defined yet.</p>
                            <?php else: ?>
                                <?php foreach ($flights as $flight): ?>
                                    <?php $checked = in_array($flight['FlightID'], $linked_flights) ? 'checked' : ''; ?>
                                    <label class="flex items-start gap-2.5 p-2 border border-outline-variant rounded hover:bg-background-light cursor-pointer select-none <?php echo $checked ? 'border-primary/50 bg-primary/5' : ''; ?>">
                                        <input type="checkbox" name="flights[]" value="<?php echo $flight['FlightID']; ?>" <?php echo $checked; ?> class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
                                        <span class="text-xs font-medium text-text-main leading-tight">
                                            <?php echo htmlspecialchars($flight['Airline']) . ' #' . htmlspecialchars($flight['FlightNum']); ?>
                                            <span class="block text-[10px] text-muted font-normal uppercase mt-0.5"><?php echo $flight['DepAirport_Code'] . ' → ' . $flight['ArrAirport_Code']; ?> • $<?php echo number_format($flight['Cost'], 0); ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Accordion Attractions -->
                    <div class="border border-outline-variant rounded-lg overflow-hidden bg-surface">
                        <div class="px-5 py-3 border-b border-outline-variant bg-surface-container flex justify-between items-center cursor-pointer select-none" onclick="toggleAccordion('acc-attractions')">
                            <span class="font-bold text-text-main flex items-center gap-2">
                                <span class="material-symbols-outlined text-accent">explore</span> Attractions & Landmarks
                            </span>
                            <div class="flex items-center gap-3">
                                <button type="button" class="px-2.5 py-1 text-xs font-bold text-primary border border-primary/30 rounded hover:bg-primary/10 flex items-center gap-1 transition-colors" onclick="event.stopPropagation(); openQuickModal('attraction')">
                                    <span class="material-symbols-outlined text-[14px]">add</span> Quick Add
                                </button>
                                <span id="acc-attractions-icon" class="material-symbols-outlined text-muted">expand_less</span>
                            </div>
                        </div>
                        <div id="acc-attractions" class="p-5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 max-h-[250px] overflow-y-auto">
                            <?php if (empty($attractions)): ?>
                                <p class="text-sm text-muted italic col-span-full text-center py-2">No attractions defined yet.</p>
                            <?php else: ?>
                                <?php foreach ($attractions as $attr): ?>
                                    <?php $checked = in_array($attr['AttractionID'], $linked_attractions) ? 'checked' : ''; ?>
                                    <label class="flex items-start gap-2.5 p-2 border border-outline-variant rounded hover:bg-background-light cursor-pointer select-none <?php echo $checked ? 'border-primary/50 bg-primary/5' : ''; ?>">
                                        <input type="checkbox" name="attractions[]" value="<?php echo $attr['AttractionID']; ?>" <?php echo $checked; ?> class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
                                        <span class="text-xs font-medium text-text-main leading-tight">
                                            <?php echo htmlspecialchars($attr['Name']); ?>
                                            <span class="block text-[10px] text-muted font-normal uppercase mt-0.5"><?php echo htmlspecialchars($attr['Category']); ?> • Entry: $<?php echo number_format($attr['EntryFee'], 0); ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Accordion Restaurants -->
                    <div class="border border-outline-variant rounded-lg overflow-hidden bg-surface">
                        <div class="px-5 py-3 border-b border-outline-variant bg-surface-container flex justify-between items-center cursor-pointer select-none" onclick="toggleAccordion('acc-restaurants')">
                            <span class="font-bold text-text-main flex items-center gap-2">
                                <span class="material-symbols-outlined text-accent">restaurant</span> Restaurants & Diners
                            </span>
                            <div class="flex items-center gap-3">
                                <button type="button" class="px-2.5 py-1 text-xs font-bold text-primary border border-primary/30 rounded hover:bg-primary/10 flex items-center gap-1 transition-colors" onclick="event.stopPropagation(); openQuickModal('restaurant')">
                                    <span class="material-symbols-outlined text-[14px]">add</span> Quick Add
                                </button>
                                <span id="acc-restaurants-icon" class="material-symbols-outlined text-muted">expand_less</span>
                            </div>
                        </div>
                        <div id="acc-restaurants" class="p-5 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 max-h-[250px] overflow-y-auto">
                            <?php if (empty($restaurants)): ?>
                                <p class="text-sm text-muted italic col-span-full text-center py-2">No restaurants defined yet.</p>
                            <?php else: ?>
                                <?php foreach ($restaurants as $rest): ?>
                                    <?php $checked = in_array($rest['RestaurantID'], $linked_restaurants) ? 'checked' : ''; ?>
                                    <label class="flex items-start gap-2.5 p-2 border border-outline-variant rounded hover:bg-background-light cursor-pointer select-none <?php echo $checked ? 'border-primary/50 bg-primary/5' : ''; ?>">
                                        <input type="checkbox" name="restaurants[]" value="<?php echo $rest['RestaurantID']; ?>" <?php echo $checked; ?> class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
                                        <span class="text-xs font-medium text-text-main leading-tight">
                                            <?php echo htmlspecialchars($rest['Name']); ?>
                                            <span class="block text-[10px] text-muted font-normal uppercase mt-0.5"><?php echo htmlspecialchars($rest['CuisineType']); ?> • Avg: $<?php echo number_format($rest['AverageCost'], 0); ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-outline-variant flex justify-end">
                    <button type="submit" class="btn w-full md:w-auto px-12">Update Travel Package</button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT SIDE: Group Trip Date Scheduler & Status Manager -->
    <div class="lg:w-1/3 flex flex-col gap-6">
        
        <!-- Schedule New Dates Card -->
        <div class="bg-surface rounded-xl border border-outline-variant p-6 shadow-sm">
            <h3 class="text-lg font-heading font-semibold text-text-main mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">calendar_month</span> Schedule Group Trip
            </h3>
            <p class="text-xs text-secondary mb-4">Add a new date range during which travellers can book and travel together as a group trip.</p>
            
            <form method="POST" action="edit_package.php?id=<?php echo $package_id; ?>" class="flex flex-col gap-4">
                <input type="hidden" name="schedule_trip" value="1">
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1" for="startDate">Start Date *</label>
                    <input type="date" id="startDate" name="startDate" required min="<?php echo date('Y-m-d'); ?>" class="input-field h-[40px] text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1" for="endDate">End Date *</label>
                    <input type="date" id="endDate" name="endDate" required min="<?php echo date('Y-m-d'); ?>" class="input-field h-[40px] text-sm">
                </div>
                <button type="submit" class="btn h-[40px] w-full text-xs font-bold uppercase tracking-wider mt-2">Schedule Date Range</button>
            </form>
        </div>

        <!-- Scheduled Dates Status Table -->
        <div class="bg-surface rounded-xl border border-outline-variant overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-outline-variant bg-white">
                <h3 class="text-base font-heading font-semibold text-text-main flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">date_range</span> Active Group Trips
                </h3>
            </div>
            
            <div class="p-0 bg-white max-h-[380px] overflow-y-auto">
                <?php if (empty($group_trips)): ?>
                    <p class="p-6 text-center text-xs text-muted italic">No group trips scheduled yet.</p>
                <?php else: ?>
                    <ul class="divide-y divide-outline-variant">
                        <?php foreach ($group_trips as $gt): ?>
                            <li class="p-4 hover:bg-background-light/40 transition-all flex flex-col gap-2.5">
                                <div class="flex justify-between items-start">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-xs font-bold text-text-main flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px] text-muted">event</span>
                                            <?php echo date('M d, Y', strtotime($gt['StartDate'])); ?> →
                                        </span>
                                        <span class="text-xs font-bold text-text-main ml-[18px]">
                                            <?php echo date('M d, Y', strtotime($gt['EndDate'])); ?>
                                        </span>
                                    </div>
                                    <span class="px-2 py-0.5 text-[9px] font-bold uppercase rounded tracking-wider <?php 
                                        echo $gt['Status'] === 'Scheduled' ? 'bg-blue-100 text-blue-800' : 
                                            ($gt['Status'] === 'Ongoing' ? 'bg-amber-100 text-amber-800' : 
                                            ($gt['Status'] === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800')); 
                                    ?>">
                                        <?php echo htmlspecialchars($gt['Status']); ?>
                                    </span>
                                </div>
                                
                                <div class="flex items-center justify-between text-[11px] text-secondary mt-1">
                                    <span class="font-medium flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[13px] text-muted">people</span>
                                        Bookings: <strong><?php echo $gt['BookedCount']; ?></strong> / <?php echo $package['MaxCapacity']; ?>
                                    </span>
                                </div>

                                <!-- Actions Grid -->
                                <div class="flex gap-2 items-center justify-between border-t border-outline-variant/40 pt-2 mt-1">
                                    <div class="flex items-center gap-1">
                                        <label class="text-[10px] text-muted uppercase font-bold mr-1">Status:</label>
                                        <select class="text-[10px] py-0.5 px-1 border border-outline-variant rounded bg-surface focus:outline-none" onchange="window.location.href='edit_package.php?id=<?php echo $package_id; ?>&action=update_trip_status&trip_date_id=<?php echo $gt['TripDateID']; ?>&status=' + this.value">
                                            <option value="Scheduled" <?php echo $gt['Status'] === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                            <option value="Ongoing" <?php echo $gt['Status'] === 'Ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                            <option value="Completed" <?php echo $gt['Status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="Cancelled" <?php echo $gt['Status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    
                                    <a href="edit_package.php?id=<?php echo $package_id; ?>&action=delete_trip&trip_date_id=<?php echo $gt['TripDateID']; ?>" onclick="return confirm('Are you sure you want to delete this trip date range? Bookings for this range may fail.');" class="text-secondary hover:text-error transition-colors p-1" title="Delete Trip Date">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<!-- ================= QUICK-CREATE MODALS (AJAX) ================= -->

<!-- Destination Modal -->
<div id="modal-dest" class="fixed inset-0 z-50 bg-black/45 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-surface rounded-card shadow-2xl border border-outline-variant w-full max-w-[420px] p-6">
        <h3 class="text-lg font-heading font-semibold text-text-main mb-4 flex items-center gap-1.5"><span class="material-symbols-outlined text-primary">add_location</span> Add Destination</h3>
        <form onsubmit="submitQuickModal(event, 'Destination')" class="flex flex-col gap-4">
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">City Name *</label>
                <input type="text" id="m-dest-name" required class="input-field h-[40px] text-sm" placeholder="e.g. Paris">
            </div>
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Country *</label>
                <input type="text" id="m-dest-country" required class="input-field h-[40px] text-sm" placeholder="e.g. France">
            </div>
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Region (Optional)</label>
                <input type="text" id="m-dest-region" class="input-field h-[40px] text-sm" placeholder="e.g. Europe">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="px-4 py-2 border border-outline-variant rounded-md text-sm text-text-main hover:bg-background-light font-medium" onclick="closeQuickModal('dest')">Cancel</button>
                <button type="submit" class="bg-primary text-white px-5 py-2 rounded-md text-sm font-semibold hover:opacity-95 transition-opacity">Add & Select</button>
            </div>
        </form>
    </div>
</div>

<!-- Accommodation Modal -->
<div id="modal-accomm" class="fixed inset-0 z-50 bg-black/45 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-surface rounded-card shadow-2xl border border-outline-variant w-full max-w-[420px] p-6">
        <h3 class="text-lg font-heading font-semibold text-text-main mb-4 flex items-center gap-1.5"><span class="material-symbols-outlined text-primary">bedroom_parent</span> Add Accommodation</h3>
        <form onsubmit="submitQuickModal(event, 'Accommodation')" class="flex flex-col gap-4">
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Accommodation Name *</label>
                <input type="text" id="m-accomm-name" required class="input-field h-[40px] text-sm" placeholder="e.g. Eiffel Suites">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Type *</label>
                    <select id="m-accomm-type" required class="input-field h-[40px] text-sm py-0">
                        <option value="Hotel">Hotel</option>
                        <option value="Resort">Resort</option>
                        <option value="Villa">Villa</option>
                        <option value="Apartment">Apartment</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Price per Night ($) *</label>
                    <input type="number" id="m-accomm-price" required min="0" class="input-field h-[40px] text-sm" placeholder="0.00">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Star Rating (1-5)</label>
                <input type="number" id="m-accomm-stars" min="1" max="5" class="input-field h-[40px] text-sm" placeholder="e.g. 4">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="px-4 py-2 border border-outline-variant rounded-md text-sm text-text-main hover:bg-background-light font-medium" onclick="closeQuickModal('accomm')">Cancel</button>
                <button type="submit" class="bg-primary text-white px-5 py-2 rounded-md text-sm font-semibold hover:opacity-95 transition-opacity">Add & Select</button>
            </div>
        </form>
    </div>
</div>

<!-- Flight Modal -->
<div id="modal-flight" class="fixed inset-0 z-50 bg-black/45 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-surface rounded-card shadow-2xl border border-outline-variant w-full max-w-[460px] p-6">
        <h3 class="text-lg font-heading font-semibold text-text-main mb-4 flex items-center gap-1.5"><span class="material-symbols-outlined text-primary">local_airport</span> Add Flight</h3>
        <form onsubmit="submitQuickModal(event, 'Flight')" class="flex flex-col gap-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Airline *</label>
                    <input type="text" id="m-flight-airline" required class="input-field h-[40px] text-sm" placeholder="e.g. Air France">
                </div>
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Flight Number *</label>
                    <input type="text" id="m-flight-num" required class="input-field h-[40px] text-sm" placeholder="e.g. AF015">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Departure Time *</label>
                    <input type="datetime-local" id="m-flight-dep" required class="input-field h-[40px] text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Arrival Time *</label>
                    <input type="datetime-local" id="m-flight-arr" required class="input-field h-[40px] text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Dep Airport Code (3 chars) *</label>
                    <input type="text" id="m-flight-depcode" required maxlength="3" class="input-field h-[40px] text-sm font-mono uppercase" placeholder="e.g. JFK">
                </div>
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Arr Airport Code (3 chars) *</label>
                    <input type="text" id="m-flight-arrcode" required maxlength="3" class="input-field h-[40px] text-sm font-mono uppercase" placeholder="e.g. CDG">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Cost ($) *</label>
                <input type="number" id="m-flight-cost" required min="0" step="0.01" class="input-field h-[40px] text-sm" placeholder="0.00">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="px-4 py-2 border border-outline-variant rounded-md text-sm text-text-main hover:bg-background-light font-medium" onclick="closeQuickModal('flight')">Cancel</button>
                <button type="submit" class="bg-primary text-white px-5 py-2 rounded-md text-sm font-semibold hover:opacity-95 transition-opacity">Add & Select</button>
            </div>
        </form>
    </div>
</div>

<!-- Attraction Modal -->
<div id="modal-attraction" class="fixed inset-0 z-50 bg-black/45 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-surface rounded-card shadow-2xl border border-outline-variant w-full max-w-[420px] p-6">
        <h3 class="text-lg font-heading font-semibold text-text-main mb-4 flex items-center gap-1.5"><span class="material-symbols-outlined text-primary">theater_comedy</span> Add Attraction</h3>
        <form onsubmit="submitQuickModal(event, 'Attraction')" class="flex flex-col gap-4">
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Attraction Name *</label>
                <input type="text" id="m-attr-name" required class="input-field h-[40px] text-sm" placeholder="e.g. Louvre Museum">
            </div>
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Category</label>
                <input type="text" id="m-attr-category" class="input-field h-[40px] text-sm" placeholder="e.g. Museum, Landmark, Beach">
            </div>
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Entry Fee ($)</label>
                <input type="number" id="m-attr-fee" min="0" step="0.01" class="input-field h-[40px] text-sm" placeholder="0.00">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="px-4 py-2 border border-outline-variant rounded-md text-sm text-text-main hover:bg-background-light font-medium" onclick="closeQuickModal('attraction')">Cancel</button>
                <button type="submit" class="bg-primary text-white px-5 py-2 rounded-md text-sm font-semibold hover:opacity-95 transition-opacity">Add & Select</button>
            </div>
        </form>
    </div>
</div>

<!-- Restaurant Modal -->
<div id="modal-restaurant" class="fixed inset-0 z-50 bg-black/45 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-surface rounded-card shadow-2xl border border-outline-variant w-full max-w-[420px] p-6">
        <h3 class="text-lg font-heading font-semibold text-text-main mb-4 flex items-center gap-1.5"><span class="material-symbols-outlined text-primary">lunch_dining</span> Add Restaurant</h3>
        <form onsubmit="submitQuickModal(event, 'Restaurant')" class="flex flex-col gap-4">
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Restaurant Name *</label>
                <input type="text" id="m-rest-name" required class="input-field h-[40px] text-sm" placeholder="e.g. Le Jules Verne">
            </div>
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Cuisine Type</label>
                <input type="text" id="m-rest-cuisine" class="input-field h-[40px] text-sm" placeholder="e.g. French, Japanese Ramen, Vegan">
            </div>
            <div>
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Average Cost per Meal ($)</label>
                <input type="number" id="m-rest-cost" min="0" step="0.01" class="input-field h-[40px] text-sm" placeholder="0.00">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="px-4 py-2 border border-outline-variant rounded-md text-sm text-text-main hover:bg-background-light font-medium" onclick="closeQuickModal('restaurant')">Cancel</button>
                <button type="submit" class="bg-primary text-white px-5 py-2 rounded-md text-sm font-semibold hover:opacity-95 transition-opacity">Add & Select</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleAccordion(id) {
        const container = document.getElementById(id);
        const icon = document.getElementById(id + '-icon');
        if (container.classList.contains('hidden')) {
            container.classList.remove('hidden');
            icon.textContent = 'expand_less';
        } else {
            container.classList.add('hidden');
            icon.textContent = 'expand_more';
        }
    }

    // Modal Control Functions
    function openQuickModal(type) {
        document.getElementById('modal-' + type).style.display = 'flex';
        document.getElementById('modal-' + type).classList.remove('hidden');
    }

    function closeQuickModal(type) {
        document.getElementById('modal-' + type).style.display = 'none';
        document.getElementById('modal-' + type).classList.add('hidden');
    }

    // AJAX Submission handler for quick adding assets
    async function submitQuickModal(e, componentName) {
        e.preventDefault();
        const type = 'Create' + componentName;
        const lowercaseType = componentName.toLowerCase();
        let payload = { type: type };

        if (componentName === 'Destination') {
            payload.name = document.getElementById('m-dest-name').value;
            payload.country = document.getElementById('m-dest-country').value;
            payload.region = document.getElementById('m-dest-region').value;
        } else if (componentName === 'Accommodation') {
            payload.name = document.getElementById('m-accomm-name').value;
            payload.type = document.getElementById('m-accomm-type').value;
            payload.pricePerNight = parseFloat(document.getElementById('m-accomm-price').value);
            payload.starRating = parseInt(document.getElementById('m-accomm-stars').value);
        } else if (componentName === 'Flight') {
            payload.airline = document.getElementById('m-flight-airline').value;
            payload.flightNum = document.getElementById('m-flight-num').value;
            payload.depTime = document.getElementById('m-flight-dep').value;
            payload.arrTime = document.getElementById('m-flight-arr').value;
            payload.depAirportCode = document.getElementById('m-flight-depcode').value.toUpperCase();
            payload.arrAirportCode = document.getElementById('m-flight-arrcode').value.toUpperCase();
            payload.cost = parseFloat(document.getElementById('m-flight-cost').value);
        } else if (componentName === 'Attraction') {
            payload.name = document.getElementById('m-attr-name').value;
            payload.category = document.getElementById('m-attr-category').value;
            payload.entryFee = parseFloat(document.getElementById('m-attr-fee').value);
        } else if (componentName === 'Restaurant') {
            payload.name = document.getElementById('m-rest-name').value;
            payload.cuisineType = document.getElementById('m-rest-cuisine').value;
            payload.averageCost = parseFloat(document.getElementById('m-rest-cost').value);
        }

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (data.status === 'success') {
                const insertedId = data.data.id;
                
                const listContainer = document.getElementById('acc-' + (lowercaseType === 'accommodation' ? 'accomm' : lowercaseType === 'destination' ? 'dest' : lowercaseType === 'flight' ? 'flights' : lowercaseType === 'attraction' ? 'attractions' : 'restaurants'));
                
                let labelText = '';
                let detailsText = '';
                
                if (componentName === 'Destination') {
                    labelText = payload.name + ', ' + payload.country;
                } else if (componentName === 'Accommodation') {
                    labelText = payload.name;
                    detailsText = payload.type + ' • $' + payload.pricePerNight + '/night';
                } else if (componentName === 'Flight') {
                    labelText = payload.airline + ' #' + payload.flightNum;
                    detailsText = payload.depAirportCode + ' → ' + payload.arrAirportCode + ' • $' + payload.cost;
                } else if (componentName === 'Attraction') {
                    labelText = payload.name;
                    detailsText = payload.category + ' • Entry: $' + payload.entryFee;
                } else if (componentName === 'Restaurant') {
                    labelText = payload.name;
                    detailsText = payload.cuisineType + ' • Avg: $' + payload.averageCost;
                }

                if (listContainer.querySelector('.text-muted')) {
                    listContainer.innerHTML = '';
                }

                const fieldName = lowercaseType === 'accommodation' ? 'accommodations[]' : lowercaseType === 'destination' ? 'destinations[]' : lowercaseType === 'flight' ? 'flights[]' : lowercaseType === 'attraction' ? 'attractions[]' : 'restaurants[]';

                const newCardHTML = `
                <label class="flex items-start gap-2.5 p-2 border border-primary/50 bg-primary/5 rounded hover:bg-background-light cursor-pointer select-none">
                    <input type="checkbox" name="${fieldName}" value="${insertedId}" checked class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
                    <span class="text-xs font-medium text-text-main leading-tight">
                        ${labelText}
                        ${detailsText ? `<span class="block text-[10px] text-muted font-normal uppercase mt-0.5">${detailsText}</span>` : ''}
                    </span>
                </label>`;

                listContainer.insertAdjacentHTML('afterbegin', newCardHTML);
                
                closeQuickModal(lowercaseType === 'accommodation' ? 'accomm' : lowercaseType === 'destination' ? 'dest' : lowercaseType === 'flight' ? 'flight' : lowercaseType === 'attraction' ? 'attraction' : 'restaurant');
                alert(payload.name + ' successfully created and automatically linked!');
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Failed to insert component: ' + err.message);
        }
    }
</script>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
