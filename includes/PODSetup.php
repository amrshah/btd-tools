<?php
namespace BTD;

/**
 * POD Framework Setup
 * 
 * Configures POD for tools catalog and content management
 */
class PODSetup {
    
    public function init() {
        add_action('init', [$this, 'register'], 25);
    }
    
    public function register() {
        // Check if POD is installed
        if (!function_exists('pods_register_type')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>';
                echo '<strong>BTD Tools:</strong> Pods Framework plugin is required. ';
                echo '<a href="' . admin_url('plugin-install.php?s=pods&tab=search&type=term') . '">Install Pods</a>';
                echo '</p></div>';
            });
            return;
        }
        
        // Register Tools Custom Post Type
        $this->registerToolsCPT();
        
        // Register Tool Categories Taxonomy
        $this->registerToolCategories();
        
        // Register Tool Fields
        $this->registerToolFields();
    }
    
    /**
     * Register Tools Custom Post Type
     */
    private function registerToolsCPT() {
        pods_register_type('btd_tool', [
            'label' => __('Business Tools', 'btd-tools'),
            'label_singular' => __('Tool', 'btd-tools'),
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'menu_position' => 31,
            'menu_icon' => 'dashicons-calculator',
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
            'has_archive' => true,
            'rewrite' => [
                'slug' => 'business-tools',
                'with_front' => false
            ],
            'query_var' => true,
            'can_export' => true,
            'show_in_rest' => true,
            'rest_base' => 'btd-tools',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ]);
    }
    
    /**
     * Register Tool Categories Taxonomy
     */
    private function registerToolCategories() {
        pods_register_taxonomy('btd_tool_category', 'btd_tool', [
            'label' => __('Tool Categories', 'btd-tools'),
            'label_singular' => __('Tool Category', 'btd-tools'),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_quick_edit' => true,
            'show_admin_column' => true,
            'hierarchical' => true,
            'rewrite' => [
                'slug' => 'tool-category',
                'with_front' => false,
                'hierarchical' => true
            ],
            'query_var' => true,
            'show_in_rest' => true,
            'rest_base' => 'tool-categories',
        ]);
    }
    
    /**
     * Register Tool Custom Fields
     */
    private function registerToolFields() {
        // Tool Slug (unique identifier)
        pods_register_field('btd_tool', [
            'name' => 'tool_slug',
            'label' => __('Tool Slug', 'btd-tools'),
            'type' => 'slug',
            'slug_field' => 'post_title',
            'required' => true,
            'unique' => true,
            'admin_only' => false,
        ]);
        
        // Tool Type
        pods_register_field('btd_tool', [
            'name' => 'tool_type',
            'label' => __('Tool Type', 'btd-tools'),
            'type' => 'pick',
            'pick_format_type' => 'single',
            'pick_format_single' => 'dropdown',
            'data' => [
                'calculator' => __('Calculator', 'btd-tools'),
                'generator' => __('Generator', 'btd-tools'),
                'ai_tool' => __('AI Tool', 'btd-tools'),
                'tracker' => __('Tracker', 'btd-tools'),
            ],
            'required' => true,
        ]);
        
        // Tier Required
        pods_register_field('btd_tool', [
            'name' => 'tier_required',
            'label' => __('Subscription Tier Required', 'btd-tools'),
            'type' => 'pick',
            'pick_format_type' => 'single',
            'pick_format_single' => 'dropdown',
            'data' => [
                'free' => __('Free', 'btd-tools'),
                'starter' => __('Starter', 'btd-tools'),
                'pro' => __('Professional', 'btd-tools'),
                'business' => __('Business', 'btd-tools'),
            ],
            'default_value' => 'free',
            'required' => true,
        ]);
        
        // Icon
        pods_register_field('btd_tool', [
            'name' => 'tool_icon',
            'label' => __('Tool Icon', 'btd-tools'),
            'type' => 'text',
            'help' => __('Dashicon class (e.g., dashicons-calculator) or emoji', 'btd-tools'),
            'default_value' => 'dashicons-calculator',
        ]);
        
        // Color
        pods_register_field('btd_tool', [
            'name' => 'tool_color',
            'label' => __('Tool Color', 'btd-tools'),
            'type' => 'color',
            'default_value' => '#2563eb',
        ]);
        
        // Short Description
        pods_register_field('btd_tool', [
            'name' => 'short_description',
            'label' => __('Short Description', 'btd-tools'),
            'type' => 'wysiwyg',
            'wysiwyg_editor' => 'tinymce',
            'wysiwyg_media_buttons' => false,
            'help' => __('Brief description shown in tool catalog', 'btd-tools'),
        ]);
        
        // How to Use
        pods_register_field('btd_tool', [
            'name' => 'how_to_use',
            'label' => __('How to Use', 'btd-tools'),
            'type' => 'wysiwyg',
            'wysiwyg_editor' => 'tinymce',
            'help' => __('Step-by-step instructions', 'btd-tools'),
        ]);
        
        // Use Cases
        pods_register_field('btd_tool', [
            'name' => 'use_cases',
            'label' => __('Use Cases', 'btd-tools'),
            'type' => 'wysiwyg',
            'wysiwyg_editor' => 'tinymce',
            'help' => __('Common use cases and examples', 'btd-tools'),
        ]);
        
        // Featured
        pods_register_field('btd_tool', [
            'name' => 'is_featured',
            'label' => __('Featured Tool', 'btd-tools'),
            'type' => 'boolean',
            'boolean_format_type' => 'checkbox',
            'default_value' => false,
        ]);
        
        // Popular
        pods_register_field('btd_tool', [
            'name' => 'is_popular',
            'label' => __('Popular Tool', 'btd-tools'),
            'type' => 'boolean',
            'boolean_format_type' => 'checkbox',
            'default_value' => false,
        ]);
        
        // New Badge (days)
        pods_register_field('btd_tool', [
            'name' => 'show_new_badge_days',
            'label' => __('Show "New" Badge (days)', 'btd-tools'),
            'type' => 'number',
            'number_format' => 'i',
            'default_value' => 30,
            'help' => __('Show "New" badge for this many days after publication', 'btd-tools'),
        ]);
        
        // Related Tools
        pods_register_field('btd_tool', [
            'name' => 'related_tools',
            'label' => __('Related Tools', 'btd-tools'),
            'type' => 'pick',
            'pick_object' => 'post_type',
            'pick_val' => 'btd_tool',
            'pick_format_type' => 'multi',
            'pick_format_multi' => 'autocomplete',
            'pick_limit' => 5,
        ]);
    }
    
    /**
     * Get tool by slug
     */
    public static function getToolBySlug($slug) {
        return pods('btd_tool', ['slug' => $slug]);
    }
    
    /**
     * Get all tools
     */
    public static function getAllTools($params = []) {
        $defaults = [
            'limit' => -1,
            'orderby' => 'menu_order ASC, post_title ASC',
        ];
        
        $params = array_merge($defaults, $params);
        
        return pods('btd_tool', $params);
    }
    
    /**
     * Get tools by category
     */
    public static function getToolsByCategory($category_slug, $params = []) {
        $defaults = [
            'where' => "btd_tool_category.slug = '{$category_slug}'",
            'limit' => -1,
            'orderby' => 'menu_order ASC, post_title ASC',
        ];
        
        $params = array_merge($defaults, $params);
        
        return pods('btd_tool', $params);
    }
    
    /**
     * Get featured tools
     */
    public static function getFeaturedTools($limit = 6) {
        return pods('btd_tool', [
            'where' => 'is_featured.meta_value = 1',
            'limit' => $limit,
            'orderby' => 'menu_order ASC',
        ]);
    }
}