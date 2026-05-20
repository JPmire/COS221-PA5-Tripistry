<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Base path dynamically calculated to support running in any subdirectory (e.g. Current Task 5)
$base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');


$current_page = basename($_SERVER['PHP_SELF']);

function getSidebarClass($pageName, $current_page, $aliasPages = []) {
    $isActive = ($current_page === $pageName) || in_array($current_page, $aliasPages);
    if ($isActive) {
        return "flex items-center gap-3 px-4.5 py-3 text-primary font-bold border-l-4 border-primary bg-primary/5 transition-all duration-200 cursor-pointer rounded-r-xl shadow-sm";
    } else {
        return "flex items-center gap-3 px-4.5 py-3 text-secondary hover:text-primary hover:bg-surface-container-low/70 hover:translate-x-1 transition-all duration-200 cursor-pointer rounded-xl";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - Tripistry' : 'Tripistry'; ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Epilogue:wght@600&amp;family=Manrope:wght@400;500;600&amp;family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #e4bebc; /* adjust based on outline-variant */
            border-radius: 10px;
        }
        .btn-glow {
            box-shadow: 0 4px 14px 0 rgba(183, 16, 42, 0.25);
        }
        .btn-glow:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(183, 16, 42, 0.35);
        }
        /* Design System Premium Tokens */
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(228, 190, 188, 0.45);
            border-radius: 1.25rem; /* rounded-2xl */
            box-shadow: 0 4px 20px -2px rgba(29, 53, 87, 0.04);
        }
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 28px -4px rgba(29, 53, 87, 0.08), 0 4px 14px -2px rgba(29, 53, 87, 0.03);
            border-color: rgba(183, 16, 42, 0.25);
        }
        .btn-premium {
            height: 48px;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
            background: linear-gradient(135deg, #b7102a 0%, #db313f 100%);
            color: #FFFFFF;
            border-radius: 0.75rem; /* rounded-xl */
            font-weight: 600;
            font-size: 15px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px 0 rgba(183, 16, 42, 0.25);
        }
        .btn-premium:hover {
            transform: translateY(-1.5px);
            box-shadow: 0 8px 22px 0 rgba(183, 16, 42, 0.35);
        }
        .btn-premium:active {
            transform: translateY(0);
        }
        .btn-premium-secondary {
            height: 48px;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
            background-color: transparent;
            color: #1D3557;
            border: 1px solid rgba(228, 190, 188, 0.8);
            border-radius: 0.75rem; /* rounded-xl */
            font-weight: 600;
            font-size: 15px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-premium-secondary:hover {
            background-color: rgba(183, 16, 42, 0.04);
            border-color: #b7102a;
            color: #b7102a;
            transform: translateY(-1px);
        }
        .btn-premium-secondary:active {
            transform: translateY(0);
        }
        .modern-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .modern-table th {
            font-family: 'Epilogue', sans-serif;
            font-weight: 600;
            font-size: 11px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #485f84;
            padding: 1rem 1.5rem;
            background-color: #eff3ff;
            border-bottom: 1px solid rgba(228, 190, 188, 0.3);
        }
        .modern-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(228, 190, 188, 0.2);
            color: #101c2c;
            transition: background-color 0.2s ease;
        }
        .modern-table tr:hover td {
            background-color: rgba(239, 243, 255, 0.3);
        }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
          darkMode: "class",
          theme: {
            extend: {
              "colors": {
                "secondary-fixed": "#d5e3ff",
                "outline": "#8f6f6e",
                "on-tertiary-container": "#fcfcff",
                "error-container": "#ffdad6",
                "inverse-primary": "#ffb3b1",
                "on-surface": "#101c2c",
                "on-primary-container": "#fffbff",
                "tertiary-fixed-dim": "#98cdf2",
                "background": "#f9f9ff",
                "background-light": "#F8F9FA",
                "surface-tint": "#bb152c",
                "primary-fixed": "#ffdad8",
                "on-tertiary-fixed-variant": "#064c6b",
                "on-surface-variant": "#5b403f",
                "surface": "#FFFFFF",
                "on-secondary-container": "#445a7f",
                "outline-variant": "#e4bebc",
                "surface-container-high": "#dde9ff",
                "secondary-fixed-dim": "#b0c7f1",
                "on-secondary-fixed": "#001b3c",
                "primary-container": "#db313f",
                "on-tertiary": "#ffffff",
                "on-primary-fixed-variant": "#92001c",
                "on-error": "#ffffff",
                "on-secondary": "#ffffff",
                "surface-bright": "#f9f9ff",
                "error": "#ba1a1a",
                "muted": "#8D99AE",
                "secondary": "#485f84",
                "surface-variant": "#d7e3fa",
                "accent": "#457B9D",
                "tertiary-container": "#447a9c",
                "tertiary": "#286182",
                "text-main": "#1D3557",
                "primary": "#b7102a",
                "on-background": "#101c2c",
                "on-tertiary-fixed": "#001e2e",
                "surface-container-low": "#eff3ff",
                "on-primary": "#ffffff",
                "tertiary-fixed": "#c7e7ff",
                "secondary-container": "#bbd3fd",
                "inverse-surface": "#253142",
                "on-error-container": "#93000a",
                "on-primary-fixed": "#410007",
                "surface-container": "#e6eeff",
                "surface-container-lowest": "#ffffff",
                "primary-fixed-dim": "#ffb3b1",
                "on-secondary-fixed-variant": "#30476a"
              },
              "borderRadius": {
                "DEFAULT": "0.25rem",
                "lg": "0.5rem",
                "xl": "0.75rem",
                "full": "9999px"
              },
              "spacing": {
                "input-height": "48px",
                "stack-gap-md": "1.25rem",
                "stack-gap-lg": "2rem",
                "container-padding-mobile": "1.5rem",
                "container-padding-desktop": "4rem",
                "stack-gap-sm": "0.5rem"
              },
              "fontFamily": {
                "headline-md": ["Epilogue"],
                "label-md": ["Manrope"],
                "button-text": ["Manrope"],
                "body-lg": ["Manrope"],
                "display-lg": ["Epilogue"],
                "body-md": ["Manrope"],
                "heading": ["Epilogue", "sans-serif"],
                "body": ["Manrope", "sans-serif"]
              },
              "fontSize": {
                "headline-md": ["24px", {"lineHeight": "1.3", "fontWeight": "600"}],
                "label-md": ["14px", {"lineHeight": "1.2", "fontWeight": "500"}],
                "button-text": ["15px", {"lineHeight": "1", "letterSpacing": "0.01em", "fontWeight": "600"}],
                "body-lg": ["16px", {"lineHeight": "1.6", "fontWeight": "400"}],
                "display-lg": ["32px", {"lineHeight": "1.2", "letterSpacing": "-0.02em", "fontWeight": "600"}],
                "body-md": ["14px", {"lineHeight": "1.5", "fontWeight": "400"}]
              }
            }
          }
        }
    </script>
    <style>
      body { font-family: 'Manrope', sans-serif; }
      h1, h2, h3, h4, h5, h6, .font-heading { font-family: 'Epilogue', sans-serif; }
      .segmented-control input:checked + div {
        background-color: white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        color: #1D3557;
      }
      .btn {
          height: 48px;
          padding-left: 1.5rem;
          padding-right: 1.5rem;
          background-color: #b7102a;
          color: #FFFFFF;
          border-radius: 0.5rem;
          font-weight: 600;
          font-size: 15px;
          transition: all 0.2s ease-in-out;
          display: inline-flex;
          align-items: center;
          justify-content: center;
      }
      .input-field {
          width: 100%;
          height: 48px;
          padding-left: 1rem;
          padding-right: 1rem;
          border-radius: 0.5rem;
          border: 1px solid #e4bebc;
          background-color: #FFFFFF;
          color: #101c2c;
          transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
      }
      .input-field::placeholder {
          color: #8D99AE;
      }
      .input-field:focus {
          outline: none;
          border-color: #b7102a;
          box-shadow: 0 0 0 1px #b7102a;
      }
    </style>
    <?php if (isset($extra_css)): ?>
    <link rel="stylesheet" href="<?php echo $base_url . '/' . $extra_css; ?>">
    <?php endif; ?>
