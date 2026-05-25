<?php
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
    header("Location: login.php");
    exit;
}

$page_title = 'Manage Packages';
require_once 'includes/header.php';


$agency_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Handle package deletion directly from the list if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_package_id'])) {
    $del_id = (int)$_POST['delete_package_id'];
    try {
        // Double-check ownership
        $chk = $pdo->prepare("SELECT PackageID FROM TravelPackage WHERE PackageID = ? AND AgencyID = ?");
        $chk->execute([$del_id, $agency_id]);
        if ($chk->fetch()) {
            $pdo->beginTransaction();
            // Delete references in M:N tables (these are ON DELETE CASCADE usually, but let's be transaction-safe)
            $pdo->prepare("DELETE FROM Package_Destination WHERE PackageID = ?")->execute([$del_id]);
            $pdo->prepare("DELETE FROM Package_Accommodation WHERE PackageID = ?")->execute([$del_id]);
            $pdo->prepare("DELETE FROM Package_Flight WHERE PackageID = ?")->execute([$del_id]);
            $pdo->prepare("DELETE FROM Package_Attraction WHERE PackageID = ?")->execute([$del_id]);
            $pdo->prepare("DELETE FROM Package_Restaurant WHERE PackageID = ?")->execute([$del_id]);
            $pdo->prepare("DELETE FROM GroupTrip WHERE PackageID = ?")->execute([$del_id]);
            $pdo->prepare("DELETE FROM TravelPackage WHERE PackageID = ?")->execute([$del_id]);
            $pdo->commit();
            $success_message = "Travel package deleted successfully.";
        } else {
            $error_message = "Unauthorized action or package does not exist.";
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error_message = "Failed to delete package: " . $e->getMessage();
    }
}

// Fetch all packages for the agency
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM GroupTrip gt WHERE gt.PackageID = p.PackageID) AS TotalGroupTrips,
               (SELECT COUNT(b.BookingID) FROM Booking b WHERE b.Trip_PackageID = p.PackageID) AS TotalBookings
        FROM TravelPackage p
        WHERE p.AgencyID = ?
        ORDER BY p.PackageID DESC
    ");
    $stmt->execute([$agency_id]);
    $packages = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error_message = "Failed to load packages: " . $e->getMessage();
}
?>

