<?php
// dashboard.php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'traveller') {
    header("Location: login.html");
    exit();
}

try {
    $stmt = $pdo->query("
        SELECT tp.PackageID, tp.Title, tp.BasePrice, ta.AgencyName 
        FROM TravelPackage tp
        JOIN TravelAgency ta ON tp.AgencyID = ta.UserID
        ORDER BY tp.BasePrice ASC
    ");
    $packages = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching packages: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Tripistry</title>
    <link rel="stylesheet" href="style.css"> 
</head>
<body>
    <header>
        <a href="#" class="logo">Tripistry</a>
        <div class="nav-links">
            <a href="#">My Bookings</a>
            <a href="#">Reviews</a>
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
            <a href="logout.php" class="btn-primary">Logout</a>
        </div>
    </header>

    <main>
        <section class="hero-section">
            <h1>Where to next?</h1>
            
            <div class="category-nav">
                <a href="browse.php?type=accommodations" class="category-item"><div class="category-icon">🏨</div>Hotels</a>
                <a href="browse.php?type=flights" class="category-item"><div class="category-icon">✈️</div>Flights</a>
                <a href="browse.php?type=attractions" class="category-item"><div class="category-icon">📸</div>Attractions</a>
                <a href="browse.php?type=restaurants" class="category-item"><div class="category-icon">🍽️</div>Restaurants</a>
                <a href="browse.php?type=destinations" class="category-item"><div class="category-icon">🌍</div>Destinations</a>
            </div>
        </section>

        <section class="content-section">
            <h2>Available Travel Packages</h2>
            <div class="card-grid">
                
                <?php if (count($packages) > 0): ?>
                    <?php foreach ($packages as $pkg): ?>
                        <div class="card">
                            <div class="card-img">Package Image</div> 
                            <div class="card-info">
                                <div class="card-title"><?php echo htmlspecialchars($pkg['Title']); ?></div>
                                <p style="font-size: 0.9rem; color: #667; margin-bottom: 0.5rem;">
                                    By <?php echo htmlspecialchars($pkg['AgencyName']); ?>
                                </p>
                                <div class="card-price">From R<?php echo number_format($pkg['BasePrice'], 2); ?></div>
                                <button class="btn-primary" style="margin-top: 10px; width: 100%;" 
                                        onclick="viewPackage(<?php echo $pkg['PackageID']; ?>)">
                                    View Details
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No packages available at the moment.</p>
                <?php endif; ?>

            </div>
        </section>
    </main>

    <script>
        function viewPackage(packageId) {
            window.location.href = `package_details.php?id=${packageId}`;
        }
    </script>
</body>
</html>