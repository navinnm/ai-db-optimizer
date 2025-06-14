<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Admin_UI {
    /**
     * The DB analyzer instance
     */
    private $analyzer;
    
    /**
     * The optimization engine instance
     */
    private $optimization_engine;
    
    /**
     * Constructor
     */
public function __construct($analyzer, $optimization_engine) {
    $this->analyzer = $analyzer;
    $this->optimization_engine = $optimization_engine;
    
    // Set up admin menu
    add_action('admin_menu', [$this, 'add_admin_menu']);
    
    // Register assets
    add_action('admin_enqueue_scripts', [$this, 'register_assets']);
    
    // Add AJAX handlers
    add_action('wp_ajax_fulgid_ai_db_optimizer_analyze', [$this, 'ajax_analyze_database']);
    add_action('wp_ajax_fulgid_ai_db_optimizer_optimize', [$this, 'ajax_optimize_database']);
    add_action('wp_ajax_fulgid_ai_db_optimizer_collect_performance', [$this, 'ajax_collect_performance_data']);
    add_action('wp_ajax_fulgid_ai_db_optimizer_get_performance_data', [$this, 'ajax_get_performance_data']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_chart_data']);
    add_action('wp_ajax_fulgid_ai_db_optimizer_get_composition_data', [$this, 'ajax_get_composition_data']);
}

    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_management_page(
            __('AI DB Optimizer', 'ai-db-optimizer'),
            __('AI DB Optimizer', 'ai-db-optimizer'),
            'manage_options',
            'ai-db-optimizer',
            [$this, 'render_admin_page']
        );
    }
    
/**
 * Register admin assets
 */
public function register_assets($hook) {
    if ($hook != 'tools_page_ai-db-optimizer') {
        return;
    }
    
    // Enqueue Chart.js from CDN
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js',
        [],
        '3.7.0',
        true
    );
    
    wp_enqueue_style(
        'ai-db-optimizer-admin',
        FULGID_AI_DB_OPTIMIZER_PLUGIN_URL . 'assets/css/ai-optimizer-admin.css',
        [],
        FULGID_AI_DB_OPTIMIZER_VERSION
    );
    
    wp_enqueue_script(
        'ai-db-optimizer-admin',
        FULGID_AI_DB_OPTIMIZER_PLUGIN_URL . 'admin/js/ai-optimizer-admin.js',
        ['jquery', 'chartjs'],
        FULGID_AI_DB_OPTIMIZER_VERSION,
        true
    );
    
    wp_localize_script(
        'ai-db-optimizer-admin',
        'aiDbOptimizer',
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fulgid_ai_db_optimizer_nonce'),
            'analyzing_text' => __('Analyzing database...', 'ai-db-optimizer'),
            'optimizing_text' => __('Optimizing database...', 'ai-db-optimizer'),
        ]
    );
}
    /**
     * Render the admin page
     */
    /**
 * Render the admin page
 */
