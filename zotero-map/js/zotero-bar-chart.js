// zotero-bar-chart.js

function lightenColor(hex, factor) {
    const f = parseInt(hex.slice(1), 16);
    const r = f >> 16;
    const g = (f >> 8) & 0x00FF;
    const b = f & 0x0000FF;
    const newR = Math.round(r + (255 - r) * factor);
    const newG = Math.round(g + (255 - g) * factor);
    const newB = Math.round(b + (255 - b) * factor);
    return `rgb(${newR}, ${newG}, ${newB})`;
}

// Wait for document to be fully loaded and parsed
window.addEventListener("load", function() {
    console.log("Window loaded, initializing Zotero bar charts...");
    
    // Give Chart.js a moment to initialize if it's loaded after our script
    setTimeout(function() {
        if (typeof Chart === 'undefined') {
            console.error("Chart.js not found! Attempting to load it dynamically...");
            
            // Try to load Chart.js dynamically as a fallback
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
            script.onload = function() {
                console.log("Chart.js loaded dynamically! Initializing charts...");
                initializeBarCharts();
            };
            script.onerror = function() {
                console.error("Failed to load Chart.js dynamically. Charts cannot be initialized.");
            };
            document.body.appendChild(script);
        } else {
            console.log("Chart.js found! Initializing charts...");
            initializeBarCharts();
        }
    }, 500); // Small delay to ensure everything is loaded
});

