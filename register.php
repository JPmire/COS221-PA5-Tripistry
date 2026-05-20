<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'TravelAgency' ? 'agency_dashboard.php' : 'traveller_dashboard.php'));
    exit;
}

$page_title = 'Create Account';
$hide_nav = true;
require_once 'includes/header.php';
?>

<style>
    /* Sliding Segmented Control slider design */
    .segmented-control {
        position: relative;
    }
    .segmented-slider {
        position: absolute;
        top: 4px;
        bottom: 4px;
        left: 4px;
        width: calc(50% - 6px);
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.02);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1;
    }
    #role-agency:checked ~ .segmented-slider {
        transform: translateX(100%);
    }
    /* Dynamic active label coloration */
    #role-traveller:checked ~ label[for="role-traveller"] .role-tab,
    #role-agency:checked ~ label[for="role-agency"] .role-tab {
        color: var(--primary, #b7102a) !important;
        font-weight: 600;
    }
    /* Floating landscape left card overlay blur */
    .backdrop-blur-box {
        background: rgba(255, 255, 255, 0.07);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    /* Form Switch Keyframe Transition */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .role-fields-container {
        animation: slideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }
</style>

<div class="h-screen w-full flex overflow-hidden bg-background">
    <!-- Left Column: Premium Immersive Experience (Hidden on mobile) -->
    <div class="hidden md:block w-1/2 lg:w-3/5 h-full relative overflow-hidden">
        <!-- Visual Backdrop overlay -->
        <div class="absolute inset-0 bg-gradient-to-tr from-text-main/90 via-primary/35 to-transparent z-10"></div>
        <div class="w-full h-full bg-cover bg-center bg-no-repeat transition-transform duration-10000 hover:scale-105" style="background-image: url('https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?q=80&w=2021&auto=format&fit=crop');"></div>
        
        <!-- Left Side Layered Glass Panel -->
        <div class="absolute bottom-12 left-12 right-12 z-20 backdrop-blur-box rounded-2xl p-8 text-white shadow-2xl flex flex-col gap-5 max-w-lg">
            <div>
                <span class="px-3 py-1 bg-white/20 text-white rounded-full text-[10px] font-bold tracking-wider uppercase backdrop-blur-sm select-none">⭐️ Premium Travel Network</span>
                <h2 class="text-3xl font-heading font-bold text-white mt-3 leading-tight tracking-wide">Embark on Your Next Horizon</h2>
                <p class="text-white/80 font-body-md text-sm mt-1">Create an account to begin. Discover incredible itineraries, interact directly with vetted creators, and track your travel records instantly.</p>
            </div>
            <div class="h-[1px] bg-white/10 w-full"></div>
            <!-- Perks -->
            <div class="flex flex-col gap-2.5">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-[20px] text-primary-fixed-dim">verified_user</span>
                    <span class="font-body-md text-xs font-semibold text-white/90">Safe & Authenticated Travel Environment</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-[20px] text-primary-fixed-dim">query_stats</span>
                    <span class="font-body-md text-xs font-semibold text-white/90">Live Booking Metrics & Analytics</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-[20px] text-primary-fixed-dim">hotel</span>
                    <span class="font-body-md text-xs font-semibold text-white/90">Curated Accommodations & Excursions</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Portal Registration Container -->
    <div class="w-full md:w-1/2 lg:w-2/5 h-full flex flex-col justify-start items-center p-6 sm:p-12 md:p-16 overflow-y-auto bg-surface">
        <div class="w-full max-w-[480px] flex flex-col gap-6 my-auto py-8">
            <!-- Header Block -->
            <div class="text-center">
                <h1 class="text-display-lg font-headline-md font-bold text-text-main leading-tight mb-1">Create Your Account</h1>
                <p class="text-secondary font-body-md text-sm">Join Tripistry and begin your adventure today.</p>
            </div>

            <!-- Custom Sliding Segmented Role Toggle -->
            <div class="w-full bg-surface-container-low p-1.5 rounded-xl flex h-[48px] border border-outline-variant/60 relative segmented-control select-none">
                <!-- Inputs as direct children so sibling selectors work -->
                <input checked class="sr-only" name="role" type="radio" value="Traveller" id="role-traveller" onchange="toggleRoleFields()"/>
                <input class="sr-only" name="role" type="radio" value="TravelAgency" id="role-agency" onchange="toggleRoleFields()"/>

                <!-- Sliding Background Block -->
                <div class="segmented-slider"></div>
                
                <label for="role-traveller" class="flex-1 cursor-pointer relative z-10 w-1/2 h-full">
                    <span class="role-tab h-full w-full flex items-center justify-center rounded-[8px] text-muted text-xs font-bold transition-all duration-300">
                        Explorer Traveller
                    </span>
                </label>
                <label for="role-agency" class="flex-1 cursor-pointer relative z-10 w-1/2 h-full">
                    <span class="role-tab h-full w-full flex items-center justify-center rounded-[8px] text-muted text-xs font-bold transition-all duration-300">
                        Agency Partner
                    </span>
                </label>
            </div>

            <!-- Error Banner -->
            <div id="error-banner" class="hidden bg-error-container text-error p-3.5 rounded-xl text-xs font-semibold font-body-md border border-error/20 flex items-center gap-2 animate-pulse shadow-sm">
                <span class="material-symbols-outlined text-[18px]">error</span>
                <span id="error-text">Registration details are invalid.</span>
            </div>

            <!-- Registration Form -->
            <form id="register-form" class="flex flex-col gap-4">
                <!-- Common Email Field -->
                <div class="flex flex-col gap-1.5">
                    <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="email">Email Address <span class="text-primary">*</span></label>
                    <div class="relative flex items-center w-full">
                        <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">mail</span>
                        <input class="input-field w-full pl-11 h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="email" required placeholder="explorer@domain.com" type="email"/>
                    </div>
                </div>

                <!-- Common Passwords Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="password">Password <span class="text-primary">*</span></label>
                        <div class="relative flex items-center w-full">
                            <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">lock</span>
                            <input class="input-field w-full pl-11 pr-10 h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="password" required placeholder="Min 6 chars" type="password" minlength="6"/>
                            <button aria-label="Toggle password" class="absolute right-3 text-muted hover:text-text-main transition-colors flex items-center justify-center p-1 rounded-full hover:bg-surface-container-low" type="button" onclick="togglePassword('password')">
                                <span class="material-symbols-outlined text-[18px]">visibility_off</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="confirm-password">Confirm <span class="text-primary">*</span></label>
                        <div class="relative flex items-center w-full">
                            <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">lock</span>
                            <input class="input-field w-full pl-11 pr-10 h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="confirm-password" required placeholder="Re-enter" type="password"/>
                            <button aria-label="Toggle confirm password" class="absolute right-3 text-muted hover:text-text-main transition-colors flex items-center justify-center p-1 rounded-full hover:bg-surface-container-low" type="button" onclick="togglePassword('confirm-password')">
                                <span class="material-symbols-outlined text-[18px]">visibility_off</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- DYNAMIC FIELD CONTAINER 1: TRAVELLER -->
                <div id="traveller-fields" class="role-fields-container flex flex-col gap-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="firstName">First Name <span class="text-primary">*</span></label>
                            <div class="relative flex items-center w-full">
                                <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">person</span>
                                <input class="input-field w-full pl-11 h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="firstName" required placeholder="John" type="text"/>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="lastName">Last Name <span class="text-primary">*</span></label>
                            <div class="relative flex items-center w-full">
                                <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">person</span>
                                <input class="input-field w-full pl-11 h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="lastName" required placeholder="Doe" type="text"/>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="dob">Date of Birth <span class="text-primary">*</span></label>
                        <div class="relative flex items-center w-full">
                            <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">calendar_month</span>
                            <input class="input-field w-full pl-11 h-[48px] rounded-xl border border-outline-variant/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="dob" required type="date" max="<?php echo date('Y-m-d'); ?>"/>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="preferences">Travel Preferences</label>
                        <div class="relative flex items-center w-full">
                            <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">interests</span>
                            <input class="input-field w-full pl-11 h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="preferences" placeholder="e.g. Beaches, Luxury, Hiking" type="text"/>
                        </div>
                        <p class="text-[10px] text-muted tracking-wide mt-0.5">Separate multiple preferences with commas.</p>
                    </div>
                </div>

                <!-- DYNAMIC FIELD CONTAINER 2: TRAVEL AGENCY -->
                <div id="agency-fields" class="hidden role-fields-container flex flex-col gap-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="agencyName">Agency Name <span class="text-primary">*</span></label>
                            <div class="relative flex items-center w-full">
                                <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">corporate_fare</span>
                                <input class="input-field w-full pl-11 h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="agencyName" placeholder="Wanderlust Travels" type="text"/>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="registrationNumber">Reg. Number <span class="text-primary">*</span></label>
                            <div class="relative flex items-center w-full">
                                <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">badge</span>
                                <input class="input-field w-full pl-11 h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="registrationNumber" placeholder="REG-1001" type="text"/>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-1.5">
                        <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="contactNumbers">Contact Number(s) <span class="text-primary">*</span></label>
                        <div class="relative flex items-center w-full">
                            <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">call</span>
                            <input class="input-field w-full pl-11 h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="contactNumbers" placeholder="+27 12 345 6789" type="text"/>
                        </div>
                        <p class="text-[10px] text-muted tracking-wide mt-0.5">Separate multiple hotlines with commas.</p>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="addressStreet">Street Address <span class="text-primary">*</span></label>
                        <div class="relative flex items-center w-full">
                            <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">home</span>
                            <input class="input-field w-full pl-11 h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="addressStreet" placeholder="123 Explorer Road" type="text"/>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="addressCity">City <span class="text-primary">*</span></label>
                            <input class="input-field w-full h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm px-4" id="addressCity" placeholder="Cape Town" type="text"/>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="addressZip">Zip Code <span class="text-primary">*</span></label>
                            <input class="input-field w-full h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm px-4" id="addressZip" placeholder="8001" type="text"/>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button class="w-full h-[48px] bg-gradient-to-r from-primary to-primary-container text-white font-bold rounded-xl btn-glow transition-all duration-200 flex items-center justify-center gap-2 hover:-translate-y-0.5 shadow-md active:translate-y-0 disabled:opacity-50 disabled:cursor-not-allowed mt-3" type="submit" id="submit-btn" disabled>
                    <span>Create Account</span>
                    <span id="spinner" class="hidden animate-spin material-symbols-outlined text-[18px]">progress_activity</span>
                </button>
            </form>
            
            <!-- Link back to login -->
            <div class="text-center mt-2 border-t border-outline-variant/40 pt-5">
                <p class="font-body-md text-xs font-semibold text-secondary">
                    Already have an account? 
                    <a class="text-primary hover:text-primary-container transition-colors ml-1 font-bold hover:underline" href="login.php">Sign In Here</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.nextElementSibling.querySelector('span');
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        icon.textContent = type === 'password' ? 'visibility_off' : 'visibility';
    }

    function toggleRoleFields() {
        const isTraveller = document.getElementById('role-traveller').checked;
        const travellerFields = document.getElementById('traveller-fields');
        const agencyFields = document.getElementById('agency-fields');

        // Get inputs
        const travInputs = travellerFields.querySelectorAll('input');
        const agencyInputs = agencyFields.querySelectorAll('input');

        if (isTraveller) {
            // Apply animations and classes
            travellerFields.classList.remove('hidden');
            agencyFields.classList.add('hidden');
            
            travInputs.forEach(input => {
                if (input.id !== 'preferences') input.setAttribute('required', '');
            });
            agencyInputs.forEach(input => {
                input.removeAttribute('required');
            });
        } else {
            travellerFields.classList.add('hidden');
            agencyFields.classList.remove('hidden');
            
            travInputs.forEach(input => {
                input.removeAttribute('required');
            });
            agencyInputs.forEach(input => {
                input.setAttribute('required', '');
            });
        }
        
        validateForm();
    }

    const form = document.getElementById('register-form');
    const submitBtn = document.getElementById('submit-btn');

    function validateForm() {
        const pass = document.getElementById('password').value;
        const confirmPass = document.getElementById('confirm-password').value;
        const passwordsMatch = pass === confirmPass;

        if (pass && confirmPass && !passwordsMatch) {
            document.getElementById('confirm-password').setCustomValidity("Passwords do not match.");
        } else {
            document.getElementById('confirm-password').setCustomValidity("");
        }

        submitBtn.disabled = !form.checkValidity() || isSubmitting;
    }

    let isSubmitting = false;
    form.addEventListener('input', validateForm);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (isSubmitting) return;

        const role = document.querySelector('input[name="role"]:checked').value;
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        const errorBanner = document.getElementById('error-banner');
        const errorText = document.getElementById('error-text');

        if (password !== confirmPassword) {
            errorText.textContent = "Passwords do not match.";
            errorBanner.classList.remove('hidden');
            return;
        }

        const payload = {
            type: 'Register',
            role: role,
            email: email,
            password: password
        };

        if (role === 'Traveller') {
            payload.firstName = document.getElementById('firstName').value;
            payload.lastName = document.getElementById('lastName').value;
            payload.dob = document.getElementById('dob').value;
            payload.preferences = document.getElementById('preferences').value;
        } else {
            payload.agencyName = document.getElementById('agencyName').value;
            payload.registrationNumber = document.getElementById('registrationNumber').value;
            payload.contacts = document.getElementById('contactNumbers').value;
            payload.addressStreet = document.getElementById('addressStreet').value;
            payload.addressCity = document.getElementById('addressCity').value;
            payload.addressZip = document.getElementById('addressZip').value;
        }

        isSubmitting = true;
        submitBtn.disabled = true;
        document.getElementById('spinner').classList.remove('hidden');
        errorBanner.classList.add('hidden');

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            
            if (data.status === 'success') {
                window.location.href = data.data.redirect;
            } else {
                throw new Error(data.message || 'Registration failed');
            }
        } catch (err) {
            errorText.textContent = err.message;
            errorBanner.classList.remove('hidden');
        } finally {
            isSubmitting = false;
            submitBtn.disabled = false;
            document.getElementById('spinner').classList.add('hidden');
        }
    });

    // Initialize required attributes on page load
    toggleRoleFields();
</script>

<?php require_once 'includes/footer.php'; ?>
