// Global variable to track initialization state
let zoteroMapInitialized = false;

function resolveGeoName(name) {
  return ZoteroMapData.nameAliases && ZoteroMapData.nameAliases[name]
    ? ZoteroMapData.nameAliases[name]
    : name;
}

function initZoteroMap() {
  // Prevent double initialization
  if (zoteroMapInitialized) {
    console.log("Zotero Map: Map already initialized, skipping");
    return;
  }
  
  try {
    console.log("Zotero Map: Initializing");
    var mapEl = document.getElementById("zotero-map");
    if (!mapEl) {
      console.error("Zotero Map: Map element not found");
      return;
    }

    // Check if we have the required data
    if (!ZoteroMapData) {
      console.error("Zotero Map: ZoteroMapData is not defined");
      const loadingEl = document.getElementById("zotero-map-loading");
      if (loadingEl) {
        loadingEl.innerHTML = "Error: Map data not loaded";
      }
      return;
    }

    console.log("Zotero Map: Map data found", ZoteroMapData);
    
    // Set background color
    mapEl.style.backgroundColor = ZoteroMapData.waterColor || "#ffffff";

    // Check if we're on a mobile device
    var isMobile = window.innerWidth < 768;

    // Initialize the map with different settings based on device type
    var map = L.map("zotero-map", { 
      attributionControl: false,
      // Set minimum zoom levels
      minZoom: 1,  // The absolute minimum Leaflet allows
      maxBoundsViscosity: 1.0,
      // Mobile-friendly options
      tap: true,
      tapTolerance: 15,
      touchZoom: true,
      bounceAtZoomLimits: false,
      dragging: true,
      inertia: true
    });

    // For mobile, fit the entire world bounds instead of using setView
    if (isMobile) {
      // World bounds in latitude/longitude
      var worldBounds = [
        [-90, -180],  // Southwest corner
        [90, 180]     // Northeast corner
      ];
      map.fitBounds(worldBounds);
    } else {
      // For desktop, use the standard view
      map.setView([30, 0], 2);
    }

    // Set max bounds to prevent dragging too far
    var bounds = [
      [-90, -180],  // Southwest
      [90, 180]     // Northeast
    ];
    map.setMaxBounds(bounds);

    // Mark as initialized immediately after creating the map
    zoteroMapInitialized = true;

    // Set max bounds to prevent dragging too far
    var bounds = [
      [-90, -180],  // Southwest
      [90, 180]     // Northeast
    ];
    map.setMaxBounds(bounds);

    // Ensure Leaflet map resizes correctly
    setTimeout(() => {
      console.log("Zotero Map: Forcing map resize");
      map.invalidateSize(true);
    }, 500);
    
    window.addEventListener("resize", () => {
      map.invalidateSize(true);
    });

    // Add loading indicator for GeoJSON
    console.log("Zotero Map: Fetching GeoJSON from", ZoteroMapData.geojsonUrl);
    
    fetch(ZoteroMapData.geojsonUrl)
      .then(response => {
        if (!response.ok) {
          throw new Error(`Network response was not ok: ${response.status}`);
        }
        return response.json();
      })
      .then(geojson => {
        console.log("Zotero Map: GeoJSON loaded successfully");
        
        L.geoJSON(geojson, {
          style: function (feature) {
            const geoName = feature.properties.ADMIN || feature.properties.name;
            const country = resolveGeoName(geoName);
            const isTagged = ZoteroMapData.countryUrls[country];

            return {
              fillColor: isTagged ? ZoteroMapData.mapColors.highlight : ZoteroMapData.mapColors.default,
              color: ZoteroMapData.mapColors.border,
              weight: 1,
              fillOpacity: 1,
              opacity: 1
            };
          },
          onEachFeature: function (feature, layer) {
            const geoName = feature.properties.ADMIN || feature.properties.name;
            const country = resolveGeoName(geoName);
            const info = ZoteroMapData.countryUrls[country];

            if (info) {
              const url = typeof info === "string" ? info : info.url;
              const count = typeof info === "object" && info.count ? info.count : "?";
              layer.on("click", () => window.open(url, "_blank"));
              layer.bindTooltip(`${country} — ${count} tagged citations`, { sticky: true });
            }
          }
        }).addTo(map);

        const loadingEl = document.getElementById("zotero-map-loading");
        if (loadingEl) {
          loadingEl.remove();
        }
        
        // Force another resize after content is loaded
        setTimeout(() => map.invalidateSize(true), 100);
      })
      .catch(err => {
        console.error("Zotero Map: Failed to load GeoJSON:", err);
        const loadingEl = document.getElementById("zotero-map-loading");
        if (loadingEl) {
          loadingEl.innerHTML = "Failed to load map data. Please try again later.";
        } else {
          // Create a new error message if loading element is gone
          const mapContainer = document.getElementById("zotero-map");
          if (mapContainer) {
            mapContainer.insertAdjacentHTML('beforebegin', 
              '<div style="text-align: center; padding: 20px; color: red;">Failed to load map data. Please try again later.</div>');
          }
        }
      });
  } catch (err) {
    console.error("Zotero Map: Critical initialization error:", err);
    const loadingEl = document.getElementById("zotero-map-loading");
    if (loadingEl) {
      loadingEl.innerHTML = "Error loading map component";
    } else {
      // Create a new error message if loading element is gone
      const mapContainer = document.getElementById("zotero-map");
      if (mapContainer) {
        mapContainer.insertAdjacentHTML('beforebegin', 
          '<div style="text-align: center; padding: 20px; color: red;">Error loading map component.</div>');
      }
    }
  }
}

