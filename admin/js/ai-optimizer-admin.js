(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize charts if on the right page
        if ($('.ai-database-optimizer-wrap').length) {
            initializeCharts();
            
            // Initialize tabs at page load
            initTabs();
        }

        // Add a new button for performance data collection to the HTML
        $('.ai-database-optimizer-actions').append(
            '<button id="ai-db-collect-performance" class="button">' +
            'Collect Performance Data' +
            '</button>'
        );
        
        $('#ai-db-collect-performance').on('click', function() {
            var $button = $(this);
            var $results = $('#ai-db-results');
            
            $button.prop('disabled', true).text('Collecting data...');
            $results.html('<p class="ai-db-loading">Collecting database performance data...</p>');
            
            $.ajax({
                url: aiDbOptimizer.ajax_url,
                type: 'POST',
                data: {
                    action: 'fulgid_ai_db_optimizer_collect_performance',
                    nonce: aiDbOptimizer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $results.html(response.data.html);

                        // Initialize charts with the analysis data
                    
                            
                        // Initialize tabs functionality
                        initTabs();
                    } else {
                        $results.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $results.html('<div class="notice notice-error"><p>An error occurred while collecting performance data.</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Collect Performance Data');
                }
            });
        });

        // Handle analyze button click
        $('#ai-db-analyze').on('click', function() {
            var $button = $(this);
            var $results = $('#ai-db-results');
            
            $button.prop('disabled', true);
            $results.html('<div class="ai-db-loading"><div class="spinner"></div>' + aiDbOptimizer.analyzing_text + '</div>');
            
            $.ajax({
                url: aiDbOptimizer.ajax_url,
                type: 'POST',
                data: {
                    action: 'fulgid_ai_db_optimizer_analyze',
                    nonce: aiDbOptimizer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $results.html(response.data.html);
                        
                        // Store analysis data for optimization
                        $results.data('analysis', JSON.stringify(response.data.analysis));
                        
                        // No need to enable optimize button here as it's included in the results HTML
                    } else {
                        $results.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $results.html('<div class="notice notice-error"><p>An error occurred during analysis.</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Handle optimize button click (delegated since button might be dynamically added)
        $(document).on('click', '#ai-db-optimize', function() {
            var $button = $(this);
            var $results = $('#ai-db-results');
            var analysis = $results.data('analysis');
            
            if (!analysis) {
                $results.html('<div class="notice notice-error"><p>Please analyze the database first.</p></div>');
                return;
            }
            
            $button.prop('disabled', true);
            $results.html('<div class="ai-db-loading"><div class="spinner"></div>' + aiDbOptimizer.optimizing_text + '</div>');
            
            $.ajax({
                url: aiDbOptimizer.ajax_url,
                type: 'POST',
                data: {
                    action: 'fulgid_ai_db_optimizer_optimize',
                    nonce: aiDbOptimizer.nonce,
                    analysis: analysis
                },
                success: function(response) {
                    if (response.success) {
                        $results.html(response.data.html);
                        
                        // Reload the page after 3 seconds to show updated dashboard
                        setTimeout(function() {
                            window.location.reload();
                        }, 3000);
                    } else {
                        $results.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $results.html('<div class="notice notice-error"><p>An error occurred during optimization.</p></div>');
                },
                complete: function() {
                    // Button will be disabled anyway since we're reloading
                    $button.prop('disabled', true);
                }
            });
        });
    });
    
function initializeCharts() {
    console.log('Initializing charts...');
    
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.log('Chart.js not loaded, loading dynamically...');
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js';
        script.onload = function() {
            console.log('Chart.js loaded successfully');
            // Now initialize charts
            if (document.getElementById('db-composition-chart')) {
                console.log('Found composition chart element, initializing...');
                renderCompositionChart();
            } else {
                console.error('Composition chart canvas element not found');
            }
            initializePerformanceChart();
        };
        document.head.appendChild(script);
        return;
    }
    
    // If Chart.js is already loaded
    console.log('Chart.js already loaded');
    if (document.getElementById('db-composition-chart')) {
        console.log('Found composition chart element, initializing...');
        renderCompositionChart();
    } else {
        console.error('Composition chart canvas element not found');
    }
    initializePerformanceChart();
}


function renderCompositionChart() {
    // Get the canvas element
    var canvas = document.getElementById('db-composition-chart');
    if (!canvas) {
        console.error('Canvas element not found');
        return;
    }
    
    var ctx = canvas.getContext('2d');
    
    // Fetch actual data from the server
    jQuery.ajax({
        url: aiDbOptimizer.ajax_url,
        type: 'POST',
        data: {
            action: 'fulgid_ai_db_optimizer_get_composition_data',
            nonce: aiDbOptimizer.nonce
        },
        success: function(response) {
            console.log('Composition data received:', response);
            if (response.success) {
                var data = response.data;
                // Create the pie chart
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.values,
                            backgroundColor: [
                                '#FF6384',
                                '#36A2EB',
                                '#FFCE56',
                                '#4BC0C0',
                                '#9966FF',
                                '#FF9F40'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right'
                            },
                            title: {
                                display: true,
                                text: 'Database Composition'
                            }
                        }
                    }
                });
            } else {
                // If there's an error in the response
                console.error('Failed to fetch composition data:', response.data.message);
                jQuery('#db-composition-chart').after('<p class="error-message">Failed to load database composition data</p>');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', error);
            jQuery('#db-composition-chart').after('<p class="error-message">Failed to load database composition data</p>');
        }
    });
}

    
    function initializePerformanceChart() {
        // Get the canvas element
        var ctx = document.getElementById('db-performance-chart');
        if (!ctx) {
            console.error('Performance chart canvas not found');
            return;
        }
        
        ctx = ctx.getContext('2d');
        console.log('Fetching performance data...');
        
        // Fetch actual performance data from the server
        jQuery.ajax({
            url: aiDbOptimizer.ajax_url,
            type: 'POST',
            data: {
                action: 'fulgid_ai_db_optimizer_get_performance_data',
                nonce: aiDbOptimizer.nonce
            },
            success: function(response) {
                console.log('Performance data response received', response);
                if (response.success) {
                    var performanceData = response.data;
                    renderPerformanceChart(ctx, performanceData);
                } else {
                    console.error('Failed to fetch performance data:', response.data.message);
                    // Show error message in chart container
                    jQuery('#db-performance-chart').after('<p class="error-message">Failed to load performance data</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                // Show error message in chart container
                jQuery('#db-performance-chart').after('<p class="error-message">Failed to load performance data</p>');
            }
        });
    }

    function renderPerformanceChart(ctx, performanceData) {
        console.log('Rendering performance chart with data:', performanceData);
        // Create chart with real data
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: performanceData.dates,
                datasets: [
                    {
                        label: 'Query Time (ms)',
                        data: performanceData.queryTimes,
                        borderColor: '#007cba',
                        backgroundColor: 'rgba(0, 124, 186, 0.1)',
                        borderWidth: 2,
                        fill: true
                    },
                    {
                        label: 'DB Size (MB)',
                        data: performanceData.dbSizes,
                        borderColor: '#46b450',
                        backgroundColor: 'rgba(70, 180, 80, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        title: {
                            display: true,
                            text: 'Query Time (ms)'
                        },
                        beginAtZero: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        title: {
                            display: true,
                            text: 'DB Size (MB)'
                        },
                        beginAtZero: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    if (context.datasetIndex === 0) {
                                        label += context.parsed.y.toFixed(2) + ' ms';
                                    } else {
                                        label += context.parsed.y.toFixed(2) + ' MB';
                                    }
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
/**
 * Initialize and render the database composition chart
 * 
 * @param {string} canvasId - The ID of the canvas element to render the chart
 * @param {Object} dbData - Data about database table sizes and composition
 */
function initializeCompositionChart(chartId, labelData, valueData) {
    // Check if the chartId is defined and the canvas element exists
    if (!chartId) {
        console.error('Chart ID is undefined');
        return;
    }
    
    var canvas = document.getElementById(chartId);
    if (!canvas) {
        console.error('Canvas element with ID "' + chartId + '" not found.');
        return;
    }
    
    var ctx = canvas.getContext('2d');
    
    // Create the pie chart
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labelData,
            datasets: [{
                data: valueData,
                backgroundColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF',
                    '#FF9F40'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'right'
            },
            title: {
                display: true,
                text: 'Database Composition'
            }
        }
    });
}

/**
 * Adjust a color by lightening or darkening it
 * 
 * @param {string} color - Hex color code to adjust
 * @param {number} amount - Amount to adjust (-100 to 100)
 * @param {number} alpha - Optional alpha transparency (0 to 1)
 * @return {string} - Adjusted color
 */
function adjustColor(color, amount, alpha) {
    let usePound = false;
    
    if (color[0] === "#") {
        color = color.slice(1);
        usePound = true;
    }
    
    const num = parseInt(color, 16);
    let r = (num >> 16) + amount;
    let g = ((num >> 8) & 0x00FF) + amount;
    let b = (num & 0x0000FF) + amount;
    
    r = Math.min(255, Math.max(0, r));
    g = Math.min(255, Math.max(0, g));
    b = Math.min(255, Math.max(0, b));
    
    const hexColor = (usePound ? '#' : '') + (g | (b << 8) | (r << 16)).toString(16).padStart(6, '0');
    
    if (alpha !== undefined) {
        // Convert hex to rgba
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }
    
    return hexColor;
}

/**
 * Show detailed information about a specific table
 * 
 * @param {string} tableName - Name of the table to show details for
 * @param {Object} tableData - Data about the table
 */
function showTableDetails(tableName, tableData) {
    // This could show a modal with detailed table information
    console.log(`Showing details for table: ${tableName}`, tableData);
    
    // Example: dispatch a custom event that other components can listen for
    const event = new CustomEvent('ai-database-optimizer-table-selected', {
        detail: {
            tableName: tableName,
            tableData: tableData
        }
    });
    document.dispatchEvent(event);
    
    // Or update a details area on the page
    const detailsArea = document.getElementById('ai-db-table-details');
    if (detailsArea) {
        // Format size values
        const dataSizeMB = (tableData.dataSize / (1024 * 1024)).toFixed(2);
        const indexSizeMB = (tableData.indexSize / (1024 * 1024)).toFixed(2);
        const overheadMB = (tableData.overhead / (1024 * 1024)).toFixed(2);
        
        detailsArea.innerHTML = `
            <h3>Table Details: ${tableName}</h3>
            <div class="ai-db-table-details-grid">
                <div class="ai-db-detail-item">
                    <span class="ai-db-detail-label">Rows:</span>
                    <span class="ai-db-detail-value">${tableData.rows.toLocaleString()}</span>
                </div>
                <div class="ai-db-detail-item">
                    <span class="ai-db-detail-label">Data Size:</span>
                    <span class="ai-db-detail-value">${dataSizeMB} MB</span>
                </div>
                <div class="ai-db-detail-item">
                    <span class="ai-db-detail-label">Index Size:</span>
                    <span class="ai-db-detail-value">${indexSizeMB} MB</span>
                </div>
                <div class="ai-db-detail-item">
                    <span class="ai-db-detail-label">Overhead:</span>
                    <span class="ai-db-detail-value">${overheadMB} MB</span>
                </div>
            </div>
            
            <div class="ai-db-table-actions">
                <button class="button ai-db-analyze-table" data-table="${tableName}">
                    Analyze Table Structure
                </button>
                <button class="button ai-db-optimize-table" data-table="${tableName}">
                    Optimize Table
                </button>
            </div>
            
            <div id="ai-db-table-analysis-results" class="ai-db-analysis-results"></div>
        `;
        
        // Add event listeners to the new buttons
        detailsArea.querySelector('.ai-db-analyze-table').addEventListener('click', function() {
            analyzeTableStructure(tableName);
        });
        
        detailsArea.querySelector('.ai-db-optimize-table').addEventListener('click', function() {
            optimizeTable(tableName);
        });
    }
}

/**
 * Analyze the structure of a specific table
 * 
 * @param {string} tableName - Name of the table to analyze
 */
function analyzeTableStructure(tableName) {
    const resultsArea = document.getElementById('ai-db-table-analysis-results');
    if (resultsArea) {
        resultsArea.innerHTML = '<p class="ai-db-loading">Analyzing table structure...</p>';
        
        // Make AJAX request to analyze table
        jQuery.ajax({
            url: aiDbOptimizer.ajax_url,
            type: 'POST',
            data: {
                action: 'fulgid_ai_db_optimizer_analyze_table',
                nonce: aiDbOptimizer.nonce,
                table: tableName
            },
            success: function(response) {
                if (response.success) {
                    resultsArea.innerHTML = response.data.html;
                } else {
                    resultsArea.innerHTML = `<div class="notice notice-error"><p>${response.data.message}</p></div>`;
                }
            },
            error: function() {
                resultsArea.innerHTML = '<div class="notice notice-error"><p>An error occurred during table analysis.</p></div>';
            }
        });
    }
}

/**
 * Optimize a specific table
 * 
 * @param {string} tableName - Name of the table to optimize
 */
function optimizeTable(tableName) {
    const resultsArea = document.getElementById('ai-db-table-analysis-results');
    if (resultsArea) {
        resultsArea.innerHTML = '<p class="ai-db-loading">Optimizing table...</p>';
        
        // Make AJAX request to optimize table
        jQuery.ajax({
            url: aiDbOptimizer.ajax_url,
            type: 'POST',
            data: {
                action: 'fulgid_ai_db_optimizer_optimize_table',
                nonce: aiDbOptimizer.nonce,
                table: tableName
            },
            success: function(response) {
                if (response.success) {
                    resultsArea.innerHTML = response.data.html;
                    
                    // Update the chart if needed
                    if (window.aiDbOptimizerCharts && window.aiDbOptimizerCharts.compositionChart) {
                        // This would require updating the chart data
                        // Ideally you'd get updated data from the response
                    }
                } else {
                    resultsArea.innerHTML = `<div class="notice notice-error"><p>${response.data.message}</p></div>`;
                }
            },
            error: function() {
                resultsArea.innerHTML = '<div class="notice notice-error"><p>An error occurred during table optimization.</p></div>';
            }
        });
    }
}
    
    function initTabs() {
        console.log('Initializing tabs');
        $('.ai-db-tabs-nav a').on('click', function(e) {
            e.preventDefault();
            
            // Get the target pane
            var target = $(this).attr('href');
            console.log('Tab clicked, target:', target);
            
            // Remove active class from all tabs and panes
            $('.ai-db-tabs-nav li').removeClass('active');
            $('.ai-db-tab-pane').removeClass('active');
            
            // Add active class to current tab and pane
            $(this).parent().addClass('active');
            $(target).addClass('active');
        });
    }

})(jQuery);