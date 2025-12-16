<?php
/**
 * Tool Registry
 * 
 * Central registry for all BTD tools. Manages tool registration,
 * discovery, and access control.
 * 
 * Location: wp-content/plugins/btd-tools/includes/ToolRegistry.php
 */

namespace BTD;

/**
 * Tool Registry Class (Singleton)
 */
class ToolRegistry {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Registered tools
     * @var array
     */
    private $tools = [];
    
    /**
     * Tool categories
     * @var array
     */
    private $categories = [
        'financial' => [
            'label' => 'Financial Tools',
            'icon' => 'dashicons-chart-line',
            'color' => '#10b981',
            'description' => 'Calculate ROI, profit margins, and financial metrics',
        ],
        'marketing' => [
            'label' => 'Marketing Tools',
            'icon' => 'dashicons-megaphone',
            'color' => '#f59e0b',
            'description' => 'Marketing ROI, campaign planning, and analytics',
        ],
        'operations' => [
            'label' => 'Operations Tools',
            'icon' => 'dashicons-admin-settings',
            'color' => '#6366f1',
            'description' => 'Project management, capacity planning, and workflows',
        ],
        'hr' => [
            'label' => 'HR & Team Tools',
            'icon' => 'dashicons-groups',
            'color' => '#8b5cf6',
            'description' => 'Salary calculators, hiring costs, and team management',
        ],
        'sales' => [
            'label' => 'Sales Tools',
            'icon' => 'dashicons-cart',
            'color' => '#ec4899',
            'description' => 'Pipeline calculators, commission tracking, and proposals',
        ],
        'content' => [
            'label' => 'Content Tools',
            'icon' => 'dashicons-edit',
            'color' => '#14b8a6',
            'description' => 'AI-powered content generation and writing tools',
        ],
        'legal' => [
            'label' => 'Legal Tools',
            'icon' => 'dashicons-privacy',
            'color' => '#64748b',
            'description' => 'Contract templates, NDAs, and legal documents',
        ],
    ];
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor (Singleton)
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize registry
     */
    private function init() {
        // Hook into WordPress init to load tools
        add_action('init', [$this, 'loadTools'], 20);
        
        // Register AJAX endpoints for tool discovery
        add_action('wp_ajax_btd_get_tools', [$this, 'ajaxGetTools']);
        add_action('wp_ajax_nopriv_btd_get_tools', [$this, 'ajaxGetTools']);
        
        // Add REST API endpoints
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }
    
    /**
     * Register a tool
     * 
     * @param object $tool Instance of a Tool class
     * @return bool Success status
     */
    public function register($tool) {
        // Validate tool
        if (!is_object($tool)) {
            error_log('BTD: Cannot register tool - not an object');
            return false;
        }
        
        // Get tool metadata
        $metadata = method_exists($tool, 'getMetadata') ? $tool->getMetadata() : [];
        
        if (empty($metadata['slug'])) {
            error_log('BTD: Cannot register tool - no slug provided');
            return false;
        }
        
        $slug = $metadata['slug'];
        
        // Check if already registered
        if (isset($this->tools[$slug])) {
            error_log("BTD: Tool '{$slug}' is already registered");
            return false;
        }
        
        // Register tool
        $this->tools[$slug] = [
            'instance' => $tool,
            'metadata' => $metadata,
            'registered_at' => current_time('mysql'),
        ];
        
        // Log registration
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("BTD: Registered tool '{$slug}'");
        }
        
        // Fire action hook
        do_action('btd_tool_registered', $slug, $tool);
        
