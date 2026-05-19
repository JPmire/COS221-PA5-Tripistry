<?php
$page_title = 'Traveller Dashboard';
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Traveller') {
    header("Location: login.php");
    exit;
}

$traveller_id = $_SESSION['user_id'];
$error = '';

try {
    // 1. Fetch current Traveller details & budget
    $stmt = $pdo->prepare("SELECT * FROM Traveller WHERE UserID = ?");
    $stmt->execute([$traveller_id]);
    $traveller = $stmt->fetch();

    if (!$traveller) {
        $error = "Explorer details not found.";
    } else {
        $userBudget = (float)$traveller['SoloBudget'];

        // 2. Fetch My Bookings
        $stmtBook = $pdo->prepare("
            SELECT b.BookingID, b.BookingDate, b.TotalAmount, b.PaymentStatus, b.PartySize, 
                   tp.PackageID, tp.Title AS PackageTitle, a.AgencyName, gt.StartDate, gt.EndDate
            FROM Booking b
            JOIN GroupTrip gt ON b.Trip_PackageID = gt.PackageID AND b.Trip_TripDateID = gt.TripDateID
            JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
            JOIN TravelAgency a ON tp.AgencyID = a.UserID
            WHERE b.TravellerID = ?
            ORDER BY b.BookingDate DESC
        ");
        $stmtBook->execute([$traveller_id]);
        $bookings = $stmtBook->fetchAll();

        // 3. Fetch traveller preferences
        $stmtPref = $pdo->prepare("SELECT Preference FROM Traveller_Preferences WHERE UserID = ?");
        $stmtPref->execute([$traveller_id]);
        $preferences = $stmtPref->fetchAll(PDO::FETCH_COLUMN);

        // 4. Fetch Packages not currently booked to compile Smart Recommendations
        $stmtPkg = $pdo->prepare("
            SELECT tp.*, ta.AgencyName, ta.AverageRating AS AgencyRating,
                   (SELECT GROUP_CONCAT(d.Name SEPARATOR ', ') FROM Package_Destination pd JOIN Destination d ON pd.DestID = d.DestID WHERE pd.PackageID = tp.PackageID) AS DestinationsList,
                   (SELECT GROUP_CONCAT(attr.Name SEPARATOR ', ') FROM Package_Attraction pa JOIN Attraction attr ON pa.AttractionID = attr.AttractionID WHERE pa.PackageID = tp.PackageID) AS AttractionsList
            FROM TravelPackage tp
            JOIN TravelAgency ta ON tp.AgencyID = ta.UserID
            WHERE tp.PackageID NOT IN (
                SELECT Trip_PackageID FROM Booking WHERE TravellerID = ?
            )
        ");
        $stmtPkg->execute([$traveller_id]);
        $all_packages = $stmtPkg->fetchAll();

        $recommendations = [];
        foreach ($all_packages as $pkg) {
            $prefMatches = [];
            $searchString = strtolower($pkg['Title'] . ' ' . $pkg['Description'] . ' ' . $pkg['DestinationsList'] . ' ' . $pkg['AttractionsList']);
            
            foreach ($preferences as $pref) {
                $prefLower = strtolower($pref);
                if (strpos($searchString, $prefLower) !== false) {
                    $prefMatches[] = $pref;
                }
            }

            // Preference Score Component: 25 points per tag match, capped at 50 (2 matches required for full points)
            $prefComponent = min(50.0, count($prefMatches) * 25.0);

            // Budget Score Component: check headroom relative to traveler's SoloBudget
            $price = (float)$pkg['BasePrice'];
            $budgetComponent = 0.0;
            if ($userBudget > 0) {
                if ($price <= $userBudget) {
                    // Under budget: gets baseline 30 + up to 20 based on how cheap it is relative to their limit
                    $budgetComponent = 30.0 + 20.0 * (1.0 - ($price / $userBudget));
                } else {
                    // Over budget: gets scaled down score capped at 30 points
                    $budgetComponent = 30.0 * ($userBudget / $price);
                }
            } else {
                // No budget set: default points
                $budgetComponent = 15.0;
            }

            $totalScore = round($prefComponent + $budgetComponent);

            $pkg['match_score'] = $totalScore;
            $pkg['matched_tags'] = $prefMatches;
            $recommendations[] = $pkg;
        }

        // Sort packages by recommendation compatibility score descending
        usort($recommendations, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        // Top 3 Recommendations
        $recommendations = array_slice($recommendations, 0, 3);

        // 5. Fetch Featured Travel Agencies (Top Rated)
        $stmtAgencies = $pdo->prepare("
            SELECT ta.*, 
                   (SELECT GROUP_CONCAT(ContactNumber SEPARATOR ', ') FROM TravelAgency_Contacts WHERE UserID = ta.UserID) AS ContactsList,
                   (SELECT COUNT(*) FROM TravelPackage WHERE AgencyID = ta.UserID) AS PackageCount
            FROM TravelAgency ta
            ORDER BY ta.AverageRating DESC
            LIMIT 3
        ");
        $stmtAgencies->execute();
        $featured_agencies = $stmtAgencies->fetchAll();
    }

} catch (\PDOException $e) {
    $error = "Failed to load traveler dashboard metrics: " . $e->getMessage();
}
?>

<div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
    <div>
        <h1 class="font-display-lg text-3xl font-heading font-semibold text-text-main mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
        <p class="font-body-lg text-body-lg text-secondary">Find your custom tailored adventures and explore our top agency partners.</p>
    </div>
    <div class="flex gap-3">
        <a href="traveller_agencies.php" class="btn-premium-secondary px-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-secondary">corporate_fare</span>
            Explore Agencies
        </a>
        <a href="packages.php" class="btn-premium px-6 flex items-center gap-2">
            <span class="material-symbols-outlined">travel_explore</span>
            Browse Packages
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="bg-error-container text-error p-4 rounded-xl border border-error mb-6">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php else: ?>

    <!-- Overview Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-8">
        <!-- Total Trips -->
        <div class="glass-card card-hover p-6 flex justify-between items-start">
            <div>
                <div class="text-muted font-label-md text-xs uppercase tracking-widest mb-2 font-bold">Total Trips</div>
                <div class="font-display-lg text-2xl font-heading font-bold text-text-main"><?php echo count($bookings); ?></div>
            </div>
            <div class="bg-primary-fixed p-3 rounded-xl">
                <span class="material-symbols-outlined text-primary">luggage</span>
            </div>
        </div>
        
        <!-- Spent -->
        <div class="glass-card card-hover p-6 flex justify-between items-start">
            <div>
                <div class="text-muted font-label-md text-xs uppercase tracking-widest mb-2 font-bold">Total Invested</div>
                <?php 
                    $total_spent = array_reduce($bookings, function($carry, $item) { return $carry + (float)$item['TotalAmount']; }, 0);
                ?>
                <div class="font-display-lg text-2xl font-heading font-bold text-text-main">$<?php echo number_format($total_spent, 2); ?></div>
            </div>
            <div class="bg-secondary-container p-3 rounded-xl">
                <span class="material-symbols-outlined text-on-secondary-container">payments</span>
            </div>
        </div>

        <!-- Budget -->
        <div class="glass-card card-hover p-6 flex justify-between items-start">
            <div>
                <div class="text-muted font-label-md text-xs uppercase tracking-widest mb-2 font-bold">Solo Travel Budget</div>
                <div class="font-display-lg text-2xl font-heading font-bold text-text-main">$<?php echo number_format($traveller['SoloBudget'], 2); ?></div>
            </div>
            <div class="bg-tertiary-container p-3 rounded-xl text-white flex items-center justify-center">
                <span class="material-symbols-outlined">account_balance_wallet</span>
            </div>
        </div>

        <!-- Preferences count -->
        <div class="glass-card card-hover p-6 flex justify-between items-start">
            <div class="flex-grow">
                <div class="text-muted font-label-md text-xs uppercase tracking-widest mb-2 font-bold">Preferences Saved</div>
                <div class="flex justify-between items-center pr-2">
                    <div class="font-display-lg text-2xl font-heading font-bold text-text-main"><?php echo count($preferences); ?> tags</div>
                    <a href="traveller_profile.php" class="text-[11px] font-bold text-primary hover:text-primary-container transition-colors flex items-center gap-0.5">
                        Edit
                        <span class="material-symbols-outlined text-xs">arrow_forward</span>
                    </a>
                </div>
            </div>
            <div class="bg-surface-container-high p-3 rounded-xl flex items-center">
                <span class="material-symbols-outlined text-secondary">favorite</span>
            </div>
        </div>
    </div>

    <!-- Recommendations & Featured Agencies Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        
        <!-- Smart Match recommendations (2/3 width) -->
        <div class="lg:col-span-2 flex flex-col gap-5">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-heading font-semibold text-text-main flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[28px]">insights</span>
                    Smart Recommendation Matcher
                </h2>
                <span class="text-xs font-semibold text-muted bg-surface border border-outline-variant/60 rounded-full px-3 py-1 font-mono">Matched by preferences & budget</span>
            </div>

            <?php if (empty($recommendations)): ?>
                <div class="bg-surface border border-outline-variant rounded-xl p-8 text-center text-muted shadow-sm">
                    <span class="material-symbols-outlined text-4xl mb-2 text-outline-variant">travel_explore</span>
                    <p class="text-sm">No package matches found. Add more destination interests to get recommendations!</p>
                    <a href="traveller_profile.php" class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary-dark transition-all btn-glow mt-3">Update Travel Interests</a>
                </div>
            <?php else: ?>
                <div class="flex flex-col gap-4">
                    <?php foreach ($recommendations as $pkg): ?>
                        <div class="glass-card card-hover p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-5">
                            
                            <!-- Package Core info -->
                            <div class="flex-grow flex flex-col gap-2">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <span class="px-2.5 py-0.5 bg-green-50 border border-green-200/50 text-green-700 rounded-full text-[10px] font-bold tracking-wider font-mono flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                        <?php echo $pkg['match_score']; ?>% Match
                                    </span>
                                    <span class="text-xs text-muted font-bold tracking-wider uppercase"><?php echo htmlspecialchars($pkg['AgencyName']); ?></span>
                                </div>
                                
                                <h3 class="text-lg font-heading font-semibold text-text-main"><?php echo htmlspecialchars($pkg['Title']); ?></h3>
                                
                                <!-- Meta badges -->
                                <div class="flex items-center gap-3 text-xs text-secondary mt-1 flex-wrap">
                                    <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[15px]">schedule</span> <?php echo $pkg['DurationDays']; ?> Days</span>
                                    <span class="w-1 h-1 rounded-full bg-outline-variant"></span>
                                    <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[15px] text-amber-500">star</span> <?php echo number_format($pkg['AgencyRating'], 1); ?></span>
                                    
                                    <?php if (!empty($pkg['matched_tags'])): ?>
                                        <span class="w-1 h-1 rounded-full bg-outline-variant"></span>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-[10px] text-muted italic">Matches:</span>
                                            <?php foreach (array_slice($pkg['matched_tags'], 0, 2) as $tag): ?>
                                                <span class="px-1.5 py-0.2 bg-primary/5 text-primary text-[10px] rounded border border-primary/10 font-bold"><?php echo htmlspecialchars($tag); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Score Visual & CTA -->
                            <div class="shrink-0 flex items-center gap-5 w-full sm:w-auto border-t sm:border-t-0 pt-4 sm:pt-0 border-outline-variant/30 justify-between sm:justify-end">
                                <div class="text-right sm:pr-4">
                                    <span class="text-[10px] text-muted block mb-0.5 uppercase tracking-wider font-bold">Est Price</span>
                                    <span class="text-xl font-heading font-bold text-primary">$<?php echo number_format($pkg['BasePrice'], 2); ?></span>
                                </div>
                                <a href="package_detail.php?id=<?php echo $pkg['PackageID']; ?>" class="btn-premium-secondary h-[40px] px-5 text-xs flex items-center gap-1.5">
                                    View Details
                                    <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                </a>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

        <!-- Featured Travel Agencies Widget (1/3 width) -->
        <div class="flex flex-col gap-5">
            <h2 class="text-xl font-heading font-semibold text-text-main flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[28px]">workspace_premium</span>
                Top Travel Agencies
            </h2>

            <div class="flex flex-col gap-4">
                <?php foreach ($featured_agencies as $agency): ?>
                    <div class="glass-card card-hover p-5 flex flex-col gap-3">
                        <div class="flex justify-between items-start gap-3">
                            <div>
                                <h3 class="font-heading font-semibold text-text-main text-sm hover:text-primary transition-colors">
                                    <a href="traveller_agencies.php?agency_id=<?php echo $agency['UserID']; ?>"><?php echo htmlspecialchars($agency['AgencyName']); ?></a>
                                </h3>
                                <p class="text-xs text-muted mt-0.5 flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">location_on</span> <?php echo htmlspecialchars($agency['Address_City']); ?></p>
                            </div>
                            
                            <!-- Rating Badge -->
                            <div class="px-2 py-1 bg-amber-50 border border-amber-200 text-amber-700 font-bold rounded-lg text-xs flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-[13px] fill-1 text-amber-500">star</span>
                                <?php echo number_format($agency['AverageRating'], 1); ?>
                            </div>
                        </div>

                        <!-- Agency details contacts & count -->
                        <div class="border-t border-outline-variant/30 pt-3 flex justify-between items-center text-[11px] text-secondary">
                            <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[14px]">call</span> <?php echo $agency['ContactsList'] ? htmlspecialchars(explode(',', $agency['ContactsList'])[0]) : 'No direct line'; ?></span>
                            <span class="font-bold text-primary bg-primary-fixed/50 px-2 py-0.5 rounded-full"><?php echo $agency['PackageCount']; ?> packages</span>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <a href="traveller_agencies.php" class="w-full btn-premium-secondary h-[44px] text-xs font-bold flex items-center justify-center">View All Agencies Directory</a>
            </div>
        </div>

    </div>

    <!-- Active Bookings Log -->
    <div class="glass-card overflow-hidden shadow-sm">
        <div class="px-6 py-5 border-b border-outline-variant/30 bg-transparent flex justify-between items-center">
            <h2 class="font-heading font-semibold text-xl text-text-main flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[28px]">local_activity</span>
                Your Trip Bookings
            </h2>
            <span class="text-xs font-mono font-bold text-muted"><?php echo count($bookings); ?> bookings total</span>
        </div>
        
        <div class="overflow-x-auto bg-transparent">
            <table class="modern-table text-left">
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Booking Date</th>
                        <th>Trip Dates</th>
                        <th>Amount Paid</th>
                        <th>Status</th>
                        <th class="text-right">Sync Calendar</th>
                    </tr>
                </thead>
                <tbody class="text-body-md">
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-muted">
                                <div class="flex flex-col items-center justify-center gap-3">
                                    <span class="material-symbols-outlined text-4xl block opacity-40">event_busy</span>
                                    <span class="text-sm font-medium">You have not booked any packages yet.</span>
                                    <a href="packages.php" class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary-dark transition-all btn-glow shadow-md">
                                        <span class="material-symbols-outlined text-[16px]">explore</span>
                                        Find Your Next Adventure
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr class="hover:bg-background-light transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-text-main hover:text-primary transition-colors cursor-pointer">
                                        <a href="package_detail.php?id=<?php echo $booking['PackageID']; ?>"><?php echo htmlspecialchars($booking['PackageTitle']); ?></a>
                                    </div>
                                    <div class="text-[11px] text-muted mt-1">Agency: <?php echo htmlspecialchars($booking['AgencyName']); ?></div>
                                </td>
                                <td class="px-6 py-4 text-text-main text-xs font-mono"><?php echo date('M d, Y', strtotime($booking['BookingDate'])); ?></td>
                                <td class="px-6 py-4 text-secondary text-xs">
                                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-xs">calendar_month</span> <?php echo date('M d, Y', strtotime($booking['StartDate'])) . ' - ' . date('M d, Y', strtotime($booking['EndDate'])); ?></span>
                                </td>
                                <td class="px-6 py-4 font-bold text-text-main font-mono">$<?php echo number_format($booking['TotalAmount'], 2); ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($booking['PaymentStatus'] === 'Paid'): ?>
                                        <span class="px-3 py-1 bg-green-50 border border-green-200 text-green-700 rounded-full text-[10px] font-bold uppercase tracking-wider">Paid</span>
                                    <?php elseif ($booking['PaymentStatus'] === 'Pending'): ?>
                                        <span class="px-3 py-1 bg-amber-50 border border-amber-200 text-amber-700 rounded-full text-[10px] font-bold uppercase tracking-wider">Pending</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-red-50 border border-red-200 text-primary rounded-full text-[10px] font-bold uppercase tracking-wider"><?php echo htmlspecialchars($booking['PaymentStatus']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if ($booking['PaymentStatus'] === 'Paid'): ?>
                                        <a href="export_calendar.php?booking_id=<?php echo $booking['BookingID']; ?>" class="inline-flex items-center gap-1 btn-premium-secondary font-bold text-[10px] h-[32px] px-3.5 rounded-lg" title="Download iCalendar event for sync">
                                            <span class="material-symbols-outlined text-xs">calendar_add_on</span>
                                            Sync iCal
                                        </a>
                                    <?php else: ?>
                                        <span class="text-[10px] text-muted italic">Awaiting Payment</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>