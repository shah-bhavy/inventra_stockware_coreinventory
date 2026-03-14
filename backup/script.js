// ==============================
// TABLE SEARCH FUNCTION
// ==============================
function initializeSearch() {
    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
        searchInput.addEventListener("keyup", function () {
            let value = this.value.toLowerCase();
            let rows = document.querySelectorAll("tbody tr");
            rows.forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
            });
        });
    }
}

// ==============================
// DASHBOARD CHARTS - FIXED VERSION
// ==============================
function initializeCharts() {
    // INVENTORY MOVEMENT CHART
    const movementChart = document.getElementById("movementChart");
    if (movementChart) {
        new Chart(movementChart, {
            type: "line",
            data: {
                labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
                datasets: [
                    {
                        label: "Incoming",
                        data: [12, 19, 10, 15, 22, 30, 18],
                        borderColor: "#2563eb",
                        backgroundColor: "rgba(37,99,235,0.1)",
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: "#2563eb",
                        pointBorderColor: "white",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: "Outgoing",
                        data: [8, 15, 7, 12, 18, 25, 14],
                        borderColor: "#ef4444",
                        backgroundColor: "rgba(239,68,68,0.1)",
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: "#ef4444",
                        pointBorderColor: "white",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "top",
                        labels: {
                            usePointStyle: true,
                            boxWidth: 6,
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255,255,255,0.9)',
                        titleColor: '#1e293b',
                        bodyColor: '#64748b',
                        borderColor: 'rgba(0,0,0,0.05)',
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 12
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.02)',
                            drawBorder: false
                        },
                        ticks: {
                            stepSize: 5
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10
                    }
                }
            }
        });
    }

    // STOCK DISTRIBUTION CHART
    const stockChart = document.getElementById("stockChart");
    if (stockChart) {
        new Chart(stockChart, {
            type: "doughnut",
            data: {
                labels: ["Furniture", "Electronics", "Stationary", "Others"],
                datasets: [{
                    data: [40, 25, 20, 15],
                    backgroundColor: [
                        "#2563eb",
                        "#60a5fa",
                        "#93c5fd",
                        "#dbeafe"
                    ],
                    borderWidth: 0,
                    borderRadius: 8,
                    spacing: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            boxWidth: 8,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255,255,255,0.9)',
                        titleColor: '#1e293b',
                        bodyColor: '#64748b',
                        borderColor: 'rgba(0,0,0,0.05)',
                        borderWidth: 1,
                        cornerRadius: 8
                    }
                },
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10
                    }
                }
            }
        });
    }

    // DELIVERY CHART
    const deliveryChart = document.getElementById("deliveryChart");
    if (deliveryChart) {
        new Chart(deliveryChart, {
            type: "bar",
            data: {
                labels: ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
                datasets: [{
                    label: "Deliveries",
                    data: [8, 12, 6, 14, 10, 16, 9],
                    backgroundColor: "#2563eb",
                    borderRadius: 8,
                    barPercentage: 0.6,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255,255,255,0.9)',
                        titleColor: '#1e293b',
                        bodyColor: '#64748b'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.02)',
                            drawBorder: false
                        },
                        ticks: {
                            stepSize: 2
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10
                    }
                }
            }
        });
    }

    // MOVE HISTORY CHART
    const moveChart = document.getElementById("moveChart");
    if (moveChart) {
        new Chart(moveChart, {
            type: "line",
            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
                datasets: [
                    {
                        label: "Incoming",
                        data: [40, 55, 60, 70, 65, 80],
                        borderColor: "#16a34a",
                        backgroundColor: "rgba(22,163,74,0.1)",
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: "#16a34a",
                        pointBorderColor: "white",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: "Outgoing",
                        data: [35, 45, 50, 60, 55, 70],
                        borderColor: "#dc2626",
                        backgroundColor: "rgba(220,38,38,0.1)",
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: "#dc2626",
                        pointBorderColor: "white",
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "top",
                        labels: {
                            usePointStyle: true,
                            boxWidth: 6,
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255,255,255,0.9)',
                        titleColor: '#1e293b',
                        bodyColor: '#64748b'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.02)',
                            drawBorder: false
                        },
                        ticks: {
                            stepSize: 10
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                layout: {
                    padding: {
                        top: 10,
                        bottom: 10
                    }
                }
            }
        });
    }
}

// ==============================
// FILTER BUTTONS FUNCTIONALITY
// ==============================
function initializeFilters() {
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Here you would add actual filtering logic
            const filterValue = this.textContent;
            console.log(`Filtering by: ${filterValue}`);
        });
    });
}

// ==============================
// CHART FILTER FUNCTIONALITY
// ==============================
function initializeChartFilters() {
    const chartFilters = document.querySelectorAll('.chart-filter');
    chartFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            const value = this.value;
            console.log(`Chart filter changed to: ${value}`);
            // Here you would update the chart data based on the selected period
        });
    });
}

// ==============================
// DATE DISPLAY
// ==============================
function updateDateDisplay() {
    const dateElements = document.querySelectorAll('.date-today');
    const today = new Date();
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    const formattedDate = today.toLocaleDateString('en-US', options);
    
    dateElements.forEach(el => {
        el.innerHTML = `<i class="fa-regular fa-calendar"></i> ${formattedDate}`;
    });
}

// ==============================
// WINDOW RESIZE HANDLER FOR CHARTS
// ==============================
function handleResize() {
    // Chart.js handles resize automatically with responsive: true
    // This function ensures charts redraw properly on orientation change
    const charts = document.querySelectorAll('canvas');
    charts.forEach(chart => {
        const chartInstance = Chart.getChart(chart);
        if (chartInstance) {
            chartInstance.resize();
        }
    });
}

// ==============================
// INITIALIZE EVERYTHING
// ==============================
document.addEventListener('DOMContentLoaded', function() {
    initializeSearch();
    initializeCharts();
    initializeFilters();
    initializeChartFilters();
    updateDateDisplay();
    
    // Smooth scroll behavior
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });

    // Add resize listener
    window.addEventListener('resize', handleResize);
});

// ==============================
// ADDITIONAL UTILITY FUNCTIONS
// ==============================

// Format currency if needed
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2
    }).format(amount);
}

// Format date
function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Export table to CSV (optional feature)
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => rowData.push(col.innerText));
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename || 'export.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}