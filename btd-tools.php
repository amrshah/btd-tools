<?php
/**
 * Plugin Name: BTD Business Tools Suite
 * Plugin URI: https://silverantmarketing.com/business-tools
 * Description: Complete business tools suite with Eloquent ORM architecture
 * Version: 1.0.0
 * Author: Ali Raza 
 * License: GPL v2 or later
 * Text Domain: btd-tools
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BTD_VERSION', '1.0.0');
define('BTD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BTD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BTD_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Bootstrap Eloquent ORM
require_once BTD_PLUGIN_DIR . 'bootstrap/eloquent.php';

/**
 * include tools below
 */
require_once BTD_PLUGIN_DIR . 'tools/financial/ROICalculator.php';


// Autoload classes (or use Composer PSR-4)
spl_autoload_register(function ($class) {
    if (strpos($class, 'BTD\\') !== 0) {
        return;
    }
    
    $relative_class = substr($class, 4); // Remove BTD\
    $path = str_replace('\\', '/', $relative_class);
    
    // Define namespace mappings to directories
    $mappings = [
        'Models/' => 'models/',
        'Tools/Core/' => 'tools/core/',
        'Tools/Financial/' => 'tools/financial/',
        'Tools/' => 'tools/',
    ];
    
    $file = '';
    $matched = false;
    
    foreach ($mappings as $prefix => $dir) {
        if (strpos($path, $prefix) === 0) {
            $file = BTD_PLUGIN_DIR . $dir . substr($path, strlen($prefix)) . '.php';
            $matched = true;
            break;
        }
    }
    
    // Default to includes directory
    if (!$matched) {
        $file = BTD_PLUGIN_DIR . 'includes/' . $path . '.php';
    }
    
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Plugin Activation Hook
 */
register_activation_hook(__FILE__, function() {
    // Run database migrations
    $runner = new \BTD\MigrationRunner(BTD_PLUGIN_DIR . 'database/migrations');
    $runner->runPending();
    
    // Create settings table
    require_once BTD_PLUGIN_DIR . 'lib/wp-plugin-settings-system.php';
    $settings_db = new \Plugin_Settings_DB();
    $settings_db->create_table();
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Set activation flag
    update_option('btd_activated', true);
});

/**
 * Plugin Deactivation Hook
 */
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

/**
 * Initialize Plugin
 */
add_action('plugins_loaded', function() {
    // Load text domain
    load_plugin_textdomain('btd-tools', false, dirname(BTD_PLUGIN_BASENAME) . '/languages');
    
    // Initialize custom post type and taxonomy
    $post_type = new \BTD\ToolPostType();
    $post_type->init();
    
    // Initialize meta boxes
    $meta_boxes = new \BTD\ToolMetaBoxes();
    $meta_boxes->init();
    
    // Initialize settings system
    $settings = \BTD\BTDSettings::getInstance();
    $settings->initializeDefaults();
    
    // Initialize tool registry
    $tool_registry = \BTD\ToolRegistry::getInstance();
    
    // Register core tools
    do_action('btd_register_tools', $tool_registry);
});

/**
 * Helper function to get plugin settings
 */
function btd_get_setting($key, $default = null) {
    $settings = \BTD\BTDSettings::getInstance();
    return $settings->get($key, $default);
}

/**
 * Helper function to set plugin settings
 */
function btd_set_setting($key, $value, $group = 'general') {
    $settings = \BTD\BTDSettings::getInstance();
    return $settings->set($key, $value, $group);
}


/**
 * Enqueue Admin Assets
 */
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style(
        'btd-admin',
        BTD_PLUGIN_URL . 'assets/css/admin.css',
        [],
        BTD_VERSION
    );
    
    wp_enqueue_script(
        'btd-admin',
        BTD_PLUGIN_URL . 'assets/js/admin.js',
        ['jquery'],
        BTD_VERSION,
        true
    );
});

/**
 * Enqueue Frontend Assets
 */
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'btd-frontend',
        BTD_PLUGIN_URL . 'assets/css/frontend.css',
        [],
        BTD_VERSION
    );
    
    wp_enqueue_script(
        'btd-frontend',
        BTD_PLUGIN_URL . 'assets/js/frontend.js',
        ['jquery'],
        BTD_VERSION,
        true
    );
    
    // Localize script with AJAX URL and nonce
    wp_localize_script('btd-frontend', 'btdData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('btd_nonce'),
        'userId' => get_current_user_id(),
        'isLoggedIn' => is_user_logged_in(),
    ]);
});

/**
 * Add Admin Menu
 */
add_action('admin_menu', function() {
    add_menu_page(
        __('BTD Tools', 'btd-tools'),
        __('BTD Tools', 'btd-tools'),
        'manage_options',
        'btd-tools',
        'btd_dashboard_page',
        'dashicons-calculator',
        30
    );
    
    add_submenu_page(
        'btd-tools',
        __('Dashboard', 'btd-tools'),
        __('Dashboard', 'btd-tools'),
        'manage_options',
        'btd-tools',
        'btd_dashboard_page'
    );
    
    add_submenu_page(
        'btd-tools',
        __('Analytics', 'btd-tools'),
        __('Analytics', 'btd-tools'),
        'manage_options',
        'btd-analytics',
        'btd_analytics_page'
    );
    
    add_submenu_page(
        'btd-tools',
        __('Settings', 'btd-tools'),
        __('Settings', 'btd-tools'),
        'manage_options',
        'btd-settings',
        'btd_settings_page'
    );
});

