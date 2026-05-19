
var API_URL = "./api.php";

document.addEventListener("DOMContentLoaded", function () {
  var loaderHTML =
    '<div id="global-loader" style="display: none;"><div class="spinner"></div><h2>Fetching Data...</h2></div>';
  document.body.insertAdjacentHTML("beforeend", loaderHTML);
});

function showLoader() {
  var loader = document.getElementById("global-loader");
  if (loader) loader.style.display = "flex";
}

function hideLoader() {
  var loader = document.getElementById("global-loader");
  if (loader) loader.style.display = "none";
}

function apiRequest(bodyData, onSuccess, onError) {
  showLoader();
  var xhr = new XMLHttpRequest();
  xhr.open("POST", API_URL, true);
  xhr.setRequestHeader("Content-Type", "application/json");

  xhr.onload = function () {
    hideLoader();
    if (xhr.status >= 200 && xhr.status < 300) {
      try {
        var response = JSON.parse(xhr.responseText);
        if (response.status === "success") {
          onSuccess(response.data);
        } else {
          onError(response.message || "API returned an error.");
        }
      } catch (e) {
        onError("Failed to parse server response.");
      }
    } else {
      onError("Network error. Status: " + xhr.status);
    }
  };

  xhr.onerror = function () {
    hideLoader();
    onError("Request failed entirely. Check local server connection.");
  };

  xhr.send(JSON.stringify(bodyData));
}