public function render_admin_page() {
    $settings = get_option('fulgid_ai_db_optimizer_settings');
    ?>
    <div class="wrap ai-db-optimizer-wrap">
        <div class="ai-db-optimizer-header">
            <div class="ai-db-optimizer-logo">
                <svg width="500" height="500" viewBox="0 0 500 500" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M274.5 279C274.5 292.531 263.531 303.5 250 303.5C236.469 303.5 225.5 292.531 225.5 279M274.5 279C274.5 265.469 263.531 254.5 250 254.5M274.5 279H300.5M225.5 279C225.5 265.469 236.469 254.5 250 254.5M225.5 279H195.5M250 254.5V205.5M250 205.5C311.25 205.5 360.25 189.085 360.25 168.75V95.25M250 205.5C188.75 205.5 139.75 189.085 139.75 168.75V95.25M360.25 95.25C360.25 115.546 310.89 132 250 132C189.111 132 139.75 115.546 139.75 95.25M360.25 95.25C360.25 74.9536 310.89 58.5 250 58.5C189.111 58.5 139.75 74.9536 139.75 95.25" stroke="white" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M335 402.25L329.121 316.993C328.197 303.608 327.736 296.915 324.835 291.842C322.281 287.375 318.435 283.786 313.803 281.545C308.542 279 301.834 279 288.417 279H211.583C198.166 279 191.458 279 186.197 281.545C181.565 283.786 177.72 287.375 175.165 291.842C172.264 296.915 171.803 303.608 170.88 316.993L165 402.25M335 402.25C335 418.68 321.68 432 305.25 432H194.75C178.32 432 165 418.68 165 402.25M335 402.25C335 385.82 321.68 372.5 305.25 372.5H194.75C178.32 372.5 165 385.82 165 402.25M199 402.25H199.085M250 402.25H301" stroke="white" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>

            </div>
            <h1><?php esc_html_e('AI Database Optimizer', 'ai-db-optimizer'); ?></h1>
        </div>
        
        <div class="ai-db-optimizer-main">
            <div class="ai-db-optimizer-dashboard">
                <div class="flex" style="display: flex; flex-wrap: wrap; gap: 20px; width: 100%;">
                    <div class="ai-db-optimizer-card">
                        <h2><?php esc_html_e('Database Health Dashboard', 'ai-db-optimizer'); ?></h2>
                        
                        <div class="ai-db-health-indicator">
                            <?php 
                            $health_score = $this->calculate_db_health_score();
                            $health_class = 'high';
                            
                            if ($health_score < 70) {
                                $health_class = 'medium';
                            } elseif ($health_score < 50) {
                                $health_class = 'low';
                            }
                            ?>
                            
                            <div class="ai-db-health-score <?php echo esc_html( $health_class); ?>">
                                <div class="inner"><?php echo esc_html( $health_score); ?>%</div>
                            </div>
                            
                            <div class="ai-db-health-details">
                                <h3>
                                    <?php 
                                    if ($health_score >= 80) {
                                        esc_html_e('Excellent Health', 'ai-db-optimizer');
                                    } elseif ($health_score >= 60) {
                                        esc_html_e('Good Health', 'ai-db-optimizer');
                                    } elseif ($health_score >= 40) {
                                        esc_html_e('Fair Health', 'ai-db-optimizer');
                                    } else {
                                        esc_html_e('Poor Health', 'ai-db-optimizer');
                                    }
                                    ?>
                                </h3>
                                <p>
                                    <?php 
                                    if ($health_score >= 80) {
                                        esc_html_e('Your database is performing well with optimal structure.', 'ai-db-optimizer');
                                    } elseif ($health_score >= 60) {
                                        esc_html_e('Your database is performing adequately but could benefit from some optimizations.', 'ai-db-optimizer');
                                    } elseif ($health_score >= 40) {
                                        esc_html_e('Your database needs attention to improve performance.', 'ai-db-optimizer');
                                    } else {
                                        esc_html_e('Your database requires immediate optimization to improve performance.', 'ai-db-optimizer');
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="ai-db-status-overview">
                            <?php $this->render_database_metrics(); ?>
                        </div>
                        
                        
                        
                        <div class="ai-db-optimizer-actions">
                            <button id="ai-db-analyze" class="button button-primary">
                            <svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 20V13M12 20V10M4 20L4 16M13.4067 5.0275L18.5751 6.96567M10.7988 5.40092L5.20023 9.59983M21.0607 6.43934C21.6464 7.02513 21.6464 7.97487 21.0607 8.56066C20.4749 9.14645 19.5251 9.14645 18.9393 8.56066C18.3536 7.97487 18.3536 7.02513 18.9393 6.43934C19.5251 5.85355 20.4749 5.85355 21.0607 6.43934ZM5.06066 9.43934C5.64645 10.0251 5.64645 10.9749 5.06066 11.5607C4.47487 12.1464 3.52513 12.1464 2.93934 11.5607C2.35355 10.9749 2.35355 10.0251 2.93934 9.43934C3.52513 8.85355 4.47487 8.85355 5.06066 9.43934ZM13.0607 3.43934C13.6464 4.02513 13.6464 4.97487 13.0607 5.56066C12.4749 6.14645 11.5251 6.14645 10.9393 5.56066C10.3536 4.97487 10.3536 4.02513 10.9393 3.43934C11.5251 2.85355 12.4749 2.85355 13.0607 3.43934Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <?php esc_html_e('Analyze Database', 'ai-db-optimizer'); ?>
                            </button>
                            <button id="ai-db-optimize" class="button" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                                    <path d="M19.89 10.105a8.696 8.696 0 0 0-.789-1.456l-1.658 1.119a6.606 6.606 0 0 1 .987 2.345 6.659 6.659 0 0 1 0 2.648 6.495 6.495 0 0 1-.384 1.231 6.404 6.404 0 0 1-.603 1.112 6.654 6.654 0 0 1-1.776 1.775 6.606 6.606 0 0 1-2.343.987 6.734 6.734 0 0 1-2.646 0 6.55 6.55 0 0 1-3.317-1.788 6.605 6.605 0 0 1-1.408-2.088 6.613 6.613 0 0 1-.382-1.23 6.627 6.627 0 0 1 .382-3.877A6.551 6.551 0 0 1 7.36 8.797 6.628 6.628 0 0 1 9.446 7.39c.395-.167.81-.296 1.23-.382.107-.022.216-.032.324-.049V10l5-4-5-4v2.938a8.805 8.805 0 0 0-.725.111 8.512 8.512 0 0 0-3.063 1.29A8.566 8.566 0 0 0 4.11 16.77a8.535 8.535 0 0 0 1.835 2.724 8.614 8.614 0 0 0 2.721 1.833 8.55 8.55 0 0 0 5.061.499 8.576 8.576 0 0 0 6.162-5.056c.22-.52.389-1.061.5-1.608a8.643 8.643 0 0 0 0-3.45 8.684 8.684 0 0 0-.499-1.607z"/>
                                </svg>
                                <?php esc_html_e('Optimize Now', 'ai-db-optimizer'); ?>
                            </button>
                        </div>

                         <div class="ai-db-optimizer-card ai-db-performance-card">
                            <h2><?php esc_html_e('Performance Monitoring', 'ai-db-optimizer'); ?></h2>
                            <div class="ai-db-performance-chart-container">
                                <canvas id="db-performance-chart"></canvas>
                            </div>
                        </div>
                    </div>
                
                   
                </div>
                 <div class="ai-db-optimizer-card">
                        <h2><?php esc_html_e('Analysis & Optimization Results', 'ai-db-optimizer'); ?></h2>
                        <div id="ai-db-results" class="ai-db-optimizer-results">
                            <p><?php esc_html_e('Click "Analyze Database" to start.', 'ai-db-optimizer'); ?></p>
                        </div>
                    </div>
                <div class="flex">
                <div class="ai-db-optimizer-card">
                        <h2><?php esc_html_e('Optimization History', 'ai-db-optimizer'); ?></h2>
                        <?php $this->render_optimization_history(); ?>
                </div>
                </div>
            </div>
            
            <div class="ai-db-optimizer-sidebar">
                <div class="ai-db-optimizer-card">
                    <h2><?php esc_html_e('AI Insights', 'ai-db-optimizer'); ?></h2>
                    <div id="ai-db-insights">
                        <?php $this->render_ai_insights(); ?>
                    </div>
                </div>
                
                <div class="ai-db-optimizer-card">
                    <h2><?php esc_html_e('Settings', 'ai-db-optimizer'); ?></h2>
                    <form method="post" action="options.php" class="ai-db-settings-form">
                        <?php
                        settings_fields('fulgid_ai_db_optimizer_settings');
                        ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Schedule Frequency', 'ai-db-optimizer'); ?></th>
                                <td>
                                    <select name="fulgid_ai_db_optimizer_settings[schedule_frequency]">
                                        <option value="daily" <?php selected(isset($settings['schedule_frequency']) ? $settings['schedule_frequency'] : '', 'daily'); ?>>
                                            <?php esc_html_e('Daily', 'ai-db-optimizer'); ?>
                                        </option>
                                        <option value="weekly" <?php selected(isset($settings['schedule_frequency']) ? $settings['schedule_frequency'] : '', 'weekly'); ?>>
                                            <?php esc_html_e('Weekly', 'ai-db-optimizer'); ?>
                                        </option>
                                        <option value="monthly" <?php selected(isset($settings['schedule_frequency']) ? $settings['schedule_frequency'] : '', 'monthly'); ?>>
                                            <?php esc_html_e('Monthly', 'ai-db-optimizer'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Auto-Optimize', 'ai-db-optimizer'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="fulgid_ai_db_optimizer_settings[auto_optimize]" value="1" <?php checked(isset($settings['auto_optimize']) ? $settings['auto_optimize'] : false); ?>>
                                        <?php esc_html_e('Enable automatic database optimization', 'ai-db-optimizer'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Optimization Level', 'ai-db-optimizer'); ?></th>
                                <td>
                                    <select name="fulgid_ai_db_optimizer_settings[optimization_level]">
                                        <option value="low" <?php selected(isset($settings['optimization_level']) ? $settings['optimization_level'] : '', 'low'); ?>>
                                            <?php esc_html_e('Low - Basic optimizations only', 'ai-db-optimizer'); ?>
                                        </option>
                                        <option value="medium" <?php selected(isset($settings['optimization_level']) ? $settings['optimization_level'] : '', 'medium'); ?>>
                                            <?php esc_html_e('Medium - Standard optimizations', 'ai-db-optimizer'); ?>
                                        </option>
                                        <option value="high" <?php selected(isset($settings['optimization_level']) ? $settings['optimization_level'] : '', 'high'); ?>>
                                            <?php esc_html_e('High - Aggressive optimizations', 'ai-db-optimizer'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Notification Email', 'ai-db-optimizer'); ?></th>
                                <td>
                                    <input type="email" name="fulgid_ai_db_optimizer_settings[notification_email]" value="<?php echo esc_attr(isset($settings['notification_email']) ? $settings['notification_email'] : get_option('admin_email')); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Email to receive optimization reports', 'ai-db-optimizer'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                    </form>
                </div>

                <div class="flex">
                    <div class="ai-db-optimizer-card">
                        <h2><?php esc_html_e('Database Composition', 'ai-db-optimizer'); ?></h2>
                        <div class="ai-db-chart-container">
                            <canvas id="db-composition-chart" width="400" height="300"></canvas>
                        </div>
                    </div>

                
                </div>
                
                
            </div>
        </div>
    </div>
    <?php
}

    /**
     * Render database metrics
     */
    private function render_database_metrics() {
        global $wpdb;
        
        // Get database size
        $db_size = $wpdb->get_row("SELECT SUM(data_length + index_length) as size FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'");
        
        // Get table count
        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'");
        $table_count = count($tables);
        
        // Get row count (approximate)
        $total_rows = 0;
        foreach ($tables as $table) {
            $table_name = reset($table);
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $total_rows += $count;
        }
        
        // Get last optimization time
        $settings = get_option('fulgid_ai_db_optimizer_settings');
        $last_optimization = isset($settings['last_optimization']) ? $settings['last_optimization'] : false;
        
        ?>
        <div class="ai-db-status-metric">
            <h3><?php esc_html_e('Size', 'ai-db-optimizer'); ?></h3>
            <div class="value"><?php echo size_format($db_size->size); ?></div>
            <div class="description"><?php esc_html_e('Total database size', 'ai-db-optimizer'); ?></div>
        </div>
        
        <div class="ai-db-status-metric">
            <h3><?php esc_html_e('Tables', 'ai-db-optimizer'); ?></h3>
            <div class="value"><?php echo $table_count; ?></div>
            <div class="description"><?php esc_html_e('WordPress tables', 'ai-db-optimizer'); ?></div>
        </div>
        
        <div class="ai-db-status-metric">
            <h3><?php esc_html_e('Rows', 'ai-db-optimizer'); ?></h3>
            <div class="value"><?php echo number_format($total_rows); ?></div>
            <div class="description"><?php esc_html_e('Total data rows', 'ai-db-optimizer'); ?></div>
        </div>
        
        <div class="ai-db-status-metric">
            <h3><?php esc_html_e('Last Optimized', 'ai-db-optimizer'); ?></h3>
            <div class="value">
                <?php 
                if ($last_optimization) {
                    echo human_time_diff(strtotime($last_optimization), current_time('timestamp'));
                    echo ' ' . __('ago', 'ai-db-optimizer');
                } else {
                    esc_html_e('Never', 'ai-db-optimizer');
                }
                ?>
            </div>
            <div class="description">
                <?php 
                if ($last_optimization) {
                    echo date_i18n(get_option('date_format'), strtotime($last_optimization));
                } else {
                    esc_html_e('No optimization yet', 'ai-db-optimizer');
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Calculate DB health score
     */
    private function calculate_db_health_score() {
        global $wpdb;
        
        $score = 100; // Start with perfect score
        
        // Check for overhead
        $tables_with_overhead = $wpdb->get_results("
            SELECT TABLE_NAME, DATA_FREE
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
            AND TABLE_NAME LIKE '{$wpdb->prefix}%'
            AND DATA_FREE > 0
        ");
        
        $total_overhead = 0;
        foreach ($tables_with_overhead as $table) {
            $total_overhead += $table->DATA_FREE;
        }
        
        // Reduce score based on overhead
        if ($total_overhead > 10 * 1024 * 1024) { // More than 10MB overhead
            $score -= 20;
        } elseif ($total_overhead > 1024 * 1024) { // More than 1MB overhead
            $score -= 10;
        }
        
        // Check for missing indexes on important tables
        $important_indexes = [
            $wpdb->posts => ['post_type', 'post_status', 'post_author', 'post_date'],
            $wpdb->postmeta => ['meta_key'],
            $wpdb->comments => ['comment_approved', 'comment_post_ID']
        ];
        
        $missing_indexes = 0;
        foreach ($important_indexes as $table => $columns) {
            foreach ($columns as $column) {
                $index_exists = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Column_name = '{$column}'");
                if (empty($index_exists)) {
                    $missing_indexes++;
                }
            }
        }
        
        // Reduce score based on missing indexes
        $score -= ($missing_indexes * 5);
        
        // Check for transient buildup
        $transient_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '%_transient_%'
        ");
        
        if ($transient_count > 1000) {
            $score -= 15;
        } elseif ($transient_count > 500) {
            $score -= 10;
        } elseif ($transient_count > 200) {
            $score -= 5;
        }
        
        // Check for revision buildup
        $revision_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'revision'
        ");
        
        if ($revision_count > 1000) {
            $score -= 15;
        } elseif ($revision_count > 500) {
            $score -= 10;
        } elseif ($revision_count > 200) {
            $score -= 5;
        }
        
        // Check autoloaded options size
        $autoload_size = $wpdb->get_var("
            SELECT SUM(LENGTH(option_value)) 
            FROM {$wpdb->options} 
            WHERE autoload = 'yes'
        ");
        
        if ($autoload_size > 3 * 1024 * 1024) { // More than 3MB
            $score -= 20;
        } elseif ($autoload_size > 1 * 1024 * 1024) { // More than 1MB
            $score -= 10;
        }
        
        // Ensure score is between 0 and 100
        $score = max(0, min(100, $score));
        
        return $score;
    }

/**
 * Render AI insights
 */
private function render_ai_insights() {
    global $wpdb;
    
    // Get insights based on database status
    $insights = [];
    
    // Check for tables with overhead
    $tables_with_overhead = $wpdb->get_results("
        SELECT TABLE_NAME, DATA_FREE
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
        AND TABLE_NAME LIKE '{$wpdb->prefix}%'
        AND DATA_FREE > 1024 * 1024
        ORDER BY DATA_FREE DESC
        LIMIT 3
    ");
    
    if (!empty($tables_with_overhead)) {
        $insight = __('Tables with significant overhead detected', 'ai-db-optimizer');
        $details = sprintf(
            __('Optimizing the %1$s table could free up %2$s of space.', 'ai-db-optimizer'),
            '<strong>' . esc_html($tables_with_overhead[0]->TABLE_NAME) . '</strong>',
            '<strong>' . size_format($tables_with_overhead[0]->DATA_FREE) . '</strong>'
        );
        $insights[] = [
            'title' => $insight,
            'details' => $details,
            'type' => 'warning',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>'
        ];
    }
    
    // Check for missing important indexes
    $missing_index_tables = [];
    
    $index_check = $wpdb->get_var("SHOW INDEX FROM {$wpdb->posts} WHERE Column_name = 'post_type'");
    if (empty($index_check)) {
        $missing_index_tables[] = $wpdb->posts;
    }
    
    $index_check = $wpdb->get_var("SHOW INDEX FROM {$wpdb->postmeta} WHERE Column_name = 'meta_key'");
    if (empty($index_check)) {
        $missing_index_tables[] = $wpdb->postmeta;
    }
    
    if (!empty($missing_index_tables)) {
        $insight = __('Missing important database indexes', 'ai-db-optimizer');
        $details = sprintf(
            __('Adding indexes to %s could improve query performance by up to 30%%.', 'ai-db-optimizer'),
            '<strong>' . implode(', ', $missing_index_tables) . '</strong>'
        );
        $insights[] = [
            'title' => $insight,
            'details' => $details,
            'type' => 'error',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>'
        ];
    }
    
    // Check for transient buildup
    $transient_count = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->options} 
        WHERE option_name LIKE '%_transient_%'
    ");
    
    if ($transient_count > 200) {
        $insight = __('High number of transient options', 'ai-db-optimizer');
        $details = sprintf(
            __('Found %s transient options in your database. Cleaning expired transients could improve performance.', 'ai-db-optimizer'),
            '<strong>' . number_format($transient_count) . '</strong>'
        );
        $insights[] = [
            'title' => $insight,
            'details' => $details,
            'type' => 'warning',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>'
        ];
    }
    
    // Check for revision buildup
    $revision_count = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$wpdb->posts} 
        WHERE post_type = 'revision'
    ");
    
    if ($revision_count > 200) {
        $insight = __('High number of post revisions', 'ai-db-optimizer');
        $details = sprintf(
            __('Your database contains %s post revisions. Consider limiting or removing old revisions.', 'ai-db-optimizer'),
            '<strong>' . number_format($revision_count) . '</strong>'
        );
        $insights[] = [
            'title' => $insight,
            'details' => $details,
            'type' => 'warning',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>'
        ];
    }
    
    // Check autoloaded options size
    $autoload_size = $wpdb->get_var("
        SELECT SUM(LENGTH(option_value)) 
        FROM {$wpdb->options} 
        WHERE autoload = 'yes'
    ");
    
    if ($autoload_size > 1 * 1024 * 1024) { // More than 1MB
        $insight = __('Large autoloaded options detected', 'ai-db-optimizer');
        $details = sprintf(
            __('Your site loads %s of autoloaded options on every page. This can slow down your site.', 'ai-db-optimizer'),
            '<strong>' . size_format($autoload_size) . '</strong>'
        );
        $insights[] = [
            'title' => $insight,
            'details' => $details,
            'type' => 'error',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>'
        ];
    }
    
    // Add generic insight if none found
    if (empty($insights)) {
        $insights[] = [
            'title' => __('No significant issues detected', 'ai-db-optimizer'),
            'details' => __('Your database appears to be in good health. Regular maintenance is still recommended.', 'ai-db-optimizer'),
            'type' => 'success',
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'
        ];
    }
    
    // Display insights
    echo '<ul class="ai-db-insights-list">';
    foreach ($insights as $insight) {
        echo '<li class="ai-db-insight-item ai-db-insight-' . esc_attr($insight['type']) . '">';
        echo '<div class="ai-db-insight-icon">' . $insight['icon'] . '</div>';
        echo '<div class="ai-db-insight-content">';
        echo '<h3>' . $insight['title'] . '</h3>';
        echo '<p>' . $insight['details'] . '</p>';
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';
    
    // Add CSS for the enhanced insights styling
    echo '
    <style>
        .ai-db-insights-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .ai-db-insight-item {
            display: flex;
            margin-bottom: 16px;
            padding: 16px;
            border-radius: 6px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .ai-db-insight-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .ai-db-insight-error {
            background-color: #FEEFEF;
            border-left: 4px solid #FF5252;
        }
        
        .ai-db-insight-warning {
            background-color: #FFF8E1;
            border-left: 4px solid #FFB300;
        }
        
        .ai-db-insight-success {
            background-color: #E8F5E9;
            border-left: 4px solid #4CAF50;
        }
        
        .ai-db-insight-icon {
            flex: 0 0 40px;
            margin-right: 16px;
            display: flex;
            align-items: flex-start;
        }
        
        .ai-db-insight-error .ai-db-insight-icon svg {
            color: #FF5252;
        }
        
        .ai-db-insight-warning .ai-db-insight-icon svg {
            color: #FFB300;
        }
        
        .ai-db-insight-success .ai-db-insight-icon svg {
            color: #4CAF50;
        }
        
        .ai-db-insight-content {
            flex: 1;
        }
        ..ai-db-insight-content
        {
        paddin:0px;
        }
        
        .ai-db-insight-content h3 {
            margin-top: 0;
            margin-bottom: 8px;
            font-size: 16px;
            font-weight: 600;
        }
        
        .ai-db-insight-content p {
            margin: 0;
            color: #555;
        }
    </style>
    ';
}



    /**
     * Format analysis results for display
     */
    private function format_analysis_results($analysis) {
        ob_start();
        ?>
        <div class="ai-db-analysis-results">
            <h3><?php esc_html_e('Analysis Results', 'ai-db-optimizer'); ?></h3>
            
            <?php
            $issue_count = 0;
            
            foreach ($analysis as $table => $table_analysis) {
                if (is_array($table_analysis) && isset($table_analysis['issues'])) {
                    $issue_count += count($table_analysis['issues']);
                }
            }
            
            if (isset($analysis['ai_recommendations'])) {
                $issue_count += count($analysis['ai_recommendations']);
            }
            ?>
            
            <div class="ai-db-issue-count <?php echo $issue_count > 0 ? 'has-issues' : 'no-issues'; ?>">
                <?php printf(_n('%d issue found', '%d issues found', $issue_count, 'ai-db-optimizer'), $issue_count); ?>
            </div>
            
            <?php if ($issue_count > 0): ?>
                <div class="ai-db-recommendations">
                    <h4><?php esc_html_e('AI Recommendations', 'ai-db-optimizer'); ?></h4>
                    
                    <?php if (!empty($analysis['ai_recommendations'])): ?>
                        <ul>
                            <?php foreach ($analysis['ai_recommendations'] as $recommendation): ?>
                                <li>
                                    <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $recommendation['type']))); ?>:</strong>
                                    <?php echo esc_html($recommendation['description']); ?>
                                    <span class="ai-db-priority <?php echo esc_attr($recommendation['priority']); ?>">
                                        <?php echo esc_html(ucfirst($recommendation['priority'])); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><?php esc_html_e('No specific AI recommendations at this time.', 'ai-db-optimizer'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="ai-db-table-issues">
                    <h4><?php esc_html_e('Table Optimization Opportunities', 'ai-db-optimizer'); ?></h4>
                    
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Table', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Size', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Overhead', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Issues', 'ai-db-optimizer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($analysis as $table => $table_analysis):
                                if (!is_array($table_analysis) || !isset($table_analysis['data_size'])) {
                                    continue;
                                }
                                
                                // Skip tables with no issues
                                if (empty($table_analysis['issues'])) {
                                    continue;
                                }
                            ?>
                                <tr>
                                    <td data-title="<?php esc_html_e('Table', 'ai-db-optimizer'); ?>">
                                        <?php echo esc_html($table); ?>
                                    </td>
                                    <td data-title="<?php esc_html_e('Size', 'ai-db-optimizer'); ?>">
                                        <?php echo size_format($table_analysis['data_size'] + $table_analysis['index_size']); ?>
                                    </td>
                                    <td data-title="<?php esc_html_e('Overhead', 'ai-db-optimizer'); ?>">
                                        <?php echo size_format($table_analysis['overhead']); ?>
                                    </td>
                                    <td data-title="<?php esc_html_e('Issues', 'ai-db-optimizer'); ?>">
                                        <?php
                                        if (!empty($table_analysis['suggestions'])) {
                                            echo '<ul class="ai-db-issue-list">';
                                            foreach ($table_analysis['suggestions'] as $suggestion) {
                                                echo '<li>' . esc_html($suggestion['description']) . '</li>';
                                            }
                                            echo '</ul>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="ai-db-optimizer-actions">
                    <button id="ai-db-optimize" class="button button-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                            <path d="M19.89 10.105a8.696 8.696 0 0 0-.789-1.456l-1.658 1.119a6.606 6.606 0 0 1 .987 2.345 6.659 6.659 0 0 1 0 2.648 6.495 6.495 0 0 1-.384 1.231 6.404 6.404 0 0 1-.603 1.112 6.654 6.654 0 0 1-1.776 1.775 6.606 6.606 0 0 1-2.343.987 6.734 6.734 0 0 1-2.646 0 6.55 6.55 0 0 1-3.317-1.788 6.605 6.605 0 0 1-1.408-2.088 6.613 6.613 0 0 1-.382-1.23 6.627 6.627 0 0 1 .382-3.877A6.551 6.551 0 0 1 7.36 8.797 6.628 6.628 0 0 1 9.446 7.39c.395-.167.81-.296 1.23-.382.107-.022.216-.032.324-.049V10l5-4-5-4v2.938a8.805 8.805 0 0 0-.725.111 8.512 8.512 0 0 0-3.063 1.29A8.566 8.566 0 0 0 4.11 16.77a8.535 8.535 0 0 0 1.835 2.724 8.614 8.614 0 0 0 2.721 1.833 8.55 8.55 0 0 0 5.061.499 8.576 8.576 0 0 0 6.162-5.056c.22-.52.389-1.061.5-1.608a8.643 8.643 0 0 0 0-3.45 8.684 8.684 0 0 0-.499-1.607z"/>
                        </svg>
                        <?php esc_html_e('Optimize Now', 'ai-db-optimizer'); ?>
                    </button>
                </div>
                
            <?php else: ?>
                <div class="ai-db-optimization-summary">
                    <p><?php esc_html_e('Your database appears to be in good shape! No significant issues were found.', 'ai-db-optimizer'); ?></p>
                    <p><?php esc_html_e('Regular maintenance is still recommended to keep your database running optimally.', 'ai-db-optimizer'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Format optimization results for display
     */
    private function format_optimization_results($results) {
        ob_start();
        ?>
        <div class="ai-db-optimization-results">
            <h3><?php esc_html_e('Optimization Results', 'ai-db-optimizer'); ?></h3>
            
            <div class="ai-db-optimization-summary">
                <p>
                    <?php printf(
                        __('Database optimization completed with estimated %s%% performance improvement.', 'ai-db-optimizer'),
                        '<strong>' . number_format($results['performance_impact'], 2) . '</strong>'
                    ); ?>
                </p>
                
                <div class="ai-db-optimization-metrics">
                    <div class="ai-db-optimization-metric">
                        <div class="metric-value"><?php echo count($results['tables_affected']); ?></div>
                        <div class="metric-label"><?php esc_html_e('Tables Optimized', 'ai-db-optimizer'); ?></div>
                    </div>
                    
                    <div class="ai-db-optimization-metric">
                        <div class="metric-value"><?php echo count($results['optimization_actions']); ?></div>
                        <div class="metric-label"><?php esc_html_e('Actions Performed', 'ai-db-optimizer'); ?></div>
                    </div>
                    
                    <div class="ai-db-optimization-metric">
                        <div class="metric-value"><?php echo number_format($results['performance_impact'], 1); ?>%</div>
                        <div class="metric-label"><?php esc_html_e('Performance Gain', 'ai-db-optimizer'); ?></div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($results['optimization_actions'])): ?>
                <div class="ai-db-actions-performed">
                    <h4><?php esc_html_e('Actions Performed', 'ai-db-optimizer'); ?></h4>
                    <ul>
                        <?php foreach ($results['optimization_actions'] as $action): ?>
                            <li>
                                <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $action['type']))); ?>:</strong>
                                <?php echo esc_html($action['description']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($results['recommendations'])): ?>
                <div class="ai-db-future-recommendations">
                    <h4><?php esc_html_e('Future Recommendations', 'ai-db-optimizer'); ?></h4>
                    <ul>
                        <?php foreach ($results['recommendations'] as $recommendation): ?>
                            <li><?php echo esc_html($recommendation); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render database status
     */
    private function render_database_status() {
        global $wpdb;
        
        // Get database size
        $db_size = $wpdb->get_row("SELECT SUM(data_length + index_length) as size FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'");
        
        // Get table count
        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%'");
        $table_count = count($tables);
        
        // Get last optimization time
        $settings = get_option('fulgid_ai_db_optimizer_settings');
        $last_optimization = isset($settings['last_optimization']) ? $settings['last_optimization'] : __('Never', 'ai-db-optimizer');
        
        ?>
        <ul class="ai-db-status-list">
            <li>
                <span class="ai-db-status-label"><?php esc_html_e('Database Size:', 'ai-db-optimizer'); ?></span>
                <span class="ai-db-status-value"><?php echo size_format($db_size->size); ?></span>
            </li>
            <li>
                <span class="ai-db-status-label"><?php esc_html_e('Tables:', 'ai-db-optimizer'); ?></span>
                <span class="ai-db-status-value"><?php echo $table_count; ?></span>
            </li>
            <li>
                <span class="ai-db-status-label"><?php esc_html_e('Last Optimization:', 'ai-db-optimizer'); ?></span>
                <span class="ai-db-status-value"><?php echo $last_optimization; ?></span>
            </li>
        </ul>
        <?php
    }
    
    /**
     * Render optimization history
     */
    private function render_optimization_history() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_db_optimization_history';
        $history = $wpdb->get_results("SELECT * FROM $table_name ORDER BY optimization_time DESC LIMIT 5");
        
        if (empty($history)) {
            echo '<p>' . __('No optimization history available.', 'ai-db-optimizer') . '</p>';
            return;
        }
        
        ?>
        <table class="">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date', 'ai-db-optimizer'); ?></th>
                    <th><?php esc_html_e('Type', 'ai-db-optimizer'); ?></th>
                    <th><?php esc_html_e('Tables', 'ai-db-optimizer'); ?></th>
                    <th><?php esc_html_e('Impact', 'ai-db-optimizer'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $entry): ?>
                    <tr>
                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->optimization_time)); ?></td>
                        <td><?php echo ucfirst($entry->optimization_type); ?></td>
                        <td><?php echo count(json_decode($entry->tables_affected)); ?></td>
                        <td><?php echo round($entry->performance_impact, 2) . '%'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * AJAX handler for database analysis
     */
    public function ajax_analyze_database() {
        check_ajax_referer('fulgid_ai_db_optimizer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'ai-db-optimizer')]);
        }
        
        $analysis = $this->analyzer->analyze_database();
        
        // Format the results for display
        $formatted_results = $this->format_analysis_results($analysis);
        
        wp_send_json_success([
            'analysis' => $analysis,
            'html' => $formatted_results,
        ]);
    }
    
    /**
     * AJAX handler for database optimization
     */
    public function ajax_optimize_database() {
        check_ajax_referer('fulgid_ai_db_optimizer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'ai-db-optimizer')]);
        }
        
        $analysis = isset($_POST['analysis']) ? $_POST['analysis'] : null;
        
        if (!$analysis) {
            wp_send_json_error(['message' => __('No analysis data provided.', 'ai-db-optimizer')]);
        }
        
        $analysis = json_decode(stripslashes($analysis), true);
        $settings = get_option('fulgid_ai_db_optimizer_settings');
        $level = isset($settings['optimization_level']) ? $settings['optimization_level'] : 'medium';
        
        $results = $this->optimization_engine->optimize_database($analysis, $level);
        
        // Format the results for display
        $formatted_results = $this->format_optimization_results($results);
        
        // Update last optimization time
        $settings['last_optimization'] = current_time('mysql');
        update_option('fulgid_ai_db_optimizer_settings', $settings);
        
        wp_send_json_success([
            'results' => $results,
            'html' => $formatted_results,
        ]);
    }
    

    /**
 * AJAX handler for collecting database performance data
 */
public function ajax_collect_performance_data() {
    check_ajax_referer('fulgid_ai_db_optimizer_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'ai-db-optimizer')]);
    }
    
    global $wpdb;
    
    // Initialize the performance data collection
    $performance_data = [];
    
    // 1. Collect slow query information
    $performance_data['slow_queries'] = $this->collect_slow_queries();
    
    // 2. Collect table statistics
    $performance_data['table_stats'] = $this->collect_table_statistics();
    
    // 3. Collect database server information
    $performance_data['server_info'] = $this->collect_server_information();
    
    // 4. Collect query cache information
    $performance_data['query_cache'] = $this->collect_query_cache_information();
    
    // 5. Collect recent error logs if available
    $performance_data['error_logs'] = $this->collect_error_logs();
    
    // Store the collected data
    update_option('fulgid_ai_db_optimizer_performance_data', [
        'collected_at' => current_time('mysql'),
        'data' => $performance_data
    ]);
    
    // Format the results for display
    $formatted_results = $this->format_performance_data($performance_data);
    
    wp_send_json_success([
        'performance_data' => $performance_data,
        'html' => $formatted_results,
    ]);
}

/**
 * Collect information about slow queries
 */
private function collect_slow_queries() {
    global $wpdb;
    
    $result = [
        'available' => false,
        'queries' => []
    ];
    
    // Try to get slow query log if enabled on the server
    // This requires appropriate MySQL privileges
    try {
        // Check if slow query log is enabled
        $log_status = $wpdb->get_row("SHOW VARIABLES LIKE 'slow_query_log'");
        
        if ($log_status && $log_status->Value == 'ON') {
            $result['available'] = true;
            
            // Get the location of the slow query log
            $log_file = $wpdb->get_row("SHOW VARIABLES LIKE 'slow_query_log_file'");
            $result['log_file'] = $log_file->Value;
            
            // Get slow query time threshold
            $log_threshold = $wpdb->get_row("SHOW VARIABLES LIKE 'long_query_time'");
            $result['threshold'] = $log_threshold->Value;
            
            // Try to get the most recent slow queries
            // This is an approximation as we can't directly read the log file via PHP in most cases
            $slow_queries = $wpdb->get_results("
                SELECT * FROM information_schema.PROCESSLIST 
                WHERE TIME > 2 
                ORDER BY TIME DESC 
                LIMIT 10
            ");
            
            if ($slow_queries) {
                foreach ($slow_queries as $query) {
                    $result['queries'][] = [
                        'time' => $query->TIME,
                        'db' => $query->DB,
                        'query' => $query->INFO,
                        'state' => $query->STATE
                    ];
                }
            }
        }
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    // Alternative method if slow query log not accessible
    if (!$result['available'] || empty($result['queries'])) {
        // Collect statistics about potentially slow queries
        // using WordPress query monitor data if available
        if (class_exists('QM_Collectors')) {
            $result['using_query_monitor'] = true;
            
            // Get data from Query Monitor plugin if it's active
            $db_queries = apply_filters('qm/collect/db_queries', null);
            if ($db_queries && !empty($db_queries->data['slow_queries'])) {
                foreach ($db_queries->data['slow_queries'] as $query) {
                    $result['queries'][] = [
                        'time' => $query['ltime'],
                        'query' => $query['sql'],
                        'caller' => $query['caller'],
                        'component' => $query['component']
                    ];
                }
                $result['available'] = true;
            }
        }
    }
    
    return $result;
}

/**
 * Collect statistics about database tables
 */
private function collect_table_statistics() {
    global $wpdb;
    
    $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");
    $statistics = [];
    
    foreach ($tables as $table) {
        // Get basic table information
        $status = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table'");
        
        // Get query statistics if available
        $query_stats = null;
        try {
            // This requires MySQL performance_schema to be enabled
            $query_stats = $wpdb->get_row("
                SELECT COUNT(*) as query_count, SUM(sum_timer_wait)/1000000000000 as total_time
                FROM performance_schema.table_io_waits_summary_by_table 
                WHERE OBJECT_SCHEMA = DATABASE() AND OBJECT_NAME = '$table'
            ");
        } catch (Exception $e) {
            // Performance schema not available
        }
        
        $statistics[$table] = [
            'rows' => $status->Rows,
            'data_size' => $status->Data_length,
            'index_size' => $status->Index_length,
            'overhead' => $status->Data_free,
            'engine' => $status->Engine,
            'collation' => $status->Collation,
            'auto_increment' => $status->Auto_increment,
            'creation_time' => $status->Create_time,
            'update_time' => $status->Update_time,
            'query_stats' => $query_stats ? [
                'query_count' => $query_stats->query_count,
                'total_time' => $query_stats->total_time
            ] : null
        ];
    }
    
    return $statistics;
}

/**
 * Collect server information
 */
/**
 * Collect server information
 */
private function collect_server_information() {
    global $wpdb;
    
    $server_info = [
        'mysql_version' => $wpdb->db_version(),
        'php_version' => phpversion(),
        'wordpress_version' => get_bloginfo('version'),
        'variables' => [],
        'status' => []
    ];
    
    // Collect important MySQL variables
    $important_vars = [
        'innodb_buffer_pool_size',
        'max_connections',
        'query_cache_size',
        'table_open_cache',
        'thread_cache_size',
        'innodb_flush_log_at_trx_commit',
        'max_allowed_packet',
        'join_buffer_size',
        'sort_buffer_size',
        'read_buffer_size',
        'read_rnd_buffer_size'
    ];
    
    $vars_string = implode("','", $important_vars);
    $variables = $wpdb->get_results("SHOW VARIABLES WHERE Variable_name IN ('$vars_string')");
    
    foreach ($variables as $var) {
        $server_info['variables'][$var->Variable_name] = $var->Value;
    }
    
    // Collect important MySQL status values
    $important_status = [
        'Aborted_clients',
        'Aborted_connects',
        'Connections',
        'Created_tmp_disk_tables',
        'Created_tmp_tables',
        'Innodb_buffer_pool_reads',
        'Innodb_buffer_pool_read_requests',
        'Key_reads',
        'Key_read_requests',
        'Max_used_connections',
        'Opened_tables',
        'Select_full_join',
        'Slow_queries',
        'Table_locks_waited',
        'Threads_connected',
        'Threads_running'
    ];
    
    $status_string = implode("','", $important_status);
    $status = $wpdb->get_results("SHOW STATUS WHERE Variable_name IN ('$status_string')");
    
    foreach ($status as $stat) {
        $server_info['status'][$stat->Variable_name] = $stat->Value;
    }
    
    return $server_info;
}
/**
 * Collect query cache information
 */
/**
 * Collect query cache information
 */
private function collect_query_cache_information() {
    global $wpdb;
    
    $cache_info = [
        'enabled' => false,
        'size' => 0,
        'usage' => []
    ];
    
    try {
        // Check if query cache is enabled
        $query_cache_type = $wpdb->get_row("SHOW VARIABLES LIKE 'query_cache_type'");
        $cache_info['enabled'] = ($query_cache_type && $query_cache_type->Value != 'OFF');
        
        if ($cache_info['enabled']) {
            // Get query cache size
            $cache_size = $wpdb->get_row("SHOW VARIABLES LIKE 'query_cache_size'");
            $cache_info['size'] = $cache_size->Value;
            
            // Get query cache usage statistics
            $cache_stats = [
                'query_cache_free_memory',
                'query_cache_hits',
                'query_cache_inserts',
                'query_cache_lowmem_prunes',
                'query_cache_queries_in_cache',
                'query_cache_size'
            ];
            
            $stats_string = implode("','", $cache_stats);
            $status = $wpdb->get_results("SHOW STATUS WHERE Variable_name IN ('$stats_string')");
            
            foreach ($status as $stat) {
                $cache_info['usage'][$stat->Variable_name] = $stat->Value;
            }
            
            // Calculate cache hit ratio if possible
            if (isset($cache_info['usage']['query_cache_hits']) && isset($cache_info['usage']['query_cache_inserts'])) {
                $hits = intval($cache_info['usage']['query_cache_hits']);
                $inserts = intval($cache_info['usage']['query_cache_inserts']);
                
                if ($hits + $inserts > 0) {
                    $cache_info['hit_ratio'] = round(($hits / ($hits + $inserts)) * 100, 2);
                }
            }
        }
    } catch (Exception $e) {
        $cache_info['error'] = $e->getMessage();
    }
    
    return $cache_info;
}

/**
 * Collect recent error logs if available
 */
private function collect_error_logs() {
    global $wpdb;
    
    $error_logs = [
        'available' => false,
        'entries' => []
    ];
    
    try {
        // Try to get MySQL error log location
        $error_log = $wpdb->get_row("SHOW VARIABLES LIKE 'log_error'");
        
        if ($error_log && !empty($error_log->Value)) {
            $error_logs['available'] = true;
            $error_logs['log_file'] = $error_log->Value;
            
            // Note: Reading the actual log file often requires server-level access
            // and may not be possible via PHP in shared hosting environments
        }
        
        // As an alternative, check for database errors in WordPress
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $error_logs['wp_debug_enabled'] = true;
            
            // If using Query Monitor, we may be able to get recent DB errors
            if (class_exists('QM_Collectors')) {
                $db_errors = apply_filters('qm/collect/db_errors', null);
                if ($db_errors && !empty($db_errors->data)) {
                    foreach ($db_errors->data as $error) {
                        $error_logs['entries'][] = [
                            'query' => $error['sql'],
                            'error' => $error['error'],
                            'caller' => $error['caller']
                        ];
                    }
                    $error_logs['available'] = true;
                }
            }
        }
    } catch (Exception $e) {
        $error_logs['error'] = $e->getMessage();
    }
    
    return $error_logs;
}

/**
 * Format performance data for display
 */
private function format_performance_data($performance_data) {
    ob_start();
    ?>
    <div class="ai-db-performance-results">
        <h3><?php esc_html_e('Database Performance Data', 'ai-db-optimizer'); ?></h3>
        
        <div class="ai-db-performance-summary">
            <p>
                <?php esc_html_e('Performance data collected successfully. This information will be used to provide more accurate AI-based optimization recommendations.', 'ai-db-optimizer'); ?>
            </p>
        </div>
        
        <div class="ai-db-performance-tabs">
            <ul class="ai-db-tabs-nav">
                <li class="active"><a href="#server-info"><?php esc_html_e('Server Info', 'ai-db-optimizer'); ?></a></li>
                <li><a href="#table-stats"><?php esc_html_e('Table Statistics', 'ai-db-optimizer'); ?></a></li>
                <li><a href="#query-performance"><?php esc_html_e('Query Performance', 'ai-db-optimizer'); ?></a></li>
                <li><a href="#cache-stats"><?php esc_html_e('Cache Information', 'ai-db-optimizer'); ?></a></li>
                <?php if (!empty($performance_data['error_logs']['entries'])): ?>
                <li><a href="#error-logs"><?php esc_html_e('Error Logs', 'ai-db-optimizer'); ?></a></li>
                <?php endif; ?>
            </ul>
            
            <div class="ai-db-tabs-content">
                <!-- Server Information -->
                <div id="server-info" class="ai-db-tab-pane active">
                    <h4><?php esc_html_e('Database Server Information', 'ai-db-optimizer'); ?></h4>
                    
                    <table class="">
                        <tr>
                            <th><?php esc_html_e('MySQL Version', 'ai-db-optimizer'); ?></th>
                            <td><?php echo esc_html($performance_data['server_info']['mysql_version']); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('PHP Version', 'ai-db-optimizer'); ?></th>
                            <td><?php echo esc_html($performance_data['server_info']['php_version']); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('WordPress Version', 'ai-db-optimizer'); ?></th>
                            <td><?php echo esc_html($performance_data['server_info']['wordpress_version']); ?></td>
                        </tr>
                    </table>
                    
                    <h4><?php esc_html_e('MySQL Variables', 'ai-db-optimizer'); ?></h4>
                    <table class="">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Variable', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Value', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Recommendation', 'ai-db-optimizer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performance_data['server_info']['variables'] as $var => $value): ?>
                                <tr>
                                    <td><?php echo esc_html($var); ?></td>
                                    <td><?php echo esc_html($value); ?></td>
                                    <td>
                                        <?php 
                                        // Generate recommendations based on common best practices
                                        switch ($var) {
                                            case 'innodb_buffer_pool_size':
                                                $size_mb = intval($value) / (1024 * 1024);
                                                if ($size_mb < 128) {
                                                    esc_html_e('Consider increasing to at least 128MB for better performance', 'ai-db-optimizer');
                                                }
                                                break;
                                                
                                            case 'query_cache_size':
                                                $size_mb = intval($value) / (1024 * 1024);
                                                if ($performance_data['query_cache']['enabled'] && $size_mb < 32) {
                                                    esc_html_e('Consider increasing to at least 32MB for better caching', 'ai-db-optimizer');
                                                } elseif ($performance_data['query_cache']['enabled'] && $size_mb > 256) {
                                                    esc_html_e('Large query cache may cause overhead, consider reducing', 'ai-db-optimizer');
                                                }
                                                break;
                                                
                                            case 'table_open_cache':
                                                if (intval($value) < 400) {
                                                    esc_html_e('Consider increasing for sites with many tables', 'ai-db-optimizer');
                                                }
                                                break;
                                                
                                            default:
                                                echo '—';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Table Statistics -->
                <div id="table-stats" class="ai-db-tab-pane">
                    <h4><?php esc_html_e('Database Table Statistics', 'ai-db-optimizer'); ?></h4>
                    
                    <table class="">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Table', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Engine', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Rows', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Size', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Overhead', 'ai-db-optimizer'); ?></th>
                                <?php if (!empty(current($performance_data['table_stats'])['query_stats'])): ?>
                                <th><?php esc_html_e('Queries', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Query Time', 'ai-db-optimizer'); ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performance_data['table_stats'] as $table => $stats): ?>
                                <tr>
                                    <td><?php echo esc_html($table); ?></td>
                                    <td><?php echo esc_html($stats['engine']); ?></td>
                                    <td><?php echo number_format($stats['rows']); ?></td>
                                    <td><?php echo size_format($stats['data_size'] + $stats['index_size']); ?></td>
                                    <td>
                                        <?php 
                                        echo size_format($stats['overhead']);
                                        if ($stats['overhead'] > 1024 * 1024) {
                                            echo ' <span class="ai-db-warning">!</span>';
                                        }
                                        ?>
                                    </td>
                                    <?php if (!empty($stats['query_stats'])): ?>
                                    <td><?php echo number_format($stats['query_stats']['query_count']); ?></td>
                                    <td><?php 
                                        // Translators: %s is the query execution time in seconds
                                        echo sprintf(
                                            __('%ss', 'ai-db-optimizer'),
                                            round($stats['query_stats']['total_time'], 2)
                                        ); 
                                        ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Query Performance -->
                <div id="query-performance" class="ai-db-tab-pane">
                    <h4><?php esc_html_e('Slow Query Information', 'ai-db-optimizer'); ?></h4>
                    
                    <?php if ($performance_data['slow_queries']['available']): ?>
                        <p>
                            <?php 
                            if (isset($performance_data['slow_queries']['threshold'])) {
                                // Translators: %s is the slow query threshold time in seconds
                                printf(
                                    __('Slow query threshold: %s seconds', 'ai-db-optimizer'),
                                    $performance_data['slow_queries']['threshold']
                                );
                            }
                            ?>
                        </p>
                        
                        <?php if (!empty($performance_data['slow_queries']['queries'])): ?>
                            <table class="">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Time (s)', 'ai-db-optimizer'); ?></th>
                                        <th><?php esc_html_e('Query', 'ai-db-optimizer'); ?></th>
                                        <?php if (isset($performance_data['slow_queries']['queries'][0]['state'])): ?>
                                        <th><?php esc_html_e('State', 'ai-db-optimizer'); ?></th>
                                        <?php endif; ?>
                                        <?php if (isset($performance_data['slow_queries']['queries'][0]['caller'])): ?>
                                        <th><?php esc_html_e('Caller', 'ai-db-optimizer'); ?></th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($performance_data['slow_queries']['queries'] as $query): ?>
                                        <tr>
                                            <td><?php echo esc_html($query['time']); ?></td>
                                            <td>
                                                <div class="ai-db-query-text">
                                                    <?php echo esc_html($query['query']); ?>
                                                </div>
                                            </td>
                                            <?php if (isset($query['state'])): ?>
                                            <td><?php echo esc_html($query['state']); ?></td>
                                            <?php endif; ?>
                                            <?php if (isset($query['caller'])): ?>
                                            <td><?php echo esc_html($query['caller']); ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p><?php esc_html_e('No slow queries detected in the current session.', 'ai-db-optimizer'); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p><?php esc_html_e('Slow query logging is not enabled on this server or data is not accessible.', 'ai-db-optimizer'); ?></p>
                        <p><?php esc_html_e('To enable slow query logging, you may need to modify your MySQL configuration.', 'ai-db-optimizer'); ?></p>
                    <?php endif; ?>
                    
                    <h4><?php esc_html_e('MySQL Status', 'ai-db-optimizer'); ?></h4>
                    <table class="">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Status', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Value', 'ai-db-optimizer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performance_data['server_info']['status'] as $stat => $value): ?>
                                <tr>
                                    <td><?php echo esc_html($stat); ?></td>
                                    <td><?php echo esc_html($value); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Cache Information -->
                <div id="cache-stats" class="ai-db-tab-pane">
                    <h4><?php esc_html_e('Query Cache Information', 'ai-db-optimizer'); ?></h4>
                    
                    <?php if ($performance_data['query_cache']['enabled']): ?>
                        <p>
                            <?php 
                            // Translators: %s is the query cache size formatted in KB/MB/GB
                            printf(
                                __('Query cache is enabled with size: %s', 'ai-db-optimizer'),
                                size_format(intval($performance_data['query_cache']['size']))
                            ); 
                            ?>
                        </p>
                        
                        <?php if (isset($performance_data['query_cache']['hit_ratio'])): ?>
                            <p>
                                <?php 
                                // Translators: %s is the cache hit ratio percentage
                                printf(
                                    __('Cache hit ratio: %s%%', 'ai-db-optimizer'),
                                    $performance_data['query_cache']['hit_ratio']
                                ); 
                                ?>
                                
                                <?php if ($performance_data['query_cache']['hit_ratio'] < 20): ?>
                                    <span class="ai-db-warning">
                                        <?php esc_html_e('Low hit ratio indicates the query cache may not be effective', 'ai-db-optimizer'); ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        
                        <table class="">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Metric', 'ai-db-optimizer'); ?></th>
                                    <th><?php esc_html_e('Value', 'ai-db-optimizer'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performance_data['query_cache']['usage'] as $metric => $value): ?>
                                    <tr>
                                        <td><?php echo esc_html($metric); ?></td>
                                        <td><?php echo esc_html($value); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (isset($performance_data['query_cache']['usage']['query_cache_lowmem_prunes']) && 
                                intval($performance_data['query_cache']['usage']['query_cache_lowmem_prunes']) > 100): ?>
                            <div class="ai-db-alert">
                                <?php esc_html_e('High number of cache prunes indicates the query cache size is too small for your workload.', 'ai-db-optimizer'); ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p><?php esc_html_e('Query cache is disabled on this server.', 'ai-db-optimizer'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Error Logs -->
                <?php if (!empty($performance_data['error_logs']['entries'])): ?>
                <div id="error-logs" class="ai-db-tab-pane">
                    <h4><?php esc_html_e('Database Error Logs', 'ai-db-optimizer'); ?></h4>
                    
                    <table class="">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Query', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Error', 'ai-db-optimizer'); ?></th>
                                <th><?php esc_html_e('Caller', 'ai-db-optimizer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performance_data['error_logs']['entries'] as $error): ?>
                                <tr>
                                    <td>
                                        <div class="ai-db-query-text">
                                            <?php echo esc_html($error['query']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($error['error']); ?></td>
                                    <td><?php echo esc_html($error['caller']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


/**
 * AJAX handler for getting performance data
 */
public function ajax_get_performance_data() {
    check_ajax_referer('fulgid_ai_db_optimizer_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'ai-db-optimizer')]);
    }
    
    // Get performance data from database or generate sample data if not available
    $saved_data = get_option('fulgid_ai_db_optimizer_performance_history', []);
    
    if (empty($saved_data)) {
        // Generate sample data if no history exists
        $dates = [];
        $query_times = [];
        $db_sizes = [];
        
        // Generate data for the last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = date('M d', strtotime("-$i days"));
            $dates[] = $date;
            
            // Random query time between 50-200ms
            $query_times[] = rand(50, 200);
            
            // Random DB size between 10-100MB
            $db_sizes[] = rand(10, 100);
        }
        
        $performance_data = [
            'dates' => $dates,
            'queryTimes' => $query_times,
            'dbSizes' => $db_sizes,
        ];
    } else {
        // Use real saved data
        $performance_data = [
            'dates' => array_column($saved_data, 'date'),
            'queryTimes' => array_column($saved_data, 'query_time'),
            'dbSizes' => array_column($saved_data, 'db_size'),
        ];
    }
    
    wp_send_json_success($performance_data);
}
 

/**
 * Get dynamic data for database composition chart
 * @return array Chart data with labels and values
 */

public function get_db_composition_data() {
    global $wpdb;
    
    // Initialize result arrays
    $labels = [];
    $data = [];
    $colors = [
        '#4527A0', '#673AB7', '#9575CD', '#D1C4E9', 
        '#7E57C2', '#5E35B1', '#B39DDB', '#EDE7F6'
    ];
    
    // Get sizes of key WordPress tables
    $tables_info = [
        'posts' => $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->posts}'"),
        'postmeta' => $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->postmeta}'"),
        'comments' => $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->comments}'"),
        'commentmeta' => $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->commentmeta}'"),
        'options' => $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->options}'"),
        'users' => $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->users}'"),
        'usermeta' => $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->usermeta}'"),
        'terms' => $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->terms}'"),
        'termmeta' => $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->termmeta}'"),
        'term_taxonomy' => $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->term_taxonomy}'"),
        'term_relationships' => $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '{$wpdb->term_relationships}'"),
    ];
    
    // Get total size of all WP tables
    $total_wp_size = $wpdb->get_var("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name LIKE '{$wpdb->prefix}%'");
    
    // Calculate size of "other" tables
    $measured_tables_size = array_sum($tables_info);
    $other_tables_size = $total_wp_size - $measured_tables_size;
    if ($other_tables_size > 0) {
        $tables_info['other'] = $other_tables_size;
    }
    
    // Group some related tables
    $grouped_data = [
        __('Posts', 'ai-db-optimizer') => $tables_info['posts'],
        __('Post Meta', 'ai-db-optimizer') => $tables_info['postmeta'],
        __('Comments', 'ai-db-optimizer') => $tables_info['comments'] + $tables_info['commentmeta'],
        __('Users', 'ai-db-optimizer') => $tables_info['users'] + $tables_info['usermeta'],
        __('Terms', 'ai-db-optimizer') => $tables_info['terms'] + $tables_info['termmeta'] + $tables_info['term_taxonomy'] + $tables_info['term_relationships'],
        __('Options', 'ai-db-optimizer') => $tables_info['options'],
    ];
    
    // Add "Other" category if it exists
    if (isset($tables_info['other'])) {
        $grouped_data[__('Other', 'ai-db-optimizer')] = $tables_info['other'];
    }
    
    // Format the data
    foreach ($grouped_data as $label => $size) {
        $labels[] = $label;
        // Convert to MB with 2 decimal places for chart display
        $data[] = round($size / (1024 * 1024), 2); 
    }
    
    // Add colors (ensure we have enough)
    $bg_colors = array_slice($colors, 0, count($data));
    while (count($bg_colors) < count($data)) {
        $bg_colors[] = '#' . substr(md5(mt_rand()), 0, 6);
    }
    
    return [
        'labels' => $labels,
        'datasets' => [
            [
                'data' => $data,
                'backgroundColor' => $bg_colors,
                'borderWidth' => 0
            ]
        ]
    ];
}

/**
 * Add the chart data to the page
 */
public function enqueue_chart_data() {
    // Only add on our plugin page
    $screen = get_current_screen();
    if ($screen->id !== 'tools_page_ai-db-optimizer') {
        return;
    }
    
    // Get the chart data
    $chart_data = $this->get_db_composition_data();
    
    // Localize the script with the data
    wp_localize_script(
        'ai-db-optimizer-admin', 
        'aiDbOptimizerCharts', 
        ['dbComposition' => $chart_data]
    );
}

/**
 * AJAX handler for getting database composition data
 */
public function ajax_get_composition_data() {
    check_ajax_referer('fulgid_ai_db_optimizer_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'ai-db-optimizer')]);
    }
    
    global $wpdb;
    
    // Get all tables
    $tables = $wpdb->get_results("
        SELECT TABLE_NAME, 
               DATA_LENGTH + INDEX_LENGTH as total_size
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = '" . DB_NAME . "'
        AND TABLE_NAME LIKE '{$wpdb->prefix}%'
        ORDER BY total_size DESC
    ");
    
    // Prepare data for chart
    $labels = [];
    $values = [];
    $total_size = 0;
    $other_size = 0;
    
    // Get total size
    foreach ($tables as $table) {
        $total_size += $table->total_size;
    }
    
    // Get top 5 tables
    $count = 0;
    foreach ($tables as $table) {
        if ($count < 5) {
            $labels[] = $table->TABLE_NAME;
            // Calculate the percentage of total database size
            $values[] = round(($table->total_size / $total_size) * 100, 2);
        } else {
            $other_size += $table->total_size;
        }
        $count++;
    }
    
    // Add "Others" category if there are more than 5 tables
    if ($other_size > 0) {
        $labels[] = 'Others';
        $values[] = round(($other_size / $total_size) * 100, 2);
    }
    
    wp_send_json_success([
        'labels' => $labels,
        'values' => $values
    ]);
}

}