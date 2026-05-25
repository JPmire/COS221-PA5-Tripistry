<?php
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Traveller') {
    header("Location: login.php");
    exit;
}

$page_title = 'Explore Agencies';
require_once 'includes/header.php';


$traveller_id = $_SESSION['user_id'];
$agency_id = isset($_GET['agency_id']) ? (int)$_GET['agency_id'] : 0;

$error = '';
$success = '';

// Handle Review Submission for Agency
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'leave_agency_review') {
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = "Please choose a rating between 1 and 5 stars.";
    } elseif (empty($comment)) {
        $error = "Please write a review comment.";
    } else {
        $envPath = __DIR__ . '/.env';
        $apiKey = file_exists($envPath) ? parse_ini_file($envPath)['GEMINI_API_KEY'] ?? '' : '';

        $sentimentScore = 0.00; 

        if (!empty($apiKey)) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
            
            $prompt = "Analyze the sentiment of the following travel agency review. Return ONLY a valid JSON object with a single key 'sentiment_score' containing a float from -1.00 (very negative) to 1.00 (very positive). Review: " . $comment;

            $payload = json_encode([
                "contents" => [
                    ["parts" => [["text" => $prompt]]]
                ],
                "generationConfig" => [
                    "response_mime_type" => "application/json"
                ]
            ]);

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $responseData = json_decode($response, true);
                if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                    $aiText = $responseData['candidates'][0]['content']['parts'][0]['text'];
                    $aiJson = json_decode($aiText, true);
                    
                    if (isset($aiJson['sentiment_score'])) {
                        $sentimentScore = (float)$aiJson['sentiment_score'];
                    }
                }
            }
        }

        try {
            $pdo->beginTransaction();

            // Fetch composite key ReviewID for this traveler
            $stmtNext = $pdo->prepare("SELECT COALESCE(MAX(ReviewID), 0) + 1 FROM Review WHERE TravellerID = ?");
            $stmtNext->execute([$traveller_id]);
            $nextReviewId = $stmtNext->fetchColumn();

            // Insert direct agency review
            $stmtIns = $pdo->prepare("
                INSERT INTO Review (TravellerID, ReviewID, Rating, Comment, SentimentScore, TargetAgencyID) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtIns->execute([$traveller_id, $nextReviewId, $rating, $comment, $sentimentScore, $agency_id]);

            // Recompute dynamic AverageRating representing both direct agency reviews and indirect package reviews
            $stmtAvg = $pdo->prepare("
                SELECT AVG(Rating) 
                FROM Review r
                LEFT JOIN TravelPackage tp ON r.TargetPackageID = tp.PackageID
                WHERE r.TargetAgencyID = ? OR tp.AgencyID = ?
            ");
            $stmtAvg->execute([$agency_id, $agency_id]);
            $newAvg = $stmtAvg->fetchColumn() ?: 0.00;

            // Save new average rating
            $stmtUp = $pdo->prepare("UPDATE TravelAgency SET AverageRating = ? WHERE UserID = ?");
            $stmtUp->execute([$newAvg, $agency_id]);

            $pdo->commit();
            $success = "Review submitted successfully! Our engine sentiment analyzed your words.";
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Database transaction failed: " . $e->getMessage();
        }
    }
}

