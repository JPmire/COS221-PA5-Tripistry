document.addEventListener("DOMContentLoaded", function () {
  var params = new URLSearchParams(window.location.search);
  var packageId = params.get("id");

  if (!packageId) {
    document.getElementById("viewContainer").innerHTML =
      "<h3>No Package Selected</h3>";
    return;
  }

  apiRequest(
    { type: "GetPackageDetails", package_id: packageId },
    function (pkg) {
      var container = document.getElementById("viewContainer");

      // Process Destinations array
      var destString = "TBD";
      if (pkg.destinations && pkg.destinations.length > 0) {
        destString = pkg.destinations
          .map(function (d) {
            return d.Name + ", " + d.Country;
          })
          .join(" ➔ ");
      }

      // Process Accommodations array
      var accommHtml = "<ul>";
      if (pkg.accommodations && pkg.accommodations.length > 0) {
        for (var i = 0; i < pkg.accommodations.length; i++) {
          var a = pkg.accommodations[i];
          accommHtml +=
            "<li>" + a.Name + " (" + a.Type + ", " + a.StarRating + "⭐)</li>";
        }
      } else {
        accommHtml += "<li>Accommodation details pending.</li>";
      }
      accommHtml += "</ul>";


      var reviewsHtml = "";
      if (!pkg.reviews || pkg.reviews.length === 0) {
        reviewsHtml = "<p>No reviews yet.</p>";
      } else {
        for (var j = 0; j < pkg.reviews.length; j++) {
          var r = pkg.reviews[j];
          reviewsHtml +=
            "<div class='review-item'>" +
            "<div class='review-meta'>" +
            "<span><strong>" +
            r.FirstName +
            "</strong> <span class='star-rating'>(" +
            r.Rating +
            "⭐)</span></span>" +
            "<span>" +
            r.DatePosted +
            "</span>" +
            "</div>" +
            '<p>"' +
            r.Comment +
            '"</p></div>';
        }
      }

      container.innerHTML =
        "<div class='view-header'>" +
        "<h2>" +
        pkg.Title +
        "</h2>" +
        "<div class='price-duration'><strong>$" +
        pkg.BasePrice +
        "</strong><span>" +
        pkg.DurationDays +
        " Days</span></div>" +
        "</div>" +
        "<div class='info-section'>" +
        "<h3>Agency Information</h3>" +
        "<p><strong>Provider:</strong> " +
        pkg.AgencyName +
        " (" +
        pkg.AverageRating +
        "⭐)</p>" +
        "<p><strong>Base City:</strong> " +
        pkg.Address_City +
        "</p>" +
        "</div>" +
        "<div class='info-section'>" +
        "<h3>Itinerary</h3>" +
        "<p><strong>Route:</strong> " +
        destString +
        "</p>" +
        "<p>" +
        pkg.Description +
        "</p>" +
        "<h4>Accommodations Provided:</h4>" +
        accommHtml +
        "</div>" +
        "<div class='info-section'>" +
        "<h3>Traveller Reviews</h3>" +
        reviewsHtml +
        "</div>";
    },
    function (err) {
      document.getElementById("viewContainer").innerHTML =
        "<p style='color:red'>Error: " + err + "</p>";
    },
  );
});