function initializeBarCharts() {
    const chartCanvases = document.querySelectorAll('canvas[id^="zotero-bar-chart-"]');
    console.log(`Found ${chartCanvases.length} bar chart canvases`);

    if (!window.Chart) {
        console.error("Chart.js not loaded! Charts cannot be initialized.");
        return;
    }

    chartCanvases.forEach(async canvas => {
        try {
            const index = canvas.id.split('-').pop();
            const settingsVar = 'zoteroBarChartSettings_' + index;
            const settings = window[settingsVar];

            if (!canvas || !settings) {
                console.warn(`Zotero Bar Chart: Missing canvas element or settings for ${canvas.id}.`);
                return;
            }

            console.log(`Initializing chart ${canvas.id} with settings:`, settings);
            const { group_id, collection, color, extrapolate } = settings;
            const fileName = `bar_chart_${group_id}_${collection || 'all'}.json`;
            const fileUrl = `/wp-content/uploads/zotero-map/${fileName}`;

            let allItems = [];

            try {
                const res = await fetch(fileUrl);
                if (!res.ok) throw new Error("No server cache");
                const cachedData = await res.json();
                allItems = cachedData;
                console.log("✅ Loaded Zotero bar chart data from server cache");
            } catch (e) {
                console.log("📡 Fetching data from Zotero API...");
                const apiBase = `https://api.zotero.org/groups/${group_id}`;
                const collectionPart = collection ? `/collections/${collection}` : '';
                const apiUrl = `${apiBase}${collectionPart}/items?include=data&limit=100&start=`;

                let start = 0;
                let hasMore = true;

                while (hasMore) {
                    const response = await fetch(apiUrl + start);
                    if (!response.ok) {
                        console.error("Zotero API error", response.status);
                        break;
                    }
                    const items = await response.json();
                    if (!Array.isArray(items) || items.length === 0) break;
                    allItems.push(...items);
                    start += items.length;
                }

                // Push data to server to cache it
                try {
                    await fetch('/wp-admin/admin-ajax.php', {
                        method: 'POST',
                        headers: {
                          'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                          action: 'zotero_generate_barchart_cache',
                          group_id,
                          collection,
                        }),
                      });
                } catch (e) {
                    console.warn("⚠️ Could not save server cache");
                }
            }

            const yearCounts = {};
            const currentYear = new Date().getFullYear();
            const currentMonth = new Date().getMonth() + 1;

            allItems.forEach(item => {
                const date = item.data.date;
                if (!date) return;
                const match = date.match(/\d{4}/);
                if (match) {
                    const year = parseInt(match[0]);
                    yearCounts[year] = (yearCounts[year] || 0) + 1;
                }
            });

            const sortedYears = Object.keys(yearCounts).map(Number).sort((a, b) => a - b);
            const actualCounts = sortedYears.map(y => yearCounts[y]);

            const labels = sortedYears.map(String);
            const actualData = [...actualCounts];
            const projectedData = actualCounts.map(() => null);

            if (extrapolate) {
                // 1. Build monthlyCounts for each YYYY‑MM
                const monthlyCounts = {};
                allItems.forEach(item => {
                    const d = new Date(item.data.date);
                    if (!isNaN(d)) {
                        const key = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
                        monthlyCounts[key] = (monthlyCounts[key] || 0) + 1;
                    }
                });
            
                // 2. Get current date info
                const now = new Date();
                const currentYear = now.getFullYear();
                const currentMonth = now.getMonth() + 1;  // 1–12
                const currentDay = now.getDate();
                const daysInCurrentMonth = new Date(currentYear, currentMonth, 0).getDate();
                const fractionOfMonthPassed = currentDay / daysInCurrentMonth;
                
                console.log(`Current date: ${currentYear}-${currentMonth}-${currentDay}`);
                console.log(`Days in current month: ${daysInCurrentMonth}`);
                console.log(`Fraction of month passed: ${fractionOfMonthPassed.toFixed(2)}`);
            
                // 3. Determine rolling 12‑month window (excluding current month) and compute monthly average
                let sumPrevious11 = 0;
                let monthsWithData = 0;
                
                for (let i = 1; i < 12; i++) {  // Start at 1 to exclude current month
                    const dt = new Date(now.getFullYear(), now.getMonth() - i, 1);
                    const key = `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}`;
                    const count = monthlyCounts[key] || 0;
                    sumPrevious11 += count;
                    if (count > 0) monthsWithData++;
                }
                
                // Use average of months that have data, default to 0 if no data
                const monthlyAvg = monthsWithData > 0 ? sumPrevious11 / monthsWithData : 0;
                
                // 4. Handle current month projection
                const currentMonthKey = `${currentYear}-${String(currentMonth).padStart(2,'0')}`;
                const currentMonthActual = monthlyCounts[currentMonthKey] || 0;
                
                // Calculate projected total for current month based on days passed
                let currentMonthProjected = 0;
                if (fractionOfMonthPassed > 0) {
                    // Extrapolate current month based on progress so far
                    // If we have actual data, use that as a basis for projection; otherwise use monthly average
                    if (currentMonthActual > 0) {
                        currentMonthProjected = Math.round(currentMonthActual / fractionOfMonthPassed) - currentMonthActual;
                    } else {
                        currentMonthProjected = Math.round(monthlyAvg * (1 - fractionOfMonthPassed));
                    }
                }
                
                console.log(`Current month actual count: ${currentMonthActual}`);
                console.log(`Current month projected additional: ${currentMonthProjected}`);
                console.log(`Monthly average (from previous months): ${monthlyAvg.toFixed(2)}`);
                
                // 5. Project forward for remaining full months in the year
                const remainingFullMonths = 12 - currentMonth;
                const projectedRemainingMonths = Math.round(monthlyAvg * remainingFullMonths);
                
                console.log(`Remaining full months: ${remainingFullMonths}`);
                console.log(`Projected for remaining months: ${projectedRemainingMonths}`);
                
                // 6. Total projection = current month projection + remaining months projection
                const totalProjection = currentMonthProjected + projectedRemainingMonths;
                console.log(`Total projection for year: ${totalProjection}`);
                
                // 7. Insert into projectedData for the current year
                const currentIndex = sortedYears.indexOf(currentYear);
                if (currentIndex !== -1) {
                    projectedData[currentIndex] = totalProjection;
                }
            }

            console.log(`Rendering chart with ${labels.length} years of data`);
            
            new Chart(canvas, {
                type: "bar",
                data: {
                    labels,
                    datasets: [
                        {
                            label: "Actual",
                            data: actualData,
                            backgroundColor: color,
                            stack: 'stack1'
                        },
                        {
                            label: "Projected",
                            data: projectedData,
                            backgroundColor: lightenColor(color, 0.5),
                            stack: 'stack1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: true },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const val = context.raw;
                                    const label = context.dataset.label;
                                    if (label === "Projected") {
                                        return `${val} (projected)`;
                                    }
                                    return `${val}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { 
                                display: true, 
                                text: "Citations",
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            },
                            ticks: {
                                font: {
                                    size: 12
                                }
                            }
                        },
                        x: {
                            title: { 
                                display: true, 
                                text: "Publication Year",
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            },
                            ticks: {
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                }
                            }
                        }
                    },
                    font: {
                        family: "'Helvetica Neue', 'Helvetica', 'Arial', sans-serif"
                    }
                }
            });
        } catch (error) {
            console.error("Error initializing chart:", error);
        }
    });
}