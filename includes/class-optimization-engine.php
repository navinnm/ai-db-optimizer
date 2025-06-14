<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Optimization_Engine {
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
            // Skip excluded tables
            if (in_array($table, $excluded_tables)) {
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
            $wpdb->query("OPTIMIZE TABLE $table");
            $results['actions'][] = [
                'type' => 'optimize_table',
                'description' => sprintf(__('Optimized table %s, removed %.2f MB overhead', 'ai-db-optimizer'), $table, $analysis['overhead'] / (1024 * 1024)),
            ];
            $results['performance_impact'] += 5; // Estimate 5% improvement for removing overhead
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
                    
                    if ($should_add) {
                        $column = $suggestion['column'];
                        $index_name = 'ai_opt_' . substr(md5($table . $column), 0, 10);
                        
                        // Check if index already exists
                        $index_exists = $wpdb->get_results("SHOW INDEX FROM $table WHERE Column_name = '$column'");
                        
                        if (empty($index_exists)) {
                            // Try to add the index
                            $result = $wpdb->query("ALTER TABLE $table ADD INDEX $index_name ($column)");
                            
                            if ($result !== false) {
                                $results['actions'][] = [
                                    'type' => 'add_index',
                                    'description' => sprintf(__('Added index on %s.%s for better performance', 'ai-db-optimizer'), $table, $column),
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
            $deleted = $wpdb->query(
                "DELETE FROM $wpdb->options 
                WHERE option_name LIKE '%_transient_timeout_%' 
                AND option_value < " . time()
            );
            
            if ($deleted) {
                $wpdb->query(
                    "DELETE FROM $wpdb->options 
                    WHERE option_name LIKE '%_transient_%' 
                    AND option_name NOT LIKE '%_transient_timeout_%'"
                );
                
                $results['optimization_actions'][] = [
                    'type' => 'clean_transients',
                    'description' => sprintf(__('Removed %d expired transients', 'ai-db-optimizer'), $deleted),
                ];
                $results['performance_impact'] += 3; // Estimate 3% improvement
            }
        }
        
        // Clean post revisions
        if ($optimizations['clean_post_revisions']) {
            // Keep a certain number of revisions, delete the rest
            $keep_revisions = 3; // Could be a setting
            
            // Get posts with more than keep_revisions revisions
            $posts_with_revisions = $wpdb->get_results(
                "SELECT post_parent, COUNT(*) as revision_count 
                FROM $wpdb->posts 
                WHERE post_type = 'revision' 
                GROUP BY post_parent 
                HAVING COUNT(*) > $keep_revisions"
            );
            
            $deleted_revisions = 0;
            
            foreach ($posts_with_revisions as $post) {
                // Get the oldest revisions beyond the keep limit
                $revisions_to_delete = $wpdb->get_col(
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
                    'description' => sprintf(__('Removed %d old post revisions', 'ai-db-optimizer'), $deleted_revisions),
                ];
                $results['performance_impact'] += 5; // Estimate 5% improvement
            }
        }
        
        // Clean auto-drafts
        if ($optimizations['clean_auto_drafts']) {
            $deleted = $wpdb->query(
                "DELETE FROM $wpdb->posts 
                WHERE post_status = 'auto-draft' 
                OR post_status = 'trash'"
            );
            
            if ($deleted) {
                $results['optimization_actions'][] = [
                    'type' => 'clean_auto_drafts',
                    'description' => sprintf(__('Removed %d auto-drafts and trashed posts', 'ai-db-optimizer'), $deleted),
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
                $recommendations[] = $slow_query['recommendation'];
            }
        }
        
        // Recommend WordPress specific optimizations
        global $wpdb;
        
        // Check autoload options
        $autoload_size = $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) 
            FROM $wpdb->options 
            WHERE autoload = 'yes'"
        );
        
        if ($autoload_size > 1000000) { // More than 1MB of autoloaded options
            $recommendations[] = __('Review and optimize autoloaded options which are consuming excessive memory on each page load.', 'ai-db-optimizer');
        }
        
        return $recommendations;
    }
    
    /**
     * Store optimization history in the database
     */
/**
 * Store optimization history in the database
 */
private function store_optimization_history($results, $level) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ai_db_optimization_history';
    
    // Collect current performance data
    $performance_data = collect_current_performance_data();
    
    $wpdb->insert(
        $table_name,
        [
            'optimization_time' => current_time('mysql'),
            'optimization_type' => $level,
            'tables_affected' => json_encode($results['tables_affected']),
            'performance_impact' => $results['performance_impact'],
            'recommendations' => json_encode($results['recommendations']),
            'query_time' => $performance_data['query_time'],
            'db_size' => $performance_data['db_size']
        ],
        [
            '%s',
            '%s',
            '%s',
            '%f',
            '%s',
            '%f',
            '%d'
        ]
    );
}
}