<style>
    /* Premium button sizes & overrides */
    .btn-premium-sm {
        height: 36px;
        padding-left: 1rem;
        padding-right: 1rem;
        background: linear-gradient(135deg, #b7102a 0%, #db313f 100%);
        color: #FFFFFF;
        border-radius: 0.5rem; /* rounded-lg */
        font-weight: 600;
        font-size: 12px;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px 0 rgba(183, 16, 42, 0.2);
    }
    .btn-premium-sm:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 14px 0 rgba(183, 16, 42, 0.3);
    }
    .btn-premium-secondary-sm {
        height: 36px;
        padding-left: 1rem;
        padding-right: 1rem;
        background-color: transparent;
        color: #1D3557;
        border: 1px solid rgba(228, 190, 188, 0.7);
        border-radius: 0.5rem; /* rounded-lg */
        font-weight: 600;
        font-size: 12px;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-premium-secondary-sm:hover {
        background-color: rgba(183, 16, 42, 0.04);
        border-color: #b7102a;
        color: #b7102a;
        transform: translateY(-1px);
    }
    /* Input premium overrides */
    .input-field-premium {
        background: rgba(255, 255, 255, 0.7) !important;
        border: 1px solid rgba(228, 190, 188, 0.6) !important;
        transition: all 0.2s ease-in-out;
    }
    .input-field-premium:focus {
        background: #ffffff !important;
        border-color: #b7102a !important;
        box-shadow: 0 0 0 4px rgba(183, 16, 42, 0.1) !important;
    }
</style>

<div class="flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-3xl font-heading font-semibold text-text-main">Your Catalog</h1>
            <p class="text-muted mt-1">Manage, update, and schedule all travel packages provided by your agency.</p>
        </div>
        <a href="create_package.php" class="btn-premium h-[44px] text-sm flex items-center gap-2 transition-all whitespace-nowrap">
            <span class="material-symbols-outlined text-[18px]">add</span>
            Create Package
        </a>
    </div>

    <?php if ($success_message): ?>
        <div class="p-4 bg-green-50/80 backdrop-blur-md border border-green-200 text-green-800 rounded-2xl flex items-center gap-2.5 shadow-sm">
            <span class="material-symbols-outlined text-[20px] text-green-600">check_circle</span>
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="p-4 bg-red-50/80 backdrop-blur-md border border-red-200 text-red-800 rounded-2xl flex items-center gap-2.5 shadow-sm">
            <span class="material-symbols-outlined text-[20px] text-red-600">error</span>
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <!-- Toolbar Filters -->
    <div class="glass-card p-4 flex flex-col md:flex-row justify-between items-stretch md:items-center gap-4">
        <div class="relative flex-grow max-w-md">
            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-muted text-lg">search</span>
            <input type="text" id="package-search" class="w-full h-10 pl-11 pr-4 input-field input-field-premium rounded-lg text-sm transition-all" placeholder="Search by package name or description...">
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs font-bold text-secondary uppercase tracking-wider">Show:</span>
            <select id="package-filter" class="h-10 px-3 input-field input-field-premium rounded-lg text-xs font-bold text-text-main">
                <option value="all">All Packages</option>
                <option value="active-trips">With Scheduled Dates</option>
                <option value="no-trips">No Dates Scheduled</option>
            </select>
        </div>
    </div>

    <!-- Package Grid/List -->
    <?php if (empty($packages)): ?>
        <div class="glass-card p-12 text-center flex flex-col items-center justify-center gap-4">
            <span class="material-symbols-outlined text-muted text-5xl opacity-40">inventory_2</span>
            <div>
                <h3 class="text-lg font-heading font-semibold text-text-main mb-1">Your Catalog is Empty</h3>
                <p class="text-muted text-sm">You haven't designed any travel packages yet. Let's create your first adventure!</p>
            </div>
            <a href="create_package.php" class="btn-premium h-[40px] px-6 text-sm hover:text-white mt-2">Create Package</a>
        </div>
    <?php else: ?>
        <div id="catalog-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($packages as $pkg): ?>
                <div class="package-card glass-card card-hover overflow-hidden flex flex-col justify-between" 
                     data-title="<?php echo htmlspecialchars(strtolower($pkg['Title'])); ?>"
                     data-desc="<?php echo htmlspecialchars(strtolower($pkg['Description'])); ?>"
                     data-trips="<?php echo $pkg['TotalGroupTrips']; ?>">
                    
                    <!-- Header -->
                    <div class="p-5 bg-white/40 border-b border-outline-variant/15">
                        <div class="flex justify-between items-start gap-3 mb-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-0.5 bg-secondary-fixed text-primary rounded-full">
                                ID: #<?php echo $pkg['PackageID']; ?>
                            </span>
                            <span class="text-xs font-bold text-secondary">
                                <?php echo $pkg['DurationDays']; ?> Days
                            </span>
                        </div>
                        <h3 class="text-lg font-heading font-semibold text-text-main line-clamp-1 hover:text-primary transition-colors">
                            <a href="edit_package.php?id=<?php echo $pkg['PackageID']; ?>"><?php echo htmlspecialchars($pkg['Title']); ?></a>
                        </h3>
                    </div>

                    <!-- Details body -->
                    <div class="p-5 flex-grow flex flex-col gap-4 text-xs font-body-md text-text-main">
                        <p class="text-muted/90 line-clamp-3 leading-relaxed"><?php echo htmlspecialchars($pkg['Description'] ?: 'No description provided.'); ?></p>
                        
                        <div class="grid grid-cols-2 gap-4 border-t border-b border-outline-variant/15 py-3 my-1">
                            <div>
                                <span class="text-[10px] text-muted uppercase tracking-wider font-bold">Base Cost</span>
                                <p class="text-sm font-bold text-primary mt-0.5"><?php echo formatCurrency($pkg['BasePrice']); ?></p>
                            </div>
                            <div>
                                <span class="text-[10px] text-muted uppercase tracking-wider font-bold">Max Capacity</span>
                                <p class="text-sm font-bold text-text-main mt-0.5"><?php echo $pkg['MaxCapacity']; ?> guests</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <div class="flex justify-between items-center text-on-surface-variant font-medium">
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-muted">calendar_month</span> Scheduled Dates</span>
                                <span class="font-bold text-text-main"><?php echo $pkg['TotalGroupTrips']; ?></span>
                            </div>
                            <div class="flex justify-between items-center text-on-surface-variant font-medium">
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-muted">shopping_cart</span> Total Bookings</span>
                                <span class="font-bold text-text-main"><?php echo $pkg['TotalBookings']; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="p-5 border-t border-outline-variant/15 bg-white/40 flex gap-3">
                        <a href="edit_package.php?id=<?php echo $pkg['PackageID']; ?>" class="flex-grow h-9 btn-premium-secondary-sm">
                            <span class="material-symbols-outlined text-[16px]">edit</span>
                            Edit & Schedule
                        </a>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this package? All linked items and scheduled group trips will be lost.');">
                            <input type="hidden" name="delete_package_id" value="<?php echo $pkg['PackageID']; ?>">
                            <button type="submit" class="w-9 h-9 bg-white/60 border border-outline-variant/60 rounded-lg text-secondary hover:text-error hover:border-error/30 flex items-center justify-center transition-all shadow-sm">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </form>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    const searchInput = document.getElementById('package-search');
    const filterSelect = document.getElementById('package-filter');
    const cards = document.querySelectorAll('.package-card');

    function performFiltering() {
        const query = searchInput.value.trim().toLowerCase();
        const filterVal = filterSelect.value;

        cards.forEach(card => {
            const title = card.getAttribute('data-title');
            const desc = card.getAttribute('data-desc');
            const tripsCount = parseInt(card.getAttribute('data-trips'));

            const matchesSearch = title.includes(query) || desc.includes(query);
            let matchesFilter = true;

            if (filterVal === 'active-trips') {
                matchesFilter = tripsCount > 0;
            } else if (filterVal === 'no-trips') {
                matchesFilter = tripsCount === 0;
            }

            if (matchesSearch && matchesFilter) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', performFiltering);
    if (filterSelect) filterSelect.addEventListener('change', performFiltering);
</script>

<?php require_once 'includes/footer.php'; ?>
