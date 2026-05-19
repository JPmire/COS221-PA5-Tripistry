const API_URL = 'api.php';
const TRAVELLER_ID = 1; // Hardcoded for testing. Later, get this from login session.

function loadPackages() {
    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'GetAllPackages', search: {} }) 
    })
    .then(res => res.json())
    .then(data => {
        const grid = document.getElementById('package-grid');
        if(data.status === 'success') {
            data.data.forEach(pkg => {
                grid.innerHTML += `
                    <div class="card">
                        <h3>${pkg.Title}</h3>
                        <p>Agency: ${pkg.AgencyName} (Rating: ${pkg.AverageRating})</p>
                        <p>Price: R${pkg.BasePrice} | ${pkg.DurationDays} Days</p>
                    </div>
                `;
            });
        }
    });
}

if(document.getElementById('bookForm')) {
    document.getElementById('bookForm').addEventListener('submit', (e) => {
        e.preventDefault();
        fetch(API_URL, {
            method: 'POST',
            body: JSON.stringify({
                type: 'BookPackage',
                traveller_id: TRAVELLER_ID,
                package_id: document.getElementById('packageId').value,
                trip_date_id: document.getElementById('tripDateId').value,
                party_size: document.getElementById('partySize').value
            })
        }).then(res => res.json()).then(data => alert(data.message || data.data.message));
    });
}

if(document.getElementById('reviewForm')) {
    document.getElementById('reviewForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const type = document.getElementById('reviewType').value;
        const targetId = document.getElementById('targetId').value;
        
        const payload = {
            type: 'SubmitReview',
            traveller_id: TRAVELLER_ID,
            rating: document.getElementById('rating').value,
            comment: document.getElementById('comment').value,
            agency_id: type === 'agency' ? targetId : null,
            package_id: type === 'package' ? targetId : null
        };

        fetch(API_URL, { method: 'POST', body: JSON.stringify(payload) })
        .then(res => res.json())
        .then(data => alert(data.message || data.data));
    });
}

// Convert number to stars (e.g., 4 = ★★★★☆)
function getStarRating(rating) {
    const num = Math.round(Number(rating) || 0);
    return '★'.repeat(num) + '☆'.repeat(5 - num);
}

// Format a date nicely (e.g., 2026-10-15 -> 15 Oct 2026)
function formatDate(dateString) {
    const options = { day: 'numeric', month: 'short', year: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-GB', options);
}

//compare.html
if (document.getElementById('package-grid')) {
    apiRequest({ type: 'GetAllPackages', search: {} }, 
        function(data) {
            const grid = document.getElementById('package-grid');
            grid.innerHTML = ''; 
            
            data.forEach(pkg => {
                const stars = getStarRating(pkg.AverageRating);
                grid.innerHTML += `
                    <div class="card">
                        <h3>${pkg.Title}</h3>
                        <p><strong>Agency:</strong> ${pkg.AgencyName}</p>
                        <p class="stars" title="Rating: ${pkg.AverageRating}">${stars}</p>
                        <p><strong>Duration:</strong> ${pkg.DurationDays} Days</p>
                        <p class="price-tag">R ${pkg.BasePrice}</p>
                        <a href="book.html"><button style="width:100%; margin-top:10px;">Book This</button></a>
                    </div>
                `;
            });
        }, 
        function(err) { alert("Could not load packages: " + err); }
    );
}

//book.html
if (document.getElementById('bookForm')) {
    const packageSelect = document.getElementById('packageSelect');
    const tripDateSelect = document.getElementById('tripDateSelect');

    // 1. On page load, fetch all packages to fill the first dropdown
    apiRequest({ type: 'GetAllPackages', search: {} }, 
        function(packages) {
            packageSelect.innerHTML = '<option value="">-- Select a Travel Package --</option>';
            packages.forEach(pkg => {
                packageSelect.innerHTML += `<option value="${pkg.PackageID}">${pkg.Title} (R ${pkg.BasePrice})</option>`;
            });
        },
        function(err) { packageSelect.innerHTML = '<option value="">Error loading packages</option>'; }
    );

    // 2. When user selects a package, fetch its specific dates!
    packageSelect.addEventListener('change', function() {
        const pkgId = this.value;
        tripDateSelect.innerHTML = '<option value="">-- Loading Dates... --</option>';
        tripDateSelect.disabled = true;

        if (!pkgId) {
            tripDateSelect.innerHTML = '<option value="">-- Please select a package first --</option>';
            return;
        }

        apiRequest({ type: 'GetPackageDates', package_id: pkgId },
            function(dates) {
                if (dates.length === 0) {
                    tripDateSelect.innerHTML = '<option value="">No upcoming trips scheduled</option>';
                } else {
                    tripDateSelect.innerHTML = '<option value="">-- Select a Date --</option>';
                    dates.forEach(date => {
                        const start = formatDate(date.StartDate);
                        const end = formatDate(date.EndDate);
                        tripDateSelect.innerHTML += `<option value="${date.TripDateID}">${start} to ${end}</option>`;
                    });
                    tripDateSelect.disabled = false; // Enable the dropdown
                }
            },
            function(err) { alert("Could not load dates"); }
        );
    });

    // 3. Submit Booking
    document.getElementById('bookForm').addEventListener('submit', function(e) {
        e.preventDefault();
        apiRequest({
            type: 'BookPackage',
            traveller_id: TRAVELLER_ID,
            package_id: packageSelect.value,
            trip_date_id: tripDateSelect.value,
            party_size: document.getElementById('partySize').value
        },
        function(res) { alert(res.message); window.location.reload(); },
        function(err) { alert("Booking failed: " + err); });
    });
}
