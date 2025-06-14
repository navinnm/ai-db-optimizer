<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Optimization_Engine {
    
    /**
     * Cache group for optimization data
     */
    private $cache_group = 'fulgid_ai_db_optimizer';
    
    /**
     * Cache expiry time (1 hour)
     */
    private $cache_expiry = 3600;
    
    /**
     * Optimize the database based on analysis results
     */
    public function optimize_database($analysis, $level = 'medium') {
        global $wpdb;
        
        $settings = get_option('fulgid_ai_db_optimizer_settings');
        $excluded_tables = isset($settings['tables_to_exclude']) ? $settings['tables_to_exclude'] : [];
        
        $results = [
            'tables_affected' => [],
            'optimization_actions' => [],
            'performance_impact' => 0,
            'recommendations' => [],
        ];
        
        // Determine which optimizations to perform based on level
        $optimizations = $this->get_optimizations_for_level($level);
        
        // Perform the optimizations
        foreach ($analysis as $table => $table_analysis) {
            // Skip non-table entries like 'performance_data', 'ai_recommendations', etc.
            if (!is_array($table_analysis) || !isset($table_analysis['row_count'])) {
                continue;
            }
            
            // Skip excluded tables
            if (in_array($table, $excluded_tables)) {
                continue;
            }
            
            // Validate table name
            if (!$this->is_valid_table_name($table)) {
                continue;
            }
            
            $table_results = $this->optimize_table($table, $table_analysis, $optimizations);
            
            if (!empty($table_results['actions'])) {
                $results['tables_affected'][] = $table;
                $results['optimization_actions'] = array_merge(
                    $results['optimization_actions'], 
                    $table_results['actions']
                );
                $results['performance_impact'] += $table_results['performance_impact'];
            }
        }
        
        // Process any global optimizations
        $this->perform_global_optimizations($analysis, $optimizations, $results);
        
        // Generate AI recommendations for future optimizations
        $results['recommendations'] = $this->generate_future_recommendations($analysis, $results);
        
        // Store optimization history
        $this->store_optimization_history($results, $level);
        
        return $results;
    }
    
    /**
     * Validate table name to prevent SQL injection
     */
    private function is_valid_table_name($table) {
        // Table names should only contain alphanumeric characters and underscores
        return preg_match('/^[a-zA-Z0-9_]+$/', $table) === 1;
    }
    
    /**
     * Get optimizations to perform based on level
     */
    private function get_optimizations_for_level($level) {
        $optimizations = [
            'low' => [
                'optimize_tables' => true,
                'remove_overhead' => true,
            ],
            'medium' => [
                'optimize_tables' => true,
                'remove_overhead' => true,
                'add_basic_indexes' => true,
                'clean_expired_transients' => true,
            ],
            'high' => [
                'optimize_tables' => true,
                'remove_overhead' => true,
                'add_basic_indexes' => true,
                'add_advanced_indexes' => true,
                'clean_expired_transients' => true,
                'clean_post_revisions' => true,
                'clean_auto_drafts' => true,
            ],
        ];
        
        return $optimizations[$level] ?? $optimizations['medium'];
    }
    
    /**
     * Optimize a single table
     */
    private function optimize_table($table, $analysis, $optimizations) {
        global $wpdb;
        
        $results = [
            'actions' => [],
            'performance_impact' => 0,
        ];
        
        // Check for and remove overhead
        if ($optimizations['remove_overhead'] && isset($analysis['overhead']) && $analysis['overhead'] > 0) {
            $cache_key = 'table_optimize_' . md5($table);
            $cached_result = wp_cache_get($cache_key, $this->cache_group);
            
            if (false === $cached_result) {
                // Use backticks for table name after validation
                $result = $wpdb->query("OPTIMIZE TABLE `" . esc_sql($table) . "`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
                wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_expiry);
            } else {
                $result = $cached_result;
            }
            
            if ($result !== false) {
                $results['actions'][] = [
                    'type' => 'optimize_table',
                    'description' => esc_html(
                        sprintf(
                            /* translators: %1$s is the table name, %2$s is the overhead size in megabytes */
                            __('Optimized table %1$s, removed %2$s MB overhead', 'db_ai_optimizer'), 
                            $table, 
                            number_format($analysis['overhead'] / (1024 * 1024), 2)
                        )
                    ),
                ];
                $results['performance_impact'] += 5; // Estimate 5% improvement for removing overhead
            }
        }
        
        // Add missing indexes based on suggestions
        if (isset($analysis['suggestions'])) {
            foreach ($analysis['suggestions'] as $suggestion) {
                if ($suggestion['type'] == 'add_index') {
                    $should_add = false;
                    
                    if ($suggestion['priority'] == 'high' && ($optimizations['add_basic_indexes'] || $optimizations['add_advanced_indexes'])) {
                        $should_add = true;
                    } elseif ($suggestion['priority'] == 'medium' && $optimizations['add_advanced_indexes']) {
                        $should_add = true;
                    }
                    
                    if ($should_add && isset($suggestion['column'])) {
                        $column = $suggestion['column'];
                        
                        // Validate column name
                        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                            continue;
                        }
                        
                        $index_name = 'ai_opt_' . substr(md5($table . $column), 0, 10);
                        
                        // Check if index already exists
                        $cache_key = 'table_indexes_' . md5($table . $column);
                        $index_exists = wp_cache_get($cache_key, $this->cache_group);
                        
                        if (false === $index_exists) {
                            $index_exists = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                "SHOW INDEX FROM `" . esc_sql($table) . "` WHERE Column_name = %s",
                                $column
                            ));
                            wp_cache_set($cache_key, $index_exists, $this->cache_group, $this->cache_expiry);
                        }
                        
                        if (empty($index_exists)) {
                            // Try to add the index
                            $result = $wpdb->query("ALTER TABLE `" . esc_sql($table) . "` ADD INDEX `" . esc_sql($index_name) . "` (`" . esc_sql($column) . "`)"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
                            
                            if ($result !== false) {
                                $results['actions'][] = [
                                    'type' => 'add_index',
                                    'description' => esc_html(
                                        sprintf(
                                            /* translators: %1$s is the table name, %2$s is the column name */
                                            __('Added index on %1$s.%2$s for better performance', 'db_ai_optimizer'), 
                                            $table, 
                                            $column
                                        )
                                    ),
                                ];
                                $results['performance_impact'] += 10; // Estimate 10% improvement for each new index
                            }
                        }
                    }
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Perform global optimizations across the database
     */
    private function perform_global_optimizations($analysis, $optimizations, &$results) {
        global $wpdb;
        
        // Clean expired transients
        if ($optimizations['clean_expired_transients']) {
            $cache_key = 'expired_transients_count';
            $deleted = wp_cache_get($cache_key, $this->cache_group);
            
            if (false === $deleted) {
                $deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prepare(
                        "DELETE FROM $wpdb->options 
                        WHERE option_name LIKE %s 
                        AND option_value < %d",
                        $wpdb->esc_like('_transient_timeout_') . '%',
                        time()
                    )
                );
                wp_cache_set($cache_key, $deleted, $this->cache_group, 300); // Cache for 5 minutes
            }
            
            if ($deleted) {
                // Also delete the related transient values
                $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $wpdb->prepare(
                        "DELETE FROM $wpdb->options 
                        WHERE option_name LIKE %s 
                        AND option_name NOT LIKE %s",
                        $wpdb->esc_like('_transient_') . '%',
                        $wpdb->esc_like('_transient_timeout_') . '%'
                    )
                );
                
                $results['optimization_actions'][] = [
                    'type' => 'clean_transients',
                    'description' => esc_html(
                        sprintf(
                            /* translators: %s is the number of expired transients removed */
                            __('Removed %s expired transients', 'db_ai_optimizer'), 
                            number_format($deleted)
                        )
                    ),
                ];
                $results['performance_impact'] += 3; // Estimate 3% improvement
            }
        }
        
        // Clean post revisions
        if ($optimizations['clean_post_revisions']) {
            // Keep a certain number of revisions, delete the rest
            $keep_revisions = 3; // Could be a setting
            
            // Get posts with more than keep_revisions revisions
            $cache_key = 'posts_with_revisions';
            $posts_with_revisions = wp_cache_get($cache_key, $this->cache_group);
            
            if (false === $posts_with_revisions) {
                $posts_with_revisions = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    "SELECT post_parent, COUNT(*) as revision_count 
                    FROM $wpdb->posts 
                    WHERE post_type = 'revision' 
                    GROUP BY post_parent 
                    HAVING COUNT(*) > %d",
                    $keep_revisions
                ));
                wp_cache_set($cache_key, $posts_with_revisions, $this->cache_group, $this->cache_expiry);
            }
            
            $deleted_revisions = 0;
            
            foreach ($posts_with_revisions as $post) {
                // Get the oldest revisions beyond the keep limit
                $revisions_cache_key = 'revisions_to_delete_' . $post->post_parent;
                $revisions_to_delete = wp_cache_get($revisions_cache_key, $this->cache_group);
                
                if (false === $revisions_to_delete) {
                    $revisions_to_delete = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $wpdb->prepare(
                            "SELECT ID FROM $wpdb->posts 
                            WHERE post_type = 'revision' 
                            AND post_parent = %d 
                            ORDER BY post_date DESC 
                            LIMIT %d, 99999",
                            $post->post_parent,
                            $keep_revisions
                        )
                    );
                    wp_cache_set($revisions_cache_key, $revisions_to_delete, $this->cache_group, $this->cache_expiry);
                }
                
                if (!empty($revisions_to_delete)) {
                    foreach ($revisions_to_delete as $revision_id) {
                        wp_delete_post_revision($revision_id);
                        $deleted_revisions++;
                    }
                }
            }
            
            if ($deleted_revisions) {
                $results['optimization_actions'][] = [
                    'type' => 'clean_revisions',
                    'description' => esc_html(
                        sprintf(
                            /* translators: %s is the number of old post revisions removed */
                            __('Removed %s old post revisions', 'db_ai_optimizer'), 
                            number_format($deleted_revisions)
                        )
                    ),
                ];
                $results['performance_impact'] += 5; // Estimate 5% improvement
            }
        }
        
        // Clean auto-drafts
        if ($optimizations['clean_auto_drafts']) {
            $cache_key = 'auto_drafts_count';
            $deleted = wp_cache_get($cache_key, $this->cache_group);
            
            if (false === $deleted) {
                $deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    "DELETE FROM $wpdb->posts 
                    WHERE post_status = 'auto-draft' 
                    OR post_status = 'trash'"
                );
                wp_cache_set($cache_key, $deleted, $this->cache_group, 300); // Cache for 5 minutes
            }
            
            if ($deleted) {
                $results['optimization_actions'][] = [
                    'type' => 'clean_auto_drafts',
                    'description' => esc_html(
                        sprintf(
                            /* translators: %s is the number of auto-drafts and trashed posts removed */
                            __('Removed %s auto-drafts and trashed posts', 'db_ai_optimizer'), 
                            number_format($deleted)
                        )
                    ),
                ];
                $results['performance_impact'] += 2; // Estimate 2% improvement
            }
        }
    }
    
    /**
     * Generate future recommendations based on analysis
     */
    private function generate_future_recommendations($analysis, $results) {
        $recommendations = [];
        
        // Add AI recommendations if available
        if (isset($analysis['ai_recommendations'])) {
            foreach ($analysis['ai_recommendations'] as $rec) {
                $recommendations[] = $rec['description'];
            }
        }
        
        // Recommend database structure changes
        if (isset($analysis['query_patterns']) && isset($analysis['query_patterns']['slow_queries'])) {
            foreach ($analysis['query_patterns']['slow_queries'] as $slow_query) {
                if (!empty($slow_query['recommendation'])) {
                    $recommendations[] = $slow_query['recommendation'];
                }
            }
        }
        
        // Recommend WordPress specific optimizations
        global $wpdb;
        
        // Check autoload options
        $cache_key = 'autoload_options_size';
        $autoload_size = wp_cache_get($cache_key, $this->cache_group);
        
        if (false === $autoload_size) {
            $autoload_size = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT SUM(LENGTH(option_value)) 
                FROM $wpdb->options 
                WHERE autoload = 'yes'"
            );
            wp_cache_set($cache_key, $autoload_size, $this->cache_group, $this->cache_expiry);
        }
        
        if ($autoload_size > 1000000) { // More than 1MB of autoloaded options
            $recommendations[] = esc_html__('Review and optimize autoloaded options which are consuming excessive memory on each page load.', 'db_ai_optimizer');
        }
        
        return array_unique($recommendations); // Remove duplicates
    }
    
    /**
     * Store optimization history in the database
     */
    private function store_optimization_history($results, $level) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_db_optimization_history';
        
        // Create table if it doesn't exist
        $this->create_history_table();
        
        // Collect current performance data
        $performance_data = $this->collect_current_performance_data();
        
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table_name,
            [
                'optimization_time' => current_time('mysql'),
                'optimization_type' => $level,
                'tables_affected' => wp_json_encode($results['tables_affected']),
                'performance_impact' => $results['performance_impact'],
                'recommendations' => wp_json_encode($results['recommendations']),
                'performance_data' => wp_json_encode($performance_data),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%f',
                '%s',
                '%s',
            ]
        );
    }
    
    /**
     * Create the optimization history table if it doesn't exist
     */
    private function create_history_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_db_optimization_history';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            optimization_time datetime DEFAULT CURRENT_TIMESTAMP,
            optimization_type varchar(50) NOT NULL,
            tables_affected longtext,
            performance_impact float DEFAULT 0,
            recommendations longtext,
            performance_data longtext,
            PRIMARY KEY (id),
            KEY optimization_time (optimization_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Collect current performance data for historical tracking
     */
    private function collect_current_performance_data() {
        global $wpdb;
        
        // Check cache first
        $cache_key = 'current_performance_data';
        $cached_data = wp_cache_get($cache_key, $this->cache_group);
        
        if (false !== $cached_data) {
            return $cached_data;
        }
        
        // Get basic database size using a more efficient query
        $db_size = 0;
        $table_count = 0;
        
        // Get all tables for this database
        $tables = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT 
                    table_name,
                    data_length + index_length as size
                FROM information_schema.tables 
                WHERE table_schema = %s
                AND table_name LIKE %s",
                DB_NAME,
                $wpdb->esc_like($wpdb->prefix) . '%'
            )
        );
        
        if ($tables) {
            foreach ($tables as $table) {
                $db_size += $table->size;
                $table_count++;
            }
        }
        
        // Convert to MB
        $db_size_mb = round($db_size / 1024 / 1024, 1);
        
        $performance_data = [
            'db_size' => floatval($db_size_mb),
            'table_count' => intval($table_count),
            'timestamp' => current_time('mysql'),
        ];
        
        // Cache the results
        wp_cache_set($cache_key, $performance_data, $this->cache_group, $this->cache_expiry);
        
        return $performance_data;
    }
}