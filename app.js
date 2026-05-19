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
