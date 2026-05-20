<?php
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
    header("Location: login.php");
    exit;
}

$page_title = 'Manage Bookings';
require_once 'includes/header.php';


$agency_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle payment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['payment_status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $new_status = trim($_POST['payment_status']);
    
    $valid_statuses = ['Pending', 'Paid', 'Failed', 'Refunded'];
    
    if (!in_array($new_status, $valid_statuses)) {
        $error_message = "Invalid payment status specified.";
    } else {
        try {
            // 1. Fetch booking & package details to check ownership and capacities
            $stmt = $pdo->prepare("
                SELECT b.*, tp.AgencyID, tp.MaxCapacity, tp.Title AS PackageTitle, gt.StartDate, gt.EndDate
                FROM Booking b
                JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
                JOIN GroupTrip gt ON (b.Trip_PackageID = gt.PackageID AND b.Trip_TripDateID = gt.TripDateID)
                WHERE b.BookingID = ?
            ");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch();
            
            if (!$booking) {
                $error_message = "Booking record not found.";
            } elseif ($booking['AgencyID'] !== $agency_id) {
                $error_message = "Unauthorized action. You do not own this booking's package.";
            } else {
                $allowed = true;
                
                // 2. Capacity verification when changing from a non-Paid status to 'Paid'
                if ($new_status === 'Paid' && $booking['PaymentStatus'] !== 'Paid') {
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(SUM(PartySize), 0)
                        FROM Booking
                        WHERE Trip_PackageID = ? AND Trip_TripDateID = ? AND PaymentStatus = 'Paid' AND BookingID != ?
                    ");
                    $stmt->execute([$booking['Trip_PackageID'], $booking['Trip_TripDateID'], $booking_id]);
                    $current_paid_guests = (int)$stmt->fetchColumn();
                    
                    $max_capacity = (int)$booking['MaxCapacity'];
                    $party_size = (int)$booking['PartySize'];
                    
                    if ($current_paid_guests + $party_size > $max_capacity) {
                        $allowed = false;
                        $error_message = "<strong>Capacity Exceeded:</strong> Cannot mark Booking #{$booking_id} as Paid. " .
                                         "The scheduled trip dates (<strong>" . date('M d, Y', strtotime($booking['StartDate'])) . " - " . date('M d, Y', strtotime($booking['EndDate'])) . "</strong>) " .
                                         "for package '<strong>" . htmlspecialchars($booking['PackageTitle']) . "</strong>' only has " .
                                         "<strong>" . ($max_capacity - $current_paid_guests) . "</strong> slots remaining, but this party size is <strong>{$party_size}</strong>.";
                    }
                }
                
                // 3. Perform the update if allowed
                if ($allowed) {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE Booking SET PaymentStatus = ? WHERE BookingID = ?");
                    $stmt->execute([$new_status, $booking_id]);
                    $pdo->commit();
                    
                    $success_message = "Booking #{$booking_id} payment status was successfully set to <strong>{$new_status}</strong>.";
                }
            }
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_message = "Database update error: " . $e->getMessage();
        }
    }
}

// Fetch all bookings for this agency
try {
    $stmt = $pdo->prepare("
        SELECT b.BookingID, b.BookingDate, b.TotalAmount, b.PaymentStatus, b.PartySize, b.Trip_PackageID, b.Trip_TripDateID,
               tp.Title AS PackageTitle, tp.MaxCapacity,
               gt.StartDate, gt.EndDate, gt.Status AS TripStatus,
               t.FirstName, t.LastName,
               u.Email AS TravellerEmail
        FROM Booking b
        JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
        JOIN GroupTrip gt ON (b.Trip_PackageID = gt.PackageID AND b.Trip_TripDateID = gt.TripDateID)
        JOIN Traveller t ON b.TravellerID = t.UserID
        JOIN User u ON t.UserID = u.UserID
        WHERE tp.AgencyID = ?
        ORDER BY b.BookingDate DESC
    ");
    $stmt->execute([$agency_id]);
    $bookings = $stmt->fetchAll();
    
    // Fetch unique packages for filter dropdown
    $stmt = $pdo->prepare("SELECT PackageID, Title FROM TravelPackage WHERE AgencyID = ? ORDER BY Title ASC");
    $stmt->execute([$agency_id]);
    $filter_packages = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error_message = "Failed to load bookings: " . $e->getMessage();
}
?>

<div class="flex flex-col gap-6">
    
    <!-- Title Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-heading font-semibold text-text-main">Manage Bookings</h1>
            <p class="text-muted mt-1">Review traveller reservations, manage transaction payment states, and audit date capacities.</p>
        </div>
        <div class="flex gap-2">
            <span class="text-xs font-semibold uppercase px-3 py-1.5 bg-surface-container-low text-secondary border border-outline-variant/30 rounded-full flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                <?php echo count($bookings); ?> Total Reservations
            </span>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($success_message): ?>
        <div class="p-4 bg-green-50 border border-green-200/50 text-green-800 rounded-xl flex items-center gap-2.5 shadow-sm">
            <span class="material-symbols-outlined text-[20px] text-green-600">check_circle</span>
            <span class="text-sm font-medium"><?php echo $success_message; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="p-4 bg-red-50 border border-red-200/50 text-primary rounded-xl flex items-center gap-2.5 shadow-sm">
            <span class="material-symbols-outlined text-[20px] text-red-600">error</span>
            <span class="text-sm font-medium"><?php echo $error_message; ?></span>
        </div>
    <?php endif; ?>

    <!-- Interactive Filters & Toolbars -->
    <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-4 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-4">
        <!-- Live Search -->
        <div class="relative flex-grow max-w-md">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted text-lg">search</span>
            <input type="text" id="booking-search" class="w-full h-10 pl-10 pr-4 bg-background-light border border-outline-variant/60 rounded-lg text-sm focus:outline-none focus:border-primary transition-all" placeholder="Search by traveller name or email...">
        </div>
        
        <!-- Filter selections -->
        <div class="flex flex-wrap items-center gap-4">
            <!-- Filter by Package -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider whitespace-nowrap">Package:</span>
                <select id="filter-package" class="h-10 px-3 bg-background-light border border-outline-variant/60 rounded-lg text-xs font-semibold text-text-main focus:outline-none focus:border-primary">
                    <option value="all">All Packages</option>
                    <?php foreach ($filter_packages as $fp): ?>
                        <option value="<?php echo $fp['PackageID']; ?>"><?php echo htmlspecialchars($fp['Title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Filter by Payment Status -->
            <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider whitespace-nowrap">Payment:</span>
                <select id="filter-status" class="h-10 px-3 bg-background-light border border-outline-variant/60 rounded-lg text-xs font-semibold text-text-main focus:outline-none focus:border-primary">
                    <option value="all">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Paid">Paid</option>
                    <option value="Failed">Failed</option>
                    <option value="Refunded">Refunded</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Bookings Data List -->
    <?php if (empty($bookings)): ?>
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-12 text-center flex flex-col items-center justify-center gap-4">
            <span class="material-symbols-outlined text-muted text-5xl opacity-40">payments</span>
            <div>
                <h3 class="text-lg font-heading font-semibold text-text-main mb-1">No Bookings Found</h3>
                <p class="text-muted text-sm">When travellers register and buy seats in your scheduled trips, they will appear right here.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs font-body-md text-text-main">
                    <thead>
                        <tr class="bg-surface-container border-b border-outline-variant text-[10px] uppercase font-bold tracking-widest text-muted">
                            <th class="px-6 py-4">ID</th>
                            <th class="px-6 py-4">Traveller Info</th>
                            <th class="px-6 py-4">Itinerary & Trip Dates</th>
                            <th class="px-6 py-4 text-center">Party Size</th>
                            <th class="px-6 py-4">Total Amount</th>
                            <th class="px-6 py-4">Date Reserved</th>
                            <th class="px-6 py-4 text-right">Payment Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/50">
                        <?php foreach ($bookings as $b): ?>
                            <?php 
                            // Determine status badge CSS styles
                            $badge_class = '';
                            switch ($b['PaymentStatus']) {
                                case 'Paid':
                                    $badge_class = 'bg-green-50 text-green-700 border-green-200/60';
                                    break;
                                case 'Pending':
                                    $badge_class = 'bg-amber-50 text-amber-700 border-amber-200/60';
                                    break;
                                case 'Failed':
                                    $badge_class = 'bg-red-50 text-primary border-red-200/60';
                                    break;
                                case 'Refunded':
                                    $badge_class = 'bg-gray-100 text-gray-700 border-gray-200/60';
                                    break;
                            }
                            ?>
                            <tr class="booking-row hover:bg-background-light/50 transition-colors duration-150"
                                data-traveller="<?php echo htmlspecialchars(strtolower($b['FirstName'] . ' ' . $b['LastName'] . ' ' . $b['TravellerEmail'])); ?>"
                                data-package-id="<?php echo $b['Trip_PackageID']; ?>"
                                data-status="<?php echo $b['PaymentStatus']; ?>">
                                
                                <td class="px-6 py-4 font-mono font-semibold text-secondary">
                                    #<?php echo $b['BookingID']; ?>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div class="font-bold text-text-main text-sm">
                                        <?php echo htmlspecialchars($b['FirstName'] . ' ' . $b['LastName']); ?>
                                    </div>
                                    <div class="text-xs text-secondary mt-0.5 font-mono select-all">
                                        <?php echo htmlspecialchars($b['TravellerEmail']); ?>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-text-main max-w-xs truncate" title="<?php echo htmlspecialchars($b['PackageTitle']); ?>">
                                        <?php echo htmlspecialchars($b['PackageTitle']); ?>
                                    </div>
                                    <div class="text-[11px] text-secondary/90 mt-1 flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[14px]">calendar_today</span>
                                        <?php echo date('M d, Y', strtotime($b['StartDate'])); ?> – <?php echo date('M d, Y', strtotime($b['EndDate'])); ?>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 text-center font-bold text-text-main text-sm">
                                    <?php echo $b['PartySize']; ?>
                                </td>
                                
                                <td class="px-6 py-4 font-extrabold text-primary text-sm">
                                    $<?php echo number_format($b['TotalAmount'], 2); ?>
                                </td>
                                
                                <td class="px-6 py-4 text-secondary">
                                    <?php echo date('M d, Y • g:ia', strtotime($b['BookingDate'])); ?>
                                </td>
                                
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <!-- Inline status select box form -->
                                        <form method="POST" action="agency_bookings.php" class="flex items-center gap-2">
                                            <input type="hidden" name="booking_id" value="<?php echo $b['BookingID']; ?>">
                                            <select name="payment_status" onchange="this.form.submit()" class="text-xs font-semibold rounded-lg border border-outline-variant bg-surface text-on-surface py-1 pl-2 pr-7 focus:ring-1 focus:ring-primary focus:border-primary transition-all cursor-pointer">
                                                <option value="Pending" <?php echo $b['PaymentStatus'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Paid" <?php echo $b['PaymentStatus'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                                <option value="Failed" <?php echo $b['PaymentStatus'] === 'Failed' ? 'selected' : ''; ?>>Failed</option>
                                                <option value="Refunded" <?php echo $b['PaymentStatus'] === 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                                            </select>
                                        </form>
                                        
                                        <!-- Status indicator badge -->
                                        <span class="px-2.5 py-0.5 border text-[9px] uppercase font-bold tracking-wider rounded-md <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($b['PaymentStatus']); ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
    const searchInput = document.getElementById('booking-search');
    const filterPackage = document.getElementById('filter-package');
    const filterStatus = document.getElementById('filter-status');
    const rows = document.querySelectorAll('.booking-row');

    function filterBookings() {
        const query = searchInput.value.trim().toLowerCase();
        const pkgVal = filterPackage.value;
        const statusVal = filterStatus.value;

        rows.forEach(row => {
            const travellerInfo = row.getAttribute('data-traveller');
            const packageId = row.getAttribute('data-package-id');
            const status = row.getAttribute('data-status');

            const matchesSearch = travellerInfo.includes(query);
            const matchesPackage = (pkgVal === 'all') || (packageId === pkgVal);
            const matchesStatus = (statusVal === 'all') || (status === statusVal);

            if (matchesSearch && matchesPackage && matchesStatus) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterBookings);
    if (filterPackage) filterPackage.addEventListener('change', filterBookings);
    if (filterStatus) filterStatus.addEventListener('change', filterBookings);
</script>

<?php require_once 'includes/footer.php'; ?>