try {
    if ($agency_id > 0) {
        // ==========================================
        // SINGLE AGENCY PROFILE DETAIL VIEW
        // ==========================================
        
        // Fetch Agency core details
        $stmt = $pdo->prepare("SELECT * FROM TravelAgency WHERE UserID = ?");
        $stmt->execute([$agency_id]);
        $agency = $stmt->fetch();

        if (!$agency) {
            $error = "Agency partner details not found.";
        } else {
            // Fetch contacts
            $stmtContacts = $pdo->prepare("SELECT ContactNumber FROM TravelAgency_Contacts WHERE UserID = ?");
            $stmtContacts->execute([$agency_id]);
            $contacts = $stmtContacts->fetchAll(PDO::FETCH_COLUMN);

            // Fetch active packages
            $stmtPkgs = $pdo->prepare("SELECT * FROM TravelPackage WHERE AgencyID = ? ORDER BY BasePrice ASC");
            $stmtPkgs->execute([$agency_id]);
            $packages = $stmtPkgs->fetchAll();

            // Fetch Reviews left specifically for this agency
            $stmtReviews = $pdo->prepare("
                SELECT r.*, t.FirstName, t.LastName 
                FROM Review r
                JOIN Traveller t ON r.TravellerID = t.UserID
                WHERE r.TargetAgencyID = ?
                ORDER BY r.DatePosted DESC
            ");
            $stmtReviews->execute([$agency_id]);
            $reviews = $stmtReviews->fetchAll();
        }
    } else {
        // ==========================================
        // ALL AGENCIES LIST DIRECTORY VIEW
        // ==========================================
        
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $ratingFilter = isset($_GET['rating']) ? (float)$_GET['rating'] : 0.0;

        $query = "
            SELECT ta.*, 
                   (SELECT GROUP_CONCAT(ContactNumber SEPARATOR ', ') FROM TravelAgency_Contacts WHERE UserID = ta.UserID) AS ContactsList,
                   (SELECT COUNT(*) FROM TravelPackage WHERE AgencyID = ta.UserID) AS PackageCount
            FROM TravelAgency ta
            WHERE 1=1
        ";
        $params = [];

        if (!empty($search)) {
            $query .= " AND (ta.AgencyName LIKE ? OR ta.Address_City LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($ratingFilter > 0) {
            $query .= " AND ta.AverageRating >= ?";
            $params[] = $ratingFilter;
        }

        $query .= " ORDER BY ta.AverageRating DESC, ta.AgencyName ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $agencies = $stmt->fetchAll();
    }
} catch (\PDOException $e) {
    $error = "Query execution error: " . $e->getMessage();
}
?>

<div class="max-w-6xl mx-auto flex flex-col gap-6">

    <?php if ($agency_id > 0 && isset($agency) && $agency): ?>
        
        <!-- ==========================================
             SINGLE AGENCY DETAIL INTERFACE
             ========================================== -->
        
        <!-- Back navigation bar -->
        <div class="flex justify-between items-center pb-2">
            <a href="traveller_agencies.php" class="text-xs font-bold text-secondary hover:text-primary flex items-center gap-1 transition-all">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Back to Directory
            </a>
            <span class="text-xs font-mono text-muted">Agency ID: partner_<?php echo $agency['UserID']; ?></span>
        </div>

        <?php if ($error && empty($success)): ?>
            <div class="p-4 bg-red-50 border border-red-200 text-primary rounded-xl flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-red-600">error</span>
                <span class="text-sm font-semibold"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-xl flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-green-600">check_circle</span>
                <span class="text-sm font-semibold"><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Hero Header Block -->
        <div class="bg-surface rounded-2xl border border-outline-variant p-6 lg:p-8 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div class="flex items-center gap-5">
                <div class="w-16 h-16 rounded-2xl bg-primary-fixed text-primary border-2 border-outline-variant flex items-center justify-center font-heading font-bold text-2xl shrink-0">
                    <?php echo substr($agency['AgencyName'], 0, 1); ?>
                </div>
                <div>
                    <h1 class="text-2xl font-heading font-semibold text-text-main"><?php echo htmlspecialchars($agency['AgencyName']); ?></h1>
                    <p class="text-xs text-muted font-bold tracking-wider uppercase mt-1">Registration No: <?php echo htmlspecialchars($agency['RegistrationNumber']); ?></p>
                </div>
            </div>

            <!-- Global statistics indicators -->
            <div class="flex gap-6 shrink-0 border-t md:border-t-0 pt-4 md:pt-0 border-outline-variant/35 w-full md:w-auto">
                <div class="text-center">
                    <span class="text-[10px] text-muted uppercase tracking-wider block font-bold mb-0.5">Average Rating</span>
                    <div class="inline-flex items-center gap-1 px-3 py-1 bg-amber-50 border border-amber-200 text-amber-700 font-bold rounded-lg text-sm">
                        <span class="material-symbols-outlined text-[15px] fill-1 text-amber-500">star</span>
                        <?php echo number_format($agency['AverageRating'], 1); ?> / 5.0
                    </div>
                </div>
                <div class="h-10 w-[1px] bg-outline-variant/35 self-center"></div>
                <div class="text-center">
                    <span class="text-[10px] text-muted uppercase tracking-wider block font-bold mb-0.5">Total Offers</span>
                    <span class="font-heading font-bold text-text-main text-lg"><?php echo count($packages); ?> packages</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Side column - Address & Phone book (1/3) -->
            <div class="bg-surface border border-outline-variant p-6 rounded-2xl shadow-sm self-start flex flex-col gap-6">
                <!-- Address block -->
                <div class="flex flex-col gap-2.5">
                    <h2 class="text-sm font-bold text-text-main uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-outline-variant/30">
                        <span class="material-symbols-outlined text-primary text-[18px]">location_on</span>
                        Physical Office Address
                    </h2>
                    <div class="text-xs text-secondary leading-relaxed">
                        <p class="font-bold text-text-main"><?php echo htmlspecialchars($agency['Address_Street']); ?></p>
                        <p><?php echo htmlspecialchars($agency['Address_City']); ?></p>
                        <p class="font-mono text-muted mt-0.5">ZIP: <?php echo htmlspecialchars($agency['Address_Zip']); ?></p>
                    </div>
                </div>

                <!-- Contacts list -->
                <div class="flex flex-col gap-2.5">
                    <h2 class="text-sm font-bold text-text-main uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-outline-variant/30">
                        <span class="material-symbols-outlined text-primary text-[18px]">call</span>
                        Telephone Directory
                    </h2>
                    <div class="flex flex-col gap-2">
                        <?php if (empty($contacts)): ?>
                            <p class="text-xs text-muted italic">No registered phone numbers.</p>
                        <?php else: ?>
                            <?php foreach ($contacts as $phone): ?>
                                <a href="tel:<?php echo htmlspecialchars($phone); ?>" class="flex items-center gap-2 p-2 rounded-lg bg-background-light hover:bg-surface-container-low text-xs text-secondary font-mono border border-outline-variant/30 transition-colors">
                                    <span class="material-symbols-outlined text-[15px] text-primary">phone_in_talk</span>
                                    <?php echo htmlspecialchars($phone); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Main column - Packages & Reviews (2/3) -->
            <div class="lg:col-span-2 flex flex-col gap-8">
                
                <!-- Package listings -->
                <div class="flex flex-col gap-4">
                    <h2 class="text-lg font-heading font-semibold text-text-main flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[24px]">travel_explore</span>
                        Explore Package Offerings
                    </h2>

                    <?php if (empty($packages)): ?>
                        <div class="bg-surface border border-outline-variant rounded-2xl p-6 text-center text-muted italic text-xs">
                            This travel agency hasn't listed any packages yet.
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <?php foreach ($packages as $pkg): ?>
                                <div class="bg-surface border border-outline-variant rounded-2xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col hover:-translate-y-0.5 justify-between">
                                    <div class="flex flex-col gap-2">
                                        <h3 class="font-heading font-semibold text-text-main text-sm leading-normal line-clamp-1"><?php echo htmlspecialchars($pkg['Title']); ?></h3>
                                        <p class="text-[11px] text-muted line-clamp-2 leading-relaxed"><?php echo htmlspecialchars($pkg['Description']); ?></p>
                                        
                                        <div class="flex gap-3 text-[11px] text-secondary mt-1">
                                            <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[13px]">schedule</span> <?php echo $pkg['DurationDays']; ?> Days</span>
                                            <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[13px]">groups</span> Max <?php echo $pkg['MaxCapacity']; ?> guests</span>
                                        </div>
                                    </div>

                                    <div class="border-t border-outline-variant/30 pt-3 mt-4 flex justify-between items-end">
                                        <div>
                                            <span class="text-[9px] uppercase tracking-wider block text-muted font-bold">Base Price</span>
                                            <span class="font-heading font-bold text-primary text-sm"><?php echo formatCurrency($pkg['BasePrice']); ?></span>
                                        </div>
                                        <a href="package_detail.php?id=<?php echo $pkg['PackageID']; ?>" class="bg-primary text-on-primary font-bold text-[10px] h-[32px] px-3.5 rounded-lg flex items-center gap-1 transition-all hover:bg-primary-container">
                                            Book Package
                                            <span class="material-symbols-outlined text-xs">arrow_forward</span>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reviews and leave feedback hub -->
                <div class="flex flex-col gap-5">
                    <h2 class="text-lg font-heading font-semibold text-text-main flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[24px]">reviews</span>
                        Ratings & Feedback Hub
                    </h2>

                    <!-- Feedback form -->
                    <div class="bg-surface border border-outline-variant p-6 rounded-2xl shadow-sm flex flex-col gap-4">
                        <h3 class="text-xs font-bold text-text-main uppercase tracking-wider">Leave an Agency Review</h3>
                        
                        <form method="POST" action="traveller_agencies.php?agency_id=<?php echo $agency_id; ?>" class="flex flex-col gap-4">
                            <input type="hidden" name="action" value="leave_agency_review">

                            <div class="flex flex-col gap-1.5">
                                <label class="text-[11px] font-bold text-muted uppercase">Star Rating</label>
                                <div class="flex gap-2">
                                    <?php for ($i=1; $i<=5; $i++): ?>
                                        <label class="cursor-pointer relative">
                                            <input type="radio" name="rating" value="<?php echo $i; ?>" class="peer sr-only" required <?php echo ($i===5 ? 'checked' : ''); ?>>
                                            <div class="w-8 h-8 rounded-full border border-outline-variant bg-surface flex items-center justify-center peer-checked:bg-amber-500 peer-checked:border-amber-500 peer-checked:text-white text-secondary hover:bg-amber-100 transition-colors font-bold text-xs">
                                                <?php echo $i; ?>
                                            </div>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-[11px] font-bold text-muted uppercase">Comments & Experience</label>
                                <textarea name="comment" rows="3" placeholder="Write about your coordination experience, professionalism, package selection..." required class="input-field py-3 text-xs resize-none h-auto"></textarea>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="bg-primary text-on-primary font-bold text-xs h-[40px] px-6 rounded-lg flex items-center gap-1.5 transition-all btn-glow">
                                    <span class="material-symbols-outlined text-[16px]">rate_review</span>
                                    Submit analyzed review
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Reviews List -->
                    <div class="flex flex-col gap-4">
                        <h3 class="text-xs font-bold text-text-main uppercase tracking-wider">Agency Feedback History (<?php echo count($reviews); ?>)</h3>
                        
                        <?php if (empty($reviews)): ?>
                            <p class="text-xs text-muted italic py-2">No traveler feedback has been written yet for this agency. Be the first explorer to review!</p>
                        <?php else: ?>
                            <div class="flex flex-col gap-3">
                                <?php foreach ($reviews as $rev): ?>
                                    <div class="bg-surface border border-outline-variant/60 rounded-xl p-4 shadow-sm flex flex-col gap-2.5">
                                        <div class="flex justify-between items-start gap-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-full bg-surface-container-low text-secondary border border-outline-variant/40 flex items-center justify-center font-bold text-xs">
                                                    <?php echo substr($rev['FirstName'], 0, 1); ?>
                                                </div>
                                                <div>
                                                    <h4 class="text-xs font-bold text-text-main"><?php echo htmlspecialchars($rev['FirstName'] . ' ' . $rev['LastName']); ?></h4>
                                                    <span class="text-[9px] text-muted font-mono block mt-0.5"><?php echo date('M d, Y - H:i', strtotime($rev['DatePosted'])); ?></span>
                                                </div>
                                            </div>

                                            <!-- Rating stars & sentiment score badge -->
                                            <div class="flex items-center gap-2">
                                                <!-- Sentiment badge -->
                                                <?php 
                                                $score = (float)$rev['SentimentScore'];
                                                if ($score > 0.05) {
                                                    echo '<span class="px-2 py-0.5 rounded bg-green-50 border border-green-200 text-green-700 font-bold text-[9px] uppercase tracking-wider flex items-center gap-0.5"><span class="material-symbols-outlined text-[11px] fill-1">sentiment_satisfied</span> Positive</span>';
                                                } elseif ($score < -0.05) {
                                                    echo '<span class="px-2 py-0.5 rounded bg-red-50 border border-red-200 text-primary font-bold text-[9px] uppercase tracking-wider flex items-center gap-0.5"><span class="material-symbols-outlined text-[11px] fill-1">sentiment_very_dissatisfied</span> Negative</span>';
                                                } else {
                                                    echo '<span class="px-2 py-0.5 rounded bg-surface-container-high border border-outline-variant/50 text-secondary font-bold text-[9px] uppercase tracking-wider flex items-center gap-0.5"><span class="material-symbols-outlined text-[11px] fill-1">sentiment_neutral</span> Neutral</span>';
                                                }
                                                ?>
                                                
                                                <div class="flex items-center gap-0.5 text-amber-500 font-bold text-xs bg-amber-50 border border-amber-200 px-2 py-0.5 rounded">
                                                    <span class="material-symbols-outlined text-[12px] fill-1">star</span>
                                                    <?php echo $rev['Rating']; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <p class="text-xs text-secondary leading-relaxed pl-10"><?php echo htmlspecialchars($rev['Comment']); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            </div>

        </div>

    <?php else: ?>

        <!-- ==========================================
             ALL AGENCIES DIRECTORY LISTING
             ========================================== -->
        
        <div>
            <h1 class="text-3xl font-heading font-semibold text-text-main flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[32px]">corporate_fare</span>
                Travel Agencies Directory
            </h1>
            <p class="text-muted mt-1">Locate top-rated travel agencies, view telephone lists, and explore their custom travel package offerings.</p>
        </div>

        <?php if ($error): ?>
            <div class="p-4 bg-red-50 border border-red-200 text-primary rounded-xl flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-red-600">error</span>
                <span class="text-sm font-semibold"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="bg-surface rounded-xl shadow-sm border border-outline-variant p-4">
            <form method="GET" action="traveller_agencies.php" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                
                <!-- Search text -->
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-2">Search Agencies</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Agency name or city..." class="input-field text-xs h-[40px]">
                </div>

                <!-- Star filter -->
                <div>
                    <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-2">Minimum Average Rating</label>
                    <select name="rating" class="input-field text-xs h-[40px] py-0">
                        <option value="0.0" <?php echo (isset($ratingFilter) && $ratingFilter == 0.0 ? 'selected' : ''); ?>>Any Rating</option>
                        <option value="3.0" <?php echo (isset($ratingFilter) && $ratingFilter == 3.0 ? 'selected' : ''); ?>>3.0+ Stars</option>
                        <option value="4.0" <?php echo (isset($ratingFilter) && $ratingFilter == 4.0 ? 'selected' : ''); ?>>4.0+ Stars</option>
                        <option value="4.5" <?php echo (isset($ratingFilter) && $ratingFilter == 4.5 ? 'selected' : ''); ?>>4.5+ Stars</option>
                    </select>
                </div>

                <!-- Action buttons -->
                <div class="flex gap-2.5">
                    <button type="submit" class="flex-grow bg-primary text-on-primary font-bold text-xs h-[40px] px-6 rounded-lg flex items-center justify-center gap-1.5 transition-all hover:bg-primary-container">
                        <span class="material-symbols-outlined text-[16px]">search</span>
                        Find Partners
                    </button>
                    <a href="traveller_agencies.php" class="bg-surface border border-outline-variant text-secondary hover:text-primary font-bold text-xs h-[40px] px-4 rounded-lg flex items-center justify-center transition-colors">
                        Reset
                    </a>
                </div>

            </form>
        </div>

        <!-- Agencies Directory Grid -->
        <?php if (empty($agencies)): ?>
            <div class="bg-surface border border-outline-variant rounded-2xl p-12 text-center text-muted italic">
                <span class="material-symbols-outlined text-5xl mb-2 text-outline-variant/60 block">corporate_fare</span>
                No registered travel agency partners match your search criteria.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($agencies as $agency): ?>
                    <div class="bg-surface border border-outline-variant rounded-2xl p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between gap-5 hover:-translate-y-0.5">
                        
                        <!-- Core Card Header -->
                        <div class="flex flex-col gap-2.5">
                            <div class="flex justify-between items-start gap-3">
                                <span class="px-2 py-0.5 bg-primary-fixed/55 border border-outline-variant/50 text-primary text-[9px] font-bold tracking-wider rounded font-mono">
                                    <?php echo htmlspecialchars($agency['RegistrationNumber']); ?>
                                </span>
                                
                                <div class="px-2 py-0.5 bg-amber-50 border border-amber-200 text-amber-700 font-bold rounded-lg text-xs flex items-center gap-0.5 shrink-0 select-none">
                                    <span class="material-symbols-outlined text-[12px] fill-1 text-amber-500">star</span>
                                    <?php echo number_format($agency['AverageRating'], 1); ?>
                                </div>
                            </div>

                            <h3 class="font-heading font-semibold text-text-main text-lg leading-normal mt-1"><?php echo htmlspecialchars($agency['AgencyName']); ?></h3>
                            <p class="text-xs text-muted flex items-center gap-0.5 -mt-0.5"><span class="material-symbols-outlined text-[14px]">location_on</span> <?php echo htmlspecialchars($agency['Address_City']); ?></p>
                        </div>

                        <!-- Address summary & phone sample -->
                        <div class="text-xs text-secondary leading-relaxed border-t border-b border-outline-variant/20 py-3 flex flex-col gap-1.5">
                            <div class="flex items-center gap-1 text-[11px] font-mono text-muted">
                                <span class="material-symbols-outlined text-[14px]">call</span>
                                <?php 
                                    if ($agency['ContactsList']) {
                                        $num = explode(',', $agency['ContactsList'])[0];
                                        echo htmlspecialchars($num);
                                    } else {
                                        echo 'No direct helpline';
                                    }
                                ?>
                            </div>
                            <div class="flex items-center gap-1 text-[11px] font-mono text-muted">
                                <span class="material-symbols-outlined text-[14px]">sell</span>
                                Offers: <?php echo $agency['PackageCount']; ?> Travel Packages
                            </div>
                        </div>

                        <!-- CTA Button -->
                        <div>
                            <a href="traveller_agencies.php?agency_id=<?php echo $agency['UserID']; ?>" class="w-full bg-surface hover:bg-surface-container-low text-text-main border border-outline-variant font-bold text-xs h-[40px] rounded-lg flex items-center justify-center gap-1.5 transition-all shadow-sm">
                                View Agency Details
                                <span class="material-symbols-outlined text-xs font-bold">arrow_forward</span>
                            </a>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
