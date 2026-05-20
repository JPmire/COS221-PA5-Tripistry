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
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'customers'; // customers or leads

$success_message = '';
$error_message = '';

// Handle visual Pitch/Invite proposal actions for prospective leads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pitch_lead') {
    $lead_name = htmlspecialchars($_POST['lead_name'] ?? 'Traveller');
    $lead_id = (int)($_POST['lead_id'] ?? 0);
    
    if ($lead_id > 0) {
        try {
            // Get Agency Name
            $stmt = $pdo->prepare("SELECT AgencyName FROM TravelAgency WHERE UserID = ?");
            $stmt->execute([$agency_id]);
            $agency_name = $stmt->fetchColumn() ?: "An Agency";

            // Insert real notification in db for traveller
            $title = "Exclusive Campaign Offer from " . $agency_name;
            $message = "Hey " . $lead_name . ", " . $agency_name . " has selected you for an exclusive campaign offer! They have analyzed your preferences and Solo Budget to draft a premium trip package just for you. Open your dashboard to check out their offered packages!";
            
            $stmt = $pdo->prepare("INSERT INTO Notification (UserID, Title, Message, IsRead, CreatedAt) VALUES (?, ?, ?, 0, NOW())");
            $stmt->execute([$lead_id, $title, $message]);
            
            $success_message = "<strong>Pitch Dispatched!</strong> Customized trip proposal and exclusive agency discount code successfully sent to <strong>" . $lead_name . "</strong>.";
        } catch (\PDOException $e) {
            $error_message = "Failed to dispatch pitch: " . $e->getMessage();
        }
    }
}

