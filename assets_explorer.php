<?php
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Traveller') {
    header("Location: login.php");
    exit;
}

$page_title = 'Asset Explorer';
require_once 'includes/header.php';


$active_tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'flight';
if (!in_array($active_tab, ['flight', 'accommodation', 'attraction', 'restaurant'])) {
    $active_tab = 'flight';
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$error = '';
$results = [];

try {
    if ($active_tab === 'flight') {
        $query = "
            SELECT f.*, 
                   (SELECT COUNT(*) FROM Package_Flight pf WHERE pf.FlightID = f.FlightID) AS PackageCount
            FROM Flight f
            WHERE 1=1
        ";
        $params = [];
        if ($search !== '') {
            $query .= " AND (f.Airline LIKE ? OR f.FlightNum LIKE ? OR f.DepAirport_Code LIKE ? OR f.ArrAirport_Code LIKE ?)";
            $search_param = "%$search%";
            $params = [$search_param, $search_param, $search_param, $search_param];
        }
        $query .= " ORDER BY f.DepTime ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
    } elseif ($active_tab === 'accommodation') {
        $query = "
            SELECT ac.*, 
                   (SELECT COUNT(*) FROM Package_Accommodation pa WHERE pa.AccommID = ac.AccommID) AS PackageCount
            FROM Accommodation ac
            WHERE 1=1
        ";
        $params = [];
        if ($search !== '') {
            $query .= " AND (ac.Name LIKE ? OR ac.Type LIKE ? OR ac.Address_City LIKE ?)";
            $search_param = "%$search%";
            $params = [$search_param, $search_param, $search_param];
        }
        $query .= " ORDER BY ac.StarRating DESC, ac.Name ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
    } elseif ($active_tab === 'attraction') {
        $query = "
            SELECT attr.*, 
                   (SELECT COUNT(*) FROM Package_Attraction pa WHERE pa.AttractionID = attr.AttractionID) AS PackageCount
            FROM Attraction attr
            WHERE 1=1
        ";
        $params = [];
        if ($search !== '') {
            $query .= " AND (attr.Name LIKE ? OR attr.Category LIKE ?)";
            $search_param = "%$search%";
            $params = [$search_param, $search_param];
        }
        $query .= " ORDER BY attr.EntryFee ASC, attr.Name ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
    } elseif ($active_tab === 'restaurant') {
        $query = "
            SELECT r.*, 
                   (SELECT COUNT(*) FROM Package_Restaurant pr WHERE pr.RestaurantID = r.RestaurantID) AS PackageCount
            FROM Restaurant r
            WHERE 1=1
        ";
        $params = [];
        if ($search !== '') {
            $query .= " AND (r.Name LIKE ? OR r.CuisineType LIKE ?)";
            $search_param = "%$search%";
            $params = [$search_param, $search_param];
        }
        $query .= " ORDER BY r.AverageCost ASC, r.Name ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
    }
} catch (\PDOException $e) {
    $error = "Error executing search: " . $e->getMessage();
}

// Function to fetch travel packages linking to an asset
function getPackagesForAsset($pdo, $type, $asset_id) {
    try {
        if ($type === 'flight') {
            $stmt = $pdo->prepare("
                SELECT tp.PackageID, tp.Title, tp.BasePrice, ta.AgencyName 
                FROM TravelPackage tp
                JOIN Package_Flight pf ON tp.PackageID = pf.PackageID
                JOIN TravelAgency ta ON tp.AgencyID = ta.UserID
                WHERE pf.FlightID = ?
            ");
        } elseif ($type === 'accommodation') {
            $stmt = $pdo->prepare("
                SELECT tp.PackageID, tp.Title, tp.BasePrice, ta.AgencyName 
                FROM TravelPackage tp
                JOIN Package_Accommodation pa ON tp.PackageID = pa.PackageID
                JOIN TravelAgency ta ON tp.AgencyID = ta.UserID
                WHERE pa.AccommID = ?
            ");
        } elseif ($type === 'attraction') {
            $stmt = $pdo->prepare("
                SELECT tp.PackageID, tp.Title, tp.BasePrice, ta.AgencyName 
                FROM TravelPackage tp
                JOIN Package_Attraction pa ON tp.PackageID = pa.PackageID
                JOIN TravelAgency ta ON tp.AgencyID = ta.UserID
                WHERE pa.AttractionID = ?
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT tp.PackageID, tp.Title, tp.BasePrice, ta.AgencyName 
                FROM TravelPackage tp
                JOIN Package_Restaurant pr ON tp.PackageID = pr.PackageID
                JOIN TravelAgency ta ON tp.AgencyID = ta.UserID
                WHERE pr.RestaurantID = ?
            ");
        }
        $stmt->execute([$asset_id]);
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        return [];
    }
}
?>

<div class="max-w-6xl mx-auto flex flex-col gap-6">

    <!-- Header Section -->
    <div class="flex flex-col gap-1.5">
        <h1 class="text-3xl font-heading font-semibold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[32px]">travel_explore</span>
            Multi-Relational Asset Explorer
        </h1>
        <p class="text-muted text-sm">
            Browse through individual flights, lodgings, sightseeing landmarks, and dining culinary spots. Clicking an asset dynamically reveals all the curated travel packages that offer it!
        </p>
    </div>

    <!-- Error Alert -->
    <?php if ($error): ?>
        <div class="p-4 bg-red-50 border border-red-200 text-primary rounded-xl flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px] text-red-600">error</span>
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Segmented Tab Controls & Search Bar -->
    <div class="bg-surface rounded-xl border border-outline-variant p-4 shadow-sm bg-white flex flex-col gap-4">
        
        <!-- Tabs Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-background-light p-1.5 rounded-xl border border-outline-variant/30">
            <a href="?tab=flight&search=<?php echo urlencode($search); ?>" class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-bold transition-all <?php echo $active_tab === 'flight' ? 'bg-primary text-white shadow-sm' : 'text-secondary hover:text-primary hover:bg-surface-container-low'; ?>">
                <span class="material-symbols-outlined text-[18px]">flight</span>
                Flights
            </a>
            <a href="?tab=accommodation&search=<?php echo urlencode($search); ?>" class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-bold transition-all <?php echo $active_tab === 'accommodation' ? 'bg-primary text-white shadow-sm' : 'text-secondary hover:text-primary hover:bg-surface-container-low'; ?>">
                <span class="material-symbols-outlined text-[18px]">hotel</span>
                Lodging
            </a>
            <a href="?tab=attraction&search=<?php echo urlencode($search); ?>" class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-bold transition-all <?php echo $active_tab === 'attraction' ? 'bg-primary text-white shadow-sm' : 'text-secondary hover:text-primary hover:bg-surface-container-low'; ?>">
                <span class="material-symbols-outlined text-[18px]">explore</span>
                Landmarks
            </a>
            <a href="?tab=restaurant&search=<?php echo urlencode($search); ?>" class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-bold transition-all <?php echo $active_tab === 'restaurant' ? 'bg-primary text-white shadow-sm' : 'text-secondary hover:text-primary hover:bg-surface-container-low'; ?>">
                <span class="material-symbols-outlined text-[18px]">dining</span>
                Culinary Spots
            </a>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="assets_explorer.php" class="flex gap-3">
            <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
            <div class="relative flex-grow">
                <span class="material-symbols-outlined text-muted absolute left-3.5 top-1/2 -translate-y-1/2 text-[18px]">search</span>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search assets by name, airport codes, cuisine or categories..." class="input-field text-xs pl-10 h-[42px]">
            </div>
            <button type="submit" class="bg-primary text-on-primary font-bold text-xs h-[42px] px-6 rounded-lg flex items-center gap-1.5 transition-all hover:bg-primary-container">
                Filter Assets
            </button>
            <?php if ($search !== ''): ?>
                <a href="?tab=<?php echo $active_tab; ?>" class="bg-surface border border-outline-variant text-secondary hover:text-primary font-bold text-xs h-[42px] px-4 rounded-lg flex items-center justify-center transition-colors">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Assets Display List -->
    <?php if (empty($results)): ?>
        <div class="bg-surface border border-outline-variant rounded-2xl p-12 text-center text-muted italic">
            <span class="material-symbols-outlined text-5xl mb-2 text-outline-variant/60 block">wrong_location</span>
            No assets found matching your keyword filters under this category.
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($results as $item): ?>
                <?php 
                $asset_id = 0;
                $title_text = '';
                $subtitle_text = '';
                $stat_box = '';
                $extra_details = '';
                
                if ($active_tab === 'flight') {
                    $asset_id = (int)$item['FlightID'];
                    $title_text = htmlspecialchars($item['Airline']);
                    $subtitle_text = "Flight #" . htmlspecialchars($item['FlightNum']);
                    $stat_box = '<div class="text-right"><span class="text-[9px] text-muted block uppercase tracking-wider font-bold">Fare Cost</span><span class="font-heading font-bold text-primary text-sm font-mono">' . formatCurrency($item['Cost']) . '</span></div>';
                    $extra_details = '
                        <div class="flex justify-between items-center bg-background-light p-2.5 rounded-lg border border-outline-variant/20 text-[11px] font-mono text-secondary">
                            <div>
                                <p class="font-bold text-text-main">DEP: ' . htmlspecialchars($item['DepAirport_Code']) . '</p>
                                <p class="text-[9px] mt-0.5">' . date('M d, g:ia', strtotime($item['DepTime'])) . '</p>
                            </div>
                            <span class="material-symbols-outlined text-[16px] text-muted">arrow_right_alt</span>
                            <div class="text-right">
                                <p class="font-bold text-text-main">ARR: ' . htmlspecialchars($item['ArrAirport_Code']) . '</p>
                                <p class="text-[9px] mt-0.5">' . date('M d, g:ia', strtotime($item['ArrTime'])) . '</p>
                            </div>
                        </div>
                    ';
                } elseif ($active_tab === 'accommodation') {
                    $asset_id = (int)$item['AccommID'];
                    $title_text = htmlspecialchars($item['Name']);
                    $subtitle_text = htmlspecialchars($item['Type']) . " • " . htmlspecialchars($item['Address_City']);
                    
                    $stars = '<div class="flex text-amber-500 mt-0.5">';
                    for($i=1; $i<=5; $i++) {
                        $stars .= '<span class="material-symbols-outlined text-[12px] ' . ($i <= $item['StarRating'] ? 'fill-1' : '') . '" style="font-variation-settings: \'FILL\' ' . ($i <= $item['StarRating'] ? '1' : '0') . ';">star</span>';
                    }
                    $stars .= '</div>';
                    
                    $stat_box = '<div class="text-right"><span class="text-[9px] text-muted block uppercase tracking-wider font-bold">Rate</span><span class="font-heading font-bold text-primary text-sm font-mono">' . formatCurrency($item['PricePerNight']) . '/nt</span>' . $stars . '</div>';
                    $extra_details = '<p class="text-[11px] text-secondary flex items-start gap-1"><span class="material-symbols-outlined text-[14px] text-primary shrink-0">location_on</span>' . htmlspecialchars($item['Address_Street'] . ', ' . $item['Address_City'] . ' ' . $item['Address_Zip']) . '</p>';
                    
                } elseif ($active_tab === 'attraction') {
                    $asset_id = (int)$item['AttractionID'];
                    $title_text = htmlspecialchars($item['Name']);
                    $subtitle_text = htmlspecialchars($item['Category']);
                    $stat_box = '<div class="text-right"><span class="text-[9px] text-muted block uppercase tracking-wider font-bold">Entry Fee</span><span class="font-heading font-bold text-primary text-sm font-mono">' . ($item['EntryFee'] > 0 ? formatCurrency($item['EntryFee']) : 'FREE') . '</span></div>';
                    $extra_details = '<p class="text-[11px] text-secondary flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-primary">pin_drop</span> Coordinates: ' . number_format($item['Coordinates_Lat'], 4) . ', ' . number_format($item['Coordinates_Long'], 4) . '</p>';
                    
                } elseif ($active_tab === 'restaurant') {
                    $asset_id = (int)$item['RestaurantID'];
                    $title_text = htmlspecialchars($item['Name']);
                    $subtitle_text = htmlspecialchars($item['CuisineType']) . " Cuisine";
                    $stat_box = '<div class="text-right"><span class="text-[9px] text-muted block uppercase tracking-wider font-bold">Avg Dining Cost</span><span class="font-heading font-bold text-primary text-sm font-mono">' . formatCurrency($item['AverageCost']) . '</span></div>';
                    $extra_details = '<p class="text-[11px] text-secondary flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-primary">restaurant</span> Dinner / Lunch Cuisine Service</p>';
                }
                
                // Get linked packages
                $linked_packages = getPackagesForAsset($pdo, $active_tab, $asset_id);
                ?>

                <div class="bg-surface border border-outline-variant rounded-2xl p-5 shadow-sm flex flex-col justify-between gap-4 bg-white hover:shadow-md transition-all">
                    
                    <!-- Asset Header -->
                    <div class="flex justify-between items-start gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-surface-container-low text-primary border border-outline-variant/45 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined">
                                    <?php 
                                    echo $active_tab === 'flight' ? 'flight' : 
                                         ($active_tab === 'accommodation' ? 'hotel' : 
                                         ($active_tab === 'attraction' ? 'explore' : 'dining'));
                                    ?>
                                </span>
                            </div>
                            <div>
                                <h3 class="font-heading font-semibold text-text-main text-sm leading-snug"><?php echo $title_text; ?></h3>
                                <p class="text-[10px] font-bold text-muted uppercase tracking-wider mt-0.5"><?php echo $subtitle_text; ?></p>
                            </div>
                        </div>
                        <?php echo $stat_box; ?>
                    </div>

                    <!-- Extra Details section -->
                    <div class="border-t border-outline-variant/10 pt-2.5">
                        <?php echo $extra_details; ?>
                    </div>

                    <!-- Packages Relational List -->
                    <div class="border-t border-outline-variant/15 pt-3.5 flex flex-col gap-2">
                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-muted flex items-center gap-1">
                            <span class="material-symbols-outlined text-[13px]">sell</span>
                            Curated In Packages (<?php echo count($linked_packages); ?>)
                        </h4>
                        
                        <?php if (empty($linked_packages)): ?>
                            <p class="text-[10px] text-muted italic">This asset is not currently bound to any active travel packages.</p>
                        <?php else: ?>
                            <div class="flex flex-col gap-1.5">
                                <?php foreach ($linked_packages as $pkg): ?>
                                    <a href="package_detail.php?id=<?php echo $pkg['PackageID']; ?>" class="flex justify-between items-center p-2 rounded-lg bg-background-light border border-outline-variant/30 hover:border-primary/40 hover:bg-surface-container-lowest transition-all group text-xs">
                                        <div class="flex flex-col">
                                            <span class="font-semibold text-text-main group-hover:text-primary transition-colors"><?php echo htmlspecialchars($pkg['Title']); ?></span>
                                            <span class="text-[9px] text-muted">Offered by <?php echo htmlspecialchars($pkg['AgencyName']); ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-secondary font-mono">$<?php echo number_format($pkg['BasePrice'], 2); ?></span>
                                            <span class="material-symbols-outlined text-[16px] text-muted group-hover:text-primary group-hover:translate-x-0.5 transition-all">arrow_forward</span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
