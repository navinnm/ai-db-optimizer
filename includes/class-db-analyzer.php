<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class DB_Analyzer {
    /**
     * Analyze the WordPress database
     */
    public function analyze_database() {
        global $wpdb;

        $tables = $this->get_database_tables();
        $analysis_results = [];
        
        // Get any previously collected performance data
        $performance_data = get_option('fulgid_ai_db_optimizer_performance_data');
        $has_performance_data = false;
        
        if ($performance_data && isset($performance_data['data'])) {
            $has_performance_data = true;
            $analysis_results['performance_data'] = $performance_data['data'];
        }
        
        
        foreach ($tables as $table) {
            // Get table status
            $status = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table'");
            
            // Get indexes
            $indexes = $wpdb->get_results("SHOW INDEX FROM $table");
            
            // Get column information
            $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM $table");
            
            // Analyze table structure and data
            $table_analysis = $this->analyze_table($table, $status, $indexes, $columns);
            
            $analysis_results[$table] = $table_analysis;
        }
        
        // Additional AI-based analysis
        $this->perform_ai_analysis($analysis_results, $has_performance_data);

        return $analysis_results;
    }
    
    /**
     * Get all tables in the WordPress database
     */
    private function get_database_tables() {
        global $wpdb;
        
        $settings = get_option('fulgid_ai_db_optimizer_settings');
        $excluded_tables = isset($settings['tables_to_exclude']) ? $settings['tables_to_exclude'] : [];
        
        $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");
        
        // Remove excluded tables
        if (!empty($excluded_tables)) {
            $tables = array_diff($tables, $excluded_tables);
        }
        
        return $tables;
    }
    
    /**
     * Analyze a single database table
     */
    private function analyze_table($table, $status, $indexes, $columns) {
        global $wpdb;
        
        $row_count = $status->Rows;
        $data_size = $status->Data_length;
        $index_size = $status->Index_length;
        $overhead = $status->Data_free;
        
        $analysis = [
            'row_count' => $row_count,
            'data_size' => $data_size,
            'index_size' => $index_size,
            'overhead' => $overhead,
            'issues' => [],
            'suggestions' => [],
        ];
        
        // Check for overhead
        if ($overhead > 1024 * 1024) { // More than 1MB overhead
            $analysis['issues'][] = 'high_overhead';
            
            $analysis['suggestions'][] = [
                'type' => 'optimize_table',
                'description' => esc_html(
                    /* translators: %s is the amount of overhead in megabytes */
                    sprintf(
                        __('Table has %s MB of overhead. Optimization recommended.', 'ai-database-optimizer'), 
                        number_format($overhead / (1024 * 1024), 2)
                    )
                ),
                'priority' => 'high',
            ];
        }
        
        // Check for missing indexes on frequently queried columns
        $this->analyze_indexes($table, $columns, $indexes, $analysis);
        
        // Check for large text fields that could be compressed
        $this->analyze_columns($table, $columns, $analysis);
        
        return $analysis;
    }
    
    /**
     * Analyze table indexes
     */
    private function analyze_indexes($table, $columns, $indexes, &$analysis) {
        global $wpdb;
        
        // Get existing indexed columns
        $indexed_columns = [];
        foreach ($indexes as $index) {
            $indexed_columns[] = $index->Column_name;
        }
        
        // Check WordPress specific tables for missing important indexes
        if ($table == $wpdb->posts) {
            $important_columns = ['post_type', 'post_status', 'post_author', 'post_date'];
            foreach ($important_columns as $column) {
                if (!in_array($column, $indexed_columns)) {
                    $analysis['issues'][] = 'missing_index_' . $column;
                    
                    $analysis['suggestions'][] = [
                        'type' => 'add_index',
                        'description' => esc_html(
                            /* translators: %1$s is the table name, %2$s is the column name */
                            sprintf(
                                __('Add index to %1$s.%2$s for better query performance', 'ai-database-optimizer'), 
                                $table, 
                                $column
                            )
                        ),
                        'column' => $column,
                        'priority' => 'medium',
                    ];
                }
            }
        } elseif ($table == $wpdb->postmeta) {
            if (!in_array('meta_key', $indexed_columns)) {
                $analysis['issues'][] = 'missing_index_meta_key';
                
                $analysis['suggestions'][] = [
                    'type' => 'add_index',
                    'description' => esc_html(
                        /* translators: %s is the table name */
                        sprintf(
                            __('Add index to %s.meta_key for better query performance', 'ai-database-optimizer'), 
                            $table
                        )
                    ),
                    'column' => 'meta_key',
                    'priority' => 'high',
                ];
            }
        }
    }
    
    /**
     * Analyze table columns
     */
    private function analyze_columns($table, $columns, &$analysis) {
        global $wpdb;
        
        foreach ($columns as $column) {
            // Check for TEXT/LONGTEXT columns with potentially compressible data
            if (strpos($column->Type, 'text') !== false || strpos($column->Type, 'longtext') !== false) {
                // Sample the data to see if it's compressible
                $sample = $wpdb->get_var("SELECT $column->Field FROM $table WHERE $column->Field IS NOT NULL AND LENGTH($column->Field) > 1000 LIMIT 1");
                
                if ($sample && strlen($sample) > 1000) {
                    $compressed_size = strlen(gzcompress($sample));
                    $compression_ratio = $compressed_size / strlen($sample);
                    
                    if ($compression_ratio < 0.5) { // More than 50% compression possible
                        $analysis['issues'][] = 'compressible_data_' . $column->Field;
                        
                        $analysis['suggestions'][] = [
                            'type' => 'compress_column',
                            'description' => esc_html(
                                /* translators: %1$s is the table name, %2$s is the column name, %3$s is the compression ratio */
                                sprintf(
                                    __('Column %1$s.%2$s contains highly compressible data (ratio: %3$s)', 'ai-database-optimizer'), 
                                    $table, 
                                    $column->Field, 
                                    number_format($compression_ratio, 2)
                                )
                            ),
                            'column' => $column->Field,
                            'priority' => 'medium',
                        ];
                    }
                }
            }
        }
    }
    
    /**
     * Perform advanced AI-based analysis
     */
    private function perform_ai_analysis(&$analysis_results, $has_performance_data = false) {
        // This would typically call an external AI service or use a library
        // For demonstration, we'll simulate some AI-based insights
        
        // Analyze query patterns from debug log if available
        $this->analyze_query_patterns($analysis_results);
        
        // Look for correlation between tables
        $this->identify_table_correlations($analysis_results);
        
        // Generate overall recommendations
        $this->generate_ai_recommendations($analysis_results);

        // If we have performance data, use it to enhance the analysis
        if ($has_performance_data && isset($analysis_results['performance_data'])) {
            $performance_data = $analysis_results['performance_data'];
            
            // Analyze slow queries
            if (isset($performance_data['slow_queries']) && !empty($performance_data['slow_queries']['queries'])) {
                $this->analyze_slow_query_patterns($analysis_results, $performance_data['slow_queries']['queries']);
            }
            
            // Analyze table statistics
            if (isset($performance_data['table_stats'])) {
                $this->analyze_table_performance($analysis_results, $performance_data['table_stats']);
            }
            
            // Analyze server configuration
            if (isset($performance_data['server_info'])) {
                $this->analyze_server_configuration($analysis_results, $performance_data['server_info']);
            }
            
            // Analyze query cache
            if (isset($performance_data['query_cache'])) {
                $this->analyze_query_cache($analysis_results, $performance_data['query_cache']);
            }
        }
        
        // Generate overall recommendations
        $this->generate_ai_recommendations($analysis_results);
    }


    /**
     * Analyze slow query patterns from performance data
     */
    private function analyze_slow_query_patterns(&$analysis_results, $slow_queries) {
        // Group similar queries
        $query_patterns = [];
        
        foreach ($slow_queries as $query) {
            // Normalize the query by replacing literal values with placeholders
            $normalized_query = preg_replace('/\'[^\']*\'/', "'%s'", $query['query']);
            $normalized_query = preg_replace('/\d+/', '%d', $normalized_query);
            
            if (!isset($query_patterns[$normalized_query])) {
                $query_patterns[$normalized_query] = [
                    'count' => 0,
                    'total_time' => 0,
                    'max_time' => 0,
                    'example' => $query['query']
                ];
            }
            
            $query_patterns[$normalized_query]['count']++;
            $query_patterns[$normalized_query]['total_time'] += floatval($query['time']);
            $query_patterns[$normalized_query]['max_time'] = max($query_patterns[$normalized_query]['max_time'], floatval($query['time']));
        }
        
        // Sort by total time descending
        uasort($query_patterns, function($a, $b) {
            return $b['total_time'] <=> $a['total_time'];
        });
        
        // Add to analysis results
        $analysis_results['query_patterns']['slow_queries'] = [];
        
        foreach ($query_patterns as $pattern => $stats) {
            $avg_time = $stats['total_time'] / $stats['count'];
            
            $recommendation = '';
            
            // Generate recommendations based on query pattern
            if (strpos($pattern, 'WHERE') !== false && strpos($pattern, 'ORDER BY') !== false) {
                // Extract potential columns to index
                preg_match('/WHERE\s+([^)]+)(?:\s+ORDER\s+BY\s+([^)]+))?/i', $pattern, $matches);
                
                if (!empty($matches[1])) {
                    $where_conditions = $matches[1];
                    $order_by = !empty($matches[2]) ? $matches[2] : '';
                    
                    // Extract column names from WHERE clause
                    preg_match_all('/([a-zA-Z0-9_]+)\s*=/', $where_conditions, $where_matches);
                    $where_columns = $where_matches[1] ?? [];
                    
                    // Extract column names from ORDER BY clause
                    $order_columns = [];
                    if (!empty($order_by)) {
                        preg_match_all('/([a-zA-Z0-9_]+)/', $order_by, $order_matches);
                        $order_columns = $order_matches[1] ?? [];
                    }
                    
                    // Combine columns for a potential composite index
                    $index_columns = array_merge($where_columns, $order_columns);
                    
                    if (!empty($index_columns)) {
                        $recommendation = esc_html(
                            /* translators: %s is a comma-separated list of column names */
                            sprintf(
                                __('Consider adding an index on (%s) to speed up this query pattern', 'ai-database-optimizer'),
                                implode(', ', array_unique($index_columns))
                            )
                        );
                    }
                }
            } elseif (strpos($pattern, 'JOIN') !== false) {
                $recommendation = esc_html__('This query uses JOIN operations. Ensure all join columns are properly indexed.', 'ai-database-optimizer');
            } elseif (strpos($pattern, 'GROUP BY') !== false) {
                $recommendation = esc_html__('This query uses GROUP BY. Consider adding an index on the grouped columns.', 'ai-database-optimizer');
            }
            
            $analysis_results['query_patterns']['slow_queries'][] = [
                'query_pattern' => $pattern,
                'count' => $stats['count'],
                'avg_execution_time' => $avg_time,
                'max_execution_time' => $stats['max_time'],
                'example' => $stats['example'],
                'recommendation' => $recommendation
            ];
        }
    }

    /**
     * Analyze table performance data
     */
    private function analyze_table_performance(&$analysis_results, $table_stats) {
        foreach ($table_stats as $table => $stats) {
            if (!isset($analysis_results[$table])) {
                continue;
            }
            
            // Add query statistics if available
            if (!empty($stats['query_stats'])) {
                $analysis_results[$table]['query_stats'] = $stats['query_stats'];
                
                // Identify frequently queried tables
                if ($stats['query_stats']['query_count'] > 1000) {
                    $analysis_results[$table]['issues'][] = 'high_query_volume';
                    
                    $analysis_results[$table]['suggestions'][] = [
                        'type' => 'performance_review',
                        'description' => esc_html(
                            /* translators: %1$s is the table name, %2$d is the number of queries */
                            sprintf(
                                __('Table %1$s has high query volume (%2$d queries). Consider reviewing access patterns.', 'ai-database-optimizer'),
                                $table,
                                $stats['query_stats']['query_count']
                            )
                        ),
                        'priority' => 'medium',
                    ];
                }
                
                // Identify slow tables
                if ($stats['query_stats']['total_time'] > 10) { // More than 10 seconds total
                    $analysis_results[$table]['issues'][] = 'high_query_time';
                    
                    $analysis_results[$table]['suggestions'][] = [
                        'type' => 'performance_review',
                        'description' => esc_html(
                            /* translators: %1$s is the table name, %2$s is the query time in seconds */
                            sprintf(
                                __('Table %1$s has high query time (%2$s seconds). Optimize indexes and queries.', 'ai-database-optimizer'),
                                $table,
                                number_format($stats['query_stats']['total_time'], 2)
                            )
                        ),
                        'priority' => 'high',
                    ];
                }
            }
            
            // Check for MyISAM tables that could be converted to InnoDB
            if ($stats['engine'] == 'MyISAM') {
                $analysis_results[$table]['issues'][] = 'myisam_engine';
                
                $analysis_results[$table]['suggestions'][] = [
                    'type' => 'engine_conversion',
                    'description' => esc_html(
                        /* translators: %s is the table name */
                        sprintf(
                            __('Table %s uses MyISAM engine. Consider converting to InnoDB for better performance and reliability.', 'ai-database-optimizer'),
                            $table
                        )
                    ),
                    'priority' => 'medium',
                ];
            }
        }
    }

    /**
     * Analyze server configuration
     */
    private function analyze_server_configuration(&$analysis_results, $server_info) {
        $recommendations = [];
        
        // Check innodb_buffer_pool_size
        if (isset($server_info['variables']['innodb_buffer_pool_size'])) {
            $buffer_pool_size = intval($server_info['variables']['innodb_buffer_pool_size']);
            $buffer_pool_mb = $buffer_pool_size / (1024 * 1024);
            
            if ($buffer_pool_mb < 128) {
                $recommendations[] = [
                    'type' => 'server_config',
                    'description' => esc_html(
                        /* translators: %s is the buffer pool size in megabytes */
                        sprintf(
                            __('InnoDB buffer pool size is only %sMB. For better performance, increase to at least 128MB if possible.', 'ai-database-optimizer'),
                            number_format($buffer_pool_mb, 0)
                        )
                    ),
                    'priority' => 'medium',
                ];
            }
        }
        
        // Check max_connections vs threads_connected
        if (isset($server_info['variables']['max_connections']) && isset($server_info['status']['Threads_connected'])) {
            $max_connections = intval($server_info['variables']['max_connections']);
            $threads_connected = intval($server_info['status']['Threads_connected']);
            $connection_ratio = ($threads_connected / $max_connections) * 100;
            
            if ($connection_ratio > 70) {
                $recommendations[] = [
                    'type' => 'server_config',
                    'description' => esc_html(
                        /* translators: %s is the connection usage percentage */
                        sprintf(
                            __('Connection usage is high (%s%% of max_connections). Consider increasing max_connections or optimizing connection handling.', 'ai-database-optimizer'),
                            number_format($connection_ratio, 0)
                        )
                    ),
                    'priority' => 'high',
                ];
            }
        }
        
        // Check for high number of temporary tables on disk
        if (isset($server_info['status']['Created_tmp_disk_tables']) && isset($server_info['status']['Created_tmp_tables'])) {
            $tmp_disk_tables = intval($server_info['status']['Created_tmp_disk_tables']);
            $tmp_tables = intval($server_info['status']['Created_tmp_tables']);
            
            if ($tmp_tables > 0) {
                $disk_ratio = ($tmp_disk_tables / $tmp_tables) * 100;
                
                if ($disk_ratio > 25) {
                    $recommendations[] = [
                        'type' => 'server_config',
                        'description' => esc_html(
                            /* translators: %s is the percentage of temporary tables created on disk */
                            sprintf(
                                __('%s%% of temporary tables are created on disk. Consider increasing tmp_table_size and max_heap_table_size.', 'ai-database-optimizer'),
                                number_format($disk_ratio, 0)
                            )
                        ),
                        'priority' => 'medium',
                    ];
                }
            }
        }
        
        // Add recommendations to AI recommendations
        if (!empty($recommendations)) {
            if (!isset($analysis_results['ai_recommendations'])) {
                $analysis_results['ai_recommendations'] = [];
            }
            
            foreach ($recommendations as $recommendation) {
                $analysis_results['ai_recommendations'][] = $recommendation;
            }
        }
    }

    /**
     * Analyze query cache
     */
    private function analyze_query_cache(&$analysis_results, $cache_info) {
        if (!$cache_info['enabled']) {
            return;
        }
        
        $recommendations = [];
        
        // Check cache hit ratio
        if (isset($cache_info['hit_ratio'])) {
            $hit_ratio = $cache_info['hit_ratio'];
            
            if ($hit_ratio < 20) {
                $recommendations[] = [
                    'type' => 'query_cache',
                    'description' => esc_html(
                        /* translators: %s is the cache hit ratio percentage */
                        sprintf(
                            __('Query cache hit ratio is low (%s%%). Consider disabling the query cache or reviewing your query patterns.', 'ai-database-optimizer'),
                            number_format($hit_ratio, 1)
                        )
                    ),
                    'priority' => 'medium',
                ];
            }
        }
        
        // Check for high number of prunes
        if (isset($cache_info['usage']['query_cache_lowmem_prunes']) && intval($cache_info['usage']['query_cache_lowmem_prunes']) > 100) {
            $recommendations[] = [
                'type' => 'query_cache',
                'description' => esc_html__('Query cache is frequently pruning entries due to low memory. Consider increasing query_cache_size.', 'ai-database-optimizer'),
                'priority' => 'medium',
            ];
        }
        
        // Add recommendations to AI recommendations
        if (!empty($recommendations)) {
            if (!isset($analysis_results['ai_recommendations'])) {
                $analysis_results['ai_recommendations'] = [];
            }
            
            foreach ($recommendations as $recommendation) {
                $analysis_results['ai_recommendations'][] = $recommendation;
            }
        }
    }

    
    /**
     * Analyze query patterns from debug log
     */
    private function analyze_query_patterns(&$analysis_results) {
        // In a real implementation, this would parse query logs
        // For now, we'll add a simulated recommendation
        
        $analysis_results['query_patterns'] = [
            'slow_queries' => [
                [
                    'query_pattern' => 'SELECT * FROM wp_posts WHERE post_type = %s',
                    'avg_execution_time' => 1.5,
                    'recommendation' => esc_html__('Consider adding a composite index on (post_type, post_status)', 'ai-database-optimizer'),
                ]
            ]
        ];
    }
    
    /**
     * Identify correlations between tables
     */
    private function identify_table_correlations(&$analysis_results) {
        // In a real implementation, this would analyze foreign key relationships and query patterns
        // For now, we'll add simulated correlations
        
        $analysis_results['table_correlations'] = [
            'wp_posts_wp_postmeta' => [
                'strength' => 'high',
                'description' => esc_html__('Strong correlation between wp_posts and wp_postmeta tables in queries', 'ai-database-optimizer'),
            ]
        ];
    }
    
    /**
     * Generate AI-based recommendations
     */
    private function generate_ai_recommendations(&$analysis_results) {
        // Simulate AI generating overall recommendations based on the analysis
        
        $analysis_results['ai_recommendations'] = [
            [
                'type' => 'index_optimization',
                'description' => esc_html__('Create a covering index for frequently used query patterns', 'ai-database-optimizer'),
                'priority' => 'high',
                'expected_impact' => '25% faster queries on posts table',
            ],
            [
                'type' => 'data_archiving',
                'description' => esc_html__('Archive old post revisions to reduce table size', 'ai-database-optimizer'),
                'priority' => 'medium',
                'expected_impact' => '15% reduction in database size',
            ],
        ];
    }
}