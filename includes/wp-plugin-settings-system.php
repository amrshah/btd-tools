<?php
/**
 * Plugin Settings System with Custom Database Table and Modern Admin UI
 * 
 * Features:
 * - Custom database table for settings
 * - Sidebar navigation with card-based layout
 * - Tab interface within cards
 * - Pure HTML/CSS/JS (no frameworks)
 * - AJAX saving with visual feedback
 * 
 * @package YourPlugin
 * @version 1.0.0
 */

// ============================================
// 1. DATABASE HANDLER CLASS
// ============================================

class Plugin_Settings_DB {
    
    private $table_name;
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'btdtools_settings';
    }
    
    /**
     * Create custom settings table
     */
    public function create_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_key varchar(191) NOT NULL,
            setting_value longtext NOT NULL,
            setting_group varchar(100) DEFAULT 'general',
            autoload tinyint(1) DEFAULT 1,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key),
            KEY setting_group (setting_group),
            KEY autoload (autoload)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get setting value
     */
    public function get($key, $default = null) {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT setting_value FROM {$this->table_name} WHERE setting_key = %s",
                $key
            )
        );
        
        if ($result !== null) {
            $decoded = json_decode($result, true);
            return ($decoded !== null) ? $decoded : $result;
        }
        
        return $default;
    }
    
    /**
     * Get all settings by group
     */
    public function get_group($group) {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT setting_key, setting_value FROM {$this->table_name} WHERE setting_group = %s",
                $group
            ),
            ARRAY_A
        );
        
        $settings = [];
        foreach ($results as $row) {
            $decoded = json_decode($row['setting_value'], true);
            $settings[$row['setting_key']] = ($decoded !== null) ? $decoded : $row['setting_value'];
        }
        
        return $settings;
    }
    
    /**
     * Set setting value
     */
    public function set($key, $value, $group = 'general', $autoload = 1) {
        $encoded_value = is_array($value) || is_object($value) ? json_encode($value) : $value;
        
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE setting_key = %s",
                $key
            )
        );
        
        if ($existing) {
            return $this->wpdb->update(
                $this->table_name,
                [
                    'setting_value' => $encoded_value,
                    'setting_group' => $group,
                    'autoload' => $autoload
                ],
                ['setting_key' => $key],
                ['%s', '%s', '%d'],
                ['%s']
            );
        } else {
            return $this->wpdb->insert(
                $this->table_name,
                [
                    'setting_key' => $key,
                    'setting_value' => $encoded_value,
                    'setting_group' => $group,
                    'autoload' => $autoload
                ],
                ['%s', '%s', '%s', '%d']
            );
        }
    }
    
    /**
     * Delete setting
     */
    public function delete($key) {
        return $this->wpdb->delete(
            $this->table_name,
            ['setting_key' => $key],
            ['%s']
        );
    }
    
    /**
     * Delete entire group
     */
    public function delete_group($group) {
        return $this->wpdb->delete(
            $this->table_name,
            ['setting_group' => $group],
            ['%s']
        );
    }
    
    /**
     * Get all settings (for export)
     */
    public function get_all_settings() {
        $results = $this->wpdb->get_results(
            "SELECT setting_key, setting_value, setting_group FROM {$this->table_name}",
            ARRAY_A
        );
        
        $settings = [];
        foreach ($results as $row) {
            $decoded = json_decode($row['setting_value'], true);
            $settings[] = [
                'key' => $row['setting_key'],
                'value' => ($decoded !== null) ? $decoded : $row['setting_value'],
                'group' => $row['setting_group']
            ];
        }
        
        return $settings;
    }
    
    /**
     * Clear all settings
     */
    public function clear_all() {
        return $this->wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }
}

// ============================================
// 2. SETTINGS MANAGER CLASS
// ============================================

class Plugin_Settings_Manager {
    
    private $db;
    private $cache = [];
    
    public function __construct(Plugin_Settings_DB $db) {
        $this->db = $db;
    }
    
    /**
     * Get setting with caching
     */
    public function get($key, $default = null) {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        
        $value = $this->db->get($key, $default);
        $this->cache[$key] = $value;
        
        return $value;
    }
    
    /**
     * Get all settings in a group
     */
    public function get_group($group) {
        return $this->db->get_group($group);
    }
    
    /**
     * Set setting value
     */
    public function set($key, $value, $group = 'general') {
        $result = $this->db->set($key, $value, $group);
        
        if ($result) {
            $this->cache[$key] = $value;
        }
        
        return $result;
    }
    
