<?php
$page_title = 'Agency Dashboard';
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
    header("Location: login.php");
    exit;
}

$agency_id = $_SESSION['user_id'];
$error = '';

// Fetch Stats & Info
$stats = [
    'total_packages' => 0,
    'total_bookings' => 0,
    'avg_rating' => 0.00,
    'total_revenue' => 0.00
];

try {
    // 1. Fetch Full Agency Profile
    $stmt = $pdo->prepare("SELECT * FROM TravelAgency WHERE UserID = ?");
    $stmt->execute([$agency_id]);
    $agency_info = $stmt->fetch();
    if ($agency_info) {
        $stats['avg_rating'] = $agency_info['AverageRating'] ?: 0.00;
    }

    // 2. Total Packages
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM TravelPackage WHERE AgencyID = ?");
    $stmt->execute([$agency_id]);
    $stats['total_packages'] = $stmt->fetchColumn();

    // 3. Total Bookings (for this agency's packages)
    $stmt = $pdo->prepare("
        SELECT COUNT(b.BookingID) 
        FROM Booking b
        JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
        WHERE tp.AgencyID = ?
    ");
    $stmt->execute([$agency_id]);
    $stats['total_bookings'] = $stmt->fetchColumn();

    // 3b. Total Revenue (sum of TotalAmount for Paid bookings)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(b.TotalAmount), 0.00) 
        FROM Booking b
        JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
        WHERE tp.AgencyID = ? AND b.PaymentStatus = 'Paid'
    ");
    $stmt->execute([$agency_id]);
    $stats['total_revenue'] = (float)$stmt->fetchColumn();

    // 4. Contacts
    $stmt = $pdo->prepare("SELECT ContactNumber FROM TravelAgency_Contacts WHERE UserID = ?");
    $stmt->execute([$agency_id]);
    $contacts = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 5. Recent Bookings
    $stmt = $pdo->prepare("
        SELECT b.BookingID, b.BookingDate, b.TotalAmount, b.PaymentStatus, b.PartySize, 
               tp.Title AS PackageTitle, t.FirstName, t.LastName
        FROM Booking b
        JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
        JOIN Traveller t ON b.TravellerID = t.UserID
        WHERE tp.AgencyID = ?
        ORDER BY b.BookingDate DESC
        LIMIT 10
    ");
    $stmt->execute([$agency_id]);
    $recent_bookings = $stmt->fetchAll();

    // 6. Active Packages
    $stmt = $pdo->prepare("
        SELECT p.PackageID, p.Title, p.BasePrice, p.DurationDays, p.MaxCapacity,
               (SELECT COUNT(*) FROM GroupTrip gt WHERE gt.PackageID = p.PackageID) AS TotalGroupTrips,
               (SELECT COUNT(b.BookingID) FROM Booking b WHERE b.Trip_PackageID = p.PackageID) AS TotalBookings,
               (SELECT COALESCE(SUM(b.TotalAmount), 0.00) FROM Booking b WHERE b.Trip_PackageID = p.PackageID AND b.PaymentStatus = 'Paid') AS TotalRevenue,
               (SELECT COALESCE(AVG(r.Rating), 0.0) FROM Review r WHERE r.TargetPackageID = p.PackageID) AS AvgPackageRating
        FROM TravelPackage p
        WHERE p.AgencyID = ?
        ORDER BY TotalRevenue DESC, TotalBookings DESC, p.PackageID DESC
    ");
    $stmt->execute([$agency_id]);
    $packages = $stmt->fetchAll();

    // 7. Reviews (Agency Direct + Package Reviews)
    $stmt = $pdo->prepare("
        SELECT r.*, t.FirstName, t.LastName, tp.Title AS PackageTitle
        FROM Review r
        JOIN Traveller t ON r.TravellerID = t.UserID
        LEFT JOIN TravelPackage tp ON r.TargetPackageID = tp.PackageID
        WHERE r.TargetAgencyID = ? OR tp.AgencyID = ?
        ORDER BY r.DatePosted DESC
    ");
    $stmt->execute([$agency_id, $agency_id]);
    $reviews = $stmt->fetchAll();

    // 8. Booking Funnel Status ratios
    $stmtFunnel = $pdo->prepare("
        SELECT b.PaymentStatus, COUNT(b.BookingID) as BookingCount
        FROM Booking b
        JOIN TravelPackage tp ON b.Trip_PackageID = tp.PackageID
        WHERE tp.AgencyID = ?
        GROUP BY b.PaymentStatus
    ");
    $stmtFunnel->execute([$agency_id]);
    $funnel_rows = $stmtFunnel->fetchAll();
    $funnel_data = ['Paid' => 0, 'Pending' => 0, 'Failed' => 0, 'Refunded' => 0];
    foreach ($funnel_rows as $row) {
        $funnel_data[$row['PaymentStatus']] = (int)$row['BookingCount'];
    }

    // 9. Sentiment Analysis Trend (ordered chronological average reviews sentiment score over time)
    $stmtSentimentTrend = $pdo->prepare("
        SELECT DATE_FORMAT(r.DatePosted, '%Y-%m-%d') as PostDate, AVG(r.SentimentScore) as AvgSentiment
        FROM Review r
        LEFT JOIN TravelPackage tp ON r.TargetPackageID = tp.PackageID
        WHERE (r.TargetAgencyID = ? OR tp.AgencyID = ?) AND r.SentimentScore IS NOT NULL
        GROUP BY PostDate
        ORDER BY PostDate ASC
        LIMIT 15
    ");
    $stmtSentimentTrend->execute([$agency_id, $agency_id]);
    $sentiment_rows = $stmtSentimentTrend->fetchAll();
    $sentiment_dates = [];
    $sentiment_scores = [];
    foreach ($sentiment_rows as $row) {
        $sentiment_dates[] = $row['PostDate'];
        $sentiment_scores[] = round((float)$row['AvgSentiment'], 2);
    }

} catch (\PDOException $e) {
    $error = "Failed to load dashboard data: " . $e->getMessage();
}
?>

<div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
    <div>
        <h1 class="font-display-lg text-display-lg text-text-main mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
        <p class="font-body-lg text-body-lg text-secondary">Your travel agency partner control center. Manage packages and track performance.</p>
    </div>
    <a href="create_package.php" class="bg-primary text-on-primary font-button-text text-button-text h-[48px] px-8 rounded-lg flex items-center gap-2 btn-glow transition-all whitespace-nowrap">
        <span class="material-symbols-outlined">add</span>
        Create New Package
    </a>
</div>

<?php if ($error): ?>
    <div class="bg-error-container text-error p-4 rounded-md border border-error mb-6">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<h2 class="font-headline-md text-headline-md text-text-main mb-4">Performance Summary</h2>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <!-- Active Packages Card -->
    <div class="bg-surface p-6 rounded-xl border border-outline-variant flex justify-between items-start shadow-sm hover:border-primary/20 transition-all">
        <div>
            <div class="text-muted font-label-md text-label-md uppercase tracking-wider mb-2">Active Packages</div>
            <div class="font-display-lg text-display-lg text-text-main"><?php echo $stats['total_packages']; ?></div>
        </div>
        <div class="bg-red-50 p-3 rounded-lg text-primary border border-red-100">
            <span class="material-symbols-outlined text-[24px]">package_2</span>
        </div>
    </div>
    
    <!-- Total Bookings Card -->
    <div class="bg-surface p-6 rounded-xl border border-outline-variant flex justify-between items-start shadow-sm hover:border-accent/20 transition-all">
        <div>
            <div class="text-muted font-label-md text-label-md uppercase tracking-wider mb-2">Total Bookings</div>
            <div class="font-display-lg text-display-lg text-text-main"><?php echo $stats['total_bookings']; ?></div>
        </div>
        <div class="bg-indigo-50 p-3 rounded-lg text-indigo-700 border border-indigo-100">
            <span class="material-symbols-outlined text-[24px]">receipt_long</span>
        </div>
    </div>

    <!-- Total Revenue Card -->
    <div class="bg-surface p-6 rounded-xl border border-outline-variant flex justify-between items-start shadow-sm hover:border-green-500/20 transition-all">
        <div>
            <div class="text-muted font-label-md text-label-md uppercase tracking-wider mb-2">Paid Revenue</div>
            <div class="font-display-lg text-display-lg text-green-700 font-extrabold">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
        </div>
        <div class="bg-green-50 p-3 rounded-lg text-green-700 border border-green-100">
            <span class="material-symbols-outlined text-[24px]">payments</span>
        </div>
    </div>
    
    <!-- Average Rating Card -->
    <div class="bg-surface p-6 rounded-xl border border-outline-variant flex justify-between items-start shadow-sm hover:border-amber-500/20 transition-all">
        <div>
            <div class="text-muted font-label-md text-label-md uppercase tracking-wider mb-2">Average Rating</div>
            <div class="font-display-lg text-display-lg text-text-main"><?php echo number_format($stats['avg_rating'], 2); ?> / 5</div>
        </div>
        
        <div class="bg-amber-50 p-3 rounded-lg text-amber-600 border border-amber-100">
            <span class="material-symbols-outlined text-[24px] fill-1" style="font-variation-settings: 'FILL' 1;">star</span>
        </div>
    </div>
</div>

<!-- Business Performance Analytics (Chart.js Integrations) -->
<h2 class="font-heading font-semibold text-text-main text-lg mb-4 flex items-center gap-2">
    <span class="material-symbols-outlined text-primary text-[24px]">analytics</span>
    Business Performance Analytics
</h2>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Doughnut Chart: Revenue Share -->
    <div class="bg-surface rounded-xl border border-outline-variant p-5 shadow-sm bg-white flex flex-col gap-4">
        <h3 class="text-xs font-bold text-text-main uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-outline-variant/30">
            <span class="material-symbols-outlined text-primary text-[18px]">donut_small</span>
            Revenue Share By Package
        </h3>
        <div class="flex-grow flex items-center justify-center min-h-[220px]">
            <canvas id="revenueShareChart" class="max-h-[220px]"></canvas>
        </div>
    </div>

    <!-- Line Chart: Sentiment Trend -->
    <div class="bg-surface rounded-xl border border-outline-variant p-5 shadow-sm bg-white flex flex-col gap-4">
        <h3 class="text-xs font-bold text-text-main uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-outline-variant/30">
            <span class="material-symbols-outlined text-primary text-[18px]">trending_up</span>
            Review Sentiment Timeline
        </h3>
        <div class="flex-grow flex items-center justify-center min-h-[220px]">
            <canvas id="sentimentTimelineChart" class="max-h-[220px]"></canvas>
        </div>
    </div>

    <!-- Bar Chart: Booking Funnel -->
    <div class="bg-surface rounded-xl border border-outline-variant p-5 shadow-sm bg-white flex flex-col gap-4">
        <h3 class="text-xs font-bold text-text-main uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-outline-variant/30">
            <span class="material-symbols-outlined text-primary text-[18px]">bar_chart</span>
            Booking Funnel Performance
        </h3>
        <div class="flex-grow flex items-center justify-center min-h-[220px]">
            <canvas id="bookingFunnelChart" class="max-h-[220px]"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Compile Revenue Data from PHP packages
    <?php
    $package_titles = [];
    $package_revenues = [];
    foreach ($packages as $pkg) {
        if ($pkg['TotalRevenue'] > 0) {
            $package_titles[] = htmlspecialchars($pkg['Title']);
            $package_revenues[] = (float)$pkg['TotalRevenue'];
        }
    }
    // If empty revenue, supply defaults or mock indicators
    if (empty($package_revenues)) {
        $package_titles = ['No Revenue Generated Yet'];
        $package_revenues = [0];
    }
    ?>
    const revenueLabels = <?php echo json_encode($package_titles); ?>;
    const revenueValues = <?php echo json_encode($package_revenues); ?>;
    
    // 2. Compile Booking Funnel from PHP
    const funnelData = <?php echo json_encode($funnel_data); ?>;
    
    // 3. Compile Sentiment Trend from PHP
    const sentimentDates = <?php echo json_encode($sentiment_dates); ?>;
    const sentimentScores = <?php echo json_encode($sentiment_scores); ?>;

    // Charts Font Family Styling
    Chart.defaults.font.family = "'Manrope', sans-serif";
    Chart.defaults.color = "#485f84";

    // Initialize Revenue Share Doughnut Chart
    new Chart(document.getElementById('revenueShareChart'), {
        type: 'doughnut',
        data: {
            labels: revenueLabels,
            datasets: [{
                data: revenueValues,
                backgroundColor: ['#b7102a', '#457B9D', '#2A9D8F', '#F4A261', '#E76F51', '#9B5DE5', '#F15BB5'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        padding: 6,
                        font: { size: 9 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.label + ': $' + context.raw.toLocaleString(undefined, {minimumFractionDigits: 2});
                        }
                    }
                }
            }
        }
    });

    // Initialize Sentiment Timeline Line Chart
    new Chart(document.getElementById('sentimentTimelineChart'), {
        type: 'line',
        data: {
            labels: sentimentDates.length > 0 ? sentimentDates : ['No Reviews'],
            datasets: [{
                label: 'Avg Sentiment (-1 to +1)',
                data: sentimentScores.length > 0 ? sentimentScores : [0],
                borderColor: '#2A9D8F',
                backgroundColor: 'rgba(42, 157, 143, 0.1)',
                fill: true,
                tension: 0.3,
                borderWidth: 2,
                pointRadius: 4,
                pointBackgroundColor: '#2A9D8F'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    min: -1.0,
                    max: 1.0,
                    grid: { color: 'rgba(143, 111, 110, 0.1)' }
                },
                x: {
                    grid: { display: false }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });

    // Initialize Booking Funnel Bar Chart
    new Chart(document.getElementById('bookingFunnelChart'), {
        type: 'bar',
        data: {
            labels: ['Paid', 'Pending', 'Failed', 'Refunded'],
            datasets: [{
                data: [funnelData.Paid, funnelData.Pending, funnelData.Failed, funnelData.Refunded],
                backgroundColor: ['#2A9D8F', '#F4A261', '#b7102a', '#457B9D'],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: 'rgba(143, 111, 110, 0.1)' }
                },
                x: {
                    grid: { display: false }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
});
</script>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">
    <!-- Active Packages Table (2/3 width) -->
    <div class="xl:col-span-2 bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm flex flex-col">
        <div class="px-6 py-5 border-b border-outline-variant flex justify-between items-center bg-white">
            <h2 class="font-headline-md text-headline-md text-text-main">Your Active Packages</h2>
            <span class="text-xs font-semibold uppercase px-2.5 py-1 bg-surface-container-low text-secondary rounded-full"><?php echo count($packages); ?> Packages</span>
        </div>
        
        <div class="overflow-x-auto bg-white flex-grow">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-surface-container-low text-secondary font-label-md text-[10px] uppercase tracking-widest border-b border-outline-variant">
                        <th class="px-6 py-4">Package</th>
                        <th class="px-6 py-4">Base Price</th>
                        <th class="px-6 py-4 text-center">Bookings</th>
                        <th class="px-6 py-4">Revenue</th>
                        <th class="px-6 py-4 text-center">Rating</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant text-body-md text-xs">
                    <?php if (empty($packages)): ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-muted">No packages found. <a href="create_package.php" class="text-primary hover:underline font-bold">Create one</a>.</td></tr>
                    <?php else: ?>
                        <?php foreach ($packages as $pkg): ?>
                            <tr class="hover:bg-background-light transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-text-main text-sm hover:text-primary transition-colors">
                                        <a href="edit_package.php?id=<?php echo $pkg['PackageID']; ?>"><?php echo htmlspecialchars($pkg['Title']); ?></a>
                                    </div>
                                    <div class="text-[11px] text-secondary mt-1 flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-[14px]">schedule</span>
                                        <?php echo htmlspecialchars($pkg['DurationDays']); ?> Days • Max Cap: <?php echo htmlspecialchars($pkg['MaxCapacity']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 font-bold text-text-main">$<?php echo number_format($pkg['BasePrice'], 2); ?></td>
                                <td class="px-6 py-4 text-center font-bold text-secondary-indigo">
                                    <?php echo $pkg['TotalBookings']; ?>
                                </td>
                                <td class="px-6 py-4 font-extrabold text-green-700">
                                    $<?php echo number_format($pkg['TotalRevenue'], 2); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-center gap-1">
                                        <span class="material-symbols-outlined text-amber-500 text-[14px] fill-1" style="font-variation-settings: 'FILL' 1;">star</span>
                                        <span class="font-bold text-text-main"><?php echo number_format($pkg['AvgPackageRating'], 1); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right flex justify-end gap-3 items-center">
                                    <a href="edit_package.php?id=<?php echo $pkg['PackageID']; ?>" class="text-secondary hover:text-primary transition-colors flex items-center justify-center p-1.5 hover:bg-surface-container rounded" title="Edit Package">
                                        <span class="material-symbols-outlined text-[20px]">edit_note</span>
                                    </a>
                                    <form method="POST" action="delete_package.php" onsubmit="return confirm('Are you absolutely sure you want to delete this package? All associated M:N relations and bookings will be modified.');" class="inline">
                                        <input type="hidden" name="package_id" value="<?php echo $pkg['PackageID']; ?>">
                                        <button type="submit" class="text-secondary hover:text-error transition-colors flex items-center justify-center p-1.5 hover:bg-error-container/20 rounded" title="Delete Package">
                                            <span class="material-symbols-outlined text-[20px]">delete</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Bookings sidebar -->
    <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm flex flex-col">
        <div class="px-6 py-5 border-b border-outline-variant bg-white flex justify-between items-center">
            <h2 class="font-headline-md text-headline-md text-text-main">Recent Bookings</h2>
            <a href="agency_bookings.php" class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                View All <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
            </a>
        </div>
        <div class="p-0 bg-white flex-grow overflow-y-auto max-h-[450px]">
            <?php if (empty($recent_bookings)): ?>
                <div class="p-6 text-center text-muted">No recent bookings.</div>
            <?php else: ?>
                <ul class="divide-y divide-outline-variant">
                    <?php foreach ($recent_bookings as $booking): ?>
                        <li class="px-6 py-4 hover:bg-background-light transition-colors">
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-bold text-text-main text-sm"><?php echo htmlspecialchars($booking['FirstName'] . ' ' . $booking['LastName']); ?></span>
                                <span class="text-[11px] font-bold text-secondary"><?php echo date('M d, g:ia', strtotime($booking['BookingDate'])); ?></span>
                            </div>
                            <div class="text-xs text-muted italic line-clamp-2">Booked: <?php echo htmlspecialchars($booking['PackageTitle']); ?> (x<?php echo $booking['PartySize']; ?>)</div>
                            <div class="mt-2 flex justify-between items-center">
                                <span class="text-sm font-bold text-accent">$<?php echo number_format($booking['TotalAmount'], 2); ?></span>
                                <?php if ($booking['PaymentStatus'] === 'Paid'): ?>
                                    <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded text-[9px] uppercase font-bold tracking-wider"><?php echo htmlspecialchars($booking['PaymentStatus']); ?></span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded text-[9px] uppercase font-bold tracking-wider"><?php echo htmlspecialchars($booking['PaymentStatus']); ?></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    <!-- Customer Reviews (2/3 width) -->
    <div class="xl:col-span-2 bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm flex flex-col">
        <div class="px-6 py-5 border-b border-outline-variant bg-white flex justify-between items-center">
            <h2 class="font-headline-md text-headline-md text-text-main">Customer Feedback & Reviews</h2>
            <span class="text-xs font-semibold uppercase px-2.5 py-1 bg-surface-container-low text-secondary rounded-full"><?php echo count($reviews); ?> Reviews</span>
        </div>
        
        <div class="p-6 bg-white flex-grow">
            <?php if (empty($reviews)): ?>
                <div class="text-center py-8 text-muted font-body-md">No customer reviews received yet. Reviews submitted for your packages will display here.</div>
            <?php else: ?>
                <div class="flex flex-col gap-6">
                    <?php foreach ($reviews as $rev): ?>
                        <div class="p-5 rounded-lg border border-outline-variant bg-background-light flex flex-col gap-3 shadow-sm hover:border-primary/30 transition-all">
                            <div class="flex justify-between items-start flex-wrap gap-2">
                                <div>
                                    <span class="font-bold text-text-main"><?php echo htmlspecialchars($rev['FirstName'] . ' ' . $rev['LastName']); ?></span>
                                    <span class="text-xs text-muted block mt-0.5">Reviewed on: <?php echo date('M d, Y', strtotime($rev['DatePosted'])); ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <!-- Stars -->
                                    <div class="flex text-amber-500">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <span class="material-symbols-outlined text-[18px] <?php echo $i <= $rev['Rating'] ? 'fill-1' : ''; ?>" style="font-variation-settings: 'FILL' <?php echo $i <= $rev['Rating'] ? '1' : '0'; ?>;">star</span>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <!-- Sentiment Score Badge -->
                                    <?php if ($rev['SentimentScore'] !== null): ?>
                                        <?php 
                                            $sentiment = (float)$rev['SentimentScore'];
                                            if ($sentiment > 0.1) {
                                                $badgeClass = 'bg-green-100 text-green-800 border-green-200';
                                                $label = 'Positive (' . number_format($sentiment, 2) . ')';
                                            } else if ($sentiment < -0.1) {
                                                $badgeClass = 'bg-red-100 text-primary border-red-200';
                                                $label = 'Negative (' . number_format($sentiment, 2) . ')';
                                            } else {
                                                $badgeClass = 'bg-gray-100 text-gray-800 border-gray-200';
                                                $label = 'Neutral (' . number_format($sentiment, 2) . ')';
                                            }
                                        ?>
                                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded border <?php echo $badgeClass; ?>"><?php echo $label; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <p class="text-sm text-text-main/80 italic leading-relaxed">"<?php echo htmlspecialchars($rev['Comment'] ?: 'No comment provided.'); ?>"</p>
                            
                            <?php if ($rev['PackageTitle']): ?>
                                <div class="text-xs text-primary font-bold mt-1 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">backpack</span>
                                    Package: <?php echo htmlspecialchars($rev['PackageTitle']); ?>
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-accent font-bold mt-1 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">domain</span>
                                    Direct Agency Review
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Agency Profile / Contact Sidebar (1/3 width) -->
    <div class="bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-sm flex flex-col">
        <div class="px-6 py-5 border-b border-outline-variant bg-white flex justify-between items-center">
            <h2 class="font-headline-md text-headline-md text-text-main">Agency Details</h2>
            <a href="agency_profile.php" class="text-xs font-bold text-primary hover:underline flex items-center gap-1">
                Edit Profile <span class="material-symbols-outlined text-[14px]">edit</span>
            </a>
        </div>
        <div class="p-6 bg-white flex-grow flex flex-col gap-6">
            <?php if ($agency_info): ?>
                <div class="flex flex-col gap-4 text-sm font-body-md text-text-main">
                    <div>
                        <div class="text-xs text-muted uppercase tracking-widest font-bold mb-1">Registration Number</div>
                        <div class="font-medium bg-background-light p-2.5 rounded border border-outline-variant select-all font-mono"><?php echo htmlspecialchars($agency_info['RegistrationNumber']); ?></div>
                    </div>
                    
                    <div>
                        <div class="text-xs text-muted uppercase tracking-widest font-bold mb-1">Business Address</div>
                        <div class="bg-background-light p-3 rounded border border-outline-variant flex flex-col gap-1.5">
                            <div class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-[18px] text-muted">pin_drop</span>
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($agency_info['Address_Street']); ?></p>
                                    <p><?php echo htmlspecialchars($agency_info['Address_City']); ?>, <?php echo htmlspecialchars($agency_info['Address_Zip']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="text-xs text-muted uppercase tracking-widest font-bold mb-1">Contact Phone Numbers</div>
                        <div class="bg-background-light p-3 rounded border border-outline-variant flex flex-col gap-2">
                            <?php if (empty($contacts)): ?>
                                <span class="text-muted italic">No contact numbers defined.</span>
                            <?php else: ?>
                                <?php foreach ($contacts as $contact): ?>
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[18px] text-muted">call</span>
                                        <span class="font-mono"><?php echo htmlspecialchars($contact); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <span class="text-muted italic">Agency profile information not found.</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>