/**
 * Dashboard Page Callback
 */
function btd_dashboard_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('BTD Tools Dashboard', 'btd-tools'); ?></h1>
        
        <div class="btd-dashboard-stats">
            <?php
            // Get statistics using Eloquent
            $total_calculations = \BTD\Models\Calculation::count();
            $today_calculations = \BTD\Models\Calculation::whereDate('created_at', today())->count();
            $active_users = \BTD\Models\Calculation::distinct('user_id')
                ->where('created_at', '>=', now()->subDays(30))
                ->count('user_id');
            ?>
            
            <div class="btd-stat-card">
                <h3><?php echo number_format($total_calculations); ?></h3>
                <p><?php _e('Total Calculations', 'btd-tools'); ?></p>
            </div>
            
            <div class="btd-stat-card">
                <h3><?php echo number_format($today_calculations); ?></h3>
                <p><?php _e('Today', 'btd-tools'); ?></p>
            </div>
            
            <div class="btd-stat-card">
                <h3><?php echo number_format($active_users); ?></h3>
                <p><?php _e('Active Users (30d)', 'btd-tools'); ?></p>
            </div>
        </div>
        
        <div class="btd-recent-activity">
            <h2><?php _e('Recent Calculations', 'btd-tools'); ?></h2>
            <?php
            $recent = \BTD\Models\Calculation::with('user')
                ->latest()
                ->limit(10)
                ->get();
            
            if ($recent->count() > 0) {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr>';
                echo '<th>' . __('Tool', 'btd-tools') . '</th>';
                echo '<th>' . __('User', 'btd-tools') . '</th>';
                echo '<th>' . __('Date', 'btd-tools') . '</th>';
                echo '</tr></thead><tbody>';
                
                foreach ($recent as $calc) {
                    echo '<tr>';
                    echo '<td>' . esc_html($calc->tool_slug) . '</td>';
                    echo '<td>' . esc_html($calc->user->display_name ?? 'Guest') . '</td>';
                    echo '<td>' . esc_html($calc->created_at->diffForHumans()) . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            } else {
                echo '<p>' . __('No calculations yet.', 'btd-tools') . '</p>';
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Analytics Page Callback
 */
function btd_analytics_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Tool Analytics', 'btd-tools'); ?></h1>
        
        <?php
        // Get tool usage statistics
        $tool_stats = \BTD\Models\Calculation::select([
            'tool_slug',
            \Illuminate\Database\Capsule\Manager::raw('COUNT(*) as total_uses'),
            \Illuminate\Database\Capsule\Manager::raw('COUNT(DISTINCT user_id) as unique_users'),
        ])
        ->groupBy('tool_slug')
        ->orderByDesc('total_uses')
        ->get();
        
        if ($tool_stats->count() > 0) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . __('Tool', 'btd-tools') . '</th>';
            echo '<th>' . __('Total Uses', 'btd-tools') . '</th>';
            echo '<th>' . __('Unique Users', 'btd-tools') . '</th>';
            echo '<th>' . __('Avg Uses/User', 'btd-tools') . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($tool_stats as $stat) {
                $avg = $stat->unique_users > 0 ? round($stat->total_uses / $stat->unique_users, 1) : 0;
                echo '<tr>';
                echo '<td><strong>' . esc_html($stat->tool_slug) . '</strong></td>';
                echo '<td>' . number_format($stat->total_uses) . '</td>';
                echo '<td>' . number_format($stat->unique_users) . '</td>';
                echo '<td>' . $avg . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No analytics data available yet.', 'btd-tools') . '</p>';
        }
        ?>
    </div>
    <?php
}

/**
 * Settings Page Callback
 */
/**
 * Deprecated: Settings are now handled by BTDSettings class
 */
// function btd_settings_page() { ... }
// function btd_settings_page() { ... }

/**
 * REST API Endpoints
 */
add_action('rest_api_init', function() {
    // Get user's calculations
    register_rest_route('btd/v1', '/calculations', [
        'methods' => 'GET',
        'callback' => function($request) {
            if (!is_user_logged_in()) {
                return new WP_Error('unauthorized', 'You must be logged in', ['status' => 401]);
            }
            
            $user_id = get_current_user_id();
            $page = $request->get_param('page') ?? 1;
            $per_page = $request->get_param('per_page') ?? 20;
            
            $calculations = \BTD\Models\Calculation::where('user_id', $user_id)
                ->latest()
                ->paginate($per_page, ['*'], 'page', $page);
            
            return rest_ensure_response($calculations);
        },
        'permission_callback' => '__return_true'
    ]);
    
    // Get tool statistics
    register_rest_route('btd/v1', '/tools/(?P<slug>[a-z0-9-]+)/stats', [
        'methods' => 'GET',
        'callback' => function($request) {
            $slug = $request->get_param('slug');
            
            $stats = [
                'total_uses' => \BTD\Models\Calculation::where('tool_slug', $slug)->count(),
                'unique_users' => \BTD\Models\Calculation::where('tool_slug', $slug)
                    ->distinct('user_id')
                    ->count('user_id'),
                'last_30_days' => \BTD\Models\Calculation::where('tool_slug', $slug)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
            ];
            
            return rest_ensure_response($stats);
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
});