    /**
     * Update multiple settings at once
     */
    public function update_group($group, $data) {
        $success = true;
        
        foreach ($data as $key => $value) {
            if (!$this->set($key, $value, $group)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Delete setting
     */
    public function delete($key) {
        unset($this->cache[$key]);
        return $this->db->delete($key);
    }
    
    /**
     * Export all settings to JSON
     */
    public function export_settings() {
        $settings = $this->db->get_all_settings();
        
        $export_data = [
            'version' => '1.0.0',
            'exported_at' => current_time('mysql'),
            'site_url' => get_site_url(),
            'settings' => $settings
        ];
        
        return json_encode($export_data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import settings from JSON
     */
    public function import_settings($json_data, $overwrite = true) {
        $data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'message' => 'Invalid JSON format'];
        }
        
        if (!isset($data['settings']) || !is_array($data['settings'])) {
            return ['success' => false, 'message' => 'Invalid settings format'];
        }
        
        $imported = 0;
        $skipped = 0;
        
        foreach ($data['settings'] as $setting) {
            if (!isset($setting['key']) || !isset($setting['value'])) {
                continue;
            }
            
            // Check if setting exists
            $exists = $this->db->get($setting['key']) !== null;
            
            if ($exists && !$overwrite) {
                $skipped++;
                continue;
            }
            
            $group = isset($setting['group']) ? $setting['group'] : 'general';
            
            if ($this->set($setting['key'], $setting['value'], $group)) {
                $imported++;
            }
        }
        
        return [
            'success' => true,
            'message' => "Successfully imported {$imported} settings" . ($skipped > 0 ? " ({$skipped} skipped)" : ""),
            'imported' => $imported,
            'skipped' => $skipped
        ];
    }
    
    /**
     * Reset settings to defaults
     */
    public function reset_to_defaults($defaults = []) {
        // Clear all existing settings
        $this->db->clear_all();
        $this->cache = [];
        
        // Import defaults if provided
        if (!empty($defaults)) {
            foreach ($defaults as $group => $settings) {
                foreach ($settings as $key => $value) {
                    $this->set($key, $value, $group);
                }
            }
            return ['success' => true, 'message' => 'Settings reset to defaults'];
        }
        
        return ['success' => true, 'message' => 'All settings cleared'];
    }
    
    /**
     * Get preset configurations
     */
    public function get_presets() {
        return [
            'development' => [
                'name' => 'Development Environment',
                'description' => 'Optimized for local development with debugging enabled',
                'settings' => [
                    'general' => [
                        'debug_mode' => 1,
                        'cache_duration' => 0,
                        'enable_plugin' => 1
                    ],
                    'api' => [
                        'api_base_url' => 'http://localhost:8000/api',
                        'api_timeout' => 60,
                        'verify_ssl' => 0
                    ],
                    'advanced' => [
                        'enable_caching' => 0,
                        'memory_limit' => 256
                    ]
                ]
            ],
            'staging' => [
                'name' => 'Staging Environment',
                'description' => 'Pre-production testing environment',
                'settings' => [
                    'general' => [
                        'debug_mode' => 1,
                        'cache_duration' => 1800,
                        'enable_plugin' => 1
                    ],
                    'api' => [
                        'api_base_url' => 'https://staging-api.example.com',
                        'api_timeout' => 30,
                        'verify_ssl' => 1
                    ],
                    'advanced' => [
                        'enable_caching' => 1,
                        'memory_limit' => 256
                    ]
                ]
            ],
            'production' => [
                'name' => 'Production Environment',
                'description' => 'Optimized for live production site',
                'settings' => [
                    'general' => [
                        'debug_mode' => 0,
                        'cache_duration' => 3600,
                        'enable_plugin' => 1
                    ],
                    'api' => [
                        'api_base_url' => 'https://api.example.com',
                        'api_timeout' => 30,
                        'verify_ssl' => 1
                    ],
                    'advanced' => [
                        'enable_caching' => 1,
                        'lazy_loading' => 1,
                        'memory_limit' => 128,
                        'auto_updates' => 1
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Apply preset configuration
     */
    public function apply_preset($preset_name) {
        $presets = $this->get_presets();
        
        if (!isset($presets[$preset_name])) {
            return ['success' => false, 'message' => 'Preset not found'];
        }
        
        $preset = $presets[$preset_name];
        $applied = 0;
        
        foreach ($preset['settings'] as $group => $settings) {
            foreach ($settings as $key => $value) {
                if ($this->set($key, $value, $group)) {
                    $applied++;
                }
            }
        }
        
        return [
            'success' => true,
            'message' => "Applied '{$preset['name']}' preset with {$applied} settings"
        ];
    }
    
    /**
     * Sanitize based on field type
     */
    public function sanitize($value, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            case 'number':
                return intval($value);
            case 'checkbox':
                return !empty($value) ? 1 : 0;
            case 'html':
                return wp_kses_post($value);
            case 'textarea':
                return sanitize_textarea_field($value);
            default:
                return sanitize_text_field($value);
        }
    }
}

// ============================================
// 3. ADMIN UI CLASS
// ============================================

class Plugin_Settings_Admin {
    
    private $manager;
    private $page_slug = 'plugin-settings';
    
    public function __construct(Plugin_Settings_Manager $manager) {
        $this->manager = $manager;
        
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_save_plugin_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_export_plugin_settings', [$this, 'ajax_export_settings']);
        add_action('wp_ajax_import_plugin_settings', [$this, 'ajax_import_settings']);
        add_action('wp_ajax_reset_plugin_settings', [$this, 'ajax_reset_settings']);
        add_action('wp_ajax_apply_preset', [$this, 'ajax_apply_preset']);
    }
    
    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        add_menu_page(
            'Plugin Settings',
            'Plugin Settings',
            'manage_options',
            $this->page_slug,
            [$this, 'render_settings_page'],
            'dashicons-admin-settings',
            30
        );
    }
    
    /**
     * Enqueue CSS and JS
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, $this->page_slug) === false) {
            return;
        }
        
        // Inline CSS
        wp_add_inline_style('wp-admin', $this->get_inline_css());
        
        // Inline JS
        wp_enqueue_script('plugin-settings-js', '', [], '1.0', true);
        wp_add_inline_script('plugin-settings-js', $this->get_inline_js());
        
        // Localize script for AJAX
        wp_localize_script('plugin-settings-js', 'pluginSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('plugin_settings_nonce')
        ]);
    }
    
    /**
     * Get inline CSS
     */
    private function get_inline_css() {
        return '
        .plugin-settings-wrapper {
            display: flex;
            min-height: 100vh;
            background: #f0f0f1;
            margin-left: -20px;
            margin-top: -10px;
        }
        
        .plugin-settings-sidebar {
            width: 260px;
            background: #fff;
            border-right: 1px solid #dcdcde;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 8px rgba(0,0,0,0.05);
        }
        
        .plugin-settings-sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid #dcdcde;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        
        .plugin-settings-sidebar-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #fff;
        }
        
        .plugin-settings-nav {
            padding: 12px 0;
        }
        
        .plugin-settings-nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #50575e;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            cursor: pointer;
        }
        
        .plugin-settings-nav-item:hover {
            background: #f6f7f7;
            color: #2271b1;
        }
        
        .plugin-settings-nav-item.active {
            background: #f0f6fc;
            color: #2271b1;
            border-left-color: #2271b1;
            font-weight: 600;
        }
        
        .plugin-settings-nav-item-icon {
            margin-right: 12px;
            font-size: 18px;
        }
        
        .plugin-settings-content {
            margin-left: 260px;
            flex: 1;
            padding: 32px;
            max-width: 1200px;
        }
        
        .plugin-settings-header {
            margin-bottom: 32px;
        }
        
        .plugin-settings-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: #1d2327;
        }
        
        .plugin-settings-header p {
            margin: 0;
            color: #646970;
            font-size: 14px;
        }
        
        .plugin-settings-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 24px;
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }
        
        .plugin-settings-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .plugin-settings-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #dcdcde;
            background: #fafafa;
        }
        
        .plugin-settings-card-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1d2327;
        }
        
        .plugin-settings-card-title-icon {
            font-size: 22px;
        }
        
        .plugin-settings-tabs {
            display: flex;
            gap: 0;
            border-bottom: 1px solid #dcdcde;
            background: #fff;
            padding: 0 24px;
        }
        
        .plugin-settings-tab {
            padding: 14px 20px;
            background: none;
            border: none;
            color: #646970;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .plugin-settings-tab:hover {
            color: #2271b1;
            background: #f6f7f7;
        }
        
        .plugin-settings-tab.active {
            color: #2271b1;
            border-bottom-color: #2271b1;
            background: #f0f6fc;
        }
        
        .plugin-settings-tab-content {
            display: none;
            padding: 24px;
            animation: fadeIn 0.3s ease;
        }
        
        .plugin-settings-tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .plugin-settings-form-group {
            margin-bottom: 20px;
        }
        
        .plugin-settings-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1d2327;
            font-size: 14px;
        }
        
