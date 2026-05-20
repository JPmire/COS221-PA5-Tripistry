<?php
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'TravelAgency') {
    header("Location: login.php");
    exit;
}

$page_title = 'Agency Profile';
require_once 'includes/header.php';


$agency_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agency_name = trim($_POST['agencyName'] ?? '');
    $address_street = trim($_POST['addressStreet'] ?? '');
    $address_city = trim($_POST['addressCity'] ?? '');
    $address_zip = trim($_POST['addressZip'] ?? '');
    $phones = $_POST['phones'] ?? [];

    // Filter and sanitize phones
    $filtered_phones = [];
    if (is_array($phones)) {
        foreach ($phones as $phone) {
            $cleaned = trim($phone);
            if ($cleaned !== '') {
                $filtered_phones[] = $cleaned;
            }
        }
    }

    if ($agency_name === '') {
        $error_message = 'Agency Name cannot be left blank.';
    } elseif ($address_street === '' || $address_city === '' || $address_zip === '') {
        $error_message = 'All Address fields (Street, City, Zip) are required.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Update TravelAgency table
            $stmt = $pdo->prepare("
                UPDATE TravelAgency 
                SET AgencyName = ?, Address_Street = ?, Address_City = ?, Address_Zip = ?
                WHERE UserID = ?
            ");
            $stmt->execute([$agency_name, $address_street, $address_city, $address_zip, $agency_id]);

            // 2. Clear out old contacts
            $stmt = $pdo->prepare("DELETE FROM TravelAgency_Contacts WHERE UserID = ?");
            $stmt->execute([$agency_id]);

            // 3. Insert new contacts
            if (!empty($filtered_phones)) {
                $stmt = $pdo->prepare("INSERT INTO TravelAgency_Contacts (UserID, ContactNumber) VALUES (?, ?)");
                foreach ($filtered_phones as $phone) {
                    $stmt->execute([$agency_id, $phone]);
                }
            }

            $pdo->commit();
            
            // Instantly update session name so it displays in header immediately
            $_SESSION['name'] = $agency_name;
            $success_message = 'Agency profile and contact list updated successfully!';
        } catch (\PDOException $e) {
            $pdo->rollBack();
            $error_message = 'Database update failed: ' . $e->getMessage();
        }
    }
}

