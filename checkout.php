<?php
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Traveller') {
    header("Location: login.php");
    exit;
}

$page_title = 'Secure Booking Checkout';
require_once 'includes/header.php';


$traveller_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';

// Self-healing: Ensure logs directory exists
if (!file_exists('logs')) {
    mkdir('logs', 0755, true);
}

// Function to log security and transactional logs
function logSecurityEvent($level, $message, $userId = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $timestamp = date('Y-m-d H:i:s');
    $logMsg = "[$timestamp] [$level] [IP: $ip] [UserID: " . ($userId ?: 'GUEST') . "] $message\n";
    file_put_contents('logs/security_audit.log', $logMsg, FILE_APPEND);
}

// Collect parameters
$package_id = isset($_REQUEST['package_id']) ? (int)$_REQUEST['package_id'] : 0;
$trip_date_id = isset($_REQUEST['tripDateId']) ? (int)$_REQUEST['tripDateId'] : (isset($_REQUEST['trip_date_id']) ? (int)$_REQUEST['trip_date_id'] : 0);
$party_size = isset($_REQUEST['partySize']) ? (int)$_REQUEST['partySize'] : (isset($_REQUEST['party_size']) ? (int)$_REQUEST['party_size'] : 1);

if ($package_id <= 0 || $trip_date_id <= 0 || $party_size <= 0) {
    logSecurityEvent('WARNING', "Invalid booking checkout arguments submitted. Pkg: $package_id, Date: $trip_date_id, Party: $party_size", $traveller_id);
    $error = "Invalid booking details requested. Please go back to the package details and try again.";
}

