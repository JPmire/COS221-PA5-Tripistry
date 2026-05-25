<?php
require_once 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Traveller') {
    header("Location: login.php");
    exit;
}

$page_title = 'My Profile';
require_once 'includes/header.php';


$traveller_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    // 1. Fetch current Traveller attributes
    $stmt = $pdo->prepare("SELECT * FROM Traveller WHERE UserID = ?");
    $stmt->execute([$traveller_id]);
    $traveller = $stmt->fetch();

    if (!$traveller) {
        $error = "Traveller profile details not found.";
    } else {
        // 2. Fetch current Traveller preferences
        $stmt = $pdo->prepare("SELECT Preference FROM Traveller_Preferences WHERE UserID = ? ORDER BY Preference ASC");
        $stmt->execute([$traveller_id]);
        $preferences = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (\PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="max-w-4xl mx-auto flex flex-col gap-6">
    
    <!-- Title Area -->
    <div>
        <h1 class="text-3xl font-heading font-semibold text-text-main flex items-center gap-2">
            <span class="material-symbols-outlined text-[32px] text-primary">person_search</span>
            My Profile & Preferences
        </h1>
        <p class="text-muted mt-1">Manage your explorer profile, define your solo travel budget constraints, and configure your preferred destinations.</p>
    </div>

    <!-- Feedback Toast -->
    <div id="toast-success" class="hidden p-4 bg-green-50 border border-green-200/60 text-green-800 rounded-xl flex items-center gap-2.5 shadow-sm">
        <span class="material-symbols-outlined text-[20px] text-green-600">check_circle</span>
        <span class="text-sm font-semibold" id="toast-success-msg">Profile updated successfully!</span>
    </div>
    <div id="toast-error" class="hidden p-4 bg-red-50 border border-red-200/60 text-primary rounded-xl flex items-center gap-2.5 shadow-sm">
        <span class="material-symbols-outlined text-[20px] text-red-600">error</span>
        <span class="text-sm font-semibold" id="toast-error-msg">Failed to update profile.</span>
    </div>

    <?php if ($error): ?>
        <div class="p-4 bg-red-50 border border-red-200/60 text-primary rounded-xl flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[20px] text-red-600">error</span>
            <span class="text-sm font-semibold"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php else: ?>
        
        <form id="traveller-profile-form" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Core Details Card (2/3 width) -->
            <div class="lg:col-span-2 bg-surface rounded-xl border border-outline-variant p-6 shadow-sm flex flex-col gap-6">
                <h2 class="text-lg font-heading font-semibold text-text-main pb-3 border-b border-outline-variant/30 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-primary text-xl">contact_page</span>
                    Personal Explorer Details
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <!-- First Name -->
                    <div>
                        <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-2">First Name</label>
                        <input type="text" name="firstName" value="<?php echo htmlspecialchars($traveller['FirstName']); ?>" required class="input-field">
                    </div>
                    
                    <!-- Last Name -->
                    <div>
                        <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-2">Last Name</label>
                        <input type="text" name="lastName" value="<?php echo htmlspecialchars($traveller['LastName']); ?>" required class="input-field">
                    </div>
                    
                    <!-- DOB -->
                    <div>
                        <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-2">Date of Birth</label>
                        <input type="date" name="dob" value="<?php echo htmlspecialchars($traveller['DOB']); ?>" required class="input-field font-mono">
                    </div>

                    <!-- Solo Budget -->
                    <div>
                        <label class="block text-xs font-bold text-muted uppercase tracking-wider mb-2">Solo Travel Budget (R)</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-muted font-bold text-sm">R</span>
                            <input type="number" name="budget" step="50" min="0" value="<?php echo htmlspecialchars($traveller['SoloBudget']); ?>" required class="input-field pl-8 font-mono">
                        </div>
                        <p class="text-[10px] text-muted mt-1.5 leading-normal">This budget is matched dynamically against group package costs to calculate your compatibility score.</p>
                    </div>
                </div>

                <div class="mt-4 pt-6 border-t border-outline-variant/30 flex justify-end">
                    <button type="submit" id="save-btn" class="bg-primary text-on-primary font-bold text-xs h-[48px] px-8 rounded-lg flex items-center gap-2 btn-glow transition-all">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Save Profile Details
                    </button>
                </div>
            </div>

            <!-- Multi-value preferences Manager (1/3 width) -->
            <div class="bg-surface rounded-xl border border-outline-variant p-6 shadow-sm flex flex-col gap-5 self-start">
                <div class="flex justify-between items-center pb-3 border-b border-outline-variant/30">
                    <h2 class="text-lg font-heading font-semibold text-text-main flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-primary text-xl">favorite</span>
                        Preferences
                    </h2>
                    <button type="button" onclick="addPreferenceRow()" class="w-8 h-8 rounded-full bg-surface-container-low text-primary border border-outline-variant/40 flex items-center justify-center hover:bg-primary/5 transition-all" title="Add Destination Preference">
                        <span class="material-symbols-outlined text-[18px] font-bold">add</span>
                    </button>
                </div>

                <p class="text-[11px] text-muted leading-relaxed">Define countries, cities, or activities (e.g. <em>Paris, Tokyo, Beaches, Romance</em>) you are interested in. These tags feed our Smart Matchmaker compatibility analysis!</p>

                <!-- Preferences list container -->
                <div id="prefs-container" class="flex flex-col gap-2.5 max-h-[300px] overflow-y-auto pr-1">
                    <?php if (empty($preferences)): ?>
                        <div id="no-prefs-msg" class="text-center py-4 text-muted italic text-[11px]">No destination preferences added yet. Click '+' to define one!</div>
                    <?php else: ?>
                        <?php foreach ($preferences as $pref): ?>
                            <div class="flex items-center gap-2 pref-row">
                                <input type="text" name="preferences[]" value="<?php echo htmlspecialchars($pref); ?>" placeholder="e.g. Japan" required class="w-full h-[40px] px-3 rounded-lg border border-outline-variant bg-surface text-xs placeholder:text-muted focus:outline-none focus:border-primary transition-colors">
                                <button type="button" onclick="removePreferenceRow(this)" class="w-10 h-10 border border-outline-variant bg-surface rounded-lg text-secondary hover:text-error hover:border-error/30 flex items-center justify-center shrink-0 transition-all">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>

        </form>

    <?php endif; ?>

</div>

<script>
    function addPreferenceRow() {
        const container = document.getElementById('prefs-container');
        const noPrefsMsg = document.getElementById('no-prefs-msg');
        if (noPrefsMsg) {
            noPrefsMsg.remove();
        }

        const div = document.createElement('div');
        div.className = 'flex items-center gap-2 pref-row opacity-0 transform -translate-y-1 transition-all duration-200';
        div.innerHTML = `
            <input type="text" name="preferences[]" placeholder="e.g. Tokyo" required class="w-full h-[40px] px-3 rounded-lg border border-outline-variant bg-surface text-xs placeholder:text-muted focus:outline-none focus:border-primary transition-colors">
            <button type="button" onclick="removePreferenceRow(this)" class="w-10 h-10 border border-outline-variant bg-surface rounded-lg text-secondary hover:text-error hover:border-error/30 flex items-center justify-center shrink-0 transition-all">
                <span class="material-symbols-outlined text-[18px]">delete</span>
            </button>
        `;
        container.appendChild(div);
        
        // Micro-animation reveal
        setTimeout(() => {
            div.classList.remove('opacity-0', '-translate-y-1');
        }, 50);
    }

    function removePreferenceRow(btn) {
        const row = btn.closest('.pref-row');
        row.classList.add('opacity-0', 'scale-95');
        
        setTimeout(() => {
            row.remove();
            
            const container = document.getElementById('prefs-container');
            const remaining = container.querySelectorAll('.pref-row');
            if (remaining.length === 0) {
                container.innerHTML = `<div id="no-prefs-msg" class="text-center py-4 text-muted italic text-[11px]">No destination preferences added yet. Click '+' to define one!</div>`;
            }
        }, 200);
    }

    const form = document.getElementById('traveller-profile-form');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const saveBtn = document.getElementById('save-btn');
            const originalHtml = saveBtn.innerHTML;
            
            saveBtn.disabled = true;
            saveBtn.innerHTML = `<span class="material-symbols-outlined text-[18px] animate-spin">sync</span> Saving...`;

            // Extract core fields
            const firstName = form.querySelector('[name="firstName"]').value;
            const lastName = form.querySelector('[name="lastName"]').value;
            const dob = form.querySelector('[name="dob"]').value;
            const budget = form.querySelector('[name="budget"]').value;

            // Extract preferences array
            const prefInputs = form.querySelectorAll('[name="preferences[]"]');
            const preferences = [];
            prefInputs.forEach(input => {
                const val = input.value.trim();
                if (val !== '') {
                    preferences.push(val);
                }
            });

            const payload = {
                type: 'UpdateTravellerProfile',
                firstName: firstName,
                lastName: lastName,
                dob: dob,
                budget: parseFloat(budget),
                preferences: preferences
            };

            const successToast = document.getElementById('toast-success');
            const errorToast = document.getElementById('toast-error');

            successToast.classList.add('hidden');
            errorToast.classList.add('hidden');

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const result = await response.json();

                if (result.status === 'success') {
                    successToast.classList.remove('hidden');
                    document.getElementById('toast-success-msg').innerHTML = result.data.message || 'Profile details saved successfully!';
                    
                    // Update user initials avatar and session name in top menu
                    const nameLabel = document.querySelector('.group .text-right .font-label-md');
                    if (nameLabel) {
                        nameLabel.textContent = `${firstName} ${lastName}`;
                    }
                    const avatar = document.querySelector('.group .w-10');
                    if (avatar) {
                        avatar.textContent = firstName.substr(0,1).toUpperCase();
                    }

                    // Smooth scroll to top of form
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    errorToast.classList.remove('hidden');
                    document.getElementById('toast-error-msg').textContent = result.message || 'Failed to update traveler profile.';
                }
            } catch (err) {
                errorToast.classList.remove('hidden');
                document.getElementById('toast-error-msg').textContent = 'A system or network error occurred: ' + err.message;
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalHtml;
            }
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
