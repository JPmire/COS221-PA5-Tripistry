<?php
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
    header("Location: login.php");
    exit;
}

$page_title = 'Create Package';
require_once 'includes/header.php';


$agency_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle Package Creation Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

            // 1. Insert into base TravelPackage
            $stmt = $pdo->prepare("INSERT INTO TravelPackage (Title, Description, BasePrice, DurationDays, MaxCapacity, AgencyID) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $basePrice, $durationDays, $maxCapacity, $agency_id]);
            $packageId = $pdo->lastInsertId();

            // 2. Insert into M:N associative tables
            if (!empty($_POST['destinations']) && is_array($_POST['destinations'])) {
                $stmtPD = $pdo->prepare("INSERT INTO Package_Destination (PackageID, DestID) VALUES (?, ?)");
                foreach ($_POST['destinations'] as $destId) {
                    $stmtPD->execute([$packageId, (int)$destId]);
                }
            }
            if (!empty($_POST['accommodations']) && is_array($_POST['accommodations'])) {
                $stmtPA = $pdo->prepare("INSERT INTO Package_Accommodation (PackageID, AccommID) VALUES (?, ?)");
                foreach ($_POST['accommodations'] as $accommId) {
                    $stmtPA->execute([$packageId, (int)$accommId]);
                }
            }
            if (!empty($_POST['flights']) && is_array($_POST['flights'])) {
                $stmtPF = $pdo->prepare("INSERT INTO Package_Flight (PackageID, FlightID) VALUES (?, ?)");
                foreach ($_POST['flights'] as $flightId) {
                    $stmtPF->execute([$packageId, (int)$flightId]);
                }
            }
            if (!empty($_POST['attractions']) && is_array($_POST['attractions'])) {
                $stmtPAttr = $pdo->prepare("INSERT INTO Package_Attraction (PackageID, AttractionID) VALUES (?, ?)");
                foreach ($_POST['attractions'] as $attrId) {
                    $stmtPAttr->execute([$packageId, (int)$attrId]);
                }
            }
            if (!empty($_POST['restaurants']) && is_array($_POST['restaurants'])) {
                $stmtPR = $pdo->prepare("INSERT INTO Package_Restaurant (PackageID, RestaurantID) VALUES (?, ?)");
                foreach ($_POST['restaurants'] as $restId) {
                    $stmtPR->execute([$packageId, (int)$restId]);
                }
            }

            // 3. Create an initial GroupTrip date (e.g. Scheduled to start in 10 days)
            $stmtGT = $pdo->prepare("
                INSERT INTO GroupTrip (PackageID, TripDateID, StartDate, EndDate, Status) 
                VALUES (?, 1, DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'Scheduled')
            ");
            $stmtGT->execute([$packageId, 10 + $durationDays]);

            $pdo->commit();
            $success_message = "Package created successfully with all linked components and scheduled group trip dates!";
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "Error creating package: " . $e->getMessage();
        }
    }
}

// Fetch all available components from the database to populate the checklist managers
try {
    $destinations = $pdo->query("SELECT * FROM Destination ORDER BY Name ASC")->fetchAll();
    $accommodations = $pdo->query("SELECT * FROM Accommodation ORDER BY Name ASC")->fetchAll();
    $flights = $pdo->query("SELECT * FROM Flight ORDER BY Airline ASC, FlightNum ASC")->fetchAll();
    $attractions = $pdo->query("SELECT * FROM Attraction ORDER BY Name ASC")->fetchAll();
    $restaurants = $pdo->query("SELECT * FROM Restaurant ORDER BY Name ASC")->fetchAll();
} catch (\PDOException $e) {
    $error_message = "Database fetch error: " . $e->getMessage();
}
?>

