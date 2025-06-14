<?php

 /**
 * AI Database Optimizer
 *
 * @package     AIContentGuide
 * @author      Fulgid
 * @copyright   2025 Fulgid Software Solutions Pvt Ltd
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: AI Database Optimizer
 * Plugin URI:  https://fulgid.in/ai-content-guide
 * Description: AI-based WordPress database optimization plugin
 * Version:     1.0.0
 * Author:      Fulgid
 * Author URI:  https://fulgid.in
 * Text Domain: ai-db-optimizer
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FULGID_AI_DB_OPTIMIZER_VERSION', '1.0.0');
define('FULGID_AI_DB_OPTIMIZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FULGID_AI_DB_OPTIMIZER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once FULGID_AI_DB_OPTIMIZER_PLUGIN_DIR . 'includes/class-ai-db-optimizer.php';

// Initialize the plugin
function fulgid_ai_db_optimizer_init() {
    $plugin = new AI_DB_Optimizer();
    $plugin->init();
}
add_action('plugins_loaded', 'fulgid_ai_db_optimizer_init');

// Activation hook
register_activation_hook(__FILE__, 'fulgid_ai_db_optimizer_activate');
function fulgid_ai_db_optimizer_activate() {
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
    
    // Set default options
    add_option('fulgid_ai_db_optimizer_settings', [
        'schedule_frequency' => 'weekly',
        'notification_email' => get_option('admin_email'),
        'auto_optimize' => false,
        'optimization_level' => 'medium',
        'tables_to_exclude' => [],
        'last_optimization' => '',
    ]);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'fulgid_ai_db_optimizer_deactivate');
function fulgid_ai_db_optimizer_deactivate() {
    // Clean up scheduled events
    wp_clear_scheduled_hook('fulgid_ai_db_optimizer_scheduled_optimization');
}