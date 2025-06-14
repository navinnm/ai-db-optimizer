<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once FULGID_AI_DB_OPTIMIZER_PLUGIN_DIR . 'includes/class-db-analyzer.php';
require_once FULGID_AI_DB_OPTIMIZER_PLUGIN_DIR . 'includes/class-optimization-engine.php';
require_once FULGID_AI_DB_OPTIMIZER_PLUGIN_DIR . 'admin/class-admin-ui.php';

class AI_DB_Optimizer {
    /**
     * The DB analyzer instance
     */
    private $analyzer;
    
    /**
     * The optimization engine instance
     */
    private $optimization_engine;
    
    /**
     * The admin UI instance
     */
    private $admin_ui;
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize components
        $this->analyzer = new DB_Analyzer();
        $this->optimization_engine = new Optimization_Engine();
        $this->admin_ui = new Admin_UI($this->analyzer, $this->optimization_engine);
        
        // Set up hooks
        add_action('admin_init', [$this, 'register_settings']);
        add_action('fulgid_ai_db_optimizer_scheduled_optimization', [$this, 'run_scheduled_optimization']);
        
        // Set up the optimization schedule
        $this->setup_schedule();
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('fulgid_ai_db_optimizer_settings', 'fulgid_ai_db_optimizer_settings');
    }
    
    /**
     * Setup the optimization schedule
     */
    private function setup_schedule() {
        $settings = get_option('fulgid_ai_db_optimizer_settings');
        $frequency = isset($settings['schedule_frequency']) ? $settings['schedule_frequency'] : 'weekly';
        
        // Clear existing schedule
        wp_clear_scheduled_hook('fulgid_ai_db_optimizer_scheduled_optimization');
        
        // Set up new schedule if auto-optimize is enabled
        if (isset($settings['auto_optimize']) && $settings['auto_optimize']) {
            switch ($frequency) {
                case 'daily':
                    $interval = DAY_IN_SECONDS;
                    break;
                case 'weekly':
                    $interval = WEEK_IN_SECONDS;
                    break;
                case 'monthly':
                    $interval = MONTH_IN_SECONDS;
                    break;
                default:
                    $interval = WEEK_IN_SECONDS;
            }
            
            wp_schedule_event(time(), $frequency, 'fulgid_ai_db_optimizer_scheduled_optimization');
        }
    }
    
    /**
     * Run the scheduled optimization
     */
    public function run_scheduled_optimization() {
        $settings = get_option('fulgid_ai_db_optimizer_settings');
        $level = isset($settings['optimization_level']) ? $settings['optimization_level'] : 'medium';
        
        // Analyze database
        $analysis = $this->analyzer->analyze_database();
        
        // Run optimization
        $result = $this->optimization_engine->optimize_database($analysis, $level);
        
        // Update last optimization time
        $settings['last_optimization'] = current_time('mysql');
        update_option('fulgid_ai_db_optimizer_settings', $settings);
        
        // Send notification email if configured
        if (!empty($settings['notification_email'])) {
            $this->send_notification_email($result);
        }
    }
    
    /**
     * Send notification email about optimization results
     */
    
    private function send_notification_email($result) {
        $settings = get_option('fulgid_ai_db_optimizer_settings');
        $to = $settings['notification_email'];
        
        /* translators: %s is the site name */
        $subject = sprintf(__('Database Optimization Report - %s', 'ai-db-optimizer'), get_bloginfo('name'));
        
        /* translators: %s is the current date and time */
        $message = sprintf(__('Database optimization completed on %s.', 'ai-db-optimizer'), current_time('mysql')) . "\n\n";
        
        /* translators: %s is the optimization level (low/medium/high) */
        $message .= sprintf(__('Optimization Level: %s', 'ai-db-optimizer'), $settings['optimization_level']) . "\n";
        
        /* translators: %d is the number of database tables that were affected */
        $message .= sprintf(__('Tables Affected: %d', 'ai-db-optimizer'), count($result['tables_affected'])) . "\n";
        
        /* translators: %.2f is the performance improvement percentage */
        $message .= sprintf(__('Performance Impact: %.2f%%', 'ai-db-optimizer'), $result['performance_impact']) . "\n\n";
        
        if (!empty($result['recommendations'])) {
            $message .= __('Recommendations:', 'ai-db-optimizer') . "\n";
            foreach ($result['recommendations'] as $recommendation) {
                $message .= "- " . $recommendation . "\n";
            }
        }
        
        wp_mail($to, $subject, $message);
    }

    /**
     * Get the analyzer instance
     */
    public function get_analyzer() {
        return $this->analyzer;
    }
    
    /**
     * Get the optimization engine instance
     */
    public function get_optimization_engine() {
        return $this->optimization_engine;
    }
    
    /**
     * Get the admin UI instance
     */
    public function get_admin_ui() {
        return $this->admin_ui;
    }
}