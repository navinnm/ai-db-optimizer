<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FULGID_AIDBO_DB_Analyzer {
    
    /**
     * Cache group for analyzer data
     */
    private $cache_group = 'fulgid_ai_db_analyzer';
    
    /**
     * Cache expiry time (30 minutes)
     */
    private $cache_expiry = 1800;
    
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
            // Validate table name to prevent SQL injection
            if (!$this->is_valid_table_name($table)) {
                continue;
            }
            
            // Check cache first
            $cache_key = 'table_analysis_' . md5($table);
            $table_analysis = wp_cache_get($cache_key, $this->cache_group);
            
            if (false === $table_analysis) {
                // Get table status with proper escaping
                $status = $wpdb->get_row($wpdb->prepare("SHOW TABLE STATUS LIKE %s", $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                
                // Get indexes - table name is validated above
                $indexes = $wpdb->get_results("SHOW INDEX FROM `" . esc_sql($table) . "`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                
                // Get column information
                $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `" . esc_sql($table) . "`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                
                // Analyze table structure and data
                $table_analysis = $this->analyze_table($table, $status, $indexes, $columns);
                
                // Cache the results
                wp_cache_set($cache_key, $table_analysis, $this->cache_group, $this->cache_expiry);
            }
            
            $analysis_results[$table] = $table_analysis;
        }
        
        // Additional AI-based analysis
        $this->perform_ai_analysis($analysis_results, $has_performance_data);

        return $analysis_results;
    }
    
    /**
     * Validate table name to prevent SQL injection
     */
    private function is_valid_table_name($table) {
        // Table names should only contain alphanumeric characters and underscores
        return preg_match('/^[a-zA-Z0-9_]+$/', $table) === 1;
    }
    
    /**
     * Get all tables in the WordPress database
     */
    private function get_database_tables() {
        global $wpdb;
        
        $settings = get_option('fulgid_ai_db_optimizer_settings');
        $excluded_tables = isset($settings['tables_to_exclude']) ? $settings['tables_to_exclude'] : [];
        
        // Check cache first
        $cache_key = 'database_tables';
        $tables = wp_cache_get($cache_key, $this->cache_group);
        
        if (false === $tables) {
            $tables = $wpdb->get_col($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SHOW TABLES LIKE %s",
                $wpdb->esc_like($wpdb->prefix) . '%'
            ));
            
            // Cache the results
            wp_cache_set($cache_key, $tables, $this->cache_group, $this->cache_expiry);
        }
        
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
        if (!$status) {
            return [
                'error' => 'Could not retrieve table status'
            ];
        }
        
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
                    sprintf(
                        /* translators: %s: Amount of overhead in megabytes */
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
        
        // Check for performance-oriented composite indexes (these are often missing)
        if ($table == $wpdb->posts) {
            // Check for composite indexes that would improve performance
            $composite_indexes_needed = [
                'post_type_status' => ['post_type', 'post_status'],
                'post_status_date' => ['post_status', 'post_date'],
                'post_author_type' => ['post_author', 'post_type']
            ];
            
            foreach ($composite_indexes_needed as $index_name => $columns) {
                $has_composite = false;
                
                // Check if a composite index exists for these columns
                foreach ($indexes as $index) {
                    if ($index->Key_name !== 'PRIMARY' && $index->Key_name !== $columns[0]) {
                        // This is a potential composite index, check if it covers our columns
                        $index_columns = [];
                        foreach ($indexes as $idx) {
                            if ($idx->Key_name === $index->Key_name) {
                                $index_columns[] = $idx->Column_name;
                            }
                        }
                        
                        // Check if this index covers our required columns
                        if (count(array_intersect($columns, $index_columns)) >= count($columns)) {
                            $has_composite = true;
                            break;
                        }
                    }
                }
                
                if (!$has_composite) {
                    $analysis['issues'][] = 'missing_composite_index_' . $index_name;
                    
                    $analysis['suggestions'][] = [
                        'type' => 'add_index',
                        'description' => esc_html(
                            sprintf(
                                /* translators: 1: Table name, 2: Column names */
                                __('Add composite index to %1$s on (%2$s) for better query performance', 'ai-database-optimizer'), 
                                $table, 
                                implode(', ', $columns)
                            )
                        ),
                        'column' => $columns[0], // Primary column for the index
                        'composite_columns' => $columns,
                        'priority' => 'medium',
                    ];
                    
                    // Only suggest one composite index per analysis to avoid overwhelming
                    break;
                }
            }
        } elseif ($table == $wpdb->postmeta) {
            // Check for meta_key index (this is usually missing and very important)
            $has_meta_key_index = false;
            foreach ($indexes as $index) {
                if ($index->Column_name === 'meta_key') {
                    $has_meta_key_index = true;
                    break;
                }
            }
            
            if (!$has_meta_key_index) {
                $analysis['issues'][] = 'missing_index_meta_key';
                
                $analysis['suggestions'][] = [
                    'type' => 'add_index',
                    'description' => esc_html(
                        sprintf(
                            /* translators: %s: Table name */
                            __('Add index to %s.meta_key for better query performance', 'ai-database-optimizer'), 
                            $table
                        )
                    ),
                    'column' => 'meta_key',
                    'priority' => 'high',
                ];
            }
            
            // Check for composite meta_key + post_id index
            $has_composite_meta = false;
            foreach ($indexes as $index) {
                if ($index->Key_name !== 'PRIMARY') {
                    $index_columns = [];
                    foreach ($indexes as $idx) {
                        if ($idx->Key_name === $index->Key_name) {
                            $index_columns[] = $idx->Column_name;
                        }
                    }
                    
                    if (in_array('meta_key', $index_columns) && in_array('post_id', $index_columns)) {
                        $has_composite_meta = true;
                        break;
                    }
                }
            }
            
            if (!$has_composite_meta && $has_meta_key_index) {
                $analysis['issues'][] = 'missing_composite_meta_index';
                
                $analysis['suggestions'][] = [
                    'type' => 'add_index',
                    'description' => esc_html(
                        sprintf(
                            /* translators: %s: Table name */
                            __('Add composite index to %s on (post_id, meta_key) for better query performance', 'ai-database-optimizer'), 
                            $table
                        )
                    ),
                    'column' => 'post_id',
                    'composite_columns' => ['post_id', 'meta_key'],
                    'priority' => 'medium',
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
                // Validate table and column names before using in query
                if (!$this->is_valid_table_name($table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column->Field)) {
                    continue;
                }
                
                // Check cache for sample data first
                $cache_key = 'column_sample_' . md5($table . $column->Field);
                $sample = wp_cache_get($cache_key, $this->cache_group);
                
                if (false === $sample) {
                    // Validate table and column names for security
                    if (preg_match('/^[a-zA-Z0-9_]+$/', $column->Field) && preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                        // Use wpdb::prepare() with placeholders for the length check
                        $query = $wpdb->prepare(
                            "SELECT `" . esc_sql($column->Field) . "` FROM `" . esc_sql($table) . "` WHERE `" . esc_sql($column->Field) . "` IS NOT NULL AND LENGTH(`" . esc_sql($column->Field) . "`) > %d LIMIT 1",
                            1000
                        );
                        $sample = $wpdb->get_var($query); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
                        // Cache the sample
                        wp_cache_set($cache_key, $sample, $this->cache_group, $this->cache_expiry);
                    }
                }
                
                if ($sample && strlen($sample) > 1000) {
                    $compressed_size = strlen(gzcompress($sample));
                    $compression_ratio = $compressed_size / strlen($sample);
                    
                    if ($compression_ratio < 0.5) { // More than 50% compression possible
                        $analysis['issues'][] = 'compressible_data_' . $column->Field;
                        
                        $analysis['suggestions'][] = [
                            'type' => 'compress_column',
                            'description' => esc_html(
                                sprintf(
                                    /* translators: 1: Table name, 2: Column name, 3: Compression ratio */
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
                            sprintf(
                                /* translators: %s: Comma-separated list of column names */
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
                            sprintf(
                                /* translators: 1: Table name, 2: Number of queries */
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
                            sprintf(
                                /* translators: 1: Table name, 2: Query time in seconds */
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
            if (isset($stats['engine']) && $stats['engine'] == 'MyISAM') {
                $analysis_results[$table]['issues'][] = 'myisam_engine';
                
                $analysis_results[$table]['suggestions'][] = [
                    'type' => 'engine_conversion',
                    'description' => esc_html(
                        sprintf(
                            /* translators: %s: Table name */
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
                        sprintf(
                            /* translators: %s: Buffer pool size in megabytes */
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
                        sprintf(
                            /* translators: %s: Connection usage percentage */
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
                            sprintf(
                                /* translators: %s: Percentage of temporary tables created on disk */
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
                        sprintf(
                            /* translators: %s: Cache hit ratio percentage */
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
     * Analyze query patterns from debug log and performance data
     */
    private function analyze_query_patterns(&$analysis_results) {
        // Only analyze if we have performance data available
        if (!isset($analysis_results['performance_data']) || 
            !isset($analysis_results['performance_data']['slow_queries'])) {
            return;
        }
        
        $slow_queries_data = $analysis_results['performance_data']['slow_queries'];
        
        // Only proceed if slow query data is available and has queries
        if (!$slow_queries_data['available'] || empty($slow_queries_data['queries'])) {
            return;
        }
        
        // Initialize query patterns
        $analysis_results['query_patterns'] = [
            'slow_queries' => []
        ];
        
        // Analyze actual slow queries
        foreach ($slow_queries_data['queries'] as $query_data) {
            if (isset($query_data['query']) && isset($query_data['time'])) {
                $query = $query_data['query'];
                $time = floatval($query_data['time']);
                
                // Skip very short queries
                if ($time < 1.0) {
                    continue;
                }
                
                $recommendation = '';
                
                // Analyze the actual query for optimization opportunities
                if (stripos($query, 'WHERE') !== false && stripos($query, 'ORDER BY') !== false) {
                    $recommendation = esc_html__('This query uses WHERE and ORDER BY clauses. Consider adding composite indexes on the filtered and sorted columns.', 'ai-database-optimizer');
                } elseif (stripos($query, 'JOIN') !== false) {
                    $recommendation = esc_html__('This query uses JOIN operations. Ensure all join columns have proper indexes.', 'ai-database-optimizer');
                } elseif (stripos($query, 'GROUP BY') !== false) {
                    $recommendation = esc_html__('This query uses GROUP BY. Consider adding indexes on the grouped columns.', 'ai-database-optimizer');
                } elseif (stripos($query, 'ORDER BY') !== false) {
                    $recommendation = esc_html__('This query uses ORDER BY. Consider adding an index on the sorted column(s).', 'ai-database-optimizer');
                } elseif (stripos($query, 'WHERE') !== false) {
                    $recommendation = esc_html__('This query uses WHERE clause. Consider adding indexes on the filtered columns.', 'ai-database-optimizer');
                }
                
                // Normalize query pattern by removing specific values
                $normalized_query = preg_replace('/\'[^\']*\'/', "'%s'", $query);
                $normalized_query = preg_replace('/\d+/', '%d', $normalized_query);
                
                $analysis_results['query_patterns']['slow_queries'][] = [
                    'query_pattern' => $normalized_query,
                    'avg_execution_time' => $time,
                    'recommendation' => $recommendation,
                    'original_query' => $query
                ];
            }
        }
    }
    
    /**
     * Identify correlations between tables based on foreign key relationships and naming patterns
     */
    private function identify_table_correlations(&$analysis_results) {
        global $wpdb;
        
        $correlations = [];
        
        // Get all WordPress tables with caching
        $cache_key = 'wp_tables_list';
        $tables = wp_cache_get($cache_key, $this->cache_group);
        
        if (false === $tables) {
            $tables = $wpdb->get_col($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SHOW TABLES LIKE %s",
                $wpdb->esc_like($wpdb->prefix) . '%'
            ));
            wp_cache_set($cache_key, $tables, $this->cache_group, $this->cache_expiry);
        }
        
        // Analyze standard WordPress table relationships
        $standard_relationships = [
            $wpdb->posts => [
                $wpdb->postmeta => 'high',
                $wpdb->comments => 'medium'
            ],
            $wpdb->users => [
                $wpdb->usermeta => 'high'
            ],
            $wpdb->terms => [
                $wpdb->term_taxonomy => 'high',
                $wpdb->term_relationships => 'medium'
            ]
        ];
        
        // Check for actual table relationships
        foreach ($standard_relationships as $primary_table => $related_tables) {
            if (in_array($primary_table, $tables)) {
                foreach ($related_tables as $related_table => $strength) {
                    if (in_array($related_table, $tables)) {
                        $correlation_key = basename($primary_table) . '_' . basename($related_table);
                        $correlations[$correlation_key] = [
                            'strength' => $strength,
                            'description' => sprintf(
                                /* translators: 1: Primary table name, 2: Related table name, 3: Correlation strength */
                                esc_html__('%1$s correlation between %2$s and %3$s tables', 'ai-database-optimizer'),
                                ucfirst($strength),
                                basename($primary_table),
                                basename($related_table)
                            ),
                        ];
                    }
                }
            }
        }
        
        // Only set correlations if we found actual relationships
        if (!empty($correlations)) {
            $analysis_results['table_correlations'] = $correlations;
        }
    }
    
    /**
     * Generate AI-based recommendations
     */
    private function generate_ai_recommendations(&$analysis_results) {
        global $wpdb;
        
        $recommendations = [];
        
        // Check recent optimization history to avoid repeating completed recommendations
        $recent_optimizations = $this->get_recent_optimizations();
        $completed_actions = [];
        $completed_indexes = [];
        
        foreach ($recent_optimizations as $optimization) {
            if (!empty($optimization->optimization_actions)) {
                $actions = json_decode($optimization->optimization_actions, true);
                if (is_array($actions)) {
                    foreach ($actions as $action) {
                        $completed_actions[] = $action['type'] ?? '';
                        
                        // Track specific indexes that were created
                        if (($action['type'] ?? '') === 'add_index' && 
                            isset($action['table']) && isset($action['columns'])) {
                            $key = $action['table'] . '|' . implode(',', $action['columns']);
                            $completed_indexes[] = $key;
                        }
                    }
                }
            }
        }
        
        // Only recommend index optimization if it hasn't been done recently and for specific indexes
        $needs_index_optimization = false;
        foreach ($analysis_results as $table => $table_data) {
            if (is_array($table_data) && isset($table_data['suggestions'])) {
                foreach ($table_data['suggestions'] as $suggestion) {
                    if ($suggestion['type'] === 'add_index' && $suggestion['priority'] === 'high') {
                        // Check if this specific index was already created recently
                        $suggestion_columns = $suggestion['composite_columns'] ?? [$suggestion['column']];
                        $suggestion_key = $table . '|' . implode(',', $suggestion_columns);
                        
                        if (!in_array($suggestion_key, $completed_indexes)) {
                            $needs_index_optimization = true;
                            break 2;
                        }
                    }
                }
            }
        }
        
        if ($needs_index_optimization && !in_array('add_index', $completed_actions)) {
            $recommendations[] = [
                'type' => 'index_optimization',
                'description' => esc_html__('Create indexes for frequently used query patterns', 'ai-database-optimizer'),
                'priority' => 'high',
                'expected_impact' => '20-30% faster queries',
            ];
        }
        
        // Only recommend table optimization if overhead is found
        $has_overhead = false;
        foreach ($analysis_results as $table => $table_data) {
            if (is_array($table_data) && isset($table_data['overhead']) && $table_data['overhead'] > 1024 * 1024) {
                $has_overhead = true;
                break;
            }
        }
        
        if ($has_overhead && !in_array('optimize_table', $completed_actions)) {
            $recommendations[] = [
                'type' => 'table_optimization',
                'description' => esc_html__('Optimize tables to remove overhead and improve performance', 'ai-database-optimizer'),
                'priority' => 'medium',
                'expected_impact' => '10-15% performance improvement',
            ];
        }
        
        // Check for post revisions cleanup
        $revision_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        
        if ($revision_count > 100 && !in_array('clean_post_revisions', $completed_actions)) {
            $recommendations[] = [
                'type' => 'data_cleanup',
                'description' => esc_html(
                    sprintf(
                        /* translators: %d: Number of post revisions */
                        __('Clean up %d post revisions to reduce database size', 'ai-database-optimizer'),
                        $revision_count
                    )
                ),
                'priority' => 'medium',
                'expected_impact' => '5-10% database size reduction',
            ];
        }
        
        // Check for expired transients
        $expired_transients = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COUNT(*) FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            AND option_value < %d",
            $wpdb->esc_like('_transient_timeout_') . '%',
            time()
        ));
        
        if ($expired_transients > 50 && !in_array('clean_expired_transients', $completed_actions)) {
            $recommendations[] = [
                'type' => 'cache_cleanup',
                'description' => esc_html(
                    sprintf(
                        /* translators: %d: Number of expired transients */
                        __('Remove %d expired transients to improve performance', 'ai-database-optimizer'),
                        $expired_transients
                    )
                ),
                'priority' => 'low',
                'expected_impact' => '2-5% performance improvement',
            ];
        }
        
        // If no specific recommendations, provide general advice
        if (empty($recommendations)) {
            $last_optimization = $this->get_last_optimization_time();
            
            if ($last_optimization && (time() - strtotime($last_optimization)) > WEEK_IN_SECONDS) {
                $recommendations[] = [
                    'type' => 'maintenance',
                    'description' => esc_html__('Your database appears to be well optimized. Consider running optimization weekly for maintenance.', 'ai-database-optimizer'),
                    'priority' => 'low',
                    'expected_impact' => 'Continued optimal performance',
                ];
            } else {
                $recommendations[] = [
                    'type' => 'status',
                    'description' => esc_html__('Database is currently optimized. No immediate action required.', 'ai-database-optimizer'),
                    'priority' => 'info',
                    'expected_impact' => 'Maintaining current performance',
                ];
            }
        }
        
        $analysis_results['ai_recommendations'] = $recommendations;
    }
    
    /**
     * Get recent optimization history
     */
    private function get_recent_optimizations($limit = 5) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_db_optimization_history';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if (!$table_exists) {
            return [];
        }
        
        return $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT * FROM `" . esc_sql($table_name) . "` 
            ORDER BY optimization_time DESC 
            LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get last optimization time
     */
    private function get_last_optimization_time() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_db_optimization_history';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if (!$table_exists) {
            return null;
        }
        
        return $wpdb->get_var("SELECT optimization_time FROM `" . esc_sql($table_name) . "` ORDER BY optimization_time DESC LIMIT 1"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}