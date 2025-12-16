<?php
namespace BTD;

/**
 * BTD Settings Manager
 * 
 * Wrapper for the plugin settings system
 * Provides BTD-specific settings configuration
 */
class BTDSettings {
    
    private static $instance = null;
    private $manager;
    
    private function __construct() {
        // Load the settings system
        require_once BTD_PLUGIN_DIR . 'includes/wp-plugin-settings-system.php';
        
        // Initialize settings
        $db = new \Plugin_Settings_DB();
        $this->manager = new \Plugin_Settings_Manager($db);
        
        // Initialize admin UI if in admin
        if (is_admin()) {
            new BTDSettingsAdmin($this->manager);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get setting value
     */
    public function get($key, $default = null) {
        return $this->manager->get($key, $default);
    }
    
    /**
     * Set setting value
     */
    public function set($key, $value, $group = 'general') {
        return $this->manager->set($key, $value, $group);
    }
    
    /**
     * Get all settings in a group
     */
    public function getGroup($group) {
        return $this->manager->get_group($group);
    }
    
    /**
     * Update multiple settings
     */
    public function updateGroup($group, $data) {
        return $this->manager->update_group($group, $data);
    }
    
    /**
     * Get default settings for BTD Tools
     */
    public static function getDefaults() {
        return [
            'general' => [
                'enable_plugin' => 1,
                'debug_mode' => 0,
                'cache_duration' => 3600,
            ],
            'ai' => [
                'ai_provider' => 'gemini', // gemini, openai, anthropic
                'gemini_api_key' => '',
                'openai_api_key' => '',
                'anthropic_api_key' => '',
                'gemini_model' => 'gemini-pro',
                'openai_model' => 'gpt-4',
                'anthropic_model' => 'claude-3-sonnet-20240229',
                'api_timeout' => 30,
                'max_tokens' => 4096,
                'temperature' => 0.7,
            ],
            'tools' => [
                'enable_analytics' => 1,
                'enable_rate_limiting' => 1,
                'default_tier' => 'free',
            ],
            'subscriptions' => [
                'starter_product_id' => 0,
                'pro_product_id' => 0,
                'business_product_id' => 0,
            ],
            'rate_limits' => [
                'free_daily_limit' => 10,
                'starter_daily_limit' => 100,
                'pro_daily_limit' => -1, // unlimited
                'business_daily_limit' => -1,
            ],
        ];
    }
    
    /**
     * Initialize default settings
     */
    public function initializeDefaults() {
        $defaults = self::getDefaults();
        
        foreach ($defaults as $group => $settings) {
            foreach ($settings as $key => $value) {
                // Only set if not already exists
                if ($this->get($key) === null) {
                    $this->set($key, $value, $group);
                }
            }
        }
    }
}

/**
 * BTD Settings Admin UI
 * 
 * Customizes the settings admin page for BTD Tools
 */
class BTDSettingsAdmin {
    
    private $manager;
    private $page_slug = 'btd-settings';
    
    public function __construct(\Plugin_Settings_Manager $manager) {
        $this->manager = $manager;
        
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_ajax_save_btd_settings', [$this, 'ajaxSaveSettings']);
    }
    
    /**
     * Add settings page under BTD Tools menu
     */
    public function addMenuPage() {
        add_submenu_page(
            'edit.php?post_type=btd_tool',
            __('Settings', 'btd-tools'),
            __('Settings', 'btd-tools'),
            'manage_options',
            $this->page_slug,
            [$this, 'renderSettingsPage']
        );
    }
    
    /**
     * Enqueue assets
     */
    public function enqueueAssets($hook) {
        if (strpos($hook, $this->page_slug) === false) {
            return;
        }
        
        wp_enqueue_style('btd-settings', BTD_PLUGIN_URL . 'assets/css/settings.css', [], BTD_VERSION);
        wp_enqueue_script('btd-settings', BTD_PLUGIN_URL . 'assets/js/settings.js', ['jquery'], BTD_VERSION, true);
        
        wp_localize_script('btd-settings', 'btdSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('btd_settings_nonce'),
        ]);
    }
    
    /**
     * Render settings page
     */
    public function renderSettingsPage() {
        // Get current settings
        $general = $this->manager->get_group('general');
        $ai = $this->manager->get_group('ai');
        $tools = $this->manager->get_group('tools');
        $subscriptions = $this->manager->get_group('subscriptions');
        $rate_limits = $this->manager->get_group('rate_limits');
        
        ?>
        <div class="wrap btd-settings-page">
            <h1><?php _e('BTD Tools Settings', 'btd-tools'); ?></h1>
            
            <form id="btd-settings-form" method="post">
                <?php wp_nonce_field('btd_settings_save', 'btd_settings_nonce'); ?>
                
                <div class="btd-settings-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'btd-tools'); ?></a>
                        <a href="#ai" class="nav-tab"><?php _e('AI Settings', 'btd-tools'); ?></a>
                        <a href="#tools" class="nav-tab"><?php _e('Tools', 'btd-tools'); ?></a>
                        <a href="#subscriptions" class="nav-tab"><?php _e('Subscriptions', 'btd-tools'); ?></a>
                        <a href="#rate-limits" class="nav-tab"><?php _e('Rate Limits', 'btd-tools'); ?></a>
                    </nav>
                    
                    <!-- General Settings -->
                    <div id="general" class="btd-settings-tab-content active">
                        <h2><?php _e('General Settings', 'btd-tools'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="enable_plugin"><?php _e('Enable Plugin', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               id="enable_plugin" 
                                               name="general[enable_plugin]" 
                                               value="1" 
                                               <?php checked($general['enable_plugin'] ?? 1, 1); ?>>
                                        <?php _e('Enable BTD Tools functionality', 'btd-tools'); ?>
                                    </label>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="debug_mode"><?php _e('Debug Mode', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               id="debug_mode" 
                                               name="general[debug_mode]" 
                                               value="1" 
                                               <?php checked($general['debug_mode'] ?? 0, 1); ?>>
                                        <?php _e('Enable debug logging', 'btd-tools'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Logs will be written to the WordPress debug.log file', 'btd-tools'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="cache_duration"><?php _e('Cache Duration', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="cache_duration" 
                                           name="general[cache_duration]" 
                                           value="<?php echo esc_attr($general['cache_duration'] ?? 3600); ?>" 
                                           min="0" 
                                           class="small-text">
                                    <span><?php _e('seconds', 'btd-tools'); ?></span>
                                    <p class="description">
                                        <?php _e('How long to cache tool data (0 to disable)', 'btd-tools'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- AI Settings -->
                    <div id="ai" class="btd-settings-tab-content">
                        <h2><?php _e('AI Provider Settings', 'btd-tools'); ?></h2>
                        <p><?php _e('Configure AI provider for AI-powered tools. Gemini is the default provider.', 'btd-tools'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="ai_provider"><?php _e('AI Provider', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <select id="ai_provider" name="ai[ai_provider]">
                                        <option value="gemini" <?php selected($ai['ai_provider'] ?? 'gemini', 'gemini'); ?>>
                                            <?php _e('Google Gemini (Default)', 'btd-tools'); ?>
                                        </option>
                                        <option value="openai" <?php selected($ai['ai_provider'] ?? '', 'openai'); ?>>
                                            <?php _e('OpenAI', 'btd-tools'); ?>
                                        </option>
                                        <option value="anthropic" <?php selected($ai['ai_provider'] ?? '', 'anthropic'); ?>>
                                            <?php _e('Anthropic Claude', 'btd-tools'); ?>
                                        </option>
                                    </select>
                                    <p class="description">
                                        <?php _e('Select which AI provider to use for AI-powered tools', 'btd-tools'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="gemini_api_key"><?php _e('Gemini API Key', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="gemini_api_key" 
                                           name="ai[gemini_api_key]" 
                                           value="<?php echo esc_attr($ai['gemini_api_key'] ?? ''); ?>" 
                                           class="regular-text" 
                                           placeholder="AIza...">
                                    <p class="description">
                                        <?php _e('Get your key from', 'btd-tools'); ?>
                                        <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="openai_api_key"><?php _e('OpenAI API Key', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="openai_api_key" 
                                           name="ai[openai_api_key]" 
                                           value="<?php echo esc_attr($ai['openai_api_key'] ?? ''); ?>" 
                                           class="regular-text" 
                                           placeholder="sk-...">
                                    <p class="description">
                                        <?php _e('Get your key from', 'btd-tools'); ?>
                                        <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="anthropic_api_key"><?php _e('Anthropic API Key', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="text" 
                                           id="anthropic_api_key" 
                                           name="ai[anthropic_api_key]" 
                                           value="<?php echo esc_attr($ai['anthropic_api_key'] ?? ''); ?>" 
                                           class="regular-text" 
                                           placeholder="sk-ant-...">
                                    <p class="description">
                                        <?php _e('Get your key from', 'btd-tools'); ?>
                                        <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="api_timeout"><?php _e('API Timeout', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="api_timeout" 
                                           name="ai[api_timeout]" 
                                           value="<?php echo esc_attr($ai['api_timeout'] ?? 30); ?>" 
                                           min="5" 
                                           max="120" 
                                           class="small-text">
                                    <span><?php _e('seconds', 'btd-tools'); ?></span>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="max_tokens"><?php _e('Max Tokens', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="max_tokens" 
                                           name="ai[max_tokens]" 
                                           value="<?php echo esc_attr($ai['max_tokens'] ?? 4096); ?>" 
                                           min="256" 
                                           max="8192" 
                                           class="small-text">
                                    <p class="description">
                                        <?php _e('Maximum tokens for AI responses', 'btd-tools'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="temperature"><?php _e('Temperature', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="temperature" 
                                           name="ai[temperature]" 
                                           value="<?php echo esc_attr($ai['temperature'] ?? 0.7); ?>" 
                                           min="0" 
                                           max="2" 
                                           step="0.1" 
                                           class="small-text">
                                    <p class="description">
                                        <?php _e('Controls randomness: 0 = focused, 2 = creative', 'btd-tools'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Tools Settings -->
                    <div id="tools" class="btd-settings-tab-content">
                        <h2><?php _e('Tools Settings', 'btd-tools'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="enable_analytics"><?php _e('Enable Analytics', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               id="enable_analytics" 
                                               name="tools[enable_analytics]" 
                                               value="1" 
                                               <?php checked($tools['enable_analytics'] ?? 1, 1); ?>>
                                        <?php _e('Track tool usage and analytics', 'btd-tools'); ?>
                                    </label>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="enable_rate_limiting"><?php _e('Enable Rate Limiting', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               id="enable_rate_limiting" 
                                               name="tools[enable_rate_limiting]" 
                                               value="1" 
                                               <?php checked($tools['enable_rate_limiting'] ?? 1, 1); ?>>
                                        <?php _e('Enforce usage limits for free users', 'btd-tools'); ?>
                                    </label>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="default_tier"><?php _e('Default Tier', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <select id="default_tier" name="tools[default_tier]">
                                        <option value="free" <?php selected($tools['default_tier'] ?? 'free', 'free'); ?>>
                                            <?php _e('Free', 'btd-tools'); ?>
                                        </option>
                                        <option value="starter" <?php selected($tools['default_tier'] ?? '', 'starter'); ?>>
                                            <?php _e('Starter', 'btd-tools'); ?>
                                        </option>
                                        <option value="pro" <?php selected($tools['default_tier'] ?? '', 'pro'); ?>>
                                            <?php _e('Professional', 'btd-tools'); ?>
                                        </option>
                                        <option value="business" <?php selected($tools['default_tier'] ?? '', 'business'); ?>>
                                            <?php _e('Business', 'btd-tools'); ?>
                                        </option>
                                    </select>
                                    <p class="description">
                                        <?php _e('Default subscription tier for new tools', 'btd-tools'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Subscriptions Settings -->
                    <div id="subscriptions" class="btd-settings-tab-content">
                        <h2><?php _e('WooCommerce Subscription Products', 'btd-tools'); ?></h2>
                        <p><?php _e('Map your WooCommerce subscription products to BTD tiers', 'btd-tools'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="starter_product_id"><?php _e('Starter Product ID', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="starter_product_id" 
                                           name="subscriptions[starter_product_id]" 
                                           value="<?php echo esc_attr($subscriptions['starter_product_id'] ?? 0); ?>" 
                                           min="0" 
                                           class="small-text">
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="pro_product_id"><?php _e('Pro Product ID', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="pro_product_id" 
                                           name="subscriptions[pro_product_id]" 
                                           value="<?php echo esc_attr($subscriptions['pro_product_id'] ?? 0); ?>" 
                                           min="0" 
                                           class="small-text">
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="business_product_id"><?php _e('Business Product ID', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="business_product_id" 
                                           name="subscriptions[business_product_id]" 
                                           value="<?php echo esc_attr($subscriptions['business_product_id'] ?? 0); ?>" 
                                           min="0" 
                                           class="small-text">
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Rate Limits Settings -->
                    <div id="rate-limits" class="btd-settings-tab-content">
                        <h2><?php _e('Daily Usage Limits', 'btd-tools'); ?></h2>
                        <p><?php _e('Set daily usage limits for each tier (-1 for unlimited)', 'btd-tools'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="free_daily_limit"><?php _e('Free Tier', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="free_daily_limit" 
                                           name="rate_limits[free_daily_limit]" 
                                           value="<?php echo esc_attr($rate_limits['free_daily_limit'] ?? 10); ?>" 
                                           min="-1" 
                                           class="small-text">
                                    <span><?php _e('uses per day', 'btd-tools'); ?></span>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="starter_daily_limit"><?php _e('Starter Tier', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="starter_daily_limit" 
                                           name="rate_limits[starter_daily_limit]" 
                                           value="<?php echo esc_attr($rate_limits['starter_daily_limit'] ?? 100); ?>" 
                                           min="-1" 
                                           class="small-text">
                                    <span><?php _e('uses per day', 'btd-tools'); ?></span>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="pro_daily_limit"><?php _e('Pro Tier', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="pro_daily_limit" 
                                           name="rate_limits[pro_daily_limit]" 
                                           value="<?php echo esc_attr($rate_limits['pro_daily_limit'] ?? -1); ?>" 
                                           min="-1" 
                                           class="small-text">
                                    <span><?php _e('uses per day', 'btd-tools'); ?></span>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="business_daily_limit"><?php _e('Business Tier', 'btd-tools'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="business_daily_limit" 
                                           name="rate_limits[business_daily_limit]" 
                                           value="<?php echo esc_attr($rate_limits['business_daily_limit'] ?? -1); ?>" 
                                           min="-1" 
                                           class="small-text">
                                    <span><?php _e('uses per day', 'btd-tools'); ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Save Settings', 'btd-tools'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * AJAX save settings
     */
    public function ajaxSaveSettings() {
        check_ajax_referer('btd_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $groups = ['general', 'ai', 'tools', 'subscriptions', 'rate_limits'];
        $saved = 0;
        
        foreach ($groups as $group) {
            if (isset($_POST[$group]) && is_array($_POST[$group])) {
                foreach ($_POST[$group] as $key => $value) {
                    // Sanitize based on key
                    $sanitized = $this->sanitizeValue($key, $value);
                    $this->manager->set($key, $sanitized, $group);
                    $saved++;
                }
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(__('%d settings saved successfully', 'btd-tools'), $saved)
        ]);
    }
    
    /**
     * Sanitize setting value
     */
    private function sanitizeValue($key, $value) {
        // Checkbox values
        if (in_array($key, ['enable_plugin', 'debug_mode', 'enable_analytics', 'enable_rate_limiting'])) {
            return !empty($value) ? 1 : 0;
        }
        
        // Number values
        if (in_array($key, ['cache_duration', 'api_timeout', 'max_tokens', 'starter_product_id', 'pro_product_id', 'business_product_id', 'free_daily_limit', 'starter_daily_limit', 'pro_daily_limit', 'business_daily_limit'])) {
            return intval($value);
        }
        
        // API keys
        if (in_array($key, ['gemini_api_key', 'openai_api_key', 'anthropic_api_key'])) {
            return sanitize_text_field($value);
        }
        
        // Float values
        if ($key === 'temperature') {
            return floatval($value);
        }
        
        // AI provider
        if ($key === 'ai_provider') {
            return in_array($value, ['gemini', 'openai', 'anthropic']) ? $value : 'gemini';
        }
        
        // Default
        return sanitize_text_field($value);
    }
}