<div class="max-w-4xl mx-auto bg-surface rounded-card shadow-sm border border-muted/20 p-6 md:p-8">
    <div class="mb-6 flex justify-between items-center border-b border-outline-variant pb-4">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-text-main">Create New Travel Package</h1>
            <p class="text-xs text-secondary mt-1">Design an experience, schedule group trip dates, and link all travel assets.</p>
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

    <form method="POST" action="create_package.php" class="flex flex-col gap-8">
        
        <!-- SECTION A: Base Details -->
        <div class="flex flex-col gap-5 bg-background-light p-5 rounded-lg border border-outline-variant">
            <h3 class="text-lg font-heading font-semibold text-text-main flex items-center gap-2 border-b border-outline-variant pb-2">
                <span class="material-symbols-outlined text-primary">description</span> 1. Basic Package Information
            </h3>
            
            <div>
                <label class="block text-sm font-medium text-text-main mb-1" for="title">Package Title <span class="text-primary">*</span></label>
                <input type="text" id="title" name="title" required class="input-field" placeholder="e.g. Romantic Paris Getaway">
            </div>

            <div>
                <label class="block text-sm font-medium text-text-main mb-1" for="description">Detailed Description</label>
                <textarea id="description" name="description" rows="4" class="w-full p-4 rounded-input border border-outline-variant bg-surface text-on-surface placeholder:text-muted focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition-colors" placeholder="Provide a detailed daily itinerary or general description..."></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1" for="basePrice">Base Price ($) <span class="text-primary">*</span></label>
                    <input type="number" id="basePrice" name="basePrice" required min="1" step="0.01" class="input-field" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1" for="durationDays">Duration (Days) <span class="text-primary">*</span></label>
                    <input type="number" id="durationDays" name="durationDays" required min="1" class="input-field" placeholder="Duration in days">
                </div>
                <div>
                    <label class="block text-sm font-medium text-text-main mb-1" for="maxCapacity">Max Capacity <span class="text-primary">*</span></label>
                    <input type="number" id="maxCapacity" name="maxCapacity" required min="1" class="input-field" placeholder="Maximum travellers allowed">
                </div>
            </div>
        </div>

        <!-- SECTION B: Customize Itinerary Component Manager -->
        <div class="flex flex-col gap-6">
            <h3 class="text-lg font-heading font-semibold text-text-main flex items-center gap-2 border-b border-outline-variant pb-2">
                <span class="material-symbols-outlined text-primary">link</span> 2. Link Travel Assets & Destinations
            </h3>
            <p class="text-xs text-secondary -mt-3">Link destinations, accommodations, flights, attractions, and restaurants. Select or quick-create them below.</p>

            <!-- B1. Destinations Accordion -->
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
                            <label class="flex items-start gap-2.5 p-2 border border-outline-variant rounded hover:bg-background-light cursor-pointer select-none">
                                <input type="checkbox" name="destinations[]" value="<?php echo $dest['DestID']; ?>" class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
                                <span class="text-xs font-medium text-text-main leading-tight"><?php echo htmlspecialchars($dest['Name']) . ', ' . htmlspecialchars($dest['Country']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- B2. Accommodations Accordion -->
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
                            <label class="flex items-start gap-2.5 p-2 border border-outline-variant rounded hover:bg-background-light cursor-pointer select-none">
                                <input type="checkbox" name="accommodations[]" value="<?php echo $accomm['AccommID']; ?>" class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
                                <span class="text-xs font-medium text-text-main leading-tight">
                                    <?php echo htmlspecialchars($accomm['Name']); ?>
                                    <span class="block text-[10px] text-muted font-normal uppercase mt-0.5"><?php echo htmlspecialchars($accomm['Type']); ?> • $<?php echo number_format($accomm['PricePerNight'], 0); ?>/night</span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- B3. Flights Accordion -->
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
                            <label class="flex items-start gap-2.5 p-2 border border-outline-variant rounded hover:bg-background-light cursor-pointer select-none">
                                <input type="checkbox" name="flights[]" value="<?php echo $flight['FlightID']; ?>" class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
                                <span class="text-xs font-medium text-text-main leading-tight">
                                    <?php echo htmlspecialchars($flight['Airline']) . ' #' . htmlspecialchars($flight['FlightNum']); ?>
                                    <span class="block text-[10px] text-muted font-normal uppercase mt-0.5"><?php echo $flight['DepAirport_Code'] . ' → ' . $flight['ArrAirport_Code']; ?> • $<?php echo number_format($flight['Cost'], 0); ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- B4. Attractions Accordion -->
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
                            <label class="flex items-start gap-2.5 p-2 border border-outline-variant rounded hover:bg-background-light cursor-pointer select-none">
                                <input type="checkbox" name="attractions[]" value="<?php echo $attr['AttractionID']; ?>" class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
                                <span class="text-xs font-medium text-text-main leading-tight">
                                    <?php echo htmlspecialchars($attr['Name']); ?>
                                    <span class="block text-[10px] text-muted font-normal uppercase mt-0.5"><?php echo htmlspecialchars($attr['Category']); ?> • Entry: $<?php echo number_format($attr['EntryFee'], 0); ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- B5. Restaurants Accordion -->
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
                            <label class="flex items-start gap-2.5 p-2 border border-outline-variant rounded hover:bg-background-light cursor-pointer select-none">
                                <input type="checkbox" name="restaurants[]" value="<?php echo $rest['RestaurantID']; ?>" class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
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
            <button type="submit" class="btn w-full md:w-auto px-12">Publish Travel Package</button>
        </div>
    </form>
</div>

<!-- ================= QUICK-CREATE MODALS (AJAX) ================= -->

<!-- Destination Modal -->
<div id="modal-dest" class="fixed inset-0 z-50 bg-black/45 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-surface rounded-card shadow-2xl border border-outline-variant w-full max-w-[420px] p-6 animate-in fade-in zoom-in duration-150">
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
    <div class="bg-surface rounded-card shadow-2xl border border-outline-variant w-full max-w-[420px] p-6 animate-in fade-in zoom-in duration-150">
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
    <div class="bg-surface rounded-card shadow-2xl border border-outline-variant w-full max-w-[460px] p-6 animate-in fade-in zoom-in duration-150">
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
    <div class="bg-surface rounded-card shadow-2xl border border-outline-variant w-full max-w-[420px] p-6 animate-in fade-in zoom-in duration-150">
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
    <div class="bg-surface rounded-card shadow-2xl border border-outline-variant w-full max-w-[420px] p-6 animate-in fade-in zoom-in duration-150">
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
                
                // Append new option to the appropriate accordion checklist and select it
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

                // If empty placeholder is shown, clear it
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
                
                // Close modal
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

<?php require_once 'includes/footer.php'; ?>