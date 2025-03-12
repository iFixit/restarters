var map;
var infowindows = Array();
var markers = Array();
function initHomeMap() {
    // Only initialize if the map element exists
    if (!document.getElementById('groupWorldMap')) {
        return;
    }
    
    // Initialize Leaflet map with a center point and zoom level
    map = L.map('groupWorldMap').setView([20, 0], 2);
    
    // Add the tile layer (OpenStreetMap)
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager_labels_under/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, &copy; <a href="https://carto.com/attribution">CARTO</a>'
    }).addTo(map);
    
    /** get & set markers **/
    $.getJSON('/ajax/group_locations', {}, function(data){
        data.forEach(function(g){
            // Create popup content
            var popupContent = '<h4>' + g.name + '</h4><p>' + g.location + ', ' + g.area + '</p>';
            
            // Create marker
            markers[g.id] = L.marker([g.latitude, g.longitude], {
                title: g.name
            }).addTo(map);
            
            // Bind popup to marker
            markers[g.id].bindPopup(popupContent);
            
            // Store popup in infowindows array for potential later use
            infowindows[g.id] = popupContent;
        });
    });
}

// Initialize map when DOM is loaded
document.addEventListener('DOMContentLoaded', initHomeMap);