// Use a global function to trigger initialization only once
window.initZoteroMapOnce = function() {
  if (!zoteroMapInitialized && typeof L !== 'undefined') {
    initZoteroMap();
    return true;
  }
  return false;
};

// Try initializing on DOMContentLoaded
document.addEventListener("DOMContentLoaded", function() {
  console.log("Zotero Map: DOM content loaded");
  
  // First check if Leaflet is already available
  if (typeof L !== 'undefined') {
    console.log("Zotero Map: Leaflet already available on DOMContentLoaded");
    window.initZoteroMapOnce();
  } else {
    console.log("Zotero Map: Leaflet not yet available on DOMContentLoaded, will try again later");
  }
});

// Fallback - also try on window load if not initialized yet
window.addEventListener("load", function() {
  console.log("Zotero Map: Window fully loaded");
  
  if (!zoteroMapInitialized) {
    if (typeof L !== 'undefined') {
      console.log("Zotero Map: Leaflet available on window load");
      window.initZoteroMapOnce();
    } else {
      console.log("Zotero Map: Leaflet still not available on window load");
      
      // Check if we should try loading Leaflet directly
      const loadingEl = document.getElementById("zotero-map-loading");
      if (loadingEl && !zoteroMapInitialized) {
        console.log("Zotero Map: Attempting to load Leaflet directly");
        loadingEl.innerHTML = "Loading map resources...";
        
        // Load Leaflet CSS if needed
        if (!document.querySelector('link[href*="leaflet.css"]')) {
          const leafletCss = document.createElement('link');
          leafletCss.rel = "stylesheet";
          leafletCss.href = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
          document.head.appendChild(leafletCss);
        }
        
        // Load Leaflet JS
        const leafletScript = document.createElement('script');
        leafletScript.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        leafletScript.onload = function() {
          console.log("Zotero Map: Leaflet loaded via fallback");
          window.initZoteroMapOnce();
        };
        leafletScript.onerror = function() {
          console.error("Zotero Map: Failed to load Leaflet library");
          if (loadingEl) {
            loadingEl.innerHTML = "Error: Could not load map library. Please try refreshing the page.";
          }
        };
        document.head.appendChild(leafletScript);
      }
    }
  }
});