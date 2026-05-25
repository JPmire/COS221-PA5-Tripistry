<?php
$page_title = 'Explore Packages';
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
?>

<style>
    /* Premium override for filters section and chips */
    .filter-chip {
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid rgba(228, 190, 188, 0.45);
        color: #485f84;
    }
    .filter-chip:hover {
        background: rgba(183, 16, 42, 0.05) !important;
        border-color: rgba(183, 16, 42, 0.4) !important;
        color: #b7102a !important;
        transform: translateY(-1px);
    }
    .filter-chip.active {
        background: linear-gradient(135deg, #b7102a 0%, #db313f 100%) !important;
        border-color: #b7102a !important;
        color: #FFFFFF !important;
        box-shadow: 0 4px 12px rgba(183, 16, 42, 0.25);
        transform: translateY(-1px);
    }
    .btn-premium-sm {
        height: 36px;
        padding-left: 1rem;
        padding-right: 1rem;
        background: linear-gradient(135deg, #b7102a 0%, #db313f 100%);
        color: #FFFFFF;
        border-radius: 0.5rem; /* rounded-lg */
        font-weight: 600;
        font-size: 12px;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px 0 rgba(183, 16, 42, 0.2);
    }
    .btn-premium-sm:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 14px 0 rgba(183, 16, 42, 0.3);
    }
    .btn-premium-secondary-sm {
        height: 36px;
        padding-left: 1rem;
        padding-right: 1rem;
        background-color: transparent;
        color: #1D3557;
        border: 1px solid rgba(228, 190, 188, 0.7);
        border-radius: 0.5rem; /* rounded-lg */
        font-weight: 600;
        font-size: 12px;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-premium-secondary-sm:hover {
        background-color: rgba(183, 16, 42, 0.04);
        border-color: #b7102a;
        color: #b7102a;
        transform: translateY(-1px);
    }
    /* Input premium overrides */
    .input-field-premium {
        background: rgba(255, 255, 255, 0.7) !important;
        border: 1px solid rgba(228, 190, 188, 0.6) !important;
        transition: all 0.2s ease-in-out;
    }
    .input-field-premium:focus {
        background: #ffffff !important;
        border-color: #b7102a !important;
        box-shadow: 0 0 0 4px rgba(183, 16, 42, 0.1) !important;
    }
</style>

<div class="flex flex-col gap-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
        <div>
            <h1 class="text-3xl font-heading font-semibold text-text-main flex items-center gap-2">
                <span class="material-symbols-outlined text-[32px] text-primary">travel_explore</span>
                Explore Destinations
            </h1>
            <p class="text-muted mt-1">Find and book your next dream vacation effortlessly.</p>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="glass-card p-5 lg:p-6 mb-2 flex flex-col gap-4">
        <form id="filter-form" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" onsubmit="return false;">
            <!-- Unified Search Input -->
            <div class="md:col-span-2 lg:col-span-4">
                <label class="block text-xs font-bold text-secondary uppercase tracking-wider mb-1.5 flex items-center gap-1 select-none">
                    <span class="material-symbols-outlined text-[16px] text-primary">search</span>
                    Frictionless Global Search
                </label>
                <div class="relative">
                    <input type="text" id="global-query" class="input-field input-field-premium h-[48px] pl-11 pr-4 text-base font-medium rounded-xl border border-outline-variant shadow-sm w-full" placeholder="Type destination, country, region, package name, or agency name...">
                    <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-secondary opacity-60">travel_explore</span>
                </div>
            </div>

            <!-- Quick Filter Chips -->
            <div class="md:col-span-2 lg:col-span-4 flex flex-wrap gap-2.5 items-center py-1 select-none border-b border-outline-variant/30 pb-3">
                <span class="text-xs font-bold text-secondary uppercase tracking-wider mr-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px] text-primary">local_offer</span>
                    Quick Toggles:
                </span>
                
                <button type="button" class="filter-chip px-3.5 py-1.5 rounded-full cursor-pointer flex items-center gap-1 text-xs font-semibold" data-type="price" data-value="500">
                    <span class="material-symbols-outlined text-[15px]">payments</span>
                    Budget Friendly (< R 500)
                </button>
                
                <button type="button" class="filter-chip px-3.5 py-1.5 rounded-full cursor-pointer flex items-center gap-1 text-xs font-semibold" data-type="price" data-value="1000">
                    <span class="material-symbols-outlined text-[15px]">savings</span>
                    Mid-Range (< R 1,000)
                </button>
                
                <button type="button" class="filter-chip px-3.5 py-1.5 rounded-full cursor-pointer flex items-center gap-1 text-xs font-semibold" data-type="duration" data-value="3">
                    <span class="material-symbols-outlined text-[15px]">weekend</span>
                    Weekend Escapes (1-3 Days)
                </button>
                
                <button type="button" class="filter-chip px-3.5 py-1.5 rounded-full cursor-pointer flex items-center gap-1 text-xs font-semibold" data-type="duration" data-value="7">
                    <span class="material-symbols-outlined text-[15px]">calendar_view_week</span>
                    Moderate Trips (≤ 7 Days)
                </button>
                
                <button type="button" class="filter-chip px-3.5 py-1.5 rounded-full cursor-pointer flex items-center gap-1 text-xs font-semibold" data-type="sort" data-value="AverageRating_DESC">
                    <span class="material-symbols-outlined text-[15px]">star</span>
                    Highest Rated (★)
                </button>
            </div>

            <!-- Advanced Filter Trigger -->
            <div class="md:col-span-2 lg:col-span-4 flex justify-between items-center">
                <button type="button" id="toggle-advanced" class="btn-premium-secondary-sm select-none cursor-pointer flex items-center gap-1.5 shadow-sm">
                    <span class="material-symbols-outlined text-[18px] transition-transform" id="toggle-icon">expand_more</span>
                    <span id="toggle-text">Show Detailed Advanced Filters</span>
                </button>
                <button type="button" id="reset-filters" class="btn-premium-secondary-sm cursor-pointer flex items-center gap-1.5 shadow-sm">
                    <span class="material-symbols-outlined text-xs">restart_alt</span>
                    Reset All Filters
                </button>
            </div>

            <!-- Collapsible Advanced Drawer -->
            <div id="advanced-filters" class="md:col-span-2 lg:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-4 border-t border-outline-variant/30 pt-3 hidden transition-all duration-300">
                <div>
                    <label class="block text-xs font-bold text-secondary uppercase tracking-wider mb-1">Destination Filter</label>
                    <input type="text" id="search-dest" class="input-field input-field-premium h-[40px] text-sm rounded-lg" placeholder="Country, City or Landmark">
                </div>
                <div>
                    <label class="block text-xs font-bold text-secondary uppercase tracking-wider mb-1">Specific Max Price (R)</label>
                    <input type="number" id="max-price" class="input-field input-field-premium h-[40px] text-sm rounded-lg" placeholder="e.g. 750">
                </div>
                <div>
                    <label class="block text-xs font-bold text-secondary uppercase tracking-wider mb-1">Specific Max Duration (Days)</label>
                    <input type="number" id="max-duration" class="input-field input-field-premium h-[40px] text-sm rounded-lg" placeholder="e.g. 5">
                </div>
            </div>

            <!-- Sorting & Total count summary -->
            <div class="md:col-span-2 lg:col-span-4 flex flex-col sm:flex-row justify-between items-start sm:items-center pt-3 border-t border-outline-variant/30 gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-secondary uppercase tracking-wider">Order Results By:</span>
                    <select id="sort-by" class="input-field input-field-premium h-[38px] w-auto py-0 pr-8 text-xs font-bold rounded-lg">
                        <option value="">Default Recommended</option>
                        <option value="BasePrice_ASC">Price: Low to High</option>
                        <option value="BasePrice_DESC">Price: High to Low</option>
                        <option value="DurationDays_ASC">Duration: Short to Long</option>
                        <option value="AverageRating_DESC">Highest Rated</option>
                    </select>
                </div>
                <span class="text-xs font-mono font-bold text-muted" id="results-count">Scanning packages...</span>
            </div>
        </form>
    </div>

    <!-- Package Grid -->
    <div id="packages-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Javascript renders card objects dynamically -->
    </div>
</div>

<!-- Comparison Floating Bar -->
<div id="compare-bar" class="fixed bottom-6 left-1/2 md:left-[calc(50%+128px)] -translate-x-1/2 bg-slate-900/80 backdrop-blur-xl text-white px-6 py-4 rounded-2xl shadow-2xl border border-white/15 flex items-center justify-between gap-6 z-45 transition-all duration-300 transform translate-y-24 opacity-0 pointer-events-none max-w-lg w-[90%] md:w-auto">
    <div class="flex items-center gap-3">
        <span class="material-symbols-outlined text-primary-fixed-dim text-2xl">compare_arrows</span>
        <div>
            <p class="text-sm font-semibold text-white" id="compare-count">0 Packages Selected</p>
            <p class="text-xs text-white/70">Select up to 3 packages to compare</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <button id="compare-clear" class="text-xs text-white/60 hover:text-white transition-colors py-1 px-2">Clear</button>
        <a id="compare-btn" href="#" class="btn-premium-sm">Compare Now</a>
    </div>
</div>

<script>
    let loadTimeout;
    let selectedCompare = [];

    // Collapsible drawer toggle
    const toggleBtn = document.getElementById('toggle-advanced');
    const advancedDrawer = document.getElementById('advanced-filters');
    const toggleIcon = document.getElementById('toggle-icon');
    const toggleText = document.getElementById('toggle-text');

    toggleBtn.addEventListener('click', () => {
        const isHidden = advancedDrawer.classList.contains('hidden');
        if (isHidden) {
            advancedDrawer.classList.remove('hidden');
            toggleIcon.innerText = 'expand_less';
            toggleText.innerText = 'Hide Detailed Advanced Filters';
        } else {
            advancedDrawer.classList.add('hidden');
            toggleIcon.innerText = 'expand_more';
            toggleText.innerText = 'Show Detailed Advanced Filters';
        }
    });

    function fetchPackages() {
        if (loadTimeout) clearTimeout(loadTimeout);

        loadTimeout = setTimeout(async () => {
            const globalQuery = document.getElementById('global-query').value.trim();
            const dest = document.getElementById('search-dest').value.trim();
            const maxPrice = document.getElementById('max-price').value;
            const maxDuration = document.getElementById('max-duration').value;
            
            const sortVal = document.getElementById('sort-by').value;
            let sort = '';
            let order = '';
            if (sortVal) {
                const parts = sortVal.split('_');
                sort = parts[0];
                order = parts[1];
            }

            const payload = {
                type: 'GetAllPackages',
                search: {
                    global_query: globalQuery,
                    destination: dest,
                    max_price: maxPrice,
                    max_duration: maxDuration
                },
                sort: sort,
                order: order
            };

            const container = document.getElementById('packages-container');
            container.innerHTML = `
                <div class="col-span-full py-12 flex flex-col items-center justify-center gap-3 text-muted">
                    <div class="w-8 h-8 rounded-full border-2 border-primary border-t-transparent animate-spin"></div>
                    <p class="text-xs font-bold tracking-wider uppercase">Loading matching adventures...</p>
                </div>`;

            try {
                const res = await fetch('api.php', {
                    method: 'POST',
                    headers:{ 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const response = await res.json();
                
                if (response.status === 'success') {
                    renderPackages(response.data);
                    document.getElementById('results-count').innerText = `${response.data.length} package(s) found`;
                } else {
                    container.innerHTML = `<p class="col-span-full text-center text-primary font-bold p-8">Error loading: ${response.message}</p>`;
                }
            } catch (e) {
                container.innerHTML = `<p class="col-span-full text-center text-primary font-bold p-8">Connection to XAMPP database lost. Ensure server is active.</p>`;
            }
        }, 200); // 200ms quick debounce
    }

    function renderPackages(packages) {
        const container = document.getElementById('packages-container');
        if (packages.length === 0) {
            container.innerHTML = `
                <div class="col-span-full py-16 bg-white/80 backdrop-blur-md border border-outline-variant/60 rounded-2xl text-center flex flex-col items-center justify-center gap-3 text-muted glass-card">
                    <span class="material-symbols-outlined text-4xl opacity-40">travel_explore</span>
                    <p class="text-sm font-semibold">No travel packages matched your query.</p>
                    <p class="text-xs">Try searching a different destination, lowering filters or clearing quick chips.</p>
                </div>`;
            return;
        }

        let html = '';
        packages.forEach(pkg => {
            const isChecked = selectedCompare.includes(pkg.PackageID.toString()) ? 'checked' : '';
            const rating = parseFloat(pkg.AverageRating || 0);
            
            html += `
            <div class="glass-card card-hover overflow-hidden flex flex-col relative group">
                <div class="h-48 relative overflow-hidden bg-surface-container">
                    <!-- Premium Full-Bleed Cover Image -->
                    <img src="${pkg.ImageURL}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out" alt="${pkg.Title}">
                    <!-- Elegant Linear Vignette Overlay -->
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/60 via-slate-950/10 to-transparent"></div>
                    
                    <!-- Compare Checkbox Overlay -->
                    <label class="absolute top-3 right-3 bg-white/80 backdrop-blur border border-outline-variant/35 rounded-xl px-2.5 py-1.5 flex items-center gap-1.5 cursor-pointer shadow-sm hover:bg-white/95 transition-all select-none z-10">
                        <input type="checkbox" class="compare-checkbox w-4 h-4 rounded text-primary border-outline-variant focus:ring-primary focus:ring-opacity-25 cursor-pointer" data-id="${pkg.PackageID}" ${isChecked}>
                        <span class="text-xs font-extrabold text-text-main">Compare</span>
                    </label>
                </div>
                
                <div class="p-6 flex flex-col flex-grow">
                    <div class="mb-2 flex justify-between items-center">
                        <span class="text-[10px] font-extrabold tracking-wider text-primary uppercase bg-primary/5 px-2.5 py-0.5 rounded-full border border-primary/10">${pkg.AgencyName}</span>
                    </div>
                    
                    <h3 class="text-lg font-heading font-semibold text-text-main mb-2 line-clamp-1 group-hover:text-primary transition-colors">${pkg.Title}</h3>
                    
                    <div class="flex gap-4 mt-auto mb-4 text-xs font-bold text-secondary">
                        <div class="flex items-center gap-0.5"><span class="material-symbols-outlined text-[15px]">schedule</span> ${pkg.DurationDays} Days</div>
                        <div class="flex items-center gap-0.5">
                            <span class="material-symbols-outlined text-[15px] fill-1 text-amber-500">star</span> 
                            ${rating > 0 ? rating.toFixed(1) : 'No reviews'}
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center pt-4 border-t border-outline-variant/30 mt-2">
                        <div>
                            <p class="text-[10px] font-bold text-muted uppercase tracking-wider">From Price</p>
                            <p class="text-lg font-bold text-primary font-mono">R ${parseFloat(pkg.BasePrice).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                        </div>
                        <a href="package_detail.php?id=${pkg.PackageID}" class="btn-premium-secondary-sm">
                            View Details
                            <span class="material-symbols-outlined text-xs">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </div>`;
        });
        container.innerHTML = html;
    }

    // Delegate comparison checkbox events
    document.getElementById('packages-container').addEventListener('change', (e) => {
        if (e.target.classList.contains('compare-checkbox')) {
            const id = e.target.getAttribute('data-id');
            if (e.target.checked) {
                if (selectedCompare.length >= 3) {
                    e.target.checked = false;
                    alert('You can select a maximum of 3 packages to compare.');
                    return;
                }
                selectedCompare.push(id);
            } else {
                selectedCompare = selectedCompare.filter(x => x !== id);
            }
            updateCompareBar();
        }
    });

    function updateCompareBar() {
        const bar = document.getElementById('compare-bar');
        const countText = document.getElementById('compare-count');
        const compareBtn = document.getElementById('compare-btn');

        if (selectedCompare.length > 0) {
            countText.innerText = `${selectedCompare.length} Package${selectedCompare.length > 1 ? 's' : ''} Selected`;
            const params = selectedCompare.map(id => `ids[]=${id}`).join('&');
            compareBtn.href = `compare.php?${params}`;
            bar.classList.remove('translate-y-24', 'opacity-0', 'pointer-events-none');
        } else {
            bar.classList.add('translate-y-24', 'opacity-0', 'pointer-events-none');
        }
    }

    document.getElementById('compare-clear').addEventListener('click', () => {
        selectedCompare = [];
        document.querySelectorAll('.compare-checkbox').forEach(cb => cb.checked = false);
        updateCompareBar();
    });

    // Unified Global Search and collateral form bindings
    document.getElementById('global-query').addEventListener('input', fetchPackages);
    document.getElementById('search-dest').addEventListener('input', fetchPackages);
    document.getElementById('max-price').addEventListener('input', fetchPackages);
    document.getElementById('max-duration').addEventListener('input', fetchPackages);
    document.getElementById('sort-by').addEventListener('change', fetchPackages);

    // Filter Chips Active state toggles
    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            const type = this.getAttribute('data-type');
            const val = this.getAttribute('data-value');
            
            // Check active state
            const isActive = this.classList.contains('active');
            
            // Deactivate siblings of SAME type
            document.querySelectorAll(`.filter-chip[data-type="${type}"]`).forEach(c => {
                c.classList.remove('active');
            });
            
            if (isActive) {
                // Clear the associated input field
                if (type === 'price') {
                    document.getElementById('max-price').value = '';
                } else if (type === 'duration') {
                    document.getElementById('max-duration').value = '';
                } else if (type === 'sort') {
                    document.getElementById('sort-by').value = '';
                }
            } else {
                // Activate this chip visually
                this.classList.add('active');
                
                // Write value to input field
                if (type === 'price') {
                    document.getElementById('max-price').value = val;
                } else if (type === 'duration') {
                    document.getElementById('max-duration').value = val;
                } else if (type === 'sort') {
                    document.getElementById('sort-by').value = val;
                }
            }
            
            fetchPackages();
        });
    });

    // Reset Forms
    document.getElementById('reset-filters').addEventListener('click', () => {
        document.getElementById('filter-form').reset();
        
        // Reset chips visually
        document.querySelectorAll('.filter-chip').forEach(c => {
            c.classList.remove('active');
        });
        
        selectedCompare = [];
        updateCompareBar();
        fetchPackages();
    });

    // Initial Load immediately on entry
    fetchPackages();
</script>

<?php require_once 'includes/footer.php'; ?>