if (!$error) {
    try {
        // Fetch Package and Traveler Details
        $stmtPkg = $pdo->prepare("
            SELECT p.*, ta.AgencyName 
            FROM TravelPackage p
            JOIN TravelAgency ta ON p.AgencyID = ta.UserID
            WHERE p.PackageID = ?
        ");
        $stmtPkg->execute([$package_id]);
        $package = $stmtPkg->fetch();

        $stmtTrav = $pdo->prepare("SELECT * FROM Traveller WHERE UserID = ?");
        $stmtTrav->execute([$traveller_id]);
        $traveller = $stmtTrav->fetch();

        $stmtDate = $pdo->prepare("SELECT * FROM GroupTrip WHERE PackageID = ? AND TripDateID = ? AND Status = 'Scheduled'");
        $stmtDate->execute([$package_id, $trip_date_id]);
        $trip_date = $stmtDate->fetch();

        if (!$package || !$traveller || !$trip_date) {
            logSecurityEvent('WARNING', "Checkout validation failed. Package or scheduled date not found.", $traveller_id);
            $error = "Requested package details or scheduled dates could not be verified.";
        } else {
            // Check remaining capacity dynamically using database count to prevent race-conditions
            $stmtCap = $pdo->prepare("
                SELECT (tp.MaxCapacity - COALESCE(SUM(b.PartySize), 0)) AS RemainingCapacity
                FROM TravelPackage tp
                LEFT JOIN Booking b ON tp.PackageID = b.Trip_PackageID AND b.Trip_TripDateID = ?
                WHERE tp.PackageID = ?
                GROUP BY tp.PackageID
            ");
            $stmtCap->execute([$trip_date_id, $package_id]);
            $remaining = (int)$stmtCap->fetchColumn();

            if ($party_size > $remaining) {
                logSecurityEvent('CRITICAL', "Booking overflow attempted. Requested party: $party_size, remaining: $remaining", $traveller_id);
                $error = "Capacity limit exceeded. There are only $remaining spots left for this trip date range.";
            }
        }
    } catch (\PDOException $e) {
        $error = "Database queries failed: " . $e->getMessage();
    }
}

// Calculate details if there are no loading errors
if (!$error && isset($package)) {
    $basePrice = (float)$package['BasePrice'];
    $subtotal = $basePrice * $party_size;
    
    // Group discount model: 10% off for group size of 3 or more
    $discount = 0.00;
    if ($party_size >= 3) {
        $discount = $subtotal * 0.10;
    }
    
    // Local Tourism taxes: 5% flat
    $tax = ($subtotal - $discount) * 0.05;
    $finalTotal = $subtotal - $discount + $tax;
}

// Handle Purchase Payment Confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_purchase' && !$error) {
    $payment_method = $_POST['payment_method'] ?? 'card';
    
    try {
        $pdo->beginTransaction();

        // 1. Double check capacity inside transaction using row-level locking (FOR UPDATE) to prevent race-conditions!
        $stmtLock = $pdo->prepare("
            SELECT tp.MaxCapacity 
            FROM TravelPackage tp 
            WHERE tp.PackageID = ? 
            FOR UPDATE
        ");
        $stmtLock->execute([$package_id]);
        $max_capacity = (int)$stmtLock->fetchColumn();

        $stmtSum = $pdo->prepare("
            SELECT COALESCE(SUM(b.PartySize), 0) 
            FROM Booking b 
            WHERE b.Trip_PackageID = ? AND b.Trip_TripDateID = ?
        ");
        $stmtSum->execute([$package_id, $trip_date_id]);
        $booked_count = (int)$stmtSum->fetchColumn();

        if (($booked_count + $party_size) > $max_capacity) {
            $pdo->rollBack();
            logSecurityEvent('CRITICAL', "Race-condition prevented! Booking request exceeded maximum capacity. Requested: $party_size, remaining: " . ($max_capacity - $booked_count), $traveller_id);
            $error = "Transaction aborted. Capacity limit was filled by another booking. Only " . ($max_capacity - $booked_count) . " spots remaining.";
        } else {
            // Fetch package details for notifications
            $stmtPkg = $pdo->prepare("SELECT Title, AgencyID FROM TravelPackage WHERE PackageID = ?");
            $stmtPkg->execute([$package_id]);
            $package_row = $stmtPkg->fetch();
            $agency_user_id = $package_row['AgencyID'] ?? 0;
            $package_title = $package_row['Title'] ?? 'Package';

            // 2. Process payments depending on chosen method
            if ($payment_method === 'budget') {
                $soloBudget = (float)$traveller['SoloBudget'];
                if ($soloBudget < $finalTotal) {
                    $pdo->rollBack();
                    logSecurityEvent('SECURITY', "Payment failed due to insufficient funds in SoloBudget balance.", $traveller_id);
                    $error = "Insufficient SoloBudget funds. Your current balance is $" . number_format($soloBudget, 2) . ", but you require $" . number_format($finalTotal, 2) . ".";
                } else {
                    // Deduct wallet balance
                    $stmtDeduct = $pdo->prepare("UPDATE Traveller SET SoloBudget = SoloBudget - ? WHERE UserID = ?");
                    $stmtDeduct->execute([$finalTotal, $traveller_id]);
                    
                    // Insert confirmed booking record
                    $stmtIns = $pdo->prepare("
                        INSERT INTO Booking (TotalAmount, PaymentStatus, PartySize, TravellerID, Trip_PackageID, Trip_TripDateID) 
                        VALUES (?, 'Paid', ?, ?, ?, ?)
                    ");
                    $stmtIns->execute([$finalTotal, $party_size, $traveller_id, $package_id, $trip_date_id]);
                    
                    // Send notification to agency
                    if ($agency_user_id > 0) {
                        $notifyTitle = "New Booking Received!";
                        $notifyMsg = htmlspecialchars($traveller['FirstName'] . ' ' . $traveller['LastName']) . " has booked " . $party_size . " slot(s) for your package \"" . htmlspecialchars($package_title) . "\".";
                        $stmtNotify = $pdo->prepare("INSERT INTO Notification (UserID, Title, Message, IsRead) VALUES (?, ?, ?, 0)");
                        $stmtNotify->execute([$agency_user_id, $notifyTitle, $notifyMsg]);
                    }

                    $pdo->commit();
                    logSecurityEvent('INFO', "Successfully booked package via SoloBudget balance deduction. Booking confirmed.", $traveller_id);
                    $success = "Payment processed successfully! Your SoloBudget has been debited and your booking is confirmed.";
                }
            } else {
                // Card processing simulation - secure audits validate card numbers
                $cardNumber = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
                $cardCvv = preg_replace('/\D/', '', $_POST['card_cvv'] ?? '');
                
                if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
                    $pdo->rollBack();
                    logSecurityEvent('SECURITY', "Failed payment check: invalid card number format.", $traveller_id);
                    $error = "Invalid credit card number format. Please check your credentials.";
                } elseif (strlen($cardCvv) < 3 || strlen($cardCvv) > 4) {
                    $pdo->rollBack();
                    logSecurityEvent('SECURITY', "Failed payment check: invalid CVV code.", $traveller_id);
                    $error = "Invalid CVV security code format.";
                } else {
                    // Insert confirmed booking record
                    $stmtIns = $pdo->prepare("
                        INSERT INTO Booking (TotalAmount, PaymentStatus, PartySize, TravellerID, Trip_PackageID, Trip_TripDateID) 
                        VALUES (?, 'Paid', ?, ?, ?, ?)
                    ");
                    $stmtIns->execute([$finalTotal, $party_size, $traveller_id, $package_id, $trip_date_id]);
                    
                    // Send notification to agency
                    if ($agency_user_id > 0) {
                        $notifyTitle = "New Booking Received!";
                        $notifyMsg = htmlspecialchars($traveller['FirstName'] . ' ' . $traveller['LastName']) . " has booked " . $party_size . " slot(s) for your package \"" . htmlspecialchars($package_title) . "\".";
                        $stmtNotify = $pdo->prepare("INSERT INTO Notification (UserID, Title, Message, IsRead) VALUES (?, ?, ?, 0)");
                        $stmtNotify->execute([$agency_user_id, $notifyTitle, $notifyMsg]);
                    }

                    $pdo->commit();
                    logSecurityEvent('INFO', "Successfully booked package via Simulated Credit Card payment. Booking confirmed.", $traveller_id);
                    $success = "Simulated Credit Card charge processed successfully! Your booking is confirmed.";
                }
            }
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logSecurityEvent('ERROR', "Database exception during booking checkout transaction: " . $e->getMessage(), $traveller_id);
        $error = "Database transaction failed. Please try again.";
    }
}
?>

<div class="max-w-4xl mx-auto flex flex-col gap-6">

    <!-- Header navigation -->
    <div class="flex justify-between items-center pb-2">
        <a href="package_detail.php?id=<?php echo $package_id; ?>" class="text-xs font-bold text-secondary hover:text-primary flex items-center gap-1 transition-all">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            Back to Package Details
        </a>
        <span class="text-xs font-mono text-muted">Secure SSL Gateway</span>
    </div>

    <!-- Title Header -->
    <div class="flex flex-col gap-1">
        <h1 class="text-3xl font-heading font-semibold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[32px]">shield_lock</span>
            Secure Transaction Checkout
        </h1>
        <p class="text-muted text-sm">Please review your itemized travel package details and complete your reservation.</p>
    </div>

    <!-- Error Alert -->
    <?php if ($error): ?>
        <div class="p-4 bg-red-50 border border-red-200 text-primary rounded-xl flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px] text-red-600">error</span>
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Success Alert -->
    <?php if ($success): ?>
        <div class="p-5 bg-green-50 border border-green-200 text-green-800 rounded-2xl flex flex-col gap-3">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[24px] text-green-600">check_circle</span>
                <span class="text-base font-bold"><?php echo htmlspecialchars($success); ?></span>
            </div>
            <p class="text-xs pl-8 text-green-700">Your reservation details have been confirmed and sent to our partner travel agency. You can download calendar schedules or track booking invoices on your dashboard.</p>
            <div class="flex gap-3 pl-8 mt-2">
                <a href="traveller_dashboard.php" class="bg-green-700 text-white text-xs font-bold px-4 py-2 rounded-lg hover:bg-green-800 transition-all shadow-sm">Go to Dashboard</a>
                <a href="packages.php" class="bg-white border border-green-300 text-green-800 text-xs font-bold px-4 py-2 rounded-lg hover:bg-green-50 transition-colors">Browse More Packages</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$success && !$error && isset($package)): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- LEFT PANEL: Summary & Itemized pricing (2/3 width) -->
            <div class="md:col-span-2 flex flex-col gap-6">
                <!-- Summary Card -->
                <div class="bg-surface rounded-2xl border border-outline-variant p-6 shadow-sm bg-white flex flex-col gap-4">
                    <h2 class="text-base font-heading font-semibold text-text-main flex items-center gap-2 border-b border-outline-variant/30 pb-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">inventory_2</span>
                        Travel Package Summary
                    </h2>
                    
                    <div class="flex flex-col gap-2">
                        <h3 class="font-bold text-text-main text-base leading-tight"><?php echo htmlspecialchars($package['Title']); ?></h3>
                        <p class="text-xs text-muted leading-relaxed line-clamp-3"><?php echo htmlspecialchars($package['Description']); ?></p>
                    </div>

                    <div class="flex flex-wrap gap-4 text-xs font-bold text-secondary mt-2 bg-background-light p-3 rounded-lg border border-outline-variant/35">
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[15px]">corporate_fare</span> Curated: <?php echo htmlspecialchars($package['AgencyName']); ?></span>
                        <div class="w-[1px] h-4 bg-outline-variant/35"></div>
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[15px]">schedule</span> Duration: <?php echo htmlspecialchars($package['DurationDays']); ?> Days</span>
                        <div class="w-[1px] h-4 bg-outline-variant/35"></div>
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[15px]">groups</span> Party: <?php echo $party_size; ?> guest(s)</span>
                    </div>

                    <div class="text-xs text-secondary mt-1 flex items-center gap-1.5 font-semibold text-[11px] uppercase tracking-wider text-accent">
                        <span class="material-symbols-outlined text-[14px]">calendar_month</span>
                        Scheduled Trip: <?php echo date('M d, Y', strtotime($trip_date['StartDate'])) . ' - ' . date('M d, Y', strtotime($trip_date['EndDate'])); ?>
                    </div>
                </div>

                <!-- Payment Form Card -->
                <div class="bg-surface rounded-2xl border border-outline-variant p-6 shadow-sm bg-white flex flex-col gap-4">
                    <h2 class="text-base font-heading font-semibold text-text-main flex items-center gap-2 border-b border-outline-variant/30 pb-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">credit_card</span>
                        Complete Your Secure Payment
                    </h2>

                    <form method="POST" action="checkout.php" class="flex flex-col gap-5">
                        <input type="hidden" name="package_id" value="<?php echo $package_id; ?>">
                        <input type="hidden" name="trip_date_id" value="<?php echo $trip_date_id; ?>">
                        <input type="hidden" name="party_size" value="<?php echo $party_size; ?>">
                        <input type="hidden" name="action" value="confirm_purchase">

                        <!-- Payment Method Toggle -->
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-muted uppercase tracking-wider">Select Payment Method</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_method" value="card" checked class="peer sr-only" onclick="togglePaymentFields('card')">
                                    <div class="p-3 border border-outline-variant rounded-xl flex items-center justify-center gap-2 font-bold text-xs peer-checked:border-primary peer-checked:bg-primary-fixed/20 peer-checked:text-primary transition-all hover:bg-background-light">
                                        <span class="material-symbols-outlined text-[18px]">credit_card</span>
                                        Credit/Debit Card
                                    </div>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_method" value="budget" class="peer sr-only" onclick="togglePaymentFields('budget')">
                                    <div class="p-3 border border-outline-variant rounded-xl flex items-center justify-center gap-2 font-bold text-xs peer-checked:border-primary peer-checked:bg-primary-fixed/20 peer-checked:text-primary transition-all hover:bg-background-light">
                                        <span class="material-symbols-outlined text-[18px]">account_balance_wallet</span>
                                        SoloBudget Balance
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Card input fields -->
                        <div id="payment-card-fields" class="flex flex-col gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[11px] font-bold text-muted uppercase" for="card_name">Cardholder Name *</label>
                                <input type="text" id="card_name" name="card_name" placeholder="John Doe" class="input-field text-xs h-[40px]" required>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-[11px] font-bold text-muted uppercase" for="card_number">Card Number *</label>
                                <input type="text" id="card_number" name="card_number" placeholder="4111 2222 3333 4444" class="input-field text-xs h-[40px]" required>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[11px] font-bold text-muted uppercase" for="card_expiry">Expiry Date *</label>
                                    <input type="text" id="card_expiry" name="card_expiry" placeholder="12/28" class="input-field text-xs h-[40px]" required>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="text-[11px] font-bold text-muted uppercase" for="card_cvv">CVV Code *</label>
                                    <input type="password" id="card_cvv" name="card_cvv" placeholder="***" maxlength="4" class="input-field text-xs h-[40px]" required>
                                </div>
                            </div>
                        </div>

                        <!-- Wallet / Budget fields -->
                        <div id="payment-budget-fields" class="hidden bg-background-light p-4 rounded-xl border border-outline-variant/60 flex flex-col gap-2">
                            <p class="text-xs text-secondary leading-normal">Confirm payment deduction directly from your personal <strong>SoloBudget</strong> wallet balance.</p>
                            <div class="flex justify-between items-center mt-2 p-2 bg-white rounded border border-outline-variant/30 text-xs">
                                <span class="text-muted">Your Wallet Balance:</span>
                                <span class="font-bold text-green-700 font-mono">$<?php echo number_format((float)$traveller['SoloBudget'], 2); ?></span>
                            </div>
                        </div>

                        <!-- Secure CTA -->
                        <div class="border-t border-outline-variant/25 pt-4 mt-2 flex justify-end items-center gap-4">
                            <span class="text-xs text-muted flex items-center gap-1"><span class="material-symbols-outlined text-[15px]">verified_user</span> Encrypted transaction</span>
                            <button type="submit" class="bg-primary text-on-primary font-bold text-xs h-[42px] px-8 rounded-lg flex items-center gap-1.5 btn-glow transition-all">
                                <span class="material-symbols-outlined text-[16px]">lock_open</span>
                                Confirm Payment & Reserve
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- RIGHT PANEL: Pricing details invoice (1/3 width) -->
            <div>
                <div class="bg-surface rounded-2xl border border-outline-variant p-6 shadow-sm bg-white flex flex-col gap-5 sticky top-24">
                    <h2 class="text-sm font-bold text-text-main uppercase tracking-wider flex items-center gap-1.5 border-b border-outline-variant/30 pb-2.5">
                        <span class="material-symbols-outlined text-primary text-[18px]">receipt_long</span>
                        Booking Invoice Details
                    </h2>

                    <!-- Invoice list -->
                    <div class="flex flex-col gap-3.5 text-xs text-secondary border-b border-outline-variant/20 pb-4">
                        <div class="flex justify-between items-center">
                            <span>Base Fare ($<?php echo number_format($basePrice, 2); ?> x <?php echo $party_size; ?>)</span>
                            <span class="font-bold text-text-main font-mono">$<?php echo number_format($subtotal, 2); ?></span>
                        </div>

                        <?php if ($discount > 0): ?>
                            <div class="flex justify-between items-center text-green-700">
                                <span>Group Discount (10%)</span>
                                <span class="font-bold font-mono">-$<?php echo number_format($discount, 2); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="flex justify-between items-center">
                            <span>Local Tourism Tax (5%)</span>
                            <span class="font-bold text-text-main font-mono">$<?php echo number_format($tax, 2); ?></span>
                        </div>
                    </div>

                    <!-- Grand Total -->
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="text-[9px] uppercase tracking-wider text-muted font-bold block">Grand Total Due</span>
                            <span class="text-xs text-muted">Includes local taxes & waivers</span>
                        </div>
                        <span class="text-xl font-bold text-primary font-mono">$<?php echo number_format($finalTotal, 2); ?></span>
                    </div>

                    <!-- Discount helper alert -->
                    <?php if ($party_size < 3): ?>
                        <div class="p-3 bg-indigo-50 border border-indigo-100 rounded-lg text-[10px] text-indigo-800 leading-normal flex items-start gap-1.5">
                            <span class="material-symbols-outlined text-[13px] text-indigo-600 shrink-0 mt-0.5">campaign</span>
                            <span><strong>Traveling in a group?</strong> Save 10% on your base fare instantly when you book with a party size of 3 or more explorers!</span>
                        </div>
                    <?php else: ?>
                        <div class="p-3 bg-green-50 border border-green-100 rounded-lg text-[10px] text-green-800 leading-normal flex items-start gap-1.5">
                            <span class="material-symbols-outlined text-[13px] text-green-600 shrink-0 mt-0.5">verified</span>
                            <span><strong>Group Discount Applied!</strong> 10% has been successfully subtracted from your subtotal.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    <?php endif; ?>

</div>

<script>
function togglePaymentFields(method) {
    const cardFields = document.getElementById('payment-card-fields');
    const budgetFields = document.getElementById('payment-budget-fields');
    const cardInputs = cardFields.querySelectorAll('input');
    
    if (method === 'card') {
        cardFields.classList.remove('hidden');
        budgetFields.classList.add('hidden');
        // Require inputs
        cardInputs.forEach(i => i.setAttribute('required', 'required'));
    } else {
        cardFields.classList.add('hidden');
        budgetFields.classList.remove('hidden');
        // Remove requirements
        cardInputs.forEach(i => i.removeAttribute('required'));
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
