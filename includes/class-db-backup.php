<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class FULGID_AIDBO_DB_Backup {
    
    /**
     * Backup directory within uploads
     */
    private $backup_dir = 'ai-db-optimizer-backups';
    
    /**
     * Maximum number of backups to keep
     */
    private $max_backups = 5;
    
    /**
     * Cache group for backup operations
     */
    private $cache_group = 'fulgid_ai_db_backup';
    
    /**
     * Create a database backup before optimization
     */
    public function create_backup($optimization_level = 'medium') {
        global $wpdb;
        
        try {
            // Get upload directory
            $upload_dir = wp_upload_dir();
            $backup_path = $upload_dir['basedir'] . '/' . $this->backup_dir;
            
            // Create backup directory if it doesn't exist
            if (!wp_mkdir_p($backup_path)) {
                error_log('AI DB Optimizer: Could not create backup directory: ' . $backup_path);
                throw new Exception('Could not create backup directory: ' . $backup_path);
            }
            
            // Verify directory is writable using WP_Filesystem
            if (!WP_Filesystem()) {
                throw new Exception('Could not initialize WP_Filesystem');
            }
            
            global $wp_filesystem;
            if (!$wp_filesystem->is_writable($backup_path)) {
                throw new Exception('Backup directory is not writable: ' . $backup_path);
            }
            
            // Generate backup filename with timestamp
            $timestamp = current_time('Y-m-d_H-i-s');
            $backup_filename = sprintf(
                'ai-db-backup_%s_%s.sql',
                $optimization_level,
                $timestamp
            );
            $backup_file = $backup_path . '/' . $backup_filename;
            
            // Get database connection details
            $db_host = DB_HOST;
            $db_name = DB_NAME;
            $db_user = DB_USER;
            $db_password = DB_PASSWORD;
            
            // Get tables to backup based on optimization settings
            $tables_to_backup = $this->get_tables_to_backup($optimization_level);
            
            if (empty($tables_to_backup)) {
                throw new Exception('No tables found to backup');
            }
            
            // Create the backup
            $backup_content = $this->generate_backup_content($tables_to_backup);
            
            // Write backup to file
            $result = file_put_contents($backup_file, $backup_content);
            
            if ($result === false) {
                throw new Exception('Could not write backup file');
            }
            
            // Store backup metadata
            $backup_info = [
                'filename' => $backup_filename,
                'filepath' => $backup_file,
                'timestamp' => current_time('mysql'),
                'optimization_level' => $optimization_level,
                'file_size' => filesize($backup_file),
                'tables_count' => count($tables_to_backup),
                'tables' => $tables_to_backup
            ];
            
            $this->store_backup_metadata($backup_info);
            
            // Clean up old backups
            $this->cleanup_old_backups();
            
            return [
                'success' => true,
                'backup_file' => $backup_filename,
                'backup_path' => $backup_file,
                'file_size' => $this->format_file_size($backup_info['file_size']),
                'tables_count' => count($tables_to_backup)
            ];
            
        } catch (Exception $e) {
            error_log('AI DB Optimizer Backup Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get tables that should be backed up before optimization
     */
    private function get_tables_to_backup($optimization_level) {
        global $wpdb;
        
        $settings = get_option('fulgid_ai_db_optimizer_settings');
        $excluded_tables = isset($settings['tables_to_exclude']) ? $settings['tables_to_exclude'] : [];
        
        // Get all WordPress tables
        $tables = $wpdb->get_col($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->esc_like($wpdb->prefix) . '%'
        ));
        
        // Remove excluded tables
        if (!empty($excluded_tables)) {
            $tables = array_diff($tables, $excluded_tables);
        }
        
        // For high-level optimization, include all tables
        // For medium/low, focus on core WordPress tables that might be affected
        if ($optimization_level !== 'high') {
            $core_tables = [
                $wpdb->posts,
                $wpdb->postmeta,
                $wpdb->options,
                $wpdb->comments,
                $wpdb->commentmeta,
                $wpdb->users,
                $wpdb->usermeta,
                $wpdb->terms,
                $wpdb->term_taxonomy,
                $wpdb->term_relationships
            ];
            
            // Only backup core tables for medium/low optimization
            $tables = array_intersect($tables, $core_tables);
        }
        
        return array_values($tables);
    }
    
    /**
     * Generate SQL backup content for specified tables
     */
    private function generate_backup_content($tables) {
        global $wpdb;
        
        $backup_content = '';
        
        // Add header
        $backup_content .= "-- AI Database Optimizer Backup\n";
        $backup_content .= "-- Generated: " . current_time('mysql') . "\n";
        $backup_content .= "-- WordPress Version: " . get_bloginfo('version') . "\n";
        $backup_content .= "-- Plugin: AI Database Optimizer\n\n";
        
        $backup_content .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $backup_content .= "SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n";
        $backup_content .= "SET AUTOCOMMIT=0;\n";
        $backup_content .= "START TRANSACTION;\n\n";
        
        foreach ($tables as $table) {
            // Validate table name
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                continue;
            }
            
            $backup_content .= $this->backup_table($table);
        }
        
        $backup_content .= "\nCOMMIT;\n";
        $backup_content .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        return $backup_content;
    }
    
    /**
     * Create backup SQL for a single table
     */
    private function backup_table($table) {
        global $wpdb;
        
        $sql = "\n-- Table: $table\n";
        
        // Get table structure
        $create_table = $wpdb->get_row("SHOW CREATE TABLE `" . esc_sql($table) . "`", ARRAY_N);
        
        if ($create_table) {
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $create_table[1] . ";\n\n";
        }
        
        // Get table data in chunks to handle large tables
        $chunk_size = 1000;
        $offset = 0;
        
        do {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `" . esc_sql($table) . "` LIMIT %d OFFSET %d",
                    $chunk_size,
                    $offset
                ),
                ARRAY_A
            );
            
            if (!empty($rows)) {
                $sql .= "INSERT INTO `$table` VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $escaped_values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $escaped_values[] = 'NULL';
                        } else {
                            $escaped_values[] = "'" . esc_sql($value) . "'";
                        }
                    }
                    $values[] = '(' . implode(',', $escaped_values) . ')';
                }
                
                $sql .= implode(",\n", $values) . ";\n\n";
            }
            
            $offset += $chunk_size;
            
        } while (count($rows) === $chunk_size);
        
        return $sql;
    }
    
    /**
     * Store backup metadata in database
     */
    private function store_backup_metadata($backup_info) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_db_backup_history';
        
        // Create table if it doesn't exist
        $this->create_backup_history_table();
        
        $wpdb->insert(
            $table_name,
            [
                'backup_filename' => $backup_info['filename'],
                'backup_filepath' => $backup_info['filepath'],
                'backup_time' => $backup_info['timestamp'],
                'optimization_level' => $backup_info['optimization_level'],
                'file_size' => $backup_info['file_size'],
                'tables_count' => $backup_info['tables_count'],
                'tables_list' => wp_json_encode($backup_info['tables'])
            ],
            [
                '%s', '%s', '%s', '%s', '%d', '%d', '%s'
            ]
        );
    }
    
    /**
     * Create backup history table
     */
    private function create_backup_history_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_db_backup_history';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            backup_filename varchar(255) NOT NULL,
            backup_filepath varchar(500) NOT NULL,
            backup_time datetime DEFAULT CURRENT_TIMESTAMP,
            optimization_level varchar(50) NOT NULL,
            file_size bigint(20) DEFAULT 0,
            tables_count int(11) DEFAULT 0,
            tables_list longtext,
            is_restored tinyint(1) DEFAULT 0,
            restored_time datetime NULL,
            PRIMARY KEY (id),
            KEY backup_time (backup_time),
            KEY optimization_level (optimization_level)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get list of available backups
     */
    public function get_backup_history($limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_db_backup_history';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            ORDER BY backup_time DESC 
            LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Restore database from backup
     */
    public function restore_backup($backup_id) {
        global $wpdb;
        
        try {
            // Get backup info
            $table_name = $wpdb->prefix . 'ai_db_backup_history';
            $backup = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $backup_id
            ));
            
            if (!$backup) {
                throw new Exception('Backup not found');
            }
            
            // Check if backup file exists
            if (!file_exists($backup->backup_filepath)) {
                throw new Exception('Backup file not found');
            }
            
            // Read backup content
            $backup_content = file_get_contents($backup->backup_filepath);
            
            if ($backup_content === false) {
                throw new Exception('Could not read backup file');
            }
            
            // Execute backup SQL
            $queries = explode(';', $backup_content);
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query) && !preg_match('/^--/', $query)) {
                    $result = $wpdb->query($query);
                    if ($result === false) {
                        throw new Exception('Error executing restore query: ' . $wpdb->last_error);
                    }
                }
            }
            
            // Update backup as restored
            $wpdb->update(
                $table_name,
                [
                    'is_restored' => 1,
                    'restored_time' => current_time('mysql')
                ],
                ['id' => $backup_id],
                ['%d', '%s'],
                ['%d']
            );
            
            return [
                'success' => true,
                'message' => 'Database restored successfully from backup'
            ];
            
        } catch (Exception $e) {
            error_log('AI DB Optimizer Restore Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up old backup files to save disk space
     */
    private function cleanup_old_backups() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ai_db_backup_history';
        
        // Get backups older than max_backups
        $old_backups = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            ORDER BY backup_time DESC 
            LIMIT %d, 999999",
            $this->max_backups
        ));
        
        foreach ($old_backups as $backup) {
            // Delete file if it exists
            if (file_exists($backup->backup_filepath)) {
                wp_delete_file($backup->backup_filepath);
            }
            
            // Remove from database
            $wpdb->delete(
                $table_name,
                ['id' => $backup->id],
                ['%d']
            );
        }
    }
    
    /**
     * Format file size for display
     */
    private function format_file_size($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Check if backup is required before optimization
     */
    public function is_backup_required($optimization_level) {
        // Always require backup for high-level optimization
        if ($optimization_level === 'high') {
            return true;
        }
        
        // Check settings for other levels - default to true for safety
        $settings = get_option('fulgid_ai_db_optimizer_settings');
        if (!$settings || !is_array($settings)) {
            return true; // Default to backup if no settings found
        }
        
        return isset($settings['auto_backup']) ? (bool) $settings['auto_backup'] : true;
    }
    
    /**
     * Get backup directory info
     */
    public function get_backup_directory_info() {
        $upload_dir = wp_upload_dir();
        $backup_path = $upload_dir['basedir'] . '/' . $this->backup_dir;
        
        $info = [
            'path' => $backup_path,
            'exists' => is_dir($backup_path),
            'writable' => is_writable($backup_path),
            'total_size' => 0,
            'file_count' => 0
        ];
        
        if ($info['exists']) {
            $files = glob($backup_path . '/*.sql');
            $info['file_count'] = count($files);
            
            foreach ($files as $file) {
                $info['total_size'] += filesize($file);
            }
        }
        
        return $info;
    }
}