// Fetch agency lifetime metrics for the high-level dashboard counters
$summary_stats = [
    'total_unique_customers' => 0,
    'lifetime_revenue' => 0.00,
    'total_bookings' => 0,
    'average_lead_budget' => 0.00
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

    // 4. Average prospective lead budget
    $stmt = $pdo->prepare("
        SELECT COALESCE(AVG(SoloBudget), 0.00) 
        FROM Traveller t
        WHERE t.UserID NOT IN (
            SELECT DISTINCT b.TravellerID
            FROM Booking b
            JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
            WHERE tp.AgencyID = ?
        )
    ");
    $stmt->execute([$agency_id]);
    $summary_stats['average_lead_budget'] = (float)$stmt->fetchColumn();

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
if ($tab === 'customers') {
    try {
        $sql = "
            SELECT t.UserID AS TravellerID, t.FirstName, t.LastName, t.DOB, t.SoloBudget, u.Email,
                   COUNT(DISTINCT b.BookingID) AS TotalBookings,
                   COALESCE(SUM(CASE WHEN b.PaymentStatus = 'Paid' THEN b.TotalAmount ELSE 0 END), 0) AS LifetimeContribution,
                   
                   (SELECT tp2.Title 
                    FROM Booking b2 
                    JOIN TravelPackage tp2 ON b2.Trip_PackageID = tp2.PackageID 
                    WHERE b2.TravellerID = t.UserID AND tp2.AgencyID = :agency_id 
                    ORDER BY b2.BookingDate DESC, b2.BookingID DESC LIMIT 1) AS LatestPackageTitle,
                    
                   (SELECT gt2.StartDate 
                    FROM Booking b2 
                    JOIN TravelPackage tp2 ON b2.Trip_PackageID = tp2.PackageID 
                    JOIN GroupTrip gt2 ON (b2.Trip_PackageID = gt2.PackageID AND b2.Trip_TripDateID = gt2.TripDateID)
                    WHERE b2.TravellerID = t.UserID AND tp2.AgencyID = :agency_id 
                    ORDER BY b2.BookingDate DESC, b2.BookingID DESC LIMIT 1) AS LatestStartDate,
                    
                   (SELECT gt2.EndDate 
                    FROM Booking b2 
                    JOIN TravelPackage tp2 ON b2.Trip_PackageID = tp2.PackageID 
                    JOIN GroupTrip gt2 ON (b2.Trip_PackageID = gt2.PackageID AND b2.Trip_TripDateID = gt2.TripDateID)
                    WHERE b2.TravellerID = t.UserID AND tp2.AgencyID = :agency_id 
                    ORDER BY b2.BookingDate DESC, b2.BookingID DESC LIMIT 1) AS LatestEndDate,
                    
                   (SELECT b2.PaymentStatus 
                    FROM Booking b2 
                    JOIN TravelPackage tp2 ON b2.Trip_PackageID = tp2.PackageID 
                    WHERE b2.TravellerID = t.UserID AND tp2.AgencyID = :agency_id 
                    ORDER BY b2.BookingDate DESC, b2.BookingID DESC LIMIT 1) AS LatestPaymentStatus
            FROM Booking b
            JOIN Traveller t ON b.TravellerID = t.UserID
            JOIN User u ON t.UserID = u.UserID
            JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
            WHERE tp.AgencyID = :agency_id
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
        $params = [':agency_id' => $agency_id];
        if ($search !== '') {
            $params[':search'] = '%' . $search . '%';
        }
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
    } catch (\PDOException $e) {
        $error_message = "Error fetching active customer portfolio: " . $e->getMessage();
    }
} else {
    // FETCH LEADS
    try {
        $sql = "
            SELECT t.UserID AS TravellerID, t.FirstName, t.LastName, t.DOB, t.SoloBudget, u.Email,
                   (SELECT GROUP_CONCAT(tp.Preference ORDER BY tp.Preference ASC SEPARATOR ', ') 
                    FROM Traveller_Preferences tp 
                    WHERE tp.UserID = t.UserID) AS Preferences
            FROM Traveller t
            JOIN User u ON t.UserID = u.UserID
            WHERE u.AccountStatus = 'Active'
              AND t.UserID NOT IN (
                  SELECT DISTINCT b.TravellerID
                  FROM Booking b
                  JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
                  WHERE tp.AgencyID = :agency_id
              )
        ";
        
        if ($search !== '') {
            $sql .= " AND (t.FirstName LIKE :search OR t.LastName LIKE :search OR u.Email LIKE :search)";
        }
        
        // Sort logic for leads
        if ($sort === 'budget_desc') {
            $sql .= " ORDER BY t.SoloBudget DESC";
        } elseif ($sort === 'budget_asc') {
            $sql .= " ORDER BY t.SoloBudget ASC";
        } elseif ($sort === 'name_desc') {
            $sql .= " ORDER BY t.FirstName DESC, t.LastName DESC";
        } else {
            $sql .= " ORDER BY t.FirstName ASC, t.LastName ASC"; // default alphabetical
        }
        
        $stmt = $pdo->prepare($sql);
        $params = [':agency_id' => $agency_id];
        if ($search !== '') {
            $params[':search'] = '%' . $search . '%';
        }
        $stmt->execute($params);
        $leads = $stmt->fetchAll();
    } catch (\PDOException $e) {
        $error_message = "Error fetching prospective system leads: " . $e->getMessage();
    }
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
                <h3 class="text-2xl font-bold text-text-main mt-0.5 font-mono">$<?php echo number_format($summary_stats['lifetime_revenue'], 2); ?></h3>
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
                <span class="material-symbols-outlined text-2xl">campaign</span>
            </div>
            <div>
                <p class="text-xs font-bold text-secondary uppercase tracking-wider">Avg Lead Budget</p>
                <h3 class="text-2xl font-bold text-text-main mt-0.5 font-mono">$<?php echo number_format($summary_stats['average_lead_budget'], 2); ?></h3>
            </div>
        </div>
    </div>

    <!-- Interface Tabs & Filters Panel -->
    <div class="bg-white rounded-2xl shadow-sm border border-outline-variant/60 p-5 flex flex-col gap-5">
        <!-- Snappy Search and Filter Form -->
        <form method="GET" class="flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-4 pb-4 border-b border-outline-variant/40">
            <!-- Hidden context parameters -->
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">

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
                    <?php if ($tab === 'customers'): ?>
                        <option value="" <?php echo $sort === '' ? 'selected' : ''; ?>>Alphabetical (A-Z)</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Alphabetical (Z-A)</option>
                        <option value="revenue_desc" <?php echo $sort === 'revenue_desc' ? 'selected' : ''; ?>>Lifetime Spent</option>
                        <option value="bookings_desc" <?php echo $sort === 'bookings_desc' ? 'selected' : ''; ?>>Booking Count</option>
                    <?php else: ?>
                        <option value="" <?php echo $sort === '' ? 'selected' : ''; ?>>Alphabetical (A-Z)</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Alphabetical (Z-A)</option>
                        <option value="budget_desc" <?php echo $sort === 'budget_desc' ? 'selected' : ''; ?>>Solo Budget (High-Low)</option>
                        <option value="budget_asc" <?php echo $sort === 'budget_asc' ? 'selected' : ''; ?>>Solo Budget (Low-High)</option>
                    <?php endif; ?>
                </select>

                <button type="submit" class="h-[40px] px-4 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary-dark transition-colors shadow">Apply</button>
                
                <?php if ($search !== '' || $sort !== ''): ?>
                    <a href="?tab=<?php echo $tab; ?>" class="text-xs text-primary font-bold hover:underline flex items-center gap-0.5">
                        <span class="material-symbols-outlined text-xs">restart_alt</span>
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Segmented Tab bar selectors -->
        <div class="flex border-b border-outline-variant/30 gap-6">
            <a href="?tab=customers&search=<?php echo urlencode($search); ?>" 
               class="pb-3 text-sm font-bold relative transition-colors flex items-center gap-2 <?php echo $tab === 'customers' ? 'text-primary border-b-2 border-primary' : 'text-secondary hover:text-primary'; ?>">
                <span class="material-symbols-outlined text-[18px]">verified_user</span>
                Active Customer Portfolio
                <span class="px-2 py-0.5 text-[10px] font-extrabold rounded-full bg-primary/10 text-primary">
                    <?php echo $tab === 'customers' ? count($customers) : $summary_stats['total_unique_customers']; ?>
                </span>
            </a>

            <a href="?tab=leads&search=<?php echo urlencode($search); ?>" 
               class="pb-3 text-sm font-bold relative transition-colors flex items-center gap-2 <?php echo $tab === 'leads' ? 'text-primary border-b-2 border-primary' : 'text-secondary hover:text-primary'; ?>">
                <span class="material-symbols-outlined text-[18px]">group_add</span>
                Prospective Platform Leads
                <span class="px-2 py-0.5 text-[10px] font-extrabold rounded-full bg-amber-100 text-amber-800">
                    <?php echo $tab === 'leads' ? count($leads) : 'Find Leads'; ?>
                </span>
            </a>
        </div>

        <!-- Render Target Directory lists based on selected view -->
        <?php if ($tab === 'customers'): ?>
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
                                                Budget: $<?php echo number_format($cust['SoloBudget'], 0); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Lifetime contribution metrics -->
                                <div class="text-right shrink-0">
                                    <span class="text-[10px] font-bold text-muted uppercase tracking-wider">Lifetime Contribution</span>
                                    <p class="text-lg font-bold text-green-600 font-mono mt-0.5">$<?php echo number_format($cust['LifetimeContribution'], 2); ?></p>
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

        <?php else: ?>
            <!-- PROSPECTIVE PLATFORM LEADS VIEW -->
            <?php if (empty($leads)): ?>
                <div class="py-16 text-center text-muted flex flex-col items-center justify-center gap-3">
                    <span class="material-symbols-outlined text-5xl opacity-35">group_add</span>
                    <p class="text-sm font-bold">No prospective leads found.</p>
                    <p class="text-xs">Verify if there are registered travellers on the platform who haven't booked with your agency.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php foreach ($leads as $lead): ?>
                        <?php 
                        // Compute age dynamically
                        $dob_str = $lead['DOB'];
                        $age = 'N/A';
                        if ($dob_str) {
                            $dob = new DateTime($dob_str);
                            $now = new DateTime();
                            $age = $now->diff($dob)->y;
                        }
                        
                        // Parse preferences
                        $prefs = [];
                        if ($lead['Preferences']) {
                            $prefs = array_map('trim', explode(',', $lead['Preferences']));
                        }
                        
                        $colors = ['bg-blue-100 text-blue-700', 'bg-purple-100 text-purple-700', 'bg-emerald-100 text-emerald-700', 'bg-indigo-100 text-indigo-700', 'bg-rose-100 text-rose-700'];
                        $avatar_color = $colors[array_sum(str_split(ord($lead['FirstName']))) % count($colors)];
                        ?>
                        <div class="bg-white rounded-xl border border-outline-variant/60 p-5 flex flex-col justify-between hover:shadow-md transition-all duration-300 relative group">
                            
                            <div>
                                <!-- Upper Profile Layout -->
                                <div class="flex justify-between items-start gap-4">
                                    <div class="flex gap-4">
                                        <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-base shrink-0 select-none <?php echo $avatar_color; ?>">
                                            <?php echo substr($lead['FirstName'], 0, 1) . substr($lead['LastName'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <h3 class="text-base font-bold text-text-main group-hover:text-primary transition-colors leading-tight">
                                                <?php echo htmlspecialchars($lead['FirstName'] . ' ' . $lead['LastName']); ?>
                                            </h3>
                                            <p class="text-xs text-secondary mt-0.5 select-all font-mono"><?php echo htmlspecialchars($lead['Email']); ?></p>
                                            
                                            <!-- Profile details -->
                                            <div class="flex items-center gap-2 mt-2 select-none">
                                                <span class="px-2 py-0.5 bg-surface-container-low text-secondary border border-outline-variant/30 text-[10px] font-bold rounded">
                                                    Age: <?php echo $age; ?> yrs
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Solo Budget Level -->
                                    <div class="text-right shrink-0">
                                        <span class="text-[10px] font-bold text-muted uppercase tracking-wider">Solo Budget Limit</span>
                                        <p class="text-lg font-bold text-primary font-mono mt-0.5">$<?php echo number_format($lead['SoloBudget'], 2); ?></p>
                                    </div>
                                </div>

                                <!-- Interest Preferences Grid -->
                                <div class="mt-4">
                                    <p class="text-[10px] font-bold text-secondary uppercase tracking-wider mb-2 select-none">Saved Traveler Interests</p>
                                    <?php if (empty($prefs)): ?>
                                        <p class="text-xs text-muted italic">No specific preferences listed.</p>
                                    <?php else: ?>
                                        <div class="flex flex-wrap gap-1.5">
                                            <?php foreach ($prefs as $p): ?>
                                                <?php 
                                                // Cross-reference with agency active destinations
                                                $is_match = in_array(strtolower($p), $agency_destinations);
                                                $chip_class = $is_match 
                                                    ? 'bg-green-50 border-green-200 text-green-700 font-extrabold flex items-center gap-0.5' 
                                                    : 'bg-surface-container-low border-outline-variant/30 text-secondary';
                                                ?>
                                                <span class="px-2.5 py-1 border text-[10px] rounded-full <?php echo $chip_class; ?>">
                                                    <?php if ($is_match): ?>
                                                        <span class="material-symbols-outlined text-[11px] fill-1 text-green-600">star</span>
                                                        Match: 
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($p); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Custom Lead Campaign Pitch CTA -->
                            <div class="mt-5 pt-4 border-t border-outline-variant/30 flex justify-end">
                                <form method="POST" action="?tab=leads">
                                    <input type="hidden" name="action" value="pitch_lead">
                                    <input type="hidden" name="lead_id" value="<?php echo $lead['TravellerID']; ?>">
                                    <input type="hidden" name="lead_name" value="<?php echo htmlspecialchars($lead['FirstName'] . ' ' . $lead['LastName']); ?>">
                                    <button type="submit" class="h-9 px-4 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary-dark transition-all shadow flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[15px]">send</span>
                                        Pitch Exclusive Deal
                                    </button>
                                </form>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