        .plugin-settings-form-group input[type="text"],
        .plugin-settings-form-group input[type="email"],
        .plugin-settings-form-group input[type="url"],
        .plugin-settings-form-group input[type="number"],
        .plugin-settings-form-group textarea,
        .plugin-settings-form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }
        
        .plugin-settings-form-group input:focus,
        .plugin-settings-form-group textarea:focus,
        .plugin-settings-form-group select:focus {
            outline: none;
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
        }
        
        .plugin-settings-form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .plugin-settings-form-group small {
            display: block;
            margin-top: 6px;
            color: #646970;
            font-size: 13px;
        }
        
        .plugin-settings-checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .plugin-settings-checkbox-wrapper input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .plugin-settings-save-btn {
            background: #2271b1;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .plugin-settings-save-btn:hover {
            background: #135e96;
        }
        
        .plugin-settings-save-btn:disabled {
            background: #8c8f94;
            cursor: not-allowed;
        }
        
        .plugin-settings-save-btn.saving {
            opacity: 0.7;
        }
        
        .plugin-settings-notification {
            position: fixed;
            top: 32px;
            right: 32px;
            padding: 16px 20px;
            background: #fff;
            border-left: 4px solid;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            align-items: center;
            gap: 12px;
            z-index: 9999;
            animation: slideInRight 0.3s ease;
            min-width: 300px;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .plugin-settings-notification.show {
            display: flex;
        }
        
        .plugin-settings-notification.success {
            border-left-color: #00a32a;
        }
        
        .plugin-settings-notification.error {
            border-left-color: #d63638;
        }
        
        .plugin-settings-notification-icon {
            font-size: 20px;
        }
        
        .plugin-settings-notification.success .plugin-settings-notification-icon {
            color: #00a32a;
        }
        
        .plugin-settings-notification.error .plugin-settings-notification-icon {
            color: #d63638;
        }
        
        .plugin-settings-notification-message {
            flex: 1;
            font-size: 14px;
            color: #1d2327;
        }
        
        .plugin-settings-tools-section {
            background: #fff;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .plugin-settings-tools-section h3 {
            margin: 0 0 16px 0;
            font-size: 16px;
            font-weight: 600;
            color: #1d2327;
        }
        
        .plugin-settings-tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 16px;
        }
        
        .plugin-settings-tool-card {
            border: 1px solid #dcdcde;
            border-radius: 6px;
            padding: 20px;
            transition: all 0.2s ease;
        }
        
        .plugin-settings-tool-card:hover {
            border-color: #2271b1;
            box-shadow: 0 2px 8px rgba(34, 113, 177, 0.1);
        }
        
        .plugin-settings-tool-card h4 {
            margin: 0 0 8px 0;
            font-size: 15px;
            font-weight: 600;
            color: #1d2327;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .plugin-settings-tool-card p {
            margin: 0 0 16px 0;
            font-size: 13px;
            color: #646970;
        }
        
        .plugin-settings-tool-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .plugin-settings-btn {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .plugin-settings-btn-primary {
            background: #2271b1;
            color: #fff;
        }
        
        .plugin-settings-btn-primary:hover {
            background: #135e96;
        }
        
        .plugin-settings-btn-secondary {
            background: #f0f0f1;
            color: #2c3338;
            border: 1px solid #8c8f94;
        }
        
        .plugin-settings-btn-secondary:hover {
            background: #dcdcde;
        }
        
        .plugin-settings-btn-danger {
            background: #d63638;
            color: #fff;
        }
        
        .plugin-settings-btn-danger:hover {
            background: #b32d2e;
        }
        
        .plugin-settings-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .plugin-settings-file-input {
            display: none;
        }
        
        .plugin-settings-preset-list {
            display: grid;
            gap: 12px;
            margin-top: 16px;
        }
        
        .plugin-settings-preset-item {
            border: 1px solid #dcdcde;
            border-radius: 6px;
            padding: 16px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .plugin-settings-preset-item:hover {
            border-color: #2271b1;
            background: #f0f6fc;
        }
        
        .plugin-settings-preset-item.selected {
            border-color: #2271b1;
            background: #f0f6fc;
            box-shadow: 0 0 0 1px #2271b1;
        }
        
        .plugin-settings-preset-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .plugin-settings-preset-name {
            font-weight: 600;
            color: #1d2327;
            font-size: 14px;
        }
        
        .plugin-settings-preset-badge {
            background: #2271b1;
            color: #fff;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .plugin-settings-preset-description {
            font-size: 13px;
            color: #646970;
            margin: 0;
        }
        
        .plugin-settings-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 99999;
            align-items: center;
            justify-content: center;
        }
        
        .plugin-settings-modal.show {
            display: flex;
        }
        
        .plugin-settings-modal-content {
            background: #fff;
            border-radius: 8px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            animation: modalFadeIn 0.3s ease;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .plugin-settings-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #dcdcde;
        }
        
        .plugin-settings-modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        .plugin-settings-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #646970;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }
        
        .plugin-settings-modal-close:hover {
            background: #f0f0f1;
            color: #1d2327;
        }
        
        .plugin-settings-modal-body {
            margin-bottom: 20px;
        }
        
        .plugin-settings-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding-top: 16px;
            border-top: 1px solid #dcdcde;
        }
        
        @media (max-width: 768px) {
            .plugin-settings-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1000;
            }
            
            .plugin-settings-sidebar.open {
                transform: translateX(0);
            }
            
            .plugin-settings-content {
                margin-left: 0;
                padding: 16px;
            }
            
            .plugin-settings-tabs {
                overflow-x: auto;
            }
        }
        ';
    }
    
    /**
     * Get inline JS
     */
    private function get_inline_js() {
        return '
        (function() {
            // Tab functionality
            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("plugin-settings-tab")) {
                    const card = e.target.closest(".plugin-settings-card");
                    const tabs = card.querySelectorAll(".plugin-settings-tab");
                    const contents = card.querySelectorAll(".plugin-settings-tab-content");
                    
                    tabs.forEach(tab => tab.classList.remove("active"));
                    contents.forEach(content => content.classList.remove("active"));
                    
                    e.target.classList.add("active");
                    const targetId = e.target.dataset.tab;
                    document.getElementById(targetId).classList.add("active");
                }
            });
            
            // Sidebar navigation
            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("plugin-settings-nav-item")) {
                    const items = document.querySelectorAll(".plugin-settings-nav-item");
                    items.forEach(item => item.classList.remove("active"));
                    e.target.classList.add("active");
                    
                    const targetId = e.target.dataset.target;
                    const targetCard = document.getElementById(targetId);
                    if (targetCard) {
                        targetCard.scrollIntoView({ behavior: "smooth", block: "start" });
                    }
                }
            });
            
            // Save settings via AJAX
            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("plugin-settings-save-btn")) {
                    e.preventDefault();
                    
                    const btn = e.target;
                    const card = btn.closest(".plugin-settings-card");
                    const form = card.querySelector("form");
                    const formData = new FormData(form);
                    const group = btn.dataset.group;
                    
                    btn.disabled = true;
                    btn.classList.add("saving");
                    btn.textContent = "Saving...";
                    
                    const data = {
                        action: "save_plugin_settings",
                        nonce: pluginSettings.nonce,
                        group: group,
                        settings: {}
                    };
                    
                    for (let [key, value] of formData.entries()) {
                        data.settings[key] = value;
                    }
                    
                    fetch(pluginSettings.ajaxUrl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: new URLSearchParams(data)
                    })
                    .then(response => response.json())
                    .then(result => {
                        btn.disabled = false;
                        btn.classList.remove("saving");
                        btn.textContent = "Save Changes";
                        
                        showNotification(result.success ? "success" : "error", result.data.message);
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.classList.remove("saving");
                        btn.textContent = "Save Changes";
                        
                        showNotification("error", "An error occurred while saving.");
                    });
                }
            });
            
            // Export settings
            document.addEventListener("click", function(e) {
                if (e.target.id === "export-settings-btn") {
                    e.preventDefault();
                    
                    const btn = e.target;
                    btn.disabled = true;
                    btn.textContent = "Exporting...";
                    
                    fetch(pluginSettings.ajaxUrl + "?action=export_plugin_settings&nonce=" + pluginSettings.nonce)
                    .then(response => response.json())
                    .then(result => {
                        btn.disabled = false;
                        btn.textContent = "Export Settings";
                        
                        if (result.success) {
                            // Create and download file
                            const blob = new Blob([result.data.json], { type: "application/json" });
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement("a");
                            a.href = url;
                            a.download = "plugin-settings-" + Date.now() + ".json";
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);
                            
                            showNotification("success", "Settings exported successfully!");
                        } else {
                            showNotification("error", result.data.message);
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.textContent = "Export Settings";
                        showNotification("error", "Export failed");
                    });
                }
            });
            
            // Import settings
            document.addEventListener("click", function(e) {
                if (e.target.id === "import-settings-btn") {
                    e.preventDefault();
                    document.getElementById("import-file-input").click();
                }
            });
            
            document.addEventListener("change", function(e) {
                if (e.target.id === "import-file-input") {
                    const file = e.target.files[0];
                    if (!file) return;
                    
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const btn = document.getElementById("import-settings-btn");
                        btn.disabled = true;
                        btn.textContent = "Importing...";
                        
                        const data = new FormData();
                        data.append("action", "import_plugin_settings");
                        data.append("nonce", pluginSettings.nonce);
                        data.append("json_data", event.target.result);
                        
                        fetch(pluginSettings.ajaxUrl, {
                            method: "POST",
                            body: data
                        })
                        .then(response => response.json())
                        .then(result => {
                            btn.disabled = false;
                            btn.textContent = "Import Settings";
                            e.target.value = "";
                            
                            if (result.success) {
                                showNotification("success", result.data.message);
                                setTimeout(() => location.reload(), 2000);
                            } else {
                                showNotification("error", result.data.message);
                            }
                        })
                        .catch(error => {
                            btn.disabled = false;
                            btn.textContent = "Import Settings";
                            e.target.value = "";
                            showNotification("error", "Import failed");
                        });
                    };
                    reader.readAsText(file);
                }
            });
            
            // Reset to defaults
            document.addEventListener("click", function(e) {
                if (e.target.id === "reset-defaults-btn") {
                    e.preventDefault();
                    
                    if (!confirm("Are you sure you want to reset all settings to defaults? This action cannot be undone.")) {
                        return;
                    }
                    
                    const btn = e.target;
                    btn.disabled = true;
                    btn.textContent = "Resetting...";
                    
                    const data = new URLSearchParams({
                        action: "reset_plugin_settings",
                        nonce: pluginSettings.nonce
                    });
                    
                    fetch(pluginSettings.ajaxUrl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: data
                    })
                    .then(response => response.json())
                    .then(result => {
                        btn.disabled = false;
                        btn.textContent = "Reset to Defaults";
                        
                        if (result.success) {
                            showNotification("success", result.data.message);
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showNotification("error", result.data.message);
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.textContent = "Reset to Defaults";
                        showNotification("error", "Reset failed");
                    });
                }
            });
            
            // Preset management
            document.addEventListener("click", function(e) {
                if (e.target.id === "apply-preset-btn") {
                    e.preventDefault();
                    document.getElementById("preset-modal").classList.add("show");
                }
                
                if (e.target.classList.contains("plugin-settings-modal-close") || 
                    e.target.id === "preset-modal-cancel") {
                    document.getElementById("preset-modal").classList.remove("show");
                }
                
                if (e.target.closest(".plugin-settings-preset-item")) {
                    const items = document.querySelectorAll(".plugin-settings-preset-item");
                    items.forEach(item => item.classList.remove("selected"));
                    e.target.closest(".plugin-settings-preset-item").classList.add("selected");
                }
                
                if (e.target.id === "preset-modal-apply") {
                    const selectedPreset = document.querySelector(".plugin-settings-preset-item.selected");
                    if (!selectedPreset) {
                        showNotification("error", "Please select a preset");
                        return;
                    }
                    
                    const presetName = selectedPreset.dataset.preset;
                    const btn = e.target;
                    btn.disabled = true;
                    btn.textContent = "Applying...";
                    
                    const data = new URLSearchParams({
                        action: "apply_preset",
                        nonce: pluginSettings.nonce,
                        preset: presetName
                    });
                    
                    fetch(pluginSettings.ajaxUrl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: data
                    })
                    .then(response => response.json())
                    .then(result => {
                        btn.disabled = false;
                        btn.textContent = "Apply Preset";
                        
                        if (result.success) {
                            document.getElementById("preset-modal").classList.remove("show");
                            showNotification("success", result.data.message);
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showNotification("error", result.data.message);
                        }
                    })
                    .catch(error => {
                        btn.disabled = false;
                        btn.textContent = "Apply Preset";
                        showNotification("error", "Failed to apply preset");
                    });
                }
            });
            
            // Close modal on outside click
            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("plugin-settings-modal")) {
                    e.target.classList.remove("show");
                }
            });
            
            // Show notification
            function showNotification(type, message) {
                const notification = document.querySelector(".plugin-settings-notification");
                notification.className = "plugin-settings-notification " + type + " show";
                notification.querySelector(".plugin-settings-notification-message").textContent = message;
                
                setTimeout(() => {
                    notification.classList.remove("show");
                }, 3000);
            }
        })();
        ';
    }
    
    /**
     * AJAX handler for exporting settings
     */
    public function ajax_export_settings() {
        check_ajax_referer('plugin_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $json = $this->manager->export_settings();
        
        wp_send_json_success([
            'message' => 'Settings exported successfully',
            'json' => $json
        ]);
    }
    
    /**
     * AJAX handler for importing settings
     */
    public function ajax_import_settings() {
        check_ajax_referer('plugin_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        if (!isset($_POST['json_data'])) {
            wp_send_json_error(['message' => 'No data provided']);
        }
        
        $json_data = stripslashes($_POST['json_data']);
        $result = $this->manager->import_settings($json_data, true);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler for resetting settings
     */
    public function ajax_reset_settings() {
        check_ajax_referer('plugin_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $result = $this->manager->reset_to_defaults();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler for applying presets
     */
    public function ajax_apply_preset() {
        check_ajax_referer('plugin_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        if (!isset($_POST['preset'])) {
            wp_send_json_error(['message' => 'No preset specified']);
        }
        
        $preset = sanitize_text_field($_POST['preset']);
        $result = $this->manager->apply_preset($preset);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('plugin_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $group = sanitize_text_field($_POST['group']);
        $settings = $_POST['settings'];
        
        // Sanitize and save each setting
        foreach ($settings as $key => $value) {
            $sanitized_key = sanitize_key($key);
            $sanitized_value = $this->manager->sanitize($value, 'text');
            $this->manager->set($sanitized_key, $sanitized_value, $group);
        }
        
        wp_send_json_success(['message' => 'Settings saved successfully!']);
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="plugin-settings-wrapper">
            <!-- Sidebar -->
            <div class="plugin-settings-sidebar">
                <div class="plugin-settings-sidebar-header">
                    <h2>⚙️ Settings</h2>
                </div>
                <nav class="plugin-settings-nav">
                    <a class="plugin-settings-nav-item active" data-target="card-general">
                        <span class="plugin-settings-nav-item-icon">🏠</span>
                        <span>General</span>
                    </a>
                    <a class="plugin-settings-nav-item" data-target="card-api">
                        <span class="plugin-settings-nav-item-icon">🔌</span>
                        <span>API Settings</span>
                    </a>
                    <a class="plugin-settings-nav-item" data-target="card-appearance">
                        <span class="plugin-settings-nav-item-icon">🎨</span>
                        <span>Appearance</span>
                    </a>
                    <a class="plugin-settings-nav-item" data-target="card-email">
                        <span class="plugin-settings-nav-item-icon">📧</span>
                        <span>Email</span>
                    </a>
                    <a class="plugin-settings-nav-item" data-target="card-advanced">
                        <span class="plugin-settings-nav-item-icon">🔧</span>
                        <span>Advanced</span>
                    </a>
                    <a class="plugin-settings-nav-item" data-target="card-tools">
                        <span class="plugin-settings-nav-item-icon">🛠️</span>
                        <span>Tools</span>
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="plugin-settings-content">
                <div class="plugin-settings-header">
                    <h1>Plugin Settings</h1>
                    <p>Configure your plugin settings below</p>
                </div>
                
                <!-- General Settings Card -->
                <div class="plugin-settings-card" id="card-general">
                    <div class="plugin-settings-card-header">
                        <h3 class="plugin-settings-card-title">
                            <span class="plugin-settings-card-title-icon">⚙️</span>
                            General Settings
                        </h3>
                        <button class="plugin-settings-save-btn" data-group="general">Save Changes</button>
                    </div>
                    
                    <div class="plugin-settings-tabs">
                        <button class="plugin-settings-tab active" data-tab="general-basic">Basic</button>
                        <button class="plugin-settings-tab" data-tab="general-display">Display</button>
                        <button class="plugin-settings-tab" data-tab="general-advanced">Advanced</button>
                    </div>
                    
                    <form>
                        <div class="plugin-settings-tab-content active" id="general-basic">
                            <div class="plugin-settings-form-group">
                                <label>Plugin Name</label>
                                <input type="text" name="plugin_name" value="<?php echo esc_attr($this->manager->get('plugin_name', 'My Plugin')); ?>">
                                <small>Enter your plugin display name</small>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>Description</label>
                                <textarea name="plugin_description"><?php echo esc_textarea($this->manager->get('plugin_description', '')); ?></textarea>
                                <small>Brief description of your plugin</small>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <div class="plugin-settings-checkbox-wrapper">
                                    <input type="checkbox" name="enable_plugin" id="enable_plugin" value="1" <?php checked($this->manager->get('enable_plugin', 1), 1); ?>>
                                    <label for="enable_plugin">Enable Plugin</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="plugin-settings-tab-content" id="general-display">
                            <div class="plugin-settings-form-group">
                                <label>Items Per Page</label>
                                <input type="number" name="items_per_page" value="<?php echo esc_attr($this->manager->get('items_per_page', 10)); ?>" min="1" max="100">
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>Date Format</label>
                                <select name="date_format">
                                    <option value="Y-m-d" <?php selected($this->manager->get('date_format'), 'Y-m-d'); ?>>YYYY-MM-DD</option>
                                    <option value="m/d/Y" <?php selected($this->manager->get('date_format'), 'm/d/Y'); ?>>MM/DD/YYYY</option>
                                    <option value="d/m/Y" <?php selected($this->manager->get('date_format'), 'd/m/Y'); ?>>DD/MM/YYYY</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="plugin-settings-tab-content" id="general-advanced">
                            <div class="plugin-settings-form-group">
                                <div class="plugin-settings-checkbox-wrapper">
                                    <input type="checkbox" name="debug_mode" id="debug_mode" value="1" <?php checked($this->manager->get('debug_mode', 0), 1); ?>>
                                    <label for="debug_mode">Enable Debug Mode</label>
                                </div>
                                <small>Enable detailed logging for troubleshooting</small>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>Cache Duration (seconds)</label>
                                <input type="number" name="cache_duration" value="<?php echo esc_attr($this->manager->get('cache_duration', 3600)); ?>">
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- API Settings Card -->
                <div class="plugin-settings-card" id="card-api">
                    <div class="plugin-settings-card-header">
                        <h3 class="plugin-settings-card-title">
                            <span class="plugin-settings-card-title-icon">🔌</span>
                            API Settings
                        </h3>
                        <button class="plugin-settings-save-btn" data-group="api">Save Changes</button>
                    </div>
                    
                    <div class="plugin-settings-tabs">
                        <button class="plugin-settings-tab active" data-tab="api-keys">API Keys</button>
                        <button class="plugin-settings-tab" data-tab="api-endpoints">Endpoints</button>
                        <button class="plugin-settings-tab" data-tab="api-auth">Authentication</button>
                    </div>
                    
                    <form>
                        <div class="plugin-settings-tab-content active" id="api-keys">
                            <div class="plugin-settings-form-group">
                                <label>API Key</label>
                                <input type="text" name="api_key" value="<?php echo esc_attr($this->manager->get('api_key', '')); ?>">
                                <small>Enter your API key for external services</small>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>API Secret</label>
                                <input type="password" name="api_secret" value="<?php echo esc_attr($this->manager->get('api_secret', '')); ?>">
                                <small>Keep this secret secure</small>
                            </div>
                        </div>
                        
                        <div class="plugin-settings-tab-content" id="api-endpoints">
                            <div class="plugin-settings-form-group">
                                <label>API Base URL</label>
                                <input type="url" name="api_base_url" value="<?php echo esc_attr($this->manager->get('api_base_url', 'https://api.example.com')); ?>">
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>API Version</label>
                                <input type="text" name="api_version" value="<?php echo esc_attr($this->manager->get('api_version', 'v1')); ?>">
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>Timeout (seconds)</label>
                                <input type="number" name="api_timeout" value="<?php echo esc_attr($this->manager->get('api_timeout', 30)); ?>" min="5" max="120">
                            </div>
                        </div>
                        
                        <div class="plugin-settings-tab-content" id="api-auth">
                            <div class="plugin-settings-form-group">
                                <label>Authentication Method</label>
                                <select name="auth_method">
                                    <option value="bearer" <?php selected($this->manager->get('auth_method'), 'bearer'); ?>>Bearer Token</option>
                                    <option value="basic" <?php selected($this->manager->get('auth_method'), 'basic'); ?>>Basic Auth</option>
                                    <option value="oauth" <?php selected($this->manager->get('auth_method'), 'oauth'); ?>>OAuth 2.0</option>
                                </select>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <div class="plugin-settings-checkbox-wrapper">
                                    <input type="checkbox" name="verify_ssl" id="verify_ssl" value="1" <?php checked($this->manager->get('verify_ssl', 1), 1); ?>>
                                    <label for="verify_ssl">Verify SSL Certificates</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Appearance Settings Card -->
                <div class="plugin-settings-card" id="card-appearance">
                    <div class="plugin-settings-card-header">
                        <h3 class="plugin-settings-card-title">
                            <span class="plugin-settings-card-title-icon">🎨</span>
                            Appearance Settings
                        </h3>
                        <button class="plugin-settings-save-btn" data-group="appearance">Save Changes</button>
                    </div>
                    
                    <div class="plugin-settings-tabs">
                        <button class="plugin-settings-tab active" data-tab="appearance-colors">Colors</button>
                        <button class="plugin-settings-tab" data-tab="appearance-typography">Typography</button>
                        <button class="plugin-settings-tab" data-tab="appearance-layout">Layout</button>
                    </div>
                    
                    <form>
                        <div class="plugin-settings-tab-content active" id="appearance-colors">
                            <div class="plugin-settings-form-group">
                                <label>Primary Color</label>
                                <input type="color" name="primary_color" value="<?php echo esc_attr($this->manager->get('primary_color', '#2271b1')); ?>">
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>Secondary Color</label>
                                <input type="color" name="secondary_color" value="<?php echo esc_attr($this->manager->get('secondary_color', '#646970')); ?>">
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>Accent Color</label>
                                <input type="color" name="accent_color" value="<?php echo esc_attr($this->manager->get('accent_color', '#00a32a')); ?>">
                            </div>
                        </div>
                        
                        <div class="plugin-settings-tab-content" id="appearance-typography">
                            <div class="plugin-settings-form-group">
                                <label>Font Family</label>
                                <select name="font_family">
                                    <option value="system" <?php selected($this->manager->get('font_family'), 'system'); ?>>System Default</option>
                                    <option value="arial" <?php selected($this->manager->get('font_family'), 'arial'); ?>>Arial</option>
                                    <option value="helvetica" <?php selected($this->manager->get('font_family'), 'helvetica'); ?>>Helvetica</option>
                                    <option value="georgia" <?php selected($this->manager->get('font_family'), 'georgia'); ?>>Georgia</option>
                                </select>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>Font Size (px)</label>
                                <input type="number" name="font_size" value="<?php echo esc_attr($this->manager->get('font_size', 14)); ?>" min="10" max="24">
                            </div>
                        </div>
                        
                        <div class="plugin-settings-tab-content" id="appearance-layout">
                            <div class="plugin-settings-form-group">
                                <label>Layout Style</label>
                                <select name="layout_style">
                                    <option value="boxed" <?php selected($this->manager->get('layout_style'), 'boxed'); ?>>Boxed</option>
                                    <option value="full-width" <?php selected($this->manager->get('layout_style'), 'full-width'); ?>>Full Width</option>
                                </select>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <div class="plugin-settings-checkbox-wrapper">
                                    <input type="checkbox" name="enable_animations" id="enable_animations" value="1" <?php checked($this->manager->get('enable_animations', 1), 1); ?>>
                                    <label for="enable_animations">Enable Animations</label>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Email Settings Card -->
                <div class="plugin-settings-card" id="card-email">
                    <div class="plugin-settings-card-header">
                        <h3 class="plugin-settings-card-title">
                            <span class="plugin-settings-card-title-icon">📧</span>
                            Email Settings
                        </h3>
                        <button class="plugin-settings-save-btn" data-group="email">Save Changes</button>
                    </div>
                    
                    <div class="plugin-settings-tabs">
                        <button class="plugin-settings-tab active" data-tab="email-general">General</button>
                        <button class="plugin-settings-tab" data-tab="email-smtp">SMTP</button>
                        <button class="plugin-settings-tab" data-tab="email-templates">Templates</button>
                    </div>
                    
                    <form>
                        <div class="plugin-settings-tab-content active" id="email-general">
                            <div class="plugin-settings-form-group">
                                <label>From Email</label>
                                <input type="email" name="from_email" value="<?php echo esc_attr($this->manager->get('from_email', get_option('admin_email'))); ?>">
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>From Name</label>
                                <input type="text" name="from_name" value="<?php echo esc_attr($this->manager->get('from_name', get_bloginfo('name'))); ?>">
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <div class="plugin-settings-checkbox-wrapper">
                                    <input type="checkbox" name="enable_notifications" id="enable_notifications" value="1" <?php checked($this->manager->get('enable_notifications', 1), 1); ?>>
                                    <label for="enable_notifications">Enable Email Notifications</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="plugin-settings-tab-content" id="email-smtp">
                            <div class="plugin-settings-form-group">
                                <label>SMTP Host</label>
                                <input type="text" name="smtp_host" value="<?php echo esc_attr($this->manager->get('smtp_host', '')); ?>">
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>SMTP Port</label>
                                <input type="number" name="smtp_port" value="<?php echo esc_attr($this->manager->get('smtp_port', 587)); ?>">
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>SMTP Username</label>
                                <input type="text" name="smtp_username" value="<?php echo esc_attr($this->manager->get('smtp_username', '')); ?>">
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>SMTP Password</label>
                                <input type="password" name="smtp_password" value="<?php echo esc_attr($this->manager->get('smtp_password', '')); ?>">
                            </div>
                        </div>
                        
                        <div class="plugin-settings-tab-content" id="email-templates">
                            <div class="plugin-settings-form-group">
                                <label>Email Header</label>
                                <textarea name="email_header"><?php echo esc_textarea($this->manager->get('email_header', '')); ?></textarea>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>Email Footer</label>
                                <textarea name="email_footer"><?php echo esc_textarea($this->manager->get('email_footer', '')); ?></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Advanced Settings Card -->
                <div class="plugin-settings-card" id="card-advanced">
                    <div class="plugin-settings-card-header">
                        <h3 class="plugin-settings-card-title">
                            <span class="plugin-settings-card-title-icon">🔧</span>
                            Advanced Settings
                        </h3>
                        <button class="plugin-settings-save-btn" data-group="advanced">Save Changes</button>
                    </div>
                    
                    <div class="plugin-settings-tabs">
                        <button class="plugin-settings-tab active" data-tab="advanced-performance">Performance</button>
                        <button class="plugin-settings-tab" data-tab="advanced-security">Security</button>
                        <button class="plugin-settings-tab" data-tab="advanced-maintenance">Maintenance</button>
                    </div>
                    
                    <form>
                        <div class="plugin-settings-tab-content active" id="advanced-performance">
                            <div class="plugin-settings-form-group">
                                <div class="plugin-settings-checkbox-wrapper">
                                    <input type="checkbox" name="enable_caching" id="enable_caching" value="1" <?php checked($this->manager->get('enable_caching', 1), 1); ?>>
                                    <label for="enable_caching">Enable Caching</label>
                                </div>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <div class="plugin-settings-checkbox-wrapper">
                                    <input type="checkbox" name="lazy_loading" id="lazy_loading" value="1" <?php checked($this->manager->get('lazy_loading', 0), 1); ?>>
                                    <label for="lazy_loading">Enable Lazy Loading</label>
                                </div>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>Memory Limit (MB)</label>
                                <input type="number" name="memory_limit" value="<?php echo esc_attr($this->manager->get('memory_limit', 128)); ?>" min="64" max="512">
                            </div>
                        </div>
                        
                        <div class="plugin-settings-tab-content" id="advanced-security">
                            <div class="plugin-settings-form-group">
                                <div class="plugin-settings-checkbox-wrapper">
                                    <input type="checkbox" name="enable_2fa" id="enable_2fa" value="1" <?php checked($this->manager->get('enable_2fa', 0), 1); ?>>
                                    <label for="enable_2fa">Enable Two-Factor Authentication</label>
                                </div>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <div class="plugin-settings-checkbox-wrapper">
                                    <input type="checkbox" name="enforce_https" id="enforce_https" value="1" <?php checked($this->manager->get('enforce_https', 1), 1); ?>>
                                    <label for="enforce_https">Enforce HTTPS</label>
                                </div>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>IP Whitelist (one per line)</label>
                                <textarea name="ip_whitelist"><?php echo esc_textarea($this->manager->get('ip_whitelist', '')); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="plugin-settings-tab-content" id="advanced-maintenance">
                            <div class="plugin-settings-form-group">
                                <div class="plugin-settings-checkbox-wrapper">
                                    <input type="checkbox" name="auto_updates" id="auto_updates" value="1" <?php checked($this->manager->get('auto_updates', 0), 1); ?>>
                                    <label for="auto_updates">Enable Automatic Updates</label>
                                </div>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>Backup Frequency</label>
                                <select name="backup_frequency">
                                    <option value="daily" <?php selected($this->manager->get('backup_frequency'), 'daily'); ?>>Daily</option>
                                    <option value="weekly" <?php selected($this->manager->get('backup_frequency'), 'weekly'); ?>>Weekly</option>
                                    <option value="monthly" <?php selected($this->manager->get('backup_frequency'), 'monthly'); ?>>Monthly</option>
                                </select>
                            </div>
                            
                            <div class="plugin-settings-form-group">
                                <label>Log Retention (days)</label>
                                <input type="number" name="log_retention" value="<?php echo esc_attr($this->manager->get('log_retention', 30)); ?>" min="7" max="365">
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Tools Section -->
                <div class="plugin-settings-card" id="card-tools">
                    <div class="plugin-settings-card-header">
                        <h3 class="plugin-settings-card-title">
                            <span class="plugin-settings-card-title-icon">🛠️</span>
                            Tools & Utilities
                        </h3>
                    </div>
                    
                    <div style="padding: 24px;">
                        <!-- Import/Export Section -->
                        <div class="plugin-settings-tools-section">
                            <h3>📦 Import / Export Settings</h3>
                            <p style="color: #646970; font-size: 14px; margin-bottom: 16px;">
                                Export your current settings to a JSON file or import settings from a previously exported file.
                            </p>
                            <div class="plugin-settings-tools-grid">
                                <div class="plugin-settings-tool-card">
                                    <h4>📤 Export Settings</h4>
                                    <p>Download all your current settings as a JSON file for backup or transfer to another site.</p>
                                    <div class="plugin-settings-tool-actions">
                                        <button id="export-settings-btn" class="plugin-settings-btn plugin-settings-btn-primary">
                                            Export Settings
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="plugin-settings-tool-card">
                                    <h4>📥 Import Settings</h4>
                                    <p>Upload a previously exported settings file to restore or apply configuration.</p>
                                    <div class="plugin-settings-tool-actions">
                                        <input type="file" id="import-file-input" class="plugin-settings-file-input" accept=".json">
                                        <button id="import-settings-btn" class="plugin-settings-btn plugin-settings-btn-primary">
                                            Import Settings
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Presets Section -->
                        <div class="plugin-settings-tools-section">
                            <h3>🎯 Configuration Presets</h3>
                            <p style="color: #646970; font-size: 14px; margin-bottom: 16px;">
                                Quickly apply pre-configured settings optimized for different environments.
                            </p>
                            <div class="plugin-settings-tool-card">
                                <h4>⚡ Quick Setup</h4>
                                <p>Choose from pre-configured presets for Development, Staging, or Production environments.</p>
                                <div class="plugin-settings-tool-actions">
                                    <button id="apply-preset-btn" class="plugin-settings-btn plugin-settings-btn-primary">
                                        Choose Preset
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reset Section -->
                        <div class="plugin-settings-tools-section">
                            <h3>🔄 Reset Settings</h3>
                            <p style="color: #646970; font-size: 14px; margin-bottom: 16px;">
                                Reset all settings to their default values. This action cannot be undone.
                            </p>
                            <div class="plugin-settings-tool-card">
                                <h4>⚠️ Reset to Defaults</h4>
                                <p>Clear all current settings and restore factory defaults.</p>
                                <div class="plugin-settings-tool-actions">
                                    <button id="reset-defaults-btn" class="plugin-settings-btn plugin-settings-btn-danger">
                                        Reset All Settings
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- Preset Modal -->
        <div id="preset-modal" class="plugin-settings-modal">
            <div class="plugin-settings-modal-content">
                <div class="plugin-settings-modal-header">
                    <h3>Choose a Preset</h3>
                    <button class="plugin-settings-modal-close">×</button>
                </div>
                <div class="plugin-settings-modal-body">
                    <p style="color: #646970; font-size: 14px; margin-bottom: 16px;">
                        Select a preset configuration to apply. This will override your current settings.
                    </p>
                    <div class="plugin-settings-preset-list">
                        <?php
                        $presets = $this->manager->get_presets();
                        foreach ($presets as $key => $preset) :
                        ?>
                        <div class="plugin-settings-preset-item" data-preset="<?php echo esc_attr($key); ?>">
                            <div class="plugin-settings-preset-header">
                                <span class="plugin-settings-preset-name"><?php echo esc_html($preset['name']); ?></span>
                                <span class="plugin-settings-preset-badge"><?php echo esc_html($key); ?></span>
                            </div>
                            <p class="plugin-settings-preset-description">
                                <?php echo esc_html($preset['description']); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="plugin-settings-modal-footer">
                    <button id="preset-modal-cancel" class="plugin-settings-btn plugin-settings-btn-secondary">
                        Cancel
                    </button>
                    <button id="preset-modal-apply" class="plugin-settings-btn plugin-settings-btn-primary">
                        Apply Preset
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Notification Toast -->
        <div class="plugin-settings-notification">
            <span class="plugin-settings-notification-icon">✓</span>
            <span class="plugin-settings-notification-message"></span>
        </div>
        <?php
    }
}

// Auto-initialization removed - handled by BTDSettings class

// ============================================
// 5. USAGE EXAMPLES
// ============================================

/*
// Get a setting anywhere in your plugin
$api_key = $plugin_settings->get('api_key');

// Set a setting programmatically
$plugin_settings->set('custom_option', 'value', 'general');

// Get all settings from a group
$api_settings = $plugin_settings->get_group('api');

// Update multiple settings
$plugin_settings->update_group('general', [
    'plugin_name' => 'My Awesome Plugin',
    'enable_plugin' => 1
]);

// Delete a setting
$plugin_settings->delete('old_setting');
*/