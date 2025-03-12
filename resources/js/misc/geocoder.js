/** OpenStreetMap GeoCoder **/
var map;
var marker;

function initialize() {
  var baseLat = document.getElementById('latitude').value;
  var baseLon = document.getElementById('longitude').value;
  // If nulls, set center in london
  if (baseLat === '') {
    baseLat = 51.5286416;
  }
  if (baseLon === '') {
    baseLon = -0.1015987;
  }

  // Initialize Leaflet map
  map = L.map('map-canvas').setView([baseLat, baseLon], 6);
  
  // Add OpenStreetMap tile layer
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);
  
  // Add marker at the initial position
  marker = L.marker([baseLat, baseLon]).addTo(map);
  marker.bindPopup(document.getElementById('location').value || 'Selected location').openPopup();
}

function codeAddress() {
  var address = document.getElementById('location').value;
  
  if (!address) {
    return;
  }
  
  // Use Nominatim for geocoding
  fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1`, {
    headers: {
      'User-Agent': 'Restarters.net/1.0'
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data && data.length > 0) {
      var result = data[0];
      var lat = parseFloat(result.lat);
      var lng = parseFloat(result.lon);
      
      // Update form fields
      document.getElementById('latitude').value = lat;
      document.getElementById('longitude').value = lng;
      
      // Update map
      map.setView([lat, lng], 12);
      marker.setLatLng([lat, lng]);
      marker.bindPopup(address).openPopup();
    } else {
      alert('Geocode was not successful for the following reason: Address not found');
    }
  })
  .catch(error => {
    console.error('Error during geocoding:', error);
    alert('Geocode was not successful. Please try again.');
  });
}

// Initialize map when the page loads
document.addEventListener('DOMContentLoaded', function() {
  if (document.getElementById('map-canvas')) {
    initialize();
    
    // Add event listener for the geocode button
    var geocodeButton = document.getElementById('geocode-button');
    if (geocodeButton) {
      geocodeButton.addEventListener('click', function(e) {
        e.preventDefault();
        codeAddress();
      });
    }
  }
});
