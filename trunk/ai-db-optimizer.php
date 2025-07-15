<?php
/**
 * AI Database Optimizer
 *
 * @package     AI Database Optimizer
 * @author      Fulgid
 * @copyright   2025 Fulgid Software Solutions Pvt Ltd
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: AI Database Optimizer
 * Plugin URI:  https://fulgid.in/ai-database-optimizer
 * Description: AI-based WordPress database optimization plugin that analyzes and optimizes your database for better performance.
 * Version:     1.1.5
 * Author:      Fulgid
 * Author URI:  https://fulgid.in
 * Text Domain: ai-database-optimizer
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FULGID_AI_DATABASE_OPTIMIZER_VERSION', '1.1.5');
define('FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files - use the actual filename that exists
require_once FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_DIR . 'includes/class-db-ai-optimizer.php';

// Initialize the plugin
function fulgid_ai_db_optimizer_init() {
    // Ensure database tables are up to date
    fulgid_ai_db_optimizer_create_tables();
    
    $plugin = new FULGID_AIDBO_AI_DB_Optimizer();
    $plugin->init();
}
add_action('plugins_loaded', 'fulgid_ai_db_optimizer_init');

// Activation hook
register_activation_hook(__FILE__, 'fulgid_ai_db_optimizer_activate');
function fulgid_ai_db_optimizer_activate() {
    // Check WordPress and PHP version requirements
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(esc_html__('AI Database Optimizer requires WordPress 5.0 or higher.', 'ai-database-optimizer'));
    }
    
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(esc_html__('AI Database Optimizer requires PHP 7.4 or higher.', 'ai-database-optimizer'));
    }
    
    // Create necessary tables and initial settings
    fulgid_ai_db_optimizer_create_tables();
    
    // Set default options with proper sanitization
    $default_settings = [
        'schedule_frequency' => 'weekly',
        'notification_email' => sanitize_email(get_option('admin_email')),
        'auto_optimize' => false,
        'optimization_level' => 'medium',
        'tables_to_exclude' => [],
        'last_optimization' => '',
        'auto_backup' => true,
        'max_backups' => 5,
    ];
    
    add_option('fulgid_ai_db_optimizer_settings', $default_settings);
    
    // Set activation flag for welcome screen
    add_option('fulgid_ai_db_optimizer_activation_redirect', true);
}

/**
 * Create plugin tables
 * 
 * @since 1.1.0
 */
function fulgid_ai_db_optimizer_create_tables() {
    global $wpdb;
    
    // Create optimization history table
    $table_name = $wpdb->prefix . 'ai_db_optimization_history';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$table_name} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        optimization_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        optimization_type varchar(255) NOT NULL,
        tables_affected text NOT NULL,
        performance_impact float NOT NULL,
        recommendations text,
        performance_data longtext,
        PRIMARY KEY (id)
    ) {$charset_collate};";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Check if we need to add the performance_data column to existing table
    $column_exists = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        "SHOW COLUMNS FROM `" . esc_sql($table_name) . "` LIKE %s",
        'performance_data'
    ));
    
    if (empty($column_exists)) {
        // Use direct query with escaped table name since wpdb::prepare doesn't support table names
        $wpdb->query("ALTER TABLE `" . esc_sql($table_name) . "` ADD COLUMN performance_data longtext AFTER recommendations"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }
    
    // Check if we need to add the optimization_actions column
    $actions_column_exists = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        "SHOW COLUMNS FROM `" . esc_sql($table_name) . "` LIKE %s",
        'optimization_actions'
    ));
    
    if (empty($actions_column_exists)) {
        // Use direct query with escaped table name since wpdb::prepare doesn't support table names
        $wpdb->query("ALTER TABLE `" . esc_sql($table_name) . "` ADD COLUMN optimization_actions longtext AFTER performance_data"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }
    
    // Create backup history table
    $backup_table_name = $wpdb->prefix . 'ai_db_backup_history';
    
    $backup_sql = "CREATE TABLE {$backup_table_name} (
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
    ) {$charset_collate};";
    
    dbDelta($backup_sql);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'fulgid_ai_db_optimizer_deactivate');
function fulgid_ai_db_optimizer_deactivate() {
    // Clean up scheduled events
    wp_clear_scheduled_hook('fulgid_ai_db_optimizer_scheduled_optimization');
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'fulgid_ai_db_optimizer_uninstall');
function fulgid_ai_db_optimizer_uninstall() {
    global $wpdb;
    
    // Remove plugin options
    delete_option('fulgid_ai_db_optimizer_settings');
    delete_option('fulgid_ai_db_optimizer_activation_redirect');
    
    // Remove optimization history table
    $table_name = $wpdb->prefix . 'ai_db_optimization_history';
    
    // Validate table name for security (only contains valid characters)
    if (preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        // Use direct query with validated table name since $wpdb->prepare() doesn't work with table names
        $wpdb->query("DROP TABLE IF EXISTS `" . esc_sql($table_name) . "`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
    
    // Remove backup history table
    $backup_table_name = $wpdb->prefix . 'ai_db_backup_history';
    
    // Validate table name for security (only contains valid characters)
    if (preg_match('/^[a-zA-Z0-9_]+$/', $backup_table_name)) {
        // Use direct query with validated table name since $wpdb->prepare() doesn't work with table names
        $wpdb->query("DROP TABLE IF EXISTS `" . esc_sql($backup_table_name) . "`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
    
    // Clean up backup files using WP_Filesystem
    $upload_dir = wp_upload_dir();
    $backup_dir = $upload_dir['basedir'] . '/ai-db-optimizer-backups';
    if (is_dir($backup_dir)) {
        $files = glob($backup_dir . '/*.sql');
        foreach ($files as $file) {
            wp_delete_file($file);
        }
        
        // Use WP_Filesystem to remove directory
        if (WP_Filesystem()) {
            global $wp_filesystem;
            if (is_dir($backup_dir) && count(scandir($backup_dir)) == 2) { // Only . and .. remain
                $wp_filesystem->rmdir($backup_dir);
            }
        }
    }
    
    // Clear any remaining scheduled events
    wp_clear_scheduled_hook('fulgid_ai_db_optimizer_scheduled_optimization');
}

// WordPress automatically loads translations for plugins in the .org directory since 4.6
// No need to manually load textdomain