<?php
// browse.php
session_start();
require_once 'db.php'; 

$category = isset($_GET['type']) ? $_GET['type'] : 'destinations';

$pageTitle = "Browse ";
$items = [];

try {
    switch ($category) {
        case 'flights':
            $pageTitle .= "Flights";
            $stmt = $pdo->query("SELECT FlightID as ID, CONCAT(Airline, ' - ', FlightNum) as Title, CONCAT(DepAirport_Code, ' to ', ArrAirport_Code) as Description, Cost as Price FROM Flight ORDER BY DepTime ASC");
            break;
            
        case 'accommodations':
            $pageTitle .= "Accommodations";
            $stmt = $pdo->query("SELECT AccommID as ID, Name as Title, CONCAT(Type, ' - ', StarRating, ' Stars') as Description, PricePerNight as Price FROM Accommodation ORDER BY StarRating DESC");
            break;
            
        case 'restaurants':
            $pageTitle .= "Restaurants";
            $stmt = $pdo->query("SELECT RestaurantID as ID, Name as Title, CuisineType as Description, AverageCost as Price FROM Restaurant ORDER BY AverageCost ASC");
            break;
            
        case 'attractions':
            $pageTitle .= "Attractions";
            $stmt = $pdo->query("SELECT AttractionID as ID, Name as Title, Category as Description, EntryFee as Price FROM Attraction");
            break;

        case 'destinations':
        default:
            $pageTitle .= "Destinations";
            $stmt = $pdo->query("SELECT DestID as ID, Name as Title, CONCAT(Region, ', ', Country) as Description, 0 as Price FROM Destination ORDER BY PopularityScore DESC");
            break;
    }
    
    $items = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Tripistry</title>
    <link rel="stylesheet" href="styles.css"> </head>
<body>
    <header>
        <a href="dashboard.php" class="logo">Tripistry</a>
        <div class="nav-links">
            <a href="dashboard.php">Back to Dashboard</a>
        </div>
    </header>

    <main>
        <section class="hero-section" style="padding: 2rem; background-color: var(--primary-blue); color: white;">
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        </section>

        <section class="content-section">
            <div class="card-grid">
                
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $item): ?>
                        <div class="card">
                            <div class="card-img" style="background-color: #e0f0ff; height: 150px; display: flex; align-items: center; justify-content: center;">
                                📷 <?php echo htmlspecialchars($category); ?> Image
                            </div> 
                            <div class="card-info">
                                <div class="card-title"><?php echo htmlspecialchars($item['Title']); ?></div>
                                <p style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($item['Description']); ?>
                                </p>
                                
                                <?php if ($item['Price'] > 0): ?>
                                    <div class="card-price">Avg R<?php echo number_format($item['Price'], 2); ?></div>
                                <?php endif; ?>
                                
                                <button class="btn-primary" style="margin-top: 10px; width: 100%;">View Details</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No records found in this category.</p>
                <?php endif; ?>

            </div>
        </section>
    </main>
</body>
</html>