// Fetch Current Profile Data
try {
    $stmt = $pdo->prepare("SELECT * FROM TravelAgency WHERE UserID = ?");
    $stmt->execute([$agency_id]);
    $agency = $stmt->fetch();

    if (!$agency) {
        die("<div class='max-w-4xl mx-auto p-6'><div class='bg-error-container text-on-error-container p-4 rounded-xl border border-error/20 font-bold'>Error: Agency profile not found.</div></div>");
    }

    // Fetch Contacts
    $stmt = $pdo->prepare("SELECT ContactNumber FROM TravelAgency_Contacts WHERE UserID = ?");
    $stmt->execute([$agency_id]);
    $contacts = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (\PDOException $e) {
    $error_message = 'Failed to load profile details: ' . $e->getMessage();
}
?>

<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex justify-between items-center border-b border-outline-variant pb-4">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-text-main">Agency Profile Manager</h1>
            <p class="text-xs text-secondary mt-1">Manage your agency business details, contact information, and address fields.</p>
        </div>
        <a href="agency_dashboard.php" class="text-text-main font-medium hover:text-primary transition-colors flex items-center gap-1 border border-outline-variant rounded-md px-3 py-1.5 hover:bg-background-light">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span> Dashboard
        </a>
    </div>

    <?php if ($success_message): ?>
        <div class="bg-green-50 text-green-800 p-4 rounded-xl mb-6 border border-green-200/50 flex items-center gap-2.5 shadow-sm">
            <span class="material-symbols-outlined text-green-600">check_circle</span>
            <span class="font-medium text-sm"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="bg-red-50 text-primary p-4 rounded-xl mb-6 border border-red-200/50 flex items-center gap-2.5 shadow-sm">
            <span class="material-symbols-outlined text-red-600">error</span>
            <span class="font-medium text-sm"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="agency_profile.php" class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <!-- Base Profile Info Column (Left 2/3) -->
        <div class="md:col-span-2 flex flex-col gap-6">
            <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col gap-6">
                <h3 class="text-lg font-heading font-semibold text-text-main flex items-center gap-2 border-b border-outline-variant pb-3">
                    <span class="material-symbols-outlined text-primary">domain</span> Company Profile
                </h3>
                
                <div class="grid grid-cols-1 gap-5">
                    <div>
                        <label class="block text-xs uppercase tracking-wider font-bold text-muted mb-1" for="agencyName">Agency Name <span class="text-primary">*</span></label>
                        <input type="text" id="agencyName" name="agencyName" required class="input-field" value="<?php echo htmlspecialchars($agency['AgencyName']); ?>" placeholder="Enter agency legal name">
                    </div>
                </div>

                <h3 class="text-lg font-heading font-semibold text-text-main flex items-center gap-2 border-b border-outline-variant pb-3 mt-4">
                    <span class="material-symbols-outlined text-primary">pin_drop</span> Business Location Details
                </h3>

                <div class="grid grid-cols-1 gap-5">
                    <div>
                        <label class="block text-xs uppercase tracking-wider font-bold text-muted mb-1" for="addressStreet">Street Address <span class="text-primary">*</span></label>
                        <input type="text" id="addressStreet" name="addressStreet" required class="input-field" value="<?php echo htmlspecialchars($agency['Address_Street']); ?>" placeholder="e.g. 102 Parklane Boulevard">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs uppercase tracking-wider font-bold text-muted mb-1" for="addressCity">City <span class="text-primary">*</span></label>
                            <input type="text" id="addressCity" name="addressCity" required class="input-field" value="<?php echo htmlspecialchars($agency['Address_City']); ?>" placeholder="e.g. Cape Town">
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wider font-bold text-muted mb-1" for="addressZip">Zip / Postal Code <span class="text-primary">*</span></label>
                            <input type="text" id="addressZip" name="addressZip" required class="input-field" value="<?php echo htmlspecialchars($agency['Address_Zip']); ?>" placeholder="e.g. 8001">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit action -->
            <div class="flex justify-end gap-3">
                <a href="agency_dashboard.php" class="px-6 h-[48px] border border-outline-variant text-text-main hover:bg-background-light rounded-lg font-semibold flex items-center justify-center transition-colors">Cancel</a>
                <button type="submit" class="px-6 h-[48px] bg-primary text-white rounded-lg font-semibold flex items-center justify-center gap-2 btn-glow transition-all hover:bg-primary/95">
                    <span class="material-symbols-outlined">save</span> Save Changes
                </button>
            </div>
        </div>

        <!-- Sidebar Metadata / Multivalued Phones (Right 1/3) -->
        <div class="flex flex-col gap-6">
            
            <!-- Read-only Security Metrics -->
            <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col gap-4">
                <h3 class="text-sm font-heading font-semibold text-text-main uppercase tracking-wider border-b border-outline-variant pb-2 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-accent text-[18px]">verified_user</span> System Credentials
                </h3>
                
                <div>
                    <div class="text-[10px] text-muted uppercase font-bold tracking-widest mb-0.5">Registration Number</div>
                    <div class="font-mono text-sm bg-background-light px-3 py-2 border border-outline-variant rounded select-all font-semibold text-on-surface"><?php echo htmlspecialchars($agency['RegistrationNumber']); ?></div>
                    <span class="text-[10px] text-muted mt-1 block leading-normal italic">Locked security constraint. Contact support to update the official business registry number.</span>
                </div>

                <div>
                    <div class="text-[10px] text-muted uppercase font-bold tracking-widest mb-0.5">Average Rating</div>
                    <div class="flex items-center gap-2 bg-background-light px-3 py-2 border border-outline-variant rounded">
                        <span class="material-symbols-outlined text-amber-500 fill-1" style="font-variation-settings: 'FILL' 1;">star</span>
                        <span class="font-bold text-sm text-text-main"><?php echo number_format($agency['AverageRating'], 2); ?> / 5.00</span>
                    </div>
                </div>
            </div>

            <!-- Contacts Manager -->
            <div class="bg-surface border border-outline-variant rounded-xl p-6 shadow-sm flex flex-col gap-4">
                <div class="flex justify-between items-center border-b border-outline-variant pb-2">
                    <h3 class="text-sm font-heading font-semibold text-text-main uppercase tracking-wider flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-primary text-[18px]">call</span> Contacts Directory
                    </h3>
                    <button type="button" onclick="addPhoneRow()" class="text-xs font-bold text-primary hover:underline flex items-center gap-0.5">
                        <span class="material-symbols-outlined text-[14px]">add</span> Add Number
                    </button>
                </div>
                
                <p class="text-xs text-secondary -mt-2 leading-relaxed">Provide one or more customer contact phone numbers for travellers to reach out.</p>

                <!-- Javascript Phone Container -->
                <div id="phone-container" class="flex flex-col gap-3">
                    <?php if (empty($contacts)): ?>
                        <!-- If no phone numbers exist, pre-render one empty row -->
                        <div class="phone-row flex items-center gap-2">
                            <input type="text" name="phones[]" required class="input-field font-mono" placeholder="e.g. +27-21-456-7890">
                            <button type="button" onclick="removePhoneRow(this)" class="w-10 h-10 flex items-center justify-center text-muted hover:text-error hover:bg-error-container/20 border border-outline-variant rounded-lg transition-all" title="Remove contact">
                                <span class="material-symbols-outlined text-[20px]">delete</span>
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                            <div class="phone-row flex items-center gap-2">
                                <input type="text" name="phones[]" required class="input-field font-mono" value="<?php echo htmlspecialchars($contact); ?>" placeholder="e.g. +27-21-456-7890">
                                <button type="button" onclick="removePhoneRow(this)" class="w-10 h-10 flex items-center justify-center text-muted hover:text-error hover:bg-error-container/20 border border-outline-variant rounded-lg transition-all" title="Remove contact">
                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </form>
</div>

<script>
    function addPhoneRow() {
        const container = document.getElementById('phone-container');
        const newRow = document.createElement('div');
        newRow.className = 'phone-row flex items-center gap-2 opacity-0 transform translate-y-2 transition-all duration-200';
        newRow.innerHTML = `
            <input type="text" name="phones[]" required class="input-field font-mono" placeholder="e.g. +27-21-456-7890">
            <button type="button" onclick="removePhoneRow(this)" class="w-10 h-10 flex items-center justify-center text-muted hover:text-error hover:bg-error-container/20 border border-outline-variant rounded-lg transition-all" title="Remove contact">
                <span class="material-symbols-outlined text-[20px]">delete</span>
            </button>
        `;
        container.appendChild(newRow);
        
        // Trigger reflow for slide in animation
        setTimeout(() => {
            newRow.classList.remove('opacity-0', 'translate-y-2');
        }, 10);
    }

    function removePhoneRow(btn) {
        const row = btn.closest('.phone-row');
        const container = document.getElementById('phone-container');
        
        // Ensure at least one input field remains visible
        if (container.querySelectorAll('.phone-row').length <= 1) {
            alert("At least one contact telephone number is recommended.");
            return;
        }

        row.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            row.remove();
        }, 200);
    }
</script>

<?php require_once 'includes/footer.php'; ?>
