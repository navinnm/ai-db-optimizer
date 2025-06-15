(function($) {
    'use strict';
    
    // Global chart instances
    window.aiDbOptimizerCharts = {
        performanceChart: null,
        compositionChart: null
    };
    
    $(document).ready(function() {
        console.log('DOM ready, initializing...');
        
        // Check if we're on the right page
        if ($('.ai-database-optimizer-wrap').length) {
            console.log('Found optimizer wrap, initializing charts...');
            
            // Wait a bit for all elements to be ready
            setTimeout(function() {
                initializeCharts();
                initTabs();
            }, 100);
        }

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
                        $results.data('analysis', JSON.stringify(response.data.analysis));
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
                    $button.prop('disabled', true);
                }
            });
        });

        // Handle performance data collection
        $(document).on('click', '#ai-db-collect-performance', function() {
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
    });
    
    function initializeCharts() {
        console.log('Initializing charts...');
        
        // Check if Chart.js is available
        if (typeof Chart === 'undefined') {
            console.log('Chart.js not loaded, attempting to load...');
            loadChartJS(function() {
                console.log('Chart.js loaded, now initializing charts');
                initPerformanceChart();
                initCompositionChart();
            });
            return;
        }
        
        console.log('Chart.js already available');
        initPerformanceChart();
        initCompositionChart();
    }
    
    function loadChartJS(callback) {
        // Check if already loading
        if (window.chartJSLoading) {
            console.log('Chart.js already loading, waiting...');
            var checkInterval = setInterval(function() {
                if (typeof Chart !== 'undefined') {
                    clearInterval(checkInterval);
                    callback();
                }
            }, 100);
            return;
        }
        
        window.chartJSLoading = true;
        
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js';
        script.onload = function() {
            console.log('Chart.js script loaded successfully');
            window.chartJSLoading = false;
            callback();
        };
        script.onerror = function() {
            console.error('Failed to load Chart.js');
            window.chartJSLoading = false;
        };
        document.head.appendChild(script);
    }
    
    function initPerformanceChart() {
        var canvas = document.getElementById('db-performance-chart');
        if (!canvas) {
            console.log('Performance chart canvas not found');
            return;
        }
        
        console.log('Initializing performance chart...');
        
        // Get performance data
        $.ajax({
            url: aiDbOptimizer.ajax_url,
            type: 'POST',
            data: {
                action: 'fulgid_ai_db_optimizer_get_performance_data',
                nonce: aiDbOptimizer.nonce
            },
            success: function(response) {
                console.log('Performance data response:', response);
                if (response.success) {
                    renderPerformanceChart(canvas, response.data);
                } else {
                    console.error('Failed to fetch performance data:', response);
                    showChartError(canvas, 'Failed to load performance data');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error fetching performance data:', error);
                showChartError(canvas, 'Network error loading performance data');
            }
        });
    }
    
    function initCompositionChart() {
        var canvas = document.getElementById('db-composition-chart');
        if (!canvas) {
            console.log('Composition chart canvas not found');
            return;
        }
        
        console.log('Initializing composition chart...');
        
        // Get composition data
        $.ajax({
            url: aiDbOptimizer.ajax_url,
            type: 'POST',
            data: {
                action: 'fulgid_ai_db_optimizer_get_composition_data',
                nonce: aiDbOptimizer.nonce
            },
            success: function(response) {
                console.log('Composition data response:', response);
                if (response.success) {
                    renderCompositionChart(canvas, response.data);
                } else {
                    console.error('Failed to fetch composition data:', response);
                    showChartError(canvas, 'Failed to load database composition data');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error fetching composition data:', error);
                showChartError(canvas, 'Network error loading composition data');
            }
        });
    }
    
    function renderPerformanceChart(canvas, data) {
        console.log('Rendering performance chart with data:', data);
        
        try {
            var ctx = canvas.getContext('2d');
            
            // Destroy existing chart if it exists
            if (window.aiDbOptimizerCharts.performanceChart) {
                window.aiDbOptimizerCharts.performanceChart.destroy();
            }
            
            window.aiDbOptimizerCharts.performanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.dates || [],
                    datasets: [
                        {
                            label: 'Query Time (ms)',
                            data: data.queryTimes || [],
                            borderColor: '#007cba',
                            backgroundColor: 'rgba(0, 124, 186, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.1
                        },
                        {
                            label: 'DB Size (MB)',
                            data: data.dbSizes || [],
                            borderColor: '#46b450',
                            backgroundColor: 'rgba(70, 180, 80, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.1,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Query Time (ms)'
                            },
                            beginAtZero: true
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'DB Size (MB)'
                            },
                            beginAtZero: true,
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
            
            console.log('Performance chart rendered successfully');
        } catch (error) {
            console.error('Error rendering performance chart:', error);
            showChartError(canvas, 'Error rendering performance chart');
        }
    }
    
    function renderCompositionChart(canvas, data) {
        console.log('Rendering composition chart with data:', data);
        
        try {
            var ctx = canvas.getContext('2d');
            
            // Destroy existing chart if it exists
            if (window.aiDbOptimizerCharts.compositionChart) {
                window.aiDbOptimizerCharts.compositionChart.destroy();
            }
            
            // Default colors
            var colors = [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
            ];
            
            // Ensure we have enough colors
            while (colors.length < (data.labels || []).length) {
                colors.push('#' + Math.floor(Math.random()*16777215).toString(16));
            }
            
            window.aiDbOptimizerCharts.compositionChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        data: data.values || [],
                        backgroundColor: colors.slice(0, (data.labels || []).length),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${percentage}%`;
                                }
                            }
                        }
                    }
                }
            });
            
            console.log('Composition chart rendered successfully');
        } catch (error) {
            console.error('Error rendering composition chart:', error);
            showChartError(canvas, 'Error rendering composition chart');
        }
    }
    
    function showChartError(canvas, message) {
        var container = $(canvas).parent();
        container.html('<div class="ai-chart-error"><p>' + message + '</p></div>');
    }
    
    function initTabs() {
        console.log('Initializing tabs');
        
        // Remove existing event handlers to prevent duplicates
        $('.ai-db-tabs-nav a').off('click.aidb');
        
        $('.ai-db-tabs-nav a').on('click.aidb', function(e) {
            e.preventDefault();
            
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