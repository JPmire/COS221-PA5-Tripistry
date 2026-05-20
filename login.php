<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'TravelAgency' ? 'agency_dashboard.php' : 'traveller_dashboard.php'));
    exit;
}

$page_title = 'Login';
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
</style>

<div class="h-screen w-full flex overflow-hidden bg-background">
    <!-- Left Column: Premium Immersive Experience (Hidden on mobile) -->
    <div class="hidden md:block w-1/2 lg:w-3/5 h-full relative overflow-hidden">
        <!-- Visual Backdrop overlay to blend colors -->
        <div class="absolute inset-0 bg-gradient-to-tr from-text-main/90 via-primary/35 to-transparent z-10"></div>
        <div class="w-full h-full bg-cover bg-center bg-no-repeat transition-transform duration-10000 hover:scale-105" style="background-image: url('https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?q=80&w=2021&auto=format&fit=crop');"></div>
        
        <!-- Left Side Layered Glass Panel -->
        <div class="absolute bottom-12 left-12 right-12 z-20 backdrop-blur-box rounded-2xl p-8 text-white shadow-2xl flex flex-col gap-5 max-w-lg">
            <div>
                <span class="px-3 py-1 bg-white/20 text-white rounded-full text-[10px] font-bold tracking-wider uppercase backdrop-blur-sm select-none">⭐️ Premium Travel Network</span>
                <h2 class="text-3xl font-heading font-bold text-white mt-3 leading-tight tracking-wide">Embark on Your Next Horizon</h2>
                <p class="text-white/80 font-body-md text-sm mt-1">Experience curation like never before. Directly match with elite travel creators and secure custom solo adventures.</p>
            </div>
            <div class="h-[1px] bg-white/10 w-full"></div>
            <!-- Mini Perks Checklist -->
            <div class="flex flex-col gap-2.5">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-[20px] text-primary-fixed-dim">travel_explore</span>
                    <span class="font-body-md text-xs font-semibold text-white/90">Curated Destination Package Discovery</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-[20px] text-primary-fixed-dim">groups_3</span>
                    <span class="font-body-md text-xs font-semibold text-white/90">Direct Agency Match & Private Insights</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-[20px] text-primary-fixed-dim">shield_with_heart</span>
                    <span class="font-body-md text-xs font-semibold text-white/90">Secure Escrow and Automated Wallet Systems</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Portal Login Container -->
    <div class="w-full md:w-1/2 lg:w-2/5 h-full flex flex-col justify-center items-center p-8 sm:p-12 md:p-16 overflow-y-auto bg-surface">
        <div class="w-full max-w-[400px] flex flex-col gap-7 my-auto">
            <!-- Header Block -->
            <div class="text-center">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-primary to-primary-container flex items-center justify-center text-white font-bold text-xl shadow-lg mx-auto mb-4">T</div>
                <h1 class="text-display-lg font-headline-md font-bold text-text-main leading-tight mb-1">Welcome to Tripistry</h1>
                <p class="text-secondary font-body-md text-sm">Sign in to continue your adventure.</p>
            </div>

            <!-- Custom Sliding Segmented Role Toggle -->
            <div class="w-full bg-surface-container-low p-1.5 rounded-xl flex h-[48px] border border-outline-variant/60 relative segmented-control select-none">
                <!-- Inputs as direct children of the container so sibling selectors work -->
                <input checked class="sr-only" name="role" type="radio" value="Traveller" id="role-traveller"/>
                <input class="sr-only" name="role" type="radio" value="TravelAgency" id="role-agency"/>

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
                <span id="error-text">Login details are incorrect.</span>
            </div>

            <!-- Credentials Form -->
            <form id="login-form" class="flex flex-col gap-4">
                <!-- Email Field -->
                <div class="flex flex-col gap-1.5">
                    <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="email">Email Address</label>
                    <div class="relative flex items-center w-full">
                        <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">mail</span>
                        <input class="input-field w-full pl-11 h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="email" required placeholder="name@domain.com" type="email"/>
                    </div>
                </div>
                
                <!-- Password Field -->
                <div class="flex flex-col gap-1.5">
                    <div class="flex justify-between items-center">
                        <label class="font-label-md text-xs font-bold text-secondary uppercase tracking-wider" for="password">Password</label>
                        <a class="text-xs text-primary hover:text-primary-container transition-colors font-bold" href="#">Forgot?</a>
                    </div>
                    <div class="relative flex items-center w-full">
                        <span class="absolute left-3.5 text-muted pointer-events-none material-symbols-outlined text-[20px]">lock</span>
                        <input class="input-field w-full pl-11 pr-11 h-[48px] rounded-xl border border-outline-variant/70 placeholder:text-muted/70 focus:ring-primary/20 focus:border-primary transition-all shadow-sm" id="password" required placeholder="••••••••" type="password"/>
                        <button aria-label="Toggle password visibility" class="absolute right-3 text-muted hover:text-text-main transition-colors flex items-center justify-center p-1 rounded-full hover:bg-surface-container-low" type="button" onclick="togglePassword()">
                            <span class="material-symbols-outlined text-[20px]">visibility_off</span>
                        </button>
                    </div>
                </div>
                
                <!-- Utilities Block -->
                <div class="flex items-center justify-between mt-1 mb-2">
                    <label class="flex items-center gap-2.5 cursor-pointer group">
                        <input class="rounded-[6px] border-outline-variant/80 text-primary focus:ring-primary focus:ring-offset-0 w-4 h-4 cursor-pointer transition-all" type="checkbox"/>
                        <span class="font-body-md text-xs text-text-main font-semibold group-hover:text-primary transition-colors">Keep me signed in</span>
                    </label>
                </div>
                
                <!-- Submission Button -->
                <button class="w-full h-[48px] bg-gradient-to-r from-primary to-primary-container text-white font-bold rounded-xl btn-glow transition-all duration-200 flex items-center justify-center gap-2 hover:-translate-y-0.5 shadow-md active:translate-y-0 disabled:opacity-50 disabled:cursor-not-allowed" type="submit" id="submit-btn" disabled>
                    <span>Sign In</span>
                    <span id="spinner" class="hidden animate-spin material-symbols-outlined text-[18px]">progress_activity</span>
                </button>
            </form>
            
            <!-- Link back to register -->
            <div class="text-center mt-2 border-t border-outline-variant/40 pt-5">
                <p class="font-body-md text-xs font-semibold text-secondary">
                    New to Tripistry? 
                    <a class="text-primary hover:text-primary-container transition-colors ml-1 font-bold hover:underline" href="register.php">Create Free Account</a>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const icon = passwordInput.nextElementSibling.querySelector('span');
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        icon.textContent = type === 'password' ? 'visibility_off' : 'visibility';
    }

    let isSubmitting = false;

    const form = document.getElementById('login-form');
    const submitBtn = document.getElementById('submit-btn');

    form.addEventListener('input', () => {
        submitBtn.disabled = !form.checkValidity() || isSubmitting;
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (isSubmitting) return;

        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const role = document.querySelector('input[name="role"]:checked').value;
        const errorBanner = document.getElementById('error-banner');
        const errorText = document.getElementById('error-text');
        
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
                body: JSON.stringify({
                    type: 'Login',
                    email: email,
                    password: password,
                    role: role
                })
            });

            const data = await res.json();
            
            if (data.status === 'success') {
                window.location.href = data.data.redirect;
            } else {
                throw new Error(data.message || 'Login failed');
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

    // Make sure submitBtn picks up initial valid state
    submitBtn.disabled = !form.checkValidity();
</script>

<?php require_once 'includes/footer.php'; ?>