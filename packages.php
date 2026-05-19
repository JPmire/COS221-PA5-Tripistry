<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <title>Browse Packages - Tripistry</title>
    <link rel="stylesheet" href="css/packages.css" />
  <body>
    <header><h1>Tripistry</h1></header>
    
    <nav>
      <ul class="navbar">
        <li><a class="active" href="packages.php">Browse Packages</a></li>
        </ul>
    </nav>

    <main>
      <div class="filters">
        <input type="text" id="searchTitle" placeholder="Search package titles..." />
        <input type="text" id="searchDest" placeholder="Search destinations (e.g. Paris)..." />

        <input type="number" id="maxPrice" placeholder="Max Price ($)" />
        <input type="number" id="maxDuration" placeholder="Max Days" />

        <select id="minRating">
          <option value="">Any Agency Rating</option>
          <option value="3">3+ Stars</option>
          <option value="4">4+ Stars</option>
          <option value="4.5">4.5+ Stars</option>
        </select>

        <select id="sortSelect">
          <option value="">Sort By...</option>
          <option value="BasePrice">Price</option>
          <option value="DurationDays">Duration</option>
          <option value="AverageRating">Agency Rating</option>
        </select>

        <select id="orderSelect">
          <option value="ASC">Ascending (Low-High)</option>
          <option value="DESC">Descending (High-Low)</option>
        </select>
      </div>

    <div class="packages-grid" id="packagesContainer"></div>    </main>

    <script src="js/api.js"></script>
    <script src="js/packages.js"></script>
  </body>
</html>