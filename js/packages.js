document.addEventListener("DOMContentLoaded", function () {
  loadPackages();

  var filterInputs = [
    "searchTitle",
    "searchDest",
    "maxPrice",
    "maxDuration",
    "minRating",
    "sortSelect",
    "orderSelect",
  ];

  for (var i = 0; i < filterInputs.length; i++) {
    var el = document.getElementById(filterInputs[i]);
    if (el) {
      el.addEventListener("input", function () {
        clearTimeout(window.filterTimeout);
        window.filterTimeout = setTimeout(loadPackages, 500);
      });
    }
  }
});

function loadPackages() {
  var container = document.getElementById("packagesContainer");

  var payload = {
    type: "GetAllPackages",
    search: {},
  };

  var title = document.getElementById("searchTitle").value.trim();
  var dest = document.getElementById("searchDest").value.trim();
  var price = document.getElementById("maxPrice").value;
  var duration = document.getElementById("maxDuration").value;
  var rating = document.getElementById("minRating").value;
  var sort = document.getElementById("sortSelect").value;
  var order = document.getElementById("orderSelect").value;

  if (title !== "") payload.search.title = title;
  if (dest !== "") payload.search.destination = dest;
  if (price !== "") payload.search.max_price = price;
  if (duration !== "") payload.search.max_duration = duration;
  if (rating !== "") payload.search.min_rating = rating;

  if (sort !== "") {
    payload.sort = sort;
    payload.order = order;
  }

  apiRequest(
    payload,
    function (data) {
      container.innerHTML = "";

      if (!data || data.length === 0) {
        container.innerHTML =
          "<h3>No packages found matching your criteria.</h3>";
        return;
      }

      for (var i = 0; i < data.length; i++) {
        var pkg = data[i];
        var card = document.createElement("div");
        card.className = "card";

        card.innerHTML =
          "<h3>" +
          pkg.Title +
          "</h3>" +
          "<p><strong>Agency:</strong> " +
          pkg.AgencyName +
          " (" +
          pkg.AverageRating +
          "⭐)</p>" +
          "<p><strong>Duration:</strong> " +
          pkg.DurationDays +
          " days | <strong>Price:</strong> $" +
          pkg.BasePrice +
          "</p>" +
          '<div style="margin-top: 15px;">' +
          // Note the .php extension here!
          '<a class="btn btn-primary" href="package_view.php?id=' +
          pkg.PackageID +
          '">View Details</a>' +
          "</div>";

        container.appendChild(card);
      }
    },
    function (error) {
      container.innerHTML =
        "<p style='color:red'>Error loading packages: " + error + "</p>";
    },
  );
}
