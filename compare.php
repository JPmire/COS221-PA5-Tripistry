<?php
$page_title = 'Compare Packages';
require_once 'includes/db_connect.php';
require_once 'includes/image_service.php';
require_once 'includes/header.php';

// Retrieve package IDs from query parameters
$package_ids = isset($_GET['ids']) && is_array($_GET['ids']) ? array_map('intval', $_GET['ids']) : [];

// Limit to a maximum of 3 packages to compare
$package_ids = array_slice($package_ids, 0, 3);

$packages = [];

if (!empty($package_ids)) {
    try {
        // Fetch all packages with their basic information and agency names
        $in_clause = implode(',', array_fill(0, count($package_ids), '?'));
        
        $stmt = $pdo->prepare("SELECT p.*, a.AgencyName, a.AverageRating as AgencyRating 
                               FROM TravelPackage p 
                               JOIN TravelAgency a ON p.AgencyID = a.UserID 
                               WHERE p.PackageID IN ($in_clause)");
        $stmt->execute($package_ids);
        $fetched = $stmt->fetchAll();

        // Key the packages by PackageID to keep them in order of query parameter selection
        $packages_by_id = [];
        foreach ($fetched as $pkg) {
            $packages_by_id[$pkg['PackageID']] = $pkg;
        }

        // Fetch child M:N tables for each package in order
        foreach ($package_ids as $id) {
            if (!isset($packages_by_id[$id])) continue;
            $pkg = $packages_by_id[$id];

            // 1. Destinations
            $stmtDest = $pdo->prepare("SELECT d.* FROM Destination d 
                                       JOIN Package_Destination pd ON d.DestID = pd.DestID 
                                       WHERE pd.PackageID = ?");
            $stmtDest->execute([$id]);
            $pkg['destinations'] = $stmtDest->fetchAll();

            // 2. Accommodations
            $stmtAcc = $pdo->prepare("SELECT a.* FROM Accommodation a 
                                      JOIN Package_Accommodation pa ON a.AccommID = pa.AccommID 
                                      WHERE pa.PackageID = ?");
            $stmtAcc->execute([$id]);
            $pkg['accommodations'] = $stmtAcc->fetchAll();

            // 3. Flights
            $stmtFlight = $pdo->prepare("SELECT f.* FROM Flight f 
                                         JOIN Package_Flight pf ON f.FlightID = pf.FlightID 
                                         WHERE pf.PackageID = ?");
            $stmtFlight->execute([$id]);
            $pkg['flights'] = $stmtFlight->fetchAll();

            // 4. Attractions
            $stmtAtt = $pdo->prepare("SELECT a.* FROM Attraction a 
                                      JOIN Package_Attraction pa ON a.AttractionID = pa.AttractionID 
                                      WHERE pa.PackageID = ?");
            $stmtAtt->execute([$id]);
            $pkg['attractions'] = $stmtAtt->fetchAll();

            // 5. Restaurants
            $stmtRest = $pdo->prepare("SELECT r.* FROM Restaurant r 
                                       JOIN Package_Restaurant pr ON r.RestaurantID = pr.RestaurantID 
                                       WHERE pr.PackageID = ?");
            $stmtRest->execute([$id]);
            $pkg['restaurants'] = $stmtRest->fetchAll();

            $packages[] = $pkg;
        }

    } catch (\PDOException $e) {
        $error_message = "Failed to load comparison data: " . $e->getMessage();
    }
}
?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="packages.php" class="p-2 bg-surface border border-outline-variant/50 rounded-full hover:bg-surface-container-low transition-colors duration-150 inline-flex items-center justify-center">
                <span class="material-symbols-outlined text-text-main">arrow_back</span>
            </a>
            <div>
                <h1 class="text-3xl font-heading font-semibold">Package Comparison</h1>
                <p class="text-muted mt-1">Review features and details side-by-side to make your choice.</p>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="p-4 bg-error-container text-on-error-container rounded-lg border border-error/20 flex items-center gap-3">
            <span class="material-symbols-outlined text-error">error</span>
            <p class="font-medium"><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php elseif (empty($packages)): ?>
        <div class="bg-surface rounded-card shadow-sm border border-muted/20 p-12 text-center flex flex-col items-center justify-center gap-4">
            <span class="material-symbols-outlined text-muted text-5xl opacity-40">compare_arrows</span>
            <div>
                <h3 class="text-lg font-heading font-semibold text-text-main mb-1">No packages selected</h3>
                <p class="text-muted text-sm">Please select up to 3 packages from the explore screen to compare them.</p>
            </div>
            <a href="packages.php" class="btn h-[40px] px-6 text-sm text-white hover:text-white mt-2">Browse Packages</a>
        </div>
    <?php else: ?>
        <!-- Comparison Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-<?php echo count($packages) + 1; ?> gap-6 items-stretch">
            
            <!-- Attribute Titles Column (Visible only on lg and larger screens) -->
            <div class="hidden lg:flex flex-col gap-6 bg-surface-container-low/50 border border-outline-variant/35 rounded-card p-6 justify-between">
                <div>
                    <h3 class="text-lg font-heading font-bold text-text-main mb-2">Specifications</h3>
                    <p class="text-xs text-muted">Compare elements of your potential getaway.</p>
                </div>
                
                <div class="flex flex-col gap-8 flex-grow justify-around py-8 border-t border-b border-outline-variant/30 my-6">
                    <div class="font-semibold text-sm text-text-main">Pricing & Agency</div>
                    <div class="font-semibold text-sm text-text-main">Overview</div>
                    <div class="font-semibold text-sm text-text-main">Destinations</div>
                    <div class="font-semibold text-sm text-text-main">Accommodation</div>
                    <div class="font-semibold text-sm text-text-main">Flights Included</div>
                    <div class="font-semibold text-sm text-text-main">Attractions Visited</div>
                    <div class="font-semibold text-sm text-text-main">Cuisine & Restaurants</div>
                </div>

                <div class="h-12 flex items-center text-xs text-muted italic">
                    Ready to decide?
                </div>
            </div>

            <!-- Package Columns -->
            <?php foreach ($packages as $pkg): ?>
                <div class="bg-surface border border-outline-variant/50 rounded-card shadow-sm overflow-hidden flex flex-col justify-between hover:border-primary/30 transition-all duration-200 group">
                    
                    <?php 
                        $pkgCover = !empty($pkg['ImageURL']) ? $pkg['ImageURL'] : ImageService::getPackageImage($pkg['Title'], $pkg['Description']);
                    ?>
                    <!-- Full-bleed visual cover header -->
                    <div class="relative h-40 w-full overflow-hidden border-b border-outline-variant/20">
                        <img src="<?php echo htmlspecialchars($pkgCover); ?>" alt="<?php echo htmlspecialchars($pkg['Title']); ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/70 to-transparent"></div>
                        <div class="absolute top-3 left-3 bg-primary text-white text-[9px] font-bold uppercase tracking-wider px-2.5 py-1 rounded shadow-sm border border-white/10">Compare Option</div>
                    </div>
                    
                    <!-- Card Top Header -->
                    <div class="p-6 bg-surface-container-low border-b border-outline-variant/20 relative">
                        <div class="flex justify-between items-start mb-3">
                            <span class="text-xs font-bold tracking-wider text-accent uppercase"><?php echo htmlspecialchars($pkg['AgencyName']); ?></span>
                            <div class="flex items-center gap-1 bg-surface border border-outline-variant/40 rounded-full px-2 py-0.5 text-xs text-text-main font-semibold">
                                <span class="material-symbols-outlined text-[14px] text-amber-500 fill-amber-500">star</span>
                                <?php echo number_format($pkg['AgencyRating'] ?? 0, 1); ?>
                            </div>
                        </div>
                        <h2 class="text-xl font-heading font-semibold text-text-main mb-4 leading-snug h-[56px] overflow-hidden line-clamp-2"><?php echo htmlspecialchars($pkg['Title']); ?></h2>
                        <div class="flex items-baseline gap-1">
                            <span class="text-2xl font-bold text-primary font-mono"><?php echo formatCurrency($pkg['BasePrice']); ?></span>
                            <span class="text-xs text-muted font-medium">/ package</span>
                        </div>
                    </div>

                    <!-- Side-by-Side Features Grid -->
                    <div class="p-6 flex-grow flex flex-col gap-8">
                        
                        <!-- Overview (Duration & Capacity) -->
                        <div class="flex flex-col gap-2">
                            <span class="lg:hidden text-xs font-bold uppercase tracking-wider text-muted mb-1 block">Overview</span>
                            <div class="grid grid-cols-2 gap-3 bg-background-light p-3 rounded-lg border border-outline-variant/20">
                                <div>
                                    <p class="text-[10px] text-muted uppercase tracking-wider font-semibold">Duration</p>
                                    <div class="flex items-center gap-1 mt-0.5 text-sm font-semibold text-text-main">
                                        <span class="material-symbols-outlined text-[18px] text-primary">schedule</span>
                                        <?php echo $pkg['DurationDays']; ?> Days
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] text-muted uppercase tracking-wider font-semibold">Max Capacity</p>
                                    <div class="flex items-center gap-1 mt-0.5 text-sm font-semibold text-text-main">
                                        <span class="material-symbols-outlined text-[18px] text-accent">groups</span>
                                        <?php echo $pkg['MaxCapacity']; ?> guests
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-on-surface-variant line-clamp-3 mt-1.5 leading-relaxed"><?php echo htmlspecialchars($pkg['Description']); ?></p>
                        </div>

                        <!-- Destinations Column -->
                        <div class="flex flex-col gap-2">
                            <span class="lg:hidden text-xs font-bold uppercase tracking-wider text-muted mb-1 block">Destinations</span>
                            <h4 class="text-xs font-bold text-text-main mb-1 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px] text-primary">explore</span>
                                Locations Visited (<?php echo count($pkg['destinations']); ?>)
                            </h4>
                            <?php if (empty($pkg['destinations'])): ?>
                                <p class="text-xs text-muted italic">No destinations listed.</p>
                            <?php else: ?>
                                <div class="flex flex-wrap gap-1.5 max-h-[80px] overflow-y-auto pr-1">
                                    <?php foreach ($pkg['destinations'] as $dest): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-surface-container border border-outline-variant/40 rounded-full text-xs font-medium text-on-surface">
                                            <?php echo htmlspecialchars($dest['Name']); ?>, <span class="text-muted"><?php echo htmlspecialchars($dest['Country']); ?></span>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Accommodation Column -->
                        <div class="flex flex-col gap-2">
                            <span class="lg:hidden text-xs font-bold uppercase tracking-wider text-muted mb-1 block">Accommodation</span>
                            <h4 class="text-xs font-bold text-text-main mb-1 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px] text-accent">hotel</span>
                                Premium Stays (<?php echo count($pkg['accommodations']); ?>)
                            </h4>
                            <?php if (empty($pkg['accommodations'])): ?>
                                <p class="text-xs text-muted italic">No accommodations listed.</p>
                            <?php else: ?>
                                <div class="space-y-2 max-h-[140px] overflow-y-auto pr-1">
                                    <?php foreach ($pkg['accommodations'] as $acc): ?>
                                        <div class="p-2.5 bg-surface-container-low border border-outline-variant/30 rounded-lg flex flex-col gap-0.5">
                                            <div class="flex justify-between items-start gap-2">
                                                <p class="text-xs font-bold text-text-main truncate"><?php echo htmlspecialchars($acc['Name']); ?></p>
                                                <?php if ($acc['StarRating']): ?>
                                                    <span class="flex items-center text-[10px] text-amber-500 shrink-0 font-bold">
                                                        <span class="material-symbols-outlined text-[12px] fill-amber-500">star</span>
                                                        <?php echo $acc['StarRating']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex justify-between items-center text-[10px] text-muted mt-0.5">
                                                <span>Type: <?php echo htmlspecialchars($acc['Type']); ?></span>
                                                <span class="font-semibold text-primary"><?php echo formatCurrency($acc['PricePerNight']); ?>/night</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Flights Column -->
                        <div class="flex flex-col gap-2">
                            <span class="lg:hidden text-xs font-bold uppercase tracking-wider text-muted mb-1 block">Flights</span>
                            <h4 class="text-xs font-bold text-text-main mb-1 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px] text-primary">flight_takeoff</span>
                                Included Flights (<?php echo count($pkg['flights']); ?>)
                            </h4>
                            <?php if (empty($pkg['flights'])): ?>
                                <p class="text-xs text-muted italic">No flights included.</p>
                            <?php else: ?>
                                <div class="space-y-2 max-h-[140px] overflow-y-auto pr-1">
                                    <?php foreach ($pkg['flights'] as $fl): ?>
                                        <div class="p-2.5 bg-surface-container-low border border-outline-variant/30 rounded-lg flex flex-col gap-1">
                                            <div class="flex justify-between items-center gap-2">
                                                <p class="text-xs font-bold text-text-main"><?php echo htmlspecialchars($fl['Airline']); ?></p>
                                                <span class="px-1.5 py-0.5 bg-secondary-container text-on-secondary-container rounded text-[9px] font-bold"><?php echo htmlspecialchars($fl['FlightNum']); ?></span>
                                            </div>
                                            <div class="flex items-center justify-between text-[10px] text-muted">
                                                <div class="flex items-center gap-1">
                                                    <span class="font-semibold text-text-main"><?php echo $fl['DepAirport_Code']; ?></span>
                                                    <span class="material-symbols-outlined text-[10px]">arrow_forward</span>
                                                    <span class="font-semibold text-text-main"><?php echo $fl['ArrAirport_Code']; ?></span>
                                                </div>
                                                <span class="text-primary font-bold"><?php echo formatCurrency($fl['Cost']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Attractions Column -->
                        <div class="flex flex-col gap-2">
                            <span class="lg:hidden text-xs font-bold uppercase tracking-wider text-muted mb-1 block">Attractions</span>
                            <h4 class="text-xs font-bold text-text-main mb-1 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px] text-accent">local_activity</span>
                                Attractions Visited (<?php echo count($pkg['attractions']); ?>)
                            </h4>
                            <?php if (empty($pkg['attractions'])): ?>
                                <p class="text-xs text-muted italic">No attractions listed.</p>
                            <?php else: ?>
                                <div class="space-y-1.5 max-h-[120px] overflow-y-auto pr-1">
                                    <?php foreach ($pkg['attractions'] as $att): ?>
                                        <div class="flex justify-between items-center text-xs p-2 bg-background border border-outline-variant/20 rounded-lg">
                                            <div class="min-w-0">
                                                <p class="font-semibold text-text-main truncate"><?php echo htmlspecialchars($att['Name']); ?></p>
                                                <span class="text-[9px] text-muted bg-surface border border-outline-variant/30 px-1 py-0.5 rounded"><?php echo htmlspecialchars($att['Category'] ?: 'Sightseeing'); ?></span>
                                            </div>
                                            <span class="text-[10px] text-primary font-bold shrink-0">Fee: <?php echo formatCurrency($att['EntryFee']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Dining & Restaurants Column -->
                        <div class="flex flex-col gap-2">
                            <span class="lg:hidden text-xs font-bold uppercase tracking-wider text-muted mb-1 block">Restaurants</span>
                            <h4 class="text-xs font-bold text-text-main mb-1 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px] text-primary">restaurant</span>
                                Dining & Cuisine (<?php echo count($pkg['restaurants']); ?>)
                            </h4>
                            <?php if (empty($pkg['restaurants'])): ?>
                                <p class="text-xs text-muted italic">No restaurants listed.</p>
                            <?php else: ?>
                                <div class="space-y-1.5 max-h-[120px] overflow-y-auto pr-1">
                                    <?php foreach ($pkg['restaurants'] as $rest): ?>
                                        <div class="flex justify-between items-center text-xs p-2 bg-background border border-outline-variant/20 rounded-lg">
                                            <div class="min-w-0">
                                                <p class="font-semibold text-text-main truncate"><?php echo htmlspecialchars($rest['Name']); ?></p>
                                                <span class="text-[9px] text-muted bg-surface border border-outline-variant/30 px-1 py-0.5 rounded"><?php echo htmlspecialchars($rest['CuisineType'] ?: 'Local'); ?></span>
                                            </div>
                                            <span class="text-[10px] text-accent font-bold shrink-0">Avg: <?php echo formatCurrency($rest['AverageCost']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Direct View Details CTA -->
                    <div class="p-6 bg-surface-container-low border-t border-outline-variant/20 flex flex-col gap-2">
                        <a href="package_detail.php?id=<?php echo $pkg['PackageID']; ?>" class="btn w-full h-[40px] text-sm text-white hover:text-white shadow">View Package Details</a>
                    </div>

                </div>
            <?php endforeach; ?>
            
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
