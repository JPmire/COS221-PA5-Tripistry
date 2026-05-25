<?php
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
    header("Location: login.php");
    exit;
}

$page_title = 'Traveller Insights';
require_once 'includes/header.php';

$agency_id = $_SESSION['user_id'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : '';

$success_message = '';
$error_message = '';

// Fetch agency lifetime metrics for the high-level dashboard counters
$summary_stats = [
    'total_unique_customers' => 0,
    'lifetime_revenue' => 0.00,
    'total_bookings' => 0,
    'average_customer_budget' => 0.00
];

try {
    // 1. Unique customer count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT b.TravellerID) 
        FROM Booking b
        JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
        WHERE tp.AgencyID = ?
    ");
    $stmt->execute([$agency_id]);
    $summary_stats['total_unique_customers'] = (int)$stmt->fetchColumn();

    // 2. Lifetime Revenue (Sum of Paid bookings)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(b.TotalAmount), 0.00) 
        FROM Booking b
        JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
        WHERE tp.AgencyID = ? AND b.PaymentStatus = 'Paid'
    ");
    $stmt->execute([$agency_id]);
    $summary_stats['lifetime_revenue'] = (float)$stmt->fetchColumn();

    // 3. Booking Volume
    $stmt = $pdo->prepare("
        SELECT COUNT(b.BookingID) 
        FROM Booking b
        JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
        WHERE tp.AgencyID = ?
    ");
    $stmt->execute([$agency_id]);
    $summary_stats['total_bookings'] = (int)$stmt->fetchColumn();

    // 4. Average Customer Budget
    $stmt = $pdo->prepare("
        SELECT COALESCE(AVG(SoloBudget), 0.00) 
        FROM Traveller t
        WHERE t.UserID IN (
            SELECT DISTINCT b.TravellerID
            FROM Booking b
            JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
            WHERE tp.AgencyID = ?
        )
    ");
    $stmt->execute([$agency_id]);
    $summary_stats['average_customer_budget'] = (float)$stmt->fetchColumn();

} catch (\PDOException $e) {
    $error_message = "Failed to calculate summary metrics: " . $e->getMessage();
}

// Fetch matching agency destinations to cross-reference customer interest
$agency_destinations = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT LOWER(TRIM(d.Name)) AS DestName
        FROM Package_Destination pd
        JOIN Destination d ON pd.DestID = d.DestID
        JOIN TravelPackage tp ON pd.PackageID = tp.PackageID
        WHERE tp.AgencyID = ?
    ");
    $stmt->execute([$agency_id]);
    $agency_destinations = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (\PDOException $e) {
    // Fail silently
}

// FETCH CUSTOMERS
$customers = [];
try {
    $sql = "
        SELECT t.UserID AS TravellerID, t.FirstName, t.LastName, t.DOB, t.SoloBudget, u.Email,
               COUNT(DISTINCT b.BookingID) AS TotalBookings,
               COALESCE(SUM(CASE WHEN b.PaymentStatus = 'Paid' THEN b.TotalAmount ELSE 0 END), 0) AS LifetimeContribution,
               
               (SELECT tp2.Title 
                FROM Booking b2 
                JOIN TravelPackage tp2 ON b2.Trip_PackageID = tp2.PackageID 
                WHERE b2.TravellerID = t.UserID AND tp2.AgencyID = :agency_id_sub1 
                ORDER BY b2.BookingDate DESC, b2.BookingID DESC LIMIT 1) AS LatestPackageTitle,
                
               (SELECT gt2.StartDate 
                FROM Booking b2 
                JOIN TravelPackage tp2 ON b2.Trip_PackageID = tp2.PackageID 
                JOIN GroupTrip gt2 ON (b2.Trip_PackageID = gt2.PackageID AND b2.Trip_TripDateID = gt2.TripDateID)
                WHERE b2.TravellerID = t.UserID AND tp2.AgencyID = :agency_id_sub2 
                ORDER BY b2.BookingDate DESC, b2.BookingID DESC LIMIT 1) AS LatestStartDate,
                
               (SELECT gt2.EndDate 
                FROM Booking b2 
                JOIN TravelPackage tp2 ON b2.Trip_PackageID = tp2.PackageID 
                JOIN GroupTrip gt2 ON (b2.Trip_PackageID = gt2.PackageID AND b2.Trip_TripDateID = gt2.TripDateID)
                WHERE b2.TravellerID = t.UserID AND tp2.AgencyID = :agency_id_sub3 
                ORDER BY b2.BookingDate DESC, b2.BookingID DESC LIMIT 1) AS LatestEndDate,
                
               (SELECT b2.PaymentStatus 
                FROM Booking b2 
                JOIN TravelPackage tp2 ON b2.Trip_PackageID = tp2.PackageID 
                WHERE b2.TravellerID = t.UserID AND tp2.AgencyID = :agency_id_sub4 
                ORDER BY b2.BookingDate DESC, b2.BookingID DESC LIMIT 1) AS LatestPaymentStatus
        FROM Booking b
        JOIN Traveller t ON b.TravellerID = t.UserID
        JOIN User u ON t.UserID = u.UserID
        JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
        WHERE tp.AgencyID = :agency_id_main
    ";
    
    if ($search !== '') {
        $sql .= " AND (t.FirstName LIKE :search OR t.LastName LIKE :search OR u.Email LIKE :search)";
    }
    
    $sql .= " GROUP BY t.UserID, t.FirstName, t.LastName, t.DOB, t.SoloBudget, u.Email";
    
    // Sort logic
    if ($sort === 'revenue_desc') {
        $sql .= " ORDER BY LifetimeContribution DESC";
    } elseif ($sort === 'bookings_desc') {
        $sql .= " ORDER BY TotalBookings DESC";
    } elseif ($sort === 'name_desc') {
        $sql .= " ORDER BY t.FirstName DESC, t.LastName DESC";
    } else {
        $sql .= " ORDER BY t.FirstName ASC, t.LastName ASC"; // default alphabetical
    }
    
    $stmt = $pdo->prepare($sql);
    $params = [
        ':agency_id_sub1' => $agency_id,
        ':agency_id_sub2' => $agency_id,
        ':agency_id_sub3' => $agency_id,
        ':agency_id_sub4' => $agency_id,
        ':agency_id_main' => $agency_id
    ];
    if ($search !== '') {
        $params[':search'] = '%' . $search . '%';
    }
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error_message = "Error fetching active customer portfolio: " . $e->getMessage();
}
?>

<div class="flex flex-col gap-6">
    
    <!-- Title Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-heading font-semibold text-text-main flex items-center gap-2">
                <span class="material-symbols-outlined text-[32px] text-primary">contact_phone</span>
                Traveller Insights & CRM
            </h1>
            <p class="text-muted mt-1">A centralized customer relations management directory enabling active portfolio value tracking, target campaign insights, and platform-wide lead generation.</p>
        </div>
    </div>

    <!-- Alert banners -->
    <?php if ($success_message): ?>
        <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-xl flex items-center gap-2.5 shadow-sm transition-all duration-300">
            <span class="material-symbols-outlined text-[20px] text-green-600 animate-bounce">check_circle</span>
            <span class="text-sm font-medium"><?php echo $success_message; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="p-4 bg-red-50 border border-red-200 text-primary rounded-xl flex items-center gap-2.5 shadow-sm">
            <span class="material-symbols-outlined text-[20px] text-red-600">error</span>
            <span class="text-sm font-medium"><?php echo $error_message; ?></span>
        </div>
    <?php endif; ?>

    <!-- High-level Metric Counters -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-white rounded-2xl shadow-sm border border-outline-variant/60 p-5 flex items-center gap-4 hover:shadow-md transition-all">
            <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                <span class="material-symbols-outlined text-2xl">groups</span>
            </div>
            <div>
                <p class="text-xs font-bold text-secondary uppercase tracking-wider">Active Customers</p>
                <h3 class="text-2xl font-bold text-text-main mt-0.5"><?php echo $summary_stats['total_unique_customers']; ?></h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-outline-variant/60 p-5 flex items-center gap-4 hover:shadow-md transition-all">
            <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-green-600 shrink-0">
                <span class="material-symbols-outlined text-2xl">payments</span>
            </div>
            <div>
                <p class="text-xs font-bold text-secondary uppercase tracking-wider">Lifetime Contribution</p>
                <h3 class="text-2xl font-bold text-text-main mt-0.5 font-mono"><?php echo formatCurrency($summary_stats['lifetime_revenue']); ?></h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-outline-variant/60 p-5 flex items-center gap-4 hover:shadow-md transition-all">
            <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-600 shrink-0">
                <span class="material-symbols-outlined text-2xl">receipt_long</span>
            </div>
            <div>
                <p class="text-xs font-bold text-secondary uppercase tracking-wider">Total Bookings</p>
                <h3 class="text-2xl font-bold text-text-main mt-0.5"><?php echo $summary_stats['total_bookings']; ?></h3>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-outline-variant/60 p-5 flex items-center gap-4 hover:shadow-md transition-all">
            <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center text-amber-600 shrink-0">
                <span class="material-symbols-outlined text-2xl">account_balance_wallet</span>
            </div>
            <div>
                <p class="text-xs font-bold text-secondary uppercase tracking-wider">Avg Cust Budget</p>
                <h3 class="text-2xl font-bold text-text-main mt-0.5 font-mono"><?php echo formatCurrency($summary_stats['average_customer_budget']); ?></h3>
            </div>
        </div>
    </div>

    <!-- Interface & Filters Panel -->
    <div class="bg-white rounded-2xl shadow-sm border border-outline-variant/60 p-5 flex flex-col gap-5">
        <!-- Snappy Search and Filter Form -->
        <form method="GET" class="flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-4 pb-4 border-b border-outline-variant/40">
            <!-- Search control -->
            <div class="relative flex-grow max-w-xl">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       class="input-field h-[44px] pl-11 pr-4 text-sm font-medium rounded-xl border border-outline-variant shadow-sm focus:border-primary w-full" 
                       placeholder="Search traveller by name or email...">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-secondary opacity-60">search</span>
            </div>

            <!-- Sort Control & Reset Actions -->
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-secondary uppercase tracking-wider whitespace-nowrap">Sort:</span>
                <select name="sort" onchange="this.form.submit()" 
                        class="input-field h-[40px] w-[180px] py-0 pr-8 text-xs font-bold rounded-lg border border-outline-variant">
                    <option value="" <?php echo $sort === '' ? 'selected' : ''; ?>>Alphabetical (A-Z)</option>
                    <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Alphabetical (Z-A)</option>
                    <option value="revenue_desc" <?php echo $sort === 'revenue_desc' ? 'selected' : ''; ?>>Lifetime Spent</option>
                    <option value="bookings_desc" <?php echo $sort === 'bookings_desc' ? 'selected' : ''; ?>>Booking Count</option>
                </select>

                <button type="submit" class="h-[40px] px-4 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary-dark transition-colors shadow">Apply</button>
                
                <?php if ($search !== '' || $sort !== ''): ?>
                    <a href="agency_matchmaker.php" class="text-xs text-primary font-bold hover:underline flex items-center gap-0.5">
                        <span class="material-symbols-outlined text-xs">restart_alt</span>
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- ACTIVE CUSTOMERS VIEW -->
            <?php if (empty($customers)): ?>
                <div class="py-16 text-center text-muted flex flex-col items-center justify-center gap-3">
                    <span class="material-symbols-outlined text-5xl opacity-35">contact_phone</span>
                    <p class="text-sm font-bold">No active customers found.</p>
                    <p class="text-xs">Try clearing your search query or check back once bookings are processed.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php foreach ($customers as $cust): ?>
                        <?php 
                        // Compute traveller age dynamically
                        $dob_str = $cust['DOB'];
                        $age = 'N/A';
                        if ($dob_str) {
                            $dob = new DateTime($dob_str);
                            $now = new DateTime();
                            $age = $now->diff($dob)->y;
                        }
                        
                        // Pastel avatar backgrounds for premium directory design
                        $colors = ['bg-blue-100 text-blue-700', 'bg-purple-100 text-purple-700', 'bg-emerald-100 text-emerald-700', 'bg-indigo-100 text-indigo-700', 'bg-rose-100 text-rose-700'];
                        $avatar_color = $colors[array_sum(str_split(ord($cust['FirstName']))) % count($colors)];
                        ?>
                        <div class="bg-white rounded-xl border border-outline-variant/60 p-5 flex flex-col justify-between hover:shadow-md transition-all duration-300 relative group">
                            
                            <!-- Top Profile card section -->
                            <div class="flex justify-between items-start gap-4">
                                <div class="flex gap-4">
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-base shrink-0 select-none <?php echo $avatar_color; ?>">
                                        <?php echo substr($cust['FirstName'], 0, 1) . substr($cust['LastName'], 0, 1); ?>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-text-main group-hover:text-primary transition-colors leading-tight">
                                            <?php echo htmlspecialchars($cust['FirstName'] . ' ' . $cust['LastName']); ?>
                                        </h3>
                                        <p class="text-xs text-secondary mt-0.5 select-all font-mono"><?php echo htmlspecialchars($cust['Email']); ?></p>
                                        
                                        <!-- Profile specs row -->
                                        <div class="flex flex-wrap items-center gap-2 mt-2 select-none">
                                            <span class="px-2 py-0.5 bg-surface-container-low text-secondary border border-outline-variant/30 text-[10px] font-bold rounded">
                                                Age: <?php echo $age; ?> yrs
                                            </span>
                                            <span class="px-2 py-0.5 bg-surface-container-low text-secondary border border-outline-variant/30 text-[10px] font-bold rounded font-mono">
                                                Budget: <?php echo formatCurrency($cust['SoloBudget']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lifetime contribution metrics -->
                                <div class="text-right shrink-0">
                                    <span class="text-[10px] font-bold text-muted uppercase tracking-wider">Lifetime Contribution</span>
                                    <p class="text-lg font-bold text-green-600 font-mono mt-0.5"><?php echo formatCurrency($cust['LifetimeContribution']); ?></p>
                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 bg-indigo-50 border border-indigo-100 rounded text-[9px] font-bold text-indigo-700 mt-1.5">
                                        <span class="material-symbols-outlined text-[11px]">confirmation_number</span>
                                        <?php echo $cust['TotalBookings']; ?> booking(s)
                                    </span>
                                </div>
                            </div>

                            <!-- Correlated Latest booking log details -->
                            <?php if ($cust['LatestPackageTitle']): ?>
                                <div class="bg-background-light p-3.5 rounded-lg border border-outline-variant/40 mt-4 text-[11px] text-secondary flex flex-col gap-1.5">
                                    <div class="flex justify-between items-center pb-1.5 border-b border-outline-variant/30 mb-1">
                                        <span class="font-bold text-text-main flex items-center gap-1 uppercase tracking-wide text-[9px]">
                                            <span class="material-symbols-outlined text-[13px] text-primary">history</span>
                                            Latest Booking Details
                                        </span>
                                        <?php 
                                        $status = $cust['LatestPaymentStatus'];
                                        $badge_class = 'bg-gray-100 text-gray-700 border-gray-200';
                                        if ($status === 'Paid') $badge_class = 'bg-green-50 text-green-700 border-green-200';
                                        elseif ($status === 'Pending') $badge_class = 'bg-amber-50 text-amber-800 border-amber-200';
                                        elseif ($status === 'Failed') $badge_class = 'bg-red-50 text-red-700 border-red-200';
                                        ?>
                                        <span class="px-2 py-0.5 border rounded text-[9px] font-extrabold uppercase tracking-wide <?php echo $badge_class; ?>">
                                            <?php echo $status; ?>
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Package Trip:</span>
                                        <strong class="text-text-main"><?php echo htmlspecialchars($cust['LatestPackageTitle']); ?></strong>
                                    </div>
                                    <div class="flex justify-between select-none">
                                        <span>Dates booked:</span>
                                        <span class="font-medium text-text-main">
                                            <?php echo date('M d, Y', strtotime($cust['LatestStartDate'])); ?> – <?php echo date('M d, Y', strtotime($cust['LatestEndDate'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