        return true;
    }
    
    /**
     * Unregister a tool
     * 
     * @param string $slug Tool slug
     * @return bool Success status
     */
    public function unregister($slug) {
        if (!isset($this->tools[$slug])) {
            return false;
        }
        
        unset($this->tools[$slug]);
        
        do_action('btd_tool_unregistered', $slug);
        
        return true;
    }
    
    /**
     * Get a registered tool
     * 
     * @param string $slug Tool slug
     * @return object|null Tool instance or null
     */
    public function getTool($slug) {
        if (!isset($this->tools[$slug])) {
            return null;
        }
        
        return $this->tools[$slug]['instance'];
    }
    
    /**
     * Get all registered tools
     * 
     * @param array $args Query arguments
     * @return array Array of tools
     */
    public function getTools($args = []) {
        $defaults = [
            'category' => null,
            'tier' => null,
            'orderby' => 'name',
            'order' => 'ASC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $tools = $this->tools;
        
        // Filter by category
        if ($args['category']) {
            $tools = array_filter($tools, function($tool) use ($args) {
                return $tool['metadata']['category'] === $args['category'];
            });
        }
        
        // Filter by tier
        if ($args['tier']) {
            $tools = array_filter($tools, function($tool) use ($args) {
                return $tool['metadata']['tier'] === $args['tier'];
            });
        }
        
        // Sort
        usort($tools, function($a, $b) use ($args) {
            $field = $args['orderby'];
            $order = strtoupper($args['order']);
            
            $aVal = $a['metadata'][$field] ?? '';
            $bVal = $b['metadata'][$field] ?? '';
            
            $result = strcmp($aVal, $bVal);
            
            return $order === 'DESC' ? -$result : $result;
        });
        
        return $tools;
    }
    
    /**
     * Get tool count
     * 
     * @param array $args Query arguments
     * @return int Number of tools
     */
    public function getToolCount($args = []) {
        return count($this->getTools($args));
    }
    
    /**
     * Check if tool exists
     * 
     * @param string $slug Tool slug
     * @return bool
     */
    public function toolExists($slug) {
        return isset($this->tools[$slug]);
    }
    
    /**
     * Get tool metadata
     * 
     * @param string $slug Tool slug
     * @return array|null Metadata or null
     */
    public function getToolMetadata($slug) {
        if (!isset($this->tools[$slug])) {
            return null;
        }
        
        return $this->tools[$slug]['metadata'];
    }
    
    /**
     * Get tools by category
     * 
     * @param string $category Category slug
     * @return array Array of tools
     */
    public function getToolsByCategory($category) {
        return $this->getTools(['category' => $category]);
    }
    
    /**
     * Get tools by tier
     * 
     * @param string $tier Tier slug (free, starter, pro, business)
     * @return array Array of tools
     */
    public function getToolsByTier($tier) {
        return $this->getTools(['tier' => $tier]);
    }
    
    /**
     * Get all categories
     * 
     * @return array Array of categories
     */
    public function getCategories() {
        return $this->categories;
    }
    
    /**
     * Get category info
     * 
     * @param string $slug Category slug
     * @return array|null Category info or null
     */
    public function getCategory($slug) {
        return $this->categories[$slug] ?? null;
    }
    
    /**
     * Register a custom category
     * 
     * @param string $slug Category slug
     * @param array $args Category arguments
     * @return bool Success status
     */
    public function registerCategory($slug, $args) {
        $defaults = [
            'label' => ucfirst($slug),
            'icon' => 'dashicons-admin-generic',
            'color' => '#6b7280',
            'description' => '',
        ];
        
        $this->categories[$slug] = wp_parse_args($args, $defaults);
        
        return true;
    }
    
    /**
     * Load all tools from directory
     */
    public function loadTools() {
        $tools_dir = BTD_PLUGIN_DIR . 'tools/';
        
        // Allow plugins to register tools before auto-loading
        do_action('btd_before_load_tools', $this);
        
        // Auto-load tools from tools directory
        $this->autoloadTools($tools_dir);
        
        // Allow plugins to register additional tools
        do_action('btd_load_tools', $this);
        
        // Log loaded tools count
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $count = count($this->tools);
            error_log("BTD: Loaded {$count} tools");
        }
    }
    
    /**
     * Auto-load tools from directory
     * 
     * @param string $dir Directory path
     */
    private function autoloadTools($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        // Scan subdirectories (categories)
        $categories = scandir($dir);
        
        foreach ($categories as $category) {
            if ($category === '.' || $category === '..' || $category === 'core') {
                continue;
            }
            
            $category_dir = $dir . $category;
            
            if (!is_dir($category_dir)) {
                continue;
            }
            
            // Scan for PHP files in category
            $files = glob($category_dir . '/*.php');
            
            foreach ($files as $file) {
                require_once $file;
            }
        }
    }
    
    /**
     * AJAX handler: Get tools
     */
    public function ajaxGetTools() {
        check_ajax_referer('btd_nonce', 'nonce');
        
        $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : null;
        $tier = isset($_GET['tier']) ? sanitize_text_field($_GET['tier']) : null;
        
        $args = [];
        if ($category) $args['category'] = $category;
        if ($tier) $args['tier'] = $tier;
        
        $tools = $this->getTools($args);
        
        // Format for JSON
        $formatted = array_map(function($tool) {
            return $tool['metadata'];
        }, $tools);
        
        wp_send_json_success($formatted);
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes() {
        // Get all tools
        register_rest_route('btd/v1', '/tools', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetTools'],
            'permission_callback' => '__return_true',
        ]);
        
        // Get single tool
        register_rest_route('btd/v1', '/tools/(?P<slug>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetTool'],
            'permission_callback' => '__return_true',
        ]);
        
        // Get categories
        register_rest_route('btd/v1', '/categories', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetCategories'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * REST: Get all tools
     */
    public function restGetTools($request) {
        $category = $request->get_param('category');
        $tier = $request->get_param('tier');
        
        $args = [];
        if ($category) $args['category'] = $category;
        if ($tier) $args['tier'] = $tier;
        
        $tools = $this->getTools($args);
        
        // Format for REST
        $formatted = array_map(function($tool) {
            return $tool['metadata'];
        }, $tools);
        
        return rest_ensure_response(array_values($formatted));
    }
    
    /**
     * REST: Get single tool
     */
    public function restGetTool($request) {
        $slug = $request->get_param('slug');
        
        $metadata = $this->getToolMetadata($slug);
        
        if (!$metadata) {
            return new \WP_Error(
                'tool_not_found',
                'Tool not found',
                ['status' => 404]
            );
        }
        
        return rest_ensure_response($metadata);
    }
    
    /**
     * REST: Get all categories
     */
    public function restGetCategories($request) {
        $categories = $this->getCategories();
        
        // Add tool counts
        foreach ($categories as $slug => &$category) {
            $category['tool_count'] = $this->getToolCount(['category' => $slug]);
        }
        
        return rest_ensure_response($categories);
    }
    
    /**
     * Get registry statistics
     * 
     * @return array Statistics
     */
    public function getStatistics() {
        $stats = [
            'total_tools' => count($this->tools),
            'by_category' => [],
            'by_tier' => [],
        ];
        
        // Count by category
        foreach ($this->categories as $slug => $category) {
            $stats['by_category'][$slug] = $this->getToolCount(['category' => $slug]);
        }
        
        // Count by tier
        $tiers = ['free', 'starter', 'pro', 'business'];
        foreach ($tiers as $tier) {
            $stats['by_tier'][$tier] = $this->getToolCount(['tier' => $tier]);
        }
        
        return $stats;
    }
    
    /**
     * Export tools registry as JSON
     * 
     * @return string JSON string
     */
    public function exportJSON() {
        $export = [
            'version' => BTD_VERSION,
            'exported_at' => current_time('mysql'),
            'tools' => [],
            'categories' => $this->categories,
        ];
        
        foreach ($this->tools as $slug => $tool) {
            $export['tools'][] = $tool['metadata'];
        }
        
        return json_encode($export, JSON_PRETTY_PRINT);
    }
    
    /**
     * Debug: Print registry info
     */
    public function debug() {
        $stats = $this->getStatistics();
        
        echo '<div class="btd-debug">';
        echo '<h3>BTD Tool Registry Debug</h3>';
        echo '<p><strong>Total Tools:</strong> ' . $stats['total_tools'] . '</p>';
        
        echo '<h4>By Category:</h4>';
        echo '<ul>';
        foreach ($stats['by_category'] as $category => $count) {
            $label = $this->categories[$category]['label'] ?? $category;
            echo '<li>' . $label . ': ' . $count . '</li>';
        }
        echo '</ul>';
        
        echo '<h4>By Tier:</h4>';
        echo '<ul>';
        foreach ($stats['by_tier'] as $tier => $count) {
            echo '<li>' . ucfirst($tier) . ': ' . $count . '</li>';
        }
        echo '</ul>';
        
        echo '<h4>Registered Tools:</h4>';
        echo '<ul>';
        foreach ($this->tools as $slug => $tool) {
            $name = $tool['metadata']['name'] ?? $slug;
            echo '<li><strong>' . $name . '</strong> (' . $slug . ')</li>';
        }
        echo '</ul>';
        
        echo '</div>';
    }
}

/**
 * Helper function to get registry instance
 * 
 * @return ToolRegistry
 */
function btd_registry() {
    return ToolRegistry::getInstance();
}

/**
 * Helper function to register a tool
 * 
 * @param object $tool Tool instance
 * @return bool
 */
function btd_register_tool($tool) {
    return ToolRegistry::getInstance()->register($tool);
}

/**
 * Helper function to get a tool
 * 
 * @param string $slug Tool slug
 * @return object|null
 */
function btd_get_tool($slug) {
    return ToolRegistry::getInstance()->getTool($slug);
}