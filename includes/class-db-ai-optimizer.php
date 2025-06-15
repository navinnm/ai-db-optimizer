<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

require_once FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_DIR . 'includes/class-db-analyzer.php';
require_once FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_DIR . 'includes/class-optimization-engine.php';
require_once FULGID_AI_DATABASE_OPTIMIZER_PLUGIN_DIR . 'admin/class-admin-ui.php';

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
     * Register plugin settings with proper sanitization
     */
    public function register_settings() {
        register_setting(
            'fulgid_ai_db_optimizer_settings',
            'fulgid_ai_db_optimizer_settings',
            [
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => [
                    'schedule_frequency' => 'weekly',
                    'notification_email' => get_option('admin_email'),
                    'auto_optimize' => false,
                    'optimization_level' => 'medium',
                    'tables_to_exclude' => [],
                    'last_optimization' => '',
                ]
            ]
        );
    }
    
    /**
     * Sanitize plugin settings
     */
    public function sanitize_settings($input) {
        $sanitized = [];
        
        // Sanitize schedule frequency
        $allowed_frequencies = ['daily', 'weekly', 'monthly'];
        $sanitized['schedule_frequency'] = isset($input['schedule_frequency']) && in_array($input['schedule_frequency'], $allowed_frequencies) 
            ? sanitize_text_field($input['schedule_frequency']) 
            : 'weekly';
        
        // Sanitize notification email
        $sanitized['notification_email'] = isset($input['notification_email']) 
            ? sanitize_email($input['notification_email']) 
            : get_option('admin_email');
        
        // Sanitize auto optimize (boolean)
        $sanitized['auto_optimize'] = isset($input['auto_optimize']) ? (bool) $input['auto_optimize'] : false;
        
        // Sanitize optimization level
        $allowed_levels = ['low', 'medium', 'high'];
        $sanitized['optimization_level'] = isset($input['optimization_level']) && in_array($input['optimization_level'], $allowed_levels)
            ? sanitize_text_field($input['optimization_level'])
            : 'medium';
        
        // Sanitize tables to exclude (array of table names)
        $sanitized['tables_to_exclude'] = [];
        if (isset($input['tables_to_exclude']) && is_array($input['tables_to_exclude'])) {
            foreach ($input['tables_to_exclude'] as $table) {
                $clean_table = sanitize_text_field($table);
                if (!empty($clean_table)) {
                    $sanitized['tables_to_exclude'][] = $clean_table;
                }
            }
        }
        
        // Sanitize last optimization timestamp
        $sanitized['last_optimization'] = isset($input['last_optimization']) 
            ? sanitize_text_field($input['last_optimization']) 
            : '';
        
        return $sanitized;
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
        $to = sanitize_email($settings['notification_email']);
        
        if (empty($to) || !is_email($to)) {
            return false;
        }
        
        /* translators: %s is the site name */
        $subject = sprintf(__('Database Optimization Report - %s', 'ai-database-optimizer'), get_bloginfo('name'));
        
        /* translators: %s is the current date and time */
        $message = sprintf(__('Database optimization completed on %s.', 'ai-database-optimizer'), current_time('mysql')) . "\n\n";
        
        /* translators: %s is the optimization level (low/medium/high) */
        $message .= sprintf(__('Optimization Level: %s', 'ai-database-optimizer'), $settings['optimization_level']) . "\n";
        
        /* translators: %d is the number of database tables that were affected */
        $message .= sprintf(__('Tables Affected: %d', 'ai-database-optimizer'), count($result['tables_affected'])) . "\n";
        
        /* translators: %.2f is the performance improvement percentage */
        $message .= sprintf(__('Performance Impact: %.2f%%', 'ai-database-optimizer'), $result['performance_impact']) . "\n\n";
        
        if (!empty($result['recommendations'])) {
            $message .= __('Recommendations:', 'ai-database-optimizer') . "\n";
            foreach ($result['recommendations'] as $recommendation) {
                $message .= "- " . sanitize_text_field($recommendation) . "\n";
            }
        }
        
        return wp_mail($to, $subject, $message);
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