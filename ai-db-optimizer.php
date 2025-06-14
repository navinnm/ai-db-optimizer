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
 * Version:     1.0.0
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
define('FULGID_AI_DATABASE_OPTIMIZER_VERSION', '1.0.0');
define('FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files - use the actual filename that exists
require_once FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_DIR . 'includes/class-ai-db-optimizer.php';

// Initialize the plugin
function fulgid_ai_db_optimizer_init() {
    $plugin = new AI_DB_Optimizer();
    $plugin->init();
}
add_action('plugins_loaded', 'fulgid_ai_db_optimizer_init');

// Activation hook
register_activation_hook(__FILE__, 'fulgid_ai_db_optimizer_activate');
function fulgid_ai_db_optimizer_activate() {
    // Check WordPress and PHP version requirements
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('AI Database Optimizer requires WordPress 5.0 or higher.', 'ai-database-optimizer'));
    }
    
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('AI Database Optimizer requires PHP 7.4 or higher.', 'ai-database-optimizer'));
    }
    
    // Create necessary tables and initial settings
    global $wpdb;
    
    // Create optimization history table
    $table_name = $wpdb->prefix . 'ai_db_optimization_history';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        optimization_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        optimization_type varchar(255) NOT NULL,
        tables_affected text NOT NULL,
        performance_impact float NOT NULL,
        recommendations text,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Set default options with proper sanitization
    $default_settings = [
        'schedule_frequency' => 'weekly',
        'notification_email' => sanitize_email(get_option('admin_email')),
        'auto_optimize' => false,
        'optimization_level' => 'medium',
        'tables_to_exclude' => [],
        'last_optimization' => '',
    ];
    
    add_option('fulgid_ai_db_optimizer_settings', $default_settings);
    
    // Set activation flag for welcome screen
    add_option('fulgid_ai_db_optimizer_activation_redirect', true);
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
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Clear any remaining scheduled events
    wp_clear_scheduled_hook('fulgid_ai_db_optimizer_scheduled_optimization');
}

// Load plugin textdomain for translations (only if languages folder exists)
add_action('plugins_loaded', 'fulgid_ai_db_optimizer_load_textdomain');
function fulgid_ai_db_optimizer_load_textdomain() {
    $languages_dir = dirname(plugin_basename(__FILE__)) . '/languages/';
    
    // Only load textdomain if languages directory exists
    if (is_dir(FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_DIR . 'languages/')) {
        load_plugin_textdomain(
            'ai-database-optimizer',
            false,
            $languages_dir
        );
    }
}