</head>
<body class="bg-background-light text-on-surface flex flex-col min-h-screen">
    <?php if (!isset($hide_nav) || !$hide_nav): ?>
    <!-- Sidebar Navigation -->
    <aside class="fixed left-0 top-0 h-full flex flex-col bg-surface h-screen w-64 border-r border-outline-variant z-50">
        <div class="p-6">
            <div class="font-headline-md text-headline-md font-bold text-primary mb-1">Tripistry</div>
            <?php if (isset($_SESSION['role'])): ?>
                <div class="font-body-md text-body-md text-secondary"><?php echo $_SESSION['role'] === 'TravelAgency' ? 'Agency Partner' : 'Traveller'; ?></div>
            <?php endif; ?>
        </div>
        <nav class="flex-1 px-4 space-y-1.5 mt-4">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'TravelAgency'): ?>
                    <a href="<?php echo $base_url; ?>/agency_dashboard.php" class="<?php echo getSidebarClass('agency_dashboard.php', $current_page); ?>">
                        <span class="material-symbols-outlined">dashboard</span>
                        <span class="font-body-md">Dashboard</span>
                    </a>
                    <a href="<?php echo $base_url; ?>/manage_packages.php" class="<?php echo getSidebarClass('manage_packages.php', $current_page, ['create_package.php', 'edit_package.php']); ?>">
                        <span class="material-symbols-outlined">inventory_2</span>
                        <span class="font-body-md">Manage Packages</span>
                    </a>
                    <a href="<?php echo $base_url; ?>/agency_bookings.php" class="<?php echo getSidebarClass('agency_bookings.php', $current_page); ?>">
                        <span class="material-symbols-outlined">payments</span>
                        <span class="font-body-md">Manage Bookings</span>
                    </a>
                    <a href="<?php echo $base_url; ?>/agency_matchmaker.php" class="<?php echo getSidebarClass('agency_matchmaker.php', $current_page); ?>">
                        <span class="material-symbols-outlined">groups_3</span>
                        <span class="font-body-md">Traveller Insights</span>
                    </a>
                    <a href="<?php echo $base_url; ?>/agency_profile.php" class="<?php echo getSidebarClass('agency_profile.php', $current_page); ?>">
                        <span class="material-symbols-outlined">settings</span>
                        <span class="font-body-md">Agency Profile</span>
                    </a>
                <?php else: ?>
                    <a href="<?php echo $base_url; ?>/traveller_dashboard.php" class="<?php echo getSidebarClass('traveller_dashboard.php', $current_page); ?>">
                        <span class="material-symbols-outlined">dashboard</span>
                        <span class="font-body-md">Dashboard</span>
                    </a>
                    <a href="<?php echo $base_url; ?>/packages.php" class="<?php echo getSidebarClass('packages.php', $current_page, ['package_detail.php', 'compare.php']); ?>">
                        <span class="material-symbols-outlined">travel_explore</span>
                        <span class="font-body-md">Browse Packages</span>
                    </a>
                    <a href="<?php echo $base_url; ?>/traveller_agencies.php" class="<?php echo getSidebarClass('traveller_agencies.php', $current_page); ?>">
                        <span class="material-symbols-outlined">corporate_fare</span>
                        <span class="font-body-md">Explore Agencies</span>
                    </a>
                    <a href="<?php echo $base_url; ?>/assets_explorer.php" class="<?php echo getSidebarClass('assets_explorer.php', $current_page); ?>">
                        <span class="material-symbols-outlined">explore</span>
                        <span class="font-body-md">Asset Explorer</span>
                    </a>
                    <a href="<?php echo $base_url; ?>/traveller_profile.php" class="<?php echo getSidebarClass('traveller_profile.php', $current_page); ?>">
                        <span class="material-symbols-outlined">person</span>
                        <span class="font-body-md">My Profile</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
        <div class="p-6 mt-auto">
            <a href="<?php echo $base_url; ?>/logout.php" class="flex items-center gap-3 px-4 py-3 text-secondary hover:text-primary hover:bg-surface-container-low transition-colors duration-200 rounded cursor-pointer rounded-lg">
                <span class="material-symbols-outlined">logout</span>
                <span class="font-body-md">Logout</span>
            </a>
        </div>
    </aside>

    <!-- Top AppBar -->
    <header class="sticky top-0 z-40 flex items-center justify-between px-container-desktop w-full pl-72 pr-8 h-16 bg-surface border-b border-outline-variant">
        <div class="flex items-center gap-4">
            <div class="font-heading font-semibold text-text-main text-lg tracking-wide uppercase">
                <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Tripistry Portal'; ?>
            </div>
        </div>
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-4 relative">
                <!-- Notification Bell Button -->
                <button id="notification-btn" class="relative material-symbols-outlined text-secondary hover:bg-surface-container-highest p-2 rounded-full transition-all duration-200" title="Notifications">
                    notifications
                    <!-- Unread Count Badge -->
                    <span id="notification-badge" class="absolute top-0 right-0 min-w-4 h-4 px-1 bg-primary text-white text-[9px] font-bold rounded-full flex items-center justify-center scale-0 opacity-0 transition-all duration-300 shadow-sm">
                        0
                    </span>
                </button>
                
                <!-- Floating Glassmorphic Notification Dropdown Card -->
                <div id="notification-dropdown" class="absolute right-0 top-12 w-80 max-w-sm bg-white/95 backdrop-blur-md border border-outline-variant/60 rounded-xl shadow-xl z-50 overflow-hidden transform scale-95 opacity-0 pointer-events-none transition-all duration-200 origin-top-right flex flex-col">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-4 py-3 bg-surface-container-low border-b border-outline-variant/50">
                        <span class="font-heading font-semibold text-text-main text-[14px]">Notifications</span>
                        <button id="notification-mark-read" class="text-[12px] text-primary font-bold hover:underline transition-colors hidden">Mark all read</button>
                    </div>
                    
                    <!-- Content area -->
                    <div id="notification-list" class="max-h-72 overflow-y-auto divide-y divide-outline-variant/30">
                        <!-- Loaded dynamically -->
                        <div class="p-4 text-center text-muted text-body-md">
                            Loading notifications...
                        </div>
                    </div>
                    
                    <!-- Empty State (hidden by default) -->
                    <div id="notification-empty" class="hidden p-6 text-center text-muted flex flex-col items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-3xl text-outline-variant text-outline-variant">notifications_off</span>
                        <span class="font-body-md text-secondary font-medium">All caught up!</span>
                        <span class="text-[11px] text-muted">No new alerts to show right now.</span>
                    </div>
                </div>
            </div>
            <div class="h-8 w-[1px] bg-outline-variant"></div>
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="flex items-center gap-3 cursor-pointer group">
                <div class="text-right">
                    <div class="font-label-md text-label-md text-text-main group-hover:text-primary transition-colors"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></div>
                    <div class="text-[11px] text-muted uppercase tracking-wider font-bold"><?php echo $_SESSION['role']; ?></div>
                </div>
                <div class="w-10 h-10 rounded-full border-2 border-outline-variant bg-surface-container-low flex items-center justify-center text-primary font-bold">
                    <?php echo substr($_SESSION['name'] ?? 'U', 0, 1); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <?php if (isset($_SESSION['user_id'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('notification-btn');
        const dropdown = document.getElementById('notification-dropdown');
        const badge = document.getElementById('notification-badge');
        const list = document.getElementById('notification-list');
        const markReadBtn = document.getElementById('notification-mark-read');
        const emptyState = document.getElementById('notification-empty');
        
        let notifications = [];
        let isOpen = false;

        // Toggle dropdown visibility
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            isOpen = !isOpen;
            if (isOpen) {
                dropdown.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
                dropdown.classList.add('opacity-100', 'scale-100', 'pointer-events-auto');
                // If they open it, also call API to load latest and mark all read automatically
                fetchNotifications(true);
            } else {
                closeDropdown();
            }
        });

        function closeDropdown() {
            isOpen = false;
            dropdown.classList.remove('opacity-100', 'scale-100', 'pointer-events-auto');
            dropdown.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
        }

        // Dismiss dropdown on escape key or clicking outside
        document.addEventListener('click', (e) => {
            if (isOpen && !dropdown.contains(e.target) && !btn.contains(e.target)) {
                closeDropdown();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isOpen) {
                closeDropdown();
            }
        });

        async function fetchNotifications(autoMarkRead = false) {
            try {
                const res = await fetch('<?php echo $base_url; ?>/api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'GetNotifications' })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    notifications = data.data.notifications || [];
                    const unreadCount = data.data.unread_count || 0;
                    
                    // Update badge
                    updateBadge(unreadCount);
                    
                    // Render list
                    renderNotifications();
                    
                    // Asynchronously mark as read if opening
                    if (autoMarkRead && unreadCount > 0) {
                        await markAllNotificationsAsRead(false); // mark as read silently
                    }
                }
            } catch (err) {
                console.error('Failed to retrieve notifications:', err);
            }
        }

        function updateBadge(count) {
            if (count > 0) {
                badge.textContent = count;
                badge.classList.remove('scale-0', 'opacity-0');
                badge.classList.add('scale-100', 'opacity-100');
                // Micro-animation bounce pulse
                badge.classList.add('animate-bounce');
                setTimeout(() => badge.classList.remove('animate-bounce'), 1000);
            } else {
                badge.classList.remove('scale-100', 'opacity-100');
                badge.classList.add('scale-0', 'opacity-0');
            }
        }

        function renderNotifications() {
            if (notifications.length === 0) {
                list.innerHTML = '';
                emptyState.classList.remove('hidden');
                markReadBtn.classList.add('hidden');
                return;
            }

            emptyState.classList.add('hidden');
            list.innerHTML = '';
            
            // Show mark read button if there is any unread in local memory
            const hasUnread = notifications.some(n => parseInt(n.IsRead) === 0);
            if (hasUnread) {
                markReadBtn.classList.remove('hidden');
            } else {
                markReadBtn.classList.add('hidden');
            }

            notifications.forEach(item => {
                const div = document.createElement('div');
                const isUnread = parseInt(item.IsRead) === 0;
                div.className = `p-4 hover:bg-surface-container-low transition-colors duration-150 relative flex gap-3 items-start ${isUnread ? 'bg-primary/5' : ''}`;
                
                // Map icon
                let iconName = 'info';
                let iconColor = 'text-primary';
                const titleLower = item.Title.toLowerCase();
                if (titleLower.includes('booking') || titleLower.includes('paid')) {
                    iconName = 'payments';
                    iconColor = 'text-green-600';
                } else if (titleLower.includes('campaign') || titleLower.includes('offer') || titleLower.includes('pitch')) {
                    iconName = 'campaign';
                    iconColor = 'text-accent';
                }
                
                div.innerHTML = `
                    <span class="material-symbols-outlined ${iconColor} mt-0.5" style="font-size: 20px;">${iconName}</span>
                    <div class="flex-grow flex flex-col gap-0.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-heading font-semibold text-text-main text-[12px] leading-tight">${escapeHTML(item.Title)}</span>
                            <span class="text-[10px] text-muted whitespace-nowrap font-medium">${timeAgo(item.CreatedAt)}</span>
                        </div>
                        <p class="text-[11px] text-secondary leading-normal font-medium">${escapeHTML(item.Message)}</p>
                    </div>
                    ${isUnread ? '<span class="absolute top-4 right-4 w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>' : ''}
                `;
                list.appendChild(div);
            });
        }

        async function markAllNotificationsAsRead(refresh = true) {
            try {
                const res = await fetch('<?php echo $base_url; ?>/api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'MarkNotificationsRead' })
                });
                const data = await res.json();
                if (data.status === 'success' && refresh) {
                    // Instantly update badge and marks in UI
                    updateBadge(0);
                    notifications = notifications.map(n => ({ ...n, IsRead: 1 }));
                    renderNotifications();
                }
            } catch (err) {
                console.error('Failed to mark notifications as read:', err);
            }
        }

        markReadBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            markAllNotificationsAsRead(true);
        });

        function escapeHTML(str) {
            return str.replace(/[&<>'"]/g, 
                tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
            );
        }

        function timeAgo(dateString) {
            const now = new Date();
            const past = new Date(dateString.replace(/-/g, "/")); // Cross-browser date safety
            const diffMs = now - past;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHrs = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHrs / 24);

            if (isNaN(past.getTime())) return 'Recently';
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHrs < 24) return `${diffHrs}h ago`;
            return `${diffDays}d ago`;
        }

        // Initial load
        fetchNotifications();

        // Check for alerts every 30 seconds
        setInterval(() => {
            // Only fetch in background if dropdown is closed
            if (!isOpen) {
                fetchNotifications(false);
            }
        }, 30000);
    });
    </script>
    <?php endif; ?>

    <main class="flex-grow w-full pl-72 pr-8 py-8 h-full min-h-[calc(100vh-64px)]">
    <?php endif; ?>
