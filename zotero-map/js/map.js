
document.addEventListener("DOMContentLoaded", function () {
  const mapEl = document.getElementById("zotero-map");
  if (mapEl && ZoteroMapData.waterColor) {
    mapEl.style.setProperty("background-color", ZoteroMapData.waterColor, "important");
  }

  const map = L.map("zotero-map", {
    attributionControl: false,
    zoomControl: true
  }).setView([30, 0], 2);

  const geoLayer = L.geoJSON(worldGeoJson, {
    style: function (feature) {
      const country = resolveGeoName(feature.properties.name);
      return {
        fillColor: ZoteroMapData.countryUrls[country] ? ZoteroMapData.mapColors.highlight : ZoteroMapData.mapColors.default,
        color: ZoteroMapData.mapColors.border,
        weight: 1,
        fillOpacity: 1,
        opacity: 1
      };
    },
    onEachFeature: function (feature, layer) {
      const country = resolveGeoName(feature.properties.name);
      const info = ZoteroMapData.countryUrls[country];
      if (info) {
        const url = typeof info === "string" ? info : info.url;
        const count = typeof info === "object" && info.count ? info.count : "?";
        layer.on("click", function () {
          window.open(url, "_blank");
        });
        layer.bindTooltip(country + " — " + count + " tagged citations", { sticky: true });
      }
    }
  }).addTo(map);

  // Immediately remove loading message
  const loadingEl = document.getElementById("zotero-map-loading");
  if (loadingEl) loadingEl.remove();

  // Add export button if enabled
  if (ZoteroMapData.enableExport) {
    const button = document.createElement("button");
    button.textContent = "📷 Export Map as PNG";
    button.style.margin = "10px 0";
    button.onclick = function () {
      const script = document.createElement("script");
      script.src = "https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js";
      script.onload = function () {
        html2canvas(document.getElementById("zotero-map")).then(canvas => {
          const link = document.createElement("a");
          link.download = "zotero-map.png";
          link.href = canvas.toDataURL();
          link.click();
        });
      };
      document.body.appendChild(script);
    };
    mapEl.insertAdjacentElement("afterend", button);
  }
});

function resolveGeoName(name) {
  if (!name) return "";
  const aliases = {
    "United States of America": "United States",
    "Russian Federation": "Russia",
    "Viet Nam": "Vietnam",
    "Syrian Arab Republic": "Syria",
    "Iran (Islamic Republic of)": "Iran",
    "Republic of Korea": "South Korea",
    "Democratic Republic of the Congo": "Congo (Kinshasa)",
    "Congo": "Congo (Brazzaville)"
  };
  return aliases[name] || name;
}
