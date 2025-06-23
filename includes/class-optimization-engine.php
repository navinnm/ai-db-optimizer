<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FULGID_AIDBO_Optimization_Engine {
    
    /**
     * Cache group for optimization data
     */
    private $cache_group = 'fulgid_ai_db_optimizer';
    
    /**
     * Cache expiry time (1 hour)
     */
    private $cache_expiry = 3600;
    
    /**
     * Backup engine instance
     */
    private $backup_engine;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize backup engine
        require_once FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_DIR . 'includes/class-db-backup.php';
        $this->backup_engine = new FULGID_AIDBO_DB_Backup();
    }
    
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
            'backup_info' => null,
        ];
        
        // Create backup before optimization if required
        $backup_required = $this->backup_engine->is_backup_required($level);
        
        if ($backup_required) {
            $backup_result = $this->backup_engine->create_backup($level);
            
            if (!$backup_result['success']) {
                return [
                    'success' => false,
                    'error' => 'Backup failed: ' . $backup_result['error'],
                    'backup_required' => true
                ];
            }
            
            $results['backup_info'] = $backup_result;
            $results['optimization_actions'][] = [
                'type' => 'backup_created',
                'description' => esc_html(
                    sprintf(
                        /* translators: 1: Backup filename, 2: File size, 3: Number of tables */
                        __('Created database backup: %1$s (%2$s, %3$d tables)', 'ai-database-optimizer'),
                        $backup_result['backup_file'],
                        $backup_result['file_size'],
                        $backup_result['tables_count']
                    )
                ),
            ];
        }
        
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
        
        // Clear analysis cache to refresh recommendations
        $this->clear_analysis_cache($results['tables_affected']);
        
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
                'convert_engines' => true,
                'clean_expired_transients' => true,
            ],
            'high' => [
                'optimize_tables' => true,
                'remove_overhead' => true,
                'add_basic_indexes' => true,
                'add_advanced_indexes' => true,
                'convert_engines' => true,
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
        
        // Check for and remove overhead - use short cache during optimization session
        if ($optimizations['remove_overhead'] && isset($analysis['overhead']) && $analysis['overhead'] > 0) {
            $cache_key = 'table_optimize_session_' . md5($table . gmdate('Y-m-d-H-i'));
            $cached_result = wp_cache_get($cache_key, $this->cache_group);
            
            if (false === $cached_result) {
                $result = $wpdb->query("OPTIMIZE TABLE `" . esc_sql($table) . "`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
                // Cache for very short time to avoid re-optimizing same table in same session
                wp_cache_set($cache_key, $result, $this->cache_group, 60);
                
                // Immediately clear the analysis cache for this table to force refresh
                $analysis_cache_key = 'table_analysis_' . md5($table);
                wp_cache_delete($analysis_cache_key, 'fulgid_ai_db_analyzer');
            } else {
                $result = $cached_result;
            }
            
            if ($result !== false) {
                $results['actions'][] = [
                    'type' => 'optimize_table',
                    'table' => $table,
                    'overhead_removed' => $analysis['overhead'],
                    'description' => esc_html(
                        sprintf(
                            /* translators: %1$s is the table name, %2$s is the overhead size in megabytes */
                            __('Optimized table %1$s, removed %2$s MB overhead', 'ai-database-optimizer'), 
                            $table, 
                            number_format($analysis['overhead'] / (1024 * 1024), 2)
                        )
                    ),
                ];
                $results['performance_impact'] += 5; // Estimate 5% improvement for removing overhead
            }
        }
        
        // Convert table engine if needed
        if (isset($analysis['suggestions'])) {
            foreach ($analysis['suggestions'] as $suggestion) {
                if ($suggestion['type'] == 'engine_conversion' && $optimizations['convert_engines']) {
                    $cache_key = 'table_engine_session_' . md5($table . gmdate('Y-m-d-H-i'));
                    $cached_result = wp_cache_get($cache_key, $this->cache_group);
                    
                    if (false === $cached_result) {
                        $result = $wpdb->query("ALTER TABLE `" . esc_sql($table) . "` ENGINE=InnoDB"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
                        wp_cache_set($cache_key, $result, $this->cache_group, 60);
                        
                        // Immediately clear the analysis cache for this table to force refresh
                        $analysis_cache_key = 'table_analysis_' . md5($table);
                        wp_cache_delete($analysis_cache_key, 'fulgid_ai_db_analyzer');
                    } else {
                        $result = $cached_result;
                    }
                    
                    if ($result !== false) {
                        $results['actions'][] = [
                            'type' => 'convert_engine',
                            'table' => $table,
                            'description' => esc_html(
                                sprintf(
                                    /* translators: %s is the table name */
                                    __('Converted table %s from MyISAM to InnoDB engine for better performance and reliability', 'ai-database-optimizer'), 
                                    $table
                                )
                            ),
                        ];
                        $results['performance_impact'] += 20; // Estimate 20% improvement for engine conversion
                    }
                }
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
                        $composite_columns = $suggestion['composite_columns'] ?? [$column];
                        
                        // Validate all column names
                        $valid_columns = true;
                        foreach ($composite_columns as $col) {
                            if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                                $valid_columns = false;
                                break;
                            }
                        }
                        
                        if (!$valid_columns) {
                            continue;
                        }
                        
                        // Generate index name with validation
                        $index_name = 'ai_opt_' . substr(md5($table . implode('_', $composite_columns)), 0, 10);
                        
                        // Additional validation for index name
                        if (!preg_match('/^[a-zA-Z0-9_]+$/', $index_name)) {
                            continue;
                        }
                        
                        // Check for existing indexes with session-based cache key to ensure fresh data
                        $index_cache_key = 'table_indexes_session_' . md5($table . gmdate('Y-m-d-H'));
                        $existing_indexes = wp_cache_get($index_cache_key, $this->cache_group);
                        
                        if (false === $existing_indexes) {
                            $existing_indexes = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                                "SHOW INDEX FROM `" . esc_sql($table) . "`"
                            );
                            // Cache for 1 hour with hour-based key to get fresh data for each optimization session
                            wp_cache_set($index_cache_key, $existing_indexes, $this->cache_group, 3600);
                        }
                        
                        $index_exists = false;
                        foreach ($existing_indexes as $idx) {
                            if (in_array($idx->Column_name, $composite_columns)) {
                                // Check if this index covers all our required columns
                                $index_columns = [];
                                foreach ($existing_indexes as $check_idx) {
                                    if ($check_idx->Key_name === $idx->Key_name) {
                                        $index_columns[] = $check_idx->Column_name;
                                    }
                                }
                                
                                if (count(array_intersect($composite_columns, $index_columns)) >= count($composite_columns)) {
                                    $index_exists = true;
                                    break;
                                }
                            }
                        }
                        
                        if (!$index_exists) {
                            // Validate all components before creating ALTER query
                            if (preg_match('/^[a-zA-Z0-9_]+$/', $table) && preg_match('/^[a-zA-Z0-9_]+$/', $index_name)) {
                                // Create the ALTER TABLE query with validated components
                                $columns_sql = '`' . implode('`, `', array_map('esc_sql', $composite_columns)) . '`';
                                $alter_query = "ALTER TABLE `" . esc_sql($table) . "` ADD INDEX `" . esc_sql($index_name) . "` (" . $columns_sql . ")";
                                $result = $wpdb->query($alter_query); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared
                            } else {
                                $result = false;
                            }
                            
                            if ($result !== false) {
                                // Clear analysis cache immediately after successful index creation
                                $analysis_cache_key = 'table_analysis_' . md5($table);
                                wp_cache_delete($analysis_cache_key, 'fulgid_ai_db_analyzer');
                                
                                // Clear index cache too
                                wp_cache_delete($index_cache_key, $this->cache_group);
                                
                                $results['actions'][] = [
                                    'type' => 'add_index',
                                    'table' => $table,
                                    'columns' => $composite_columns,
                                    'index_name' => $index_name,
                                    'description' => esc_html(
                                        sprintf(
                                            /* translators: %1$s is the table name, %2$s is the column names */
                                            __('Added index on %1$s (%2$s) for better performance', 'ai-database-optimizer'), 
                                            $table, 
                                            implode(', ', $composite_columns)
                                        )
                                    ),
                                ];
                                $results['performance_impact'] += (count($composite_columns) > 1 ? 15 : 10); // Higher impact for composite indexes
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
        
        // Clean expired transients - use session-based caching
        if ($optimizations['clean_expired_transients']) {
            $cache_key = 'expired_transients_session_' . gmdate('Y-m-d-H-i');
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
                            __('Removed %s expired transients', 'ai-database-optimizer'), 
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
            
            // Get posts with more than keep_revisions revisions - use hour-based cache
            $cache_key = 'posts_with_revisions_' . gmdate('Y-m-d-H');
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
                wp_cache_set($cache_key, $posts_with_revisions, $this->cache_group, 3600);
            }
            
            $deleted_revisions = 0;
            
            foreach ($posts_with_revisions as $post) {
                // Get the oldest revisions beyond the keep limit - use minute-based cache
                $revisions_cache_key = 'revisions_to_delete_' . $post->post_parent . '_' . gmdate('Y-m-d-H-i');
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
                    wp_cache_set($revisions_cache_key, $revisions_to_delete, $this->cache_group, 300);
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
                            __('Removed %s old post revisions', 'ai-database-optimizer'), 
                            number_format($deleted_revisions)
                        )
                    ),
                ];
                $results['performance_impact'] += 5; // Estimate 5% improvement
            }
        }
        
        // Clean auto-drafts - use session-based caching
        if ($optimizations['clean_auto_drafts']) {
            $cache_key = 'auto_drafts_session_' . gmdate('Y-m-d-H-i');
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
                            __('Removed %s auto-drafts and trashed posts', 'ai-database-optimizer'), 
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
            $recommendations[] = esc_html__('Review and optimize autoloaded options which are consuming excessive memory on each page load.', 'ai-database-optimizer');
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
        
        $insert_result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $table_name,
            [
                'optimization_time' => current_time('mysql'),
                'optimization_type' => $level,
                'tables_affected' => wp_json_encode($results['tables_affected']),
                'performance_impact' => $results['performance_impact'],
                'recommendations' => wp_json_encode($results['recommendations']),
                'performance_data' => wp_json_encode($performance_data),
                'optimization_actions' => wp_json_encode($results['optimization_actions']),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%f',
                '%s',
                '%s',
                '%s',
            ]
        );
        
        // Log optimization history result if debug mode is enabled
        // if (defined('WP_DEBUG') && WP_DEBUG && $insert_result === false) {
        //     error_log('AI DB Optimizer: Failed to insert optimization history. Error: ' . $wpdb->last_error);
        // }
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
            optimization_actions longtext,
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
    
    /**
     * Clear analysis cache for optimized tables
     */
    private function clear_analysis_cache($affected_tables) {
        // Clear table-specific analysis cache
        foreach ($affected_tables as $table) {
            $cache_key = 'table_analysis_' . md5($table);
            wp_cache_delete($cache_key, 'fulgid_ai_db_analyzer');
            
            // Clear table indexes cache
            $index_cache_key = 'table_indexes_session_' . md5($table . gmdate('Y-m-d-H'));
            wp_cache_delete($index_cache_key, $this->cache_group);
        }
        
        // Clear global cache keys
        wp_cache_delete('database_tables', 'fulgid_ai_db_analyzer');
        wp_cache_delete('current_performance_data', $this->cache_group);
        
        // Clear performance data option cache to force refresh
        delete_option('fulgid_ai_db_optimizer_performance_data');
    }
}