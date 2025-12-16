<?php
namespace BTD;

/**
 * Tool Post Type Registration
 * 
 * Registers custom post type and taxonomy using native WordPress functions
 * (Not Pods - we're using Eloquent for data storage)
 */
class ToolPostType {
    
    public function init() {
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerTaxonomy']);
    }
    
    /**
     * Register Tools Custom Post Type
     * 
     * Note: This is for admin UI only. Data is stored in btd_tools table via Eloquent.
     */
    public function registerPostType() {
        $labels = [
            'name' => __('Business Tools', 'btd-tools'),
            'singular_name' => __('Tool', 'btd-tools'),
            'menu_name' => __('Business Tools', 'btd-tools'),
            'add_new' => __('Add New', 'btd-tools'),
            'add_new_item' => __('Add New Tool', 'btd-tools'),
            'edit_item' => __('Edit Tool', 'btd-tools'),
            'new_item' => __('New Tool', 'btd-tools'),
            'view_item' => __('View Tool', 'btd-tools'),
            'view_items' => __('View Tools', 'btd-tools'),
            'search_items' => __('Search Tools', 'btd-tools'),
            'not_found' => __('No tools found', 'btd-tools'),
            'not_found_in_trash' => __('No tools found in trash', 'btd-tools'),
            'all_items' => __('All Tools', 'btd-tools'),
            'archives' => __('Tool Archives', 'btd-tools'),
            'attributes' => __('Tool Attributes', 'btd-tools'),
            'insert_into_item' => __('Insert into tool', 'btd-tools'),
            'uploaded_to_this_item' => __('Uploaded to this tool', 'btd-tools'),
            'filter_items_list' => __('Filter tools list', 'btd-tools'),
            'items_list_navigation' => __('Tools list navigation', 'btd-tools'),
            'items_list' => __('Tools list', 'btd-tools'),
        ];
        
        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'menu_position' => 31,
            'menu_icon' => 'dashicons-calculator',
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'page-attributes'],
            'has_archive' => true,
            'rewrite' => [
                'slug' => 'business-tools',
                'with_front' => false,
            ],
            'query_var' => true,
            'can_export' => true,
            'show_in_rest' => true,
            'rest_base' => 'btd-tools',
        ];
        
        register_post_type('btd_tool', $args);
    }
    
    /**
     * Register Tool Categories Taxonomy
     */
    public function registerTaxonomy() {
        $labels = [
            'name' => __('Tool Categories', 'btd-tools'),
            'singular_name' => __('Tool Category', 'btd-tools'),
            'menu_name' => __('Categories', 'btd-tools'),
            'all_items' => __('All Categories', 'btd-tools'),
            'edit_item' => __('Edit Category', 'btd-tools'),
            'view_item' => __('View Category', 'btd-tools'),
            'update_item' => __('Update Category', 'btd-tools'),
            'add_new_item' => __('Add New Category', 'btd-tools'),
            'new_item_name' => __('New Category Name', 'btd-tools'),
            'parent_item' => __('Parent Category', 'btd-tools'),
            'parent_item_colon' => __('Parent Category:', 'btd-tools'),
            'search_items' => __('Search Categories', 'btd-tools'),
            'popular_items' => __('Popular Categories', 'btd-tools'),
            'not_found' => __('No categories found', 'btd-tools'),
            'back_to_items' => __('← Back to Categories', 'btd-tools'),
        ];
        
        $args = [
            'labels' => $labels,
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
                'hierarchical' => true,
            ],
            'query_var' => true,
            'show_in_rest' => true,
            'rest_base' => 'tool-categories',
        ];
        
        register_taxonomy('btd_tool_category', 'btd_tool', $args);
    }
}
