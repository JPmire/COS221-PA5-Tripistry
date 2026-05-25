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
    $imageUrl = trim($_POST['imageUrl'] ?? '');

    if (empty($title) || $basePrice <= 0 || $durationDays <= 0 || $maxCapacity <= 0) {
        $error_message = "Please fill in all required fields accurately.";
    } else {
        try {
            $pdo->beginTransaction();

            if (empty($imageUrl)) {
                require_once 'includes/image_service.php';
                $imageUrl = ImageService::getPackageImage($title, $description);
            }

            // 1. Insert into base TravelPackage
            $stmt = $pdo->prepare("INSERT INTO TravelPackage (Title, Description, BasePrice, DurationDays, MaxCapacity, AgencyID, ImageURL) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $basePrice, $durationDays, $maxCapacity, $agency_id, $imageUrl]);
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
                    <label class="block text-sm font-medium text-text-main mb-1" for="basePrice">Base Price (R) <span class="text-primary">*</span></label>
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

            <!-- Visual Cover Image Selector Section -->
            <div class="border-t border-outline-variant pt-4 mt-2">
                <label class="block text-sm font-medium text-text-main mb-2">Package Cover Image</label>
                <div class="flex flex-col md:flex-row gap-4 items-start">
                    <!-- Dynamic Live Preview Card -->
                    <div class="w-full md:w-64 h-36 rounded-lg border border-outline-variant bg-surface overflow-hidden relative group flex items-center justify-center shadow-sm">
                        <img id="cover-preview" src="https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=1200&q=85" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="Package Cover Preview">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                            <span class="text-white text-xs font-semibold uppercase tracking-wider">Preview Card</span>
                        </div>
                    </div>
                    <!-- Controls -->
                    <div class="flex-grow w-full flex flex-col gap-2">
                        <div class="flex gap-2">
                            <input type="url" id="imageUrl" name="imageUrl" class="input-field flex-grow" placeholder="Paste premium custom image URL or browse stock...">
                            <button type="button" class="px-5 py-2 border border-primary text-primary hover:bg-primary/5 rounded-md text-sm font-semibold transition-colors flex items-center gap-1.5 shrink-0" onclick="openStockGallery()">
                                <span class="material-symbols-outlined text-sm">photo_library</span>
                                Browse Stock
                            </button>
                        </div>
                        <p class="text-xs text-secondary leading-normal">
                            A premium visual cover brings your package to life. If left blank, our automated matching engine will auto-assign a stunning photo based on your package title!
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION B: Customize Itinerary Component Manager -->
        <div class="flex flex-col gap-6">
            <h3 class="text-lg font-heading font-semibold text-text-main flex items-center gap-2 border-b border-outline-variant pb-2">
                <span class="material-symbols-outlined text-primary">link</span> 2. Link Travel Assets & Destinations
            </h3>
            <p class="text-xs text-secondary -mt-3">Link destinations, accommodations, flights, attractions, and restaurants. Select, discover live, or quick-create them below.</p>

            <!-- B0. Live Travel API Discovery Hub -->
            <div class="border border-primary/30 rounded-lg overflow-hidden glass-card shadow-md">
                <div class="px-5 py-3 border-b border-primary/20 bg-primary/5 flex justify-between items-center cursor-pointer select-none" onclick="toggleAccordion('acc-live-api')">
                    <span class="font-bold text-primary flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary pulse-animation">travel_explore</span>
                        Live Travel API Discovery & Import Hub
                    </span>
                    <span id="acc-live-api-icon" class="material-symbols-outlined text-primary">expand_more</span>
                </div>
                <div id="acc-live-api" class="p-5 flex flex-col gap-4 hidden">
                    <p class="text-xs text-secondary">
                        Search live global datasets (via OpenTripMap & simulated aviation lines) to immediately seed your local relational cache, auto-plot coordinates, and import components directly into this package!
                    </p>
                    
                    <div class="flex gap-3">
                        <div class="flex-grow">
                            <input type="text" id="live-api-search-city" class="input-field h-[40px] text-sm" placeholder="Search City (e.g. Cape Town, Rome, London, Paris, Tokyo)">
                        </div>
                        <button type="button" class="btn h-[40px] px-6 text-xs font-bold uppercase tracking-wider" onclick="searchLiveAPI()">
                            Discover Places
                        </button>
                    </div>

                    <!-- Search Loading / Status -->
                    <div id="live-api-status" class="hidden text-xs text-primary font-semibold flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px] animate-spin">sync</span>
                        Fetching live coordinates and nearby assets...
                    </div>

                    <!-- API Discoveries Grid Display -->
                    <div id="live-api-results" class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[350px] overflow-y-auto hidden">
                        <!-- Discovered Items Rendered Dynamically -->
                    </div>
                </div>
            </div>

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
                                    <span class="block text-[10px] text-muted font-normal uppercase mt-0.5"><?php echo htmlspecialchars($accomm['Type']); ?> • <?php echo formatCurrency($accomm['PricePerNight']); ?>/night</span>
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
                                    <span class="block text-[10px] text-muted font-normal uppercase mt-0.5"><?php echo $flight['DepAirport_Code'] . ' → ' . $flight['ArrAirport_Code']; ?> • <?php echo formatCurrency($flight['Cost']); ?></span>
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
                                    <span class="block text-[10px] text-muted font-normal uppercase mt-0.5"><?php echo htmlspecialchars($attr['Category']); ?> • Entry: <?php echo formatCurrency($attr['EntryFee']); ?></span>
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
                                    <span class="block text-[10px] text-muted font-normal uppercase mt-0.5"><?php echo htmlspecialchars($rest['CuisineType']); ?> • Avg: <?php echo formatCurrency($rest['AverageCost']); ?></span>
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
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Price per Night (R) *</label>
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
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Cost (R) *</label>
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
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Entry Fee (R)</label>
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
                <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-1">Average Cost per Meal (R)</label>
                <input type="number" id="m-rest-cost" min="0" step="0.01" class="input-field h-[40px] text-sm" placeholder="0.00">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="px-4 py-2 border border-outline-variant rounded-md text-sm text-text-main hover:bg-background-light font-medium" onclick="closeQuickModal('restaurant')">Cancel</button>
                <button type="submit" class="bg-primary text-white px-5 py-2 rounded-md text-sm font-semibold hover:opacity-95 transition-opacity">Add & Select</button>
            </div>
        </form>
    </div>
</div>

<!-- Stock Cover Gallery Modal -->
<div id="modal-stock-gallery" class="fixed inset-0 z-50 bg-black/45 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-surface rounded-card shadow-2xl border border-outline-variant w-full max-w-[720px] p-6 animate-in fade-in zoom-in duration-150 flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-heading font-semibold text-text-main flex items-center gap-1.5">
                <span class="material-symbols-outlined text-primary">photo_library</span> Browse Stock Cover Gallery
            </h3>
            <button type="button" class="text-secondary hover:text-primary transition-colors" onclick="closeStockGallery()">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <!-- Search and Filter Header -->
        <div class="flex flex-col gap-3 mb-4">
            <div class="flex gap-2">
                <input type="text" id="stock-search-query" class="input-field flex-grow" placeholder="Search stock photos (e.g. Paris, Beach, Luxury, Food)..." onkeydown="if(event.key === 'Enter') searchStockImages()">
                <button type="button" class="bg-primary text-white px-5 py-2 rounded-md text-sm font-semibold hover:opacity-95 transition-opacity" onclick="searchStockImages()">Search</button>
            </div>
            <!-- Category Tabs -->
            <div class="flex gap-1.5 overflow-x-auto pb-1" id="stock-category-tabs">
                <button type="button" class="tab-btn active px-3 py-1 rounded bg-primary/10 text-primary text-xs font-semibold uppercase tracking-wider transition-colors" onclick="filterStockCategory('All', this)">All</button>
                <button type="button" class="tab-btn px-3 py-1 rounded hover:bg-background-light text-secondary text-xs font-semibold uppercase tracking-wider transition-colors" onclick="filterStockCategory('Destinations', this)">Destinations</button>
                <button type="button" class="tab-btn px-3 py-1 rounded hover:bg-background-light text-secondary text-xs font-semibold uppercase tracking-wider transition-colors" onclick="filterStockCategory('Stays', this)">Stays</button>
                <button type="button" class="tab-btn px-3 py-1 rounded hover:bg-background-light text-secondary text-xs font-semibold uppercase tracking-wider transition-colors" onclick="filterStockCategory('Sights', this)">Sights</button>
                <button type="button" class="tab-btn px-3 py-1 rounded hover:bg-background-light text-secondary text-xs font-semibold uppercase tracking-wider transition-colors" onclick="filterStockCategory('Dining', this)">Dining</button>
                <button type="button" class="tab-btn px-3 py-1 rounded hover:bg-background-light text-secondary text-xs font-semibold uppercase tracking-wider transition-colors" onclick="filterStockCategory('Adventure', this)">Adventure</button>
            </div>
        </div>

        <!-- Stock Grid -->
        <div class="flex-grow overflow-y-auto min-h-[300px] border border-outline-variant rounded-lg p-3 bg-background-light">
            <div id="stock-grid" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <!-- Dynamic grid items go here -->
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-outline-variant mt-4">
            <button type="button" class="px-4 py-2 border border-outline-variant rounded-md text-sm text-text-main hover:bg-background-light font-medium" onclick="closeStockGallery()">Cancel</button>
        </div>
    </div>
</div>

<script>
    let currentStockCategory = 'All';
    let allStockImages = [];

    function openStockGallery() {
        const modal = document.getElementById('modal-stock-gallery');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        searchStockImages();
    }

    function closeStockGallery() {
        const modal = document.getElementById('modal-stock-gallery');
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }

    async function searchStockImages() {
        const query = document.getElementById('stock-search-query').value.trim();
        const grid = document.getElementById('stock-grid');
        grid.innerHTML = `
            <div class="col-span-full flex flex-col items-center justify-center py-12 text-muted">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mb-3"></div>
                <span>Fetching beautiful stock images...</span>
            </div>
        `;

        try {
            const response = await fetch(`api_travel.php?action=search_stock_images&query=${encodeURIComponent(query)}`);
            const result = await response.json();
            
            if (result.status === 'success') {
                allStockImages = result.data;
                renderStockGrid();
            } else {
                grid.innerHTML = `<div class="col-span-full text-center py-12 text-primary font-medium">Error loading images: ${result.message}</div>`;
            }
        } catch (err) {
            grid.innerHTML = `<div class="col-span-full text-center py-12 text-primary font-medium">Failed to search images: ${err.message}</div>`;
        }
    }

    function renderStockGrid() {
        const grid = document.getElementById('stock-grid');
        grid.innerHTML = '';
        
        const filtered = currentStockCategory === 'All' 
            ? allStockImages 
            : allStockImages.filter(item => item.category.toLowerCase() === currentStockCategory.toLowerCase());

        if (filtered.length === 0) {
            grid.innerHTML = `<div class="col-span-full text-center py-12 text-muted">No images found for this category. Try searching instead.</div>`;
            return;
        }

        filtered.forEach(img => {
            const div = document.createElement('div');
            div.className = 'group relative aspect-[4/3] rounded-lg overflow-hidden border border-outline-variant bg-surface cursor-pointer hover:border-primary/85 transition-all shadow-sm transform hover:-translate-y-0.5 duration-200';
            div.onclick = () => selectStockImage(img.url);
            div.innerHTML = `
                <img src="${img.url}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="${img.title}">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent flex flex-col justify-end p-2.5">
                    <span class="text-[9px] text-primary font-bold uppercase tracking-wider leading-none mb-1">${img.category}</span>
                    <span class="text-xs font-semibold text-white truncate leading-tight">${img.title}</span>
                </div>
            `;
            grid.appendChild(div);
        });
    }

    function filterStockCategory(category, btn) {
        currentStockCategory = category;
        
        const tabs = document.querySelectorAll('#stock-category-tabs button');
        tabs.forEach(t => {
            t.className = 'tab-btn px-3 py-1 rounded text-xs font-semibold uppercase tracking-wider transition-colors';
            if (t === btn) {
                t.classList.add('bg-primary/10', 'text-primary');
            } else {
                t.classList.add('hover:bg-background-light', 'text-secondary');
            }
        });

        renderStockGrid();
    }

    function selectStockImage(url) {
        document.getElementById('imageUrl').value = url;
        document.getElementById('cover-preview').src = url;
        closeStockGallery();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const input = document.getElementById('imageUrl');
        if (input) {
            input.addEventListener('input', (e) => {
                const val = e.target.value.trim();
                document.getElementById('cover-preview').src = val || 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=1200&q=85';
            });
        }
    });

    function toggleAccordion(id) {
        const container = document.getElementById(id);
        const icon = document.getElementById(id + '-icon');
        if (container.classList.contains('hidden')) {
            container.classList.remove('hidden');
            if (icon) icon.textContent = 'expand_less';
        } else {
            container.classList.add('hidden');
            if (icon) icon.textContent = 'expand_more';
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

    // Live API search and discover scripts
    async function searchLiveAPI() {
        const cityInput = document.getElementById('live-api-search-city');
        const city = cityInput.value.trim();
        if (!city) {
            alert('Please type in a city name to discover live travel assets.');
            return;
        }

        const statusDiv = document.getElementById('live-api-status');
        const resultsDiv = document.getElementById('live-api-results');
        
        statusDiv.classList.remove('hidden');
        resultsDiv.classList.add('hidden');
        resultsDiv.innerHTML = '';

        try {
            const res = await fetch(`api_travel.php?action=search_city&city=${encodeURIComponent(city)}`);
            const data = await res.json();
            
            statusDiv.classList.add('hidden');
            if (data.status === 'success') {
                resultsDiv.classList.remove('hidden');
                
                const acc = data.data.accommodations || [];
                const att = data.data.attractions || [];
                const rst = data.data.restaurants || [];
                
                let html = `
                    <div class="col-span-full bg-primary/5 p-3.5 rounded-lg border border-primary/20 flex justify-between items-center mb-2">
                        <div>
                            <p class="text-[10px] uppercase font-bold text-primary tracking-wider mb-0.5">Target Location Identified</p>
                            <h4 class="font-heading font-semibold text-text-main text-sm">${data.city}, ${data.country}</h4>
                        </div>
                        <button type="button" class="px-3.5 py-1.5 bg-primary text-white text-[11px] font-bold uppercase rounded shadow-sm hover:opacity-90 transition-opacity" onclick="importLiveDestination('${data.city.replace(/'/g, "\\'")}', '${data.country.replace(/'/g, "\\'")}', ${data.lat}, ${data.lon})">
                            Import Destination
                        </button>
                    </div>
                `;

                // Lodgings column
                html += `
                    <div class="flex flex-col gap-3 p-3 bg-background-light rounded border border-outline-variant/60 max-h-[300px] overflow-y-auto">
                        <h4 class="text-xs font-bold text-primary flex items-center gap-1 border-b border-outline-variant/40 pb-1.5 uppercase">
                            <span class="material-symbols-outlined text-[16px]">hotel</span> Discovered Stays (${acc.length})
                        </h4>
                `;
                if (acc.length === 0) {
                    html += `<p class="text-[11px] text-muted italic">No stays found nearby.</p>`;
                } else {
                    acc.forEach(item => {
                        const price = item.price || 1200;
                        const stars = item.stars || 4;
                        const type = item.type || 'Hotel';
                        html += `
                            <div class="p-2.5 rounded bg-surface border border-outline-variant/45 flex flex-col gap-1.5 text-xs bg-white shadow-sm">
                                <div class="flex justify-between items-start gap-1">
                                    <span class="font-bold text-text-main leading-tight">${item.name}</span>
                                    <span class="text-[9px] font-bold text-primary font-mono whitespace-nowrap">R ${price.toFixed(0)}/n</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] text-muted">${stars}★ • ${type}</span>
                                    <button type="button" class="px-2.5 py-1 bg-primary/10 hover:bg-primary text-primary hover:text-white text-[10px] font-bold uppercase rounded border border-primary/20 transition-all" onclick="importLiveAsset(this, 'accommodation', { name: '${item.name.replace(/'/g, "\\'")}', price: ${price}, rating: ${stars}, type: '${type}', lat: ${item.lat}, lon: ${item.lon} })">
                                        Import
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                }
                html += `</div>`;

                // Attractions column
                html += `
                    <div class="flex flex-col gap-3 p-3 bg-background-light rounded border border-outline-variant/60 max-h-[300px] overflow-y-auto">
                        <h4 class="text-xs font-bold text-primary flex items-center gap-1 border-b border-outline-variant/40 pb-1.5 uppercase">
                            <span class="material-symbols-outlined text-[16px]">explore</span> Discovered Sights (${att.length})
                        </h4>
                `;
                if (att.length === 0) {
                    html += `<p class="text-[11px] text-muted italic">No attractions found nearby.</p>`;
                } else {
                    att.forEach(item => {
                        const fee = item.fee || 0;
                        const category = item.category || 'Sightseeing';
                        html += `
                            <div class="p-2.5 rounded bg-surface border border-outline-variant/45 flex flex-col gap-1.5 text-xs bg-white shadow-sm">
                                <div class="flex justify-between items-start gap-1">
                                    <span class="font-bold text-text-main leading-tight">${item.name}</span>
                                    <span class="text-[9px] font-bold text-primary font-mono whitespace-nowrap">${fee > 0 ? 'R ' + fee.toFixed(0) : 'FREE'}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] text-muted">${category}</span>
                                    <button type="button" class="px-2.5 py-1 bg-primary/10 hover:bg-primary text-primary hover:text-white text-[10px] font-bold uppercase rounded border border-primary/20 transition-all" onclick="importLiveAsset(this, 'attraction', { name: '${item.name.replace(/'/g, "\\'")}', fee: ${fee}, category: '${category}', lat: ${item.lat}, lon: ${item.lon} })">
                                        Import
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                }
                html += `</div>`;

                resultsDiv.innerHTML = html;
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            statusDiv.classList.add('hidden');
            alert('Live search failed: ' + err.message);
        }
    }

    async function importLiveDestination(name, country, lat, lon) {
        try {
            const res = await fetch('api_travel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'import_asset', asset_type: 'destination', name, country, lat, lon })
            });
            const data = await res.json();
            if (data.status === 'success') {
                const insertedId = data.id;
                const listContainer = document.getElementById('acc-dest');
                
                if (listContainer.querySelector('.text-muted')) {
                    listContainer.innerHTML = '';
                }

                const newCardHTML = `
                <label class="flex items-start gap-2.5 p-2 border border-primary/50 bg-primary/5 rounded hover:bg-background-light cursor-pointer select-none">
                    <input type="checkbox" name="destinations[]" value="${insertedId}" checked class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
                    <span class="text-xs font-medium text-text-main leading-tight">${name}, ${country}</span>
                </label>`;
                
                listContainer.insertAdjacentHTML('afterbegin', newCardHTML);
                alert(`${name} imported successfully and linked!`);
            } else {
                alert('Import failed: ' + data.message);
            }
        } catch (err) {
            alert('Import failed: ' + err.message);
        }
    }

    async function importLiveAsset(btn, type, payload) {
        btn.disabled = true;
        btn.textContent = 'Importing...';
        
        try {
            const res = await fetch('api_travel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'import_asset', asset_type: type, ...payload })
            });
            const data = await res.json();
            if (data.status === 'success') {
                btn.textContent = 'LINKED';
                btn.disabled = true;
                btn.className = 'px-2.5 py-1 bg-green-500 text-white text-[10px] font-bold uppercase rounded border border-green-600 transition-all';
                
                const insertedId = data.id;
                const lowercaseType = type.toLowerCase();
                const listContainer = document.getElementById('acc-' + (lowercaseType === 'accommodation' ? 'accomm' : lowercaseType === 'attraction' ? 'attractions' : 'restaurants'));
                
                if (listContainer.querySelector('.text-muted')) {
                    listContainer.innerHTML = '';
                }

                let labelText = payload.name;
                let detailsText = '';
                let fieldName = '';
                if (lowercaseType === 'accommodation') {
                    detailsText = payload.type + ' • R ' + payload.price.toFixed(2) + '/night';
                    fieldName = 'accommodations[]';
                } else {
                    detailsText = payload.category + ' • Entry: ' + (payload.fee > 0 ? 'R ' + payload.fee.toFixed(2) : 'FREE');
                    fieldName = 'attractions[]';
                }

                const newCardHTML = `
                <label class="flex items-start gap-2.5 p-2 border border-primary/50 bg-primary/5 rounded hover:bg-background-light cursor-pointer select-none">
                    <input type="checkbox" name="${fieldName}" value="${insertedId}" checked class="rounded border-outline-variant text-primary focus:ring-primary mt-0.5">
                    <span class="text-xs font-medium text-text-main leading-tight">
                        ${labelText}
                        <span class="block text-[10px] text-muted font-normal uppercase mt-0.5">${detailsText}</span>
                    </span>
                </label>`;
                
                listContainer.insertAdjacentHTML('afterbegin', newCardHTML);
            } else {
                btn.disabled = false;
                btn.textContent = 'Import';
                alert('Import failed: ' + data.message);
            }
        } catch (err) {
            btn.disabled = false;
            btn.textContent = 'Import';
            alert('Import failed: ' + err.message);
        }
    }

    // AJAX Submission handler for quick adding assets manually via modals
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
                    detailsText = payload.type + ' • R ' + payload.pricePerNight.toFixed(2) + '/night';
                } else if (componentName === 'Flight') {
                    labelText = payload.airline + ' #' + payload.flightNum;
                    detailsText = payload.depAirportCode + ' → ' + payload.arrAirportCode + ' • R ' + payload.cost.toFixed(2);
                } else if (componentName === 'Attraction') {
                    labelText = payload.name;
                    detailsText = payload.category + ' • Entry: R ' + payload.entryFee.toFixed(2);
                } else if (componentName === 'Restaurant') {
                    labelText = payload.name;
                    detailsText = payload.cuisineType + ' • Avg: R ' + payload.averageCost.toFixed(2);
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

<?php require_once 'includes/footer.php'; ?>