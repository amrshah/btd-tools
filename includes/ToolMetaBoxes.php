<?php
namespace BTD;

use BTD\Models\Tool;
use BTD\Models\ToolCategory;

/**
 * Tool Meta Boxes
 * 
 * Handles admin meta boxes for tool editing
 * Saves data to btd_tools table via Eloquent (not post meta)
 */
class ToolMetaBoxes {
    
    public function init() {
        add_action('add_meta_boxes', [$this, 'registerMetaBoxes']);
        add_action('save_post_btd_tool', [$this, 'saveToolData'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }
    
    /**
     * Register all meta boxes
     */
    public function registerMetaBoxes() {
        // Tool Settings
        add_meta_box(
            'btd_tool_settings',
            __('Tool Settings', 'btd-tools'),
            [$this, 'renderSettingsMetaBox'],
            'btd_tool',
            'normal',
            'high'
        );
        
        // Tool Configuration
        add_meta_box(
            'btd_tool_configuration',
            __('Tool Configuration', 'btd-tools'),
            [$this, 'renderConfigurationMetaBox'],
            'btd_tool',
            'normal',
            'default'
        );
        
        // Display Options
        add_meta_box(
            'btd_tool_display',
            __('Display Options', 'btd-tools'),
            [$this, 'renderDisplayMetaBox'],
            'btd_tool',
            'side',
            'default'
        );
        
        // Related Tools
        add_meta_box(
            'btd_tool_related',
            __('Related Tools', 'btd-tools'),
            [$this, 'renderRelatedToolsMetaBox'],
            'btd_tool',
            'side',
            'default'
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueScripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }
        
        global $post;
        if (!$post || $post->post_type !== 'btd_tool') {
            return;
        }
        
        // WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Custom admin JS
        wp_enqueue_script(
            'btd-tool-admin',
            BTD_PLUGIN_URL . 'assets/js/tool-admin.js',
            ['jquery', 'wp-color-picker'],
            BTD_VERSION,
            true
        );
    }
    
    /**
     * Render Tool Settings Meta Box
     */
    public function renderSettingsMetaBox($post) {
        $tool = $this->getToolData($post->ID);
        wp_nonce_field('btd_tool_meta', 'btd_tool_nonce');
        ?>
        <table class="form-table">
            <tr>
                <th><label for="tool_slug"><?php _e('Tool Slug', 'btd-tools'); ?></label></th>
                <td>
                    <input type="text" 
                           id="tool_slug" 
                           name="tool_slug" 
                           value="<?php echo esc_attr($tool['slug'] ?? sanitize_title($post->post_title)); ?>" 
                           class="regular-text"
                           pattern="[a-z0-9-]+"
                           required>
                    <p class="description"><?php _e('URL-friendly identifier (lowercase, hyphens only)', 'btd-tools'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th><label for="tool_type"><?php _e('Tool Type', 'btd-tools'); ?> *</label></th>
                <td>
                    <select id="tool_type" name="tool_type" required>
                        <option value=""><?php _e('Select Type', 'btd-tools'); ?></option>
                        <option value="calculator" <?php selected($tool['tool_type'] ?? '', 'calculator'); ?>>
                            <?php _e('Calculator', 'btd-tools'); ?>
                        </option>
                        <option value="generator" <?php selected($tool['tool_type'] ?? '', 'generator'); ?>>
                            <?php _e('Generator', 'btd-tools'); ?>
                        </option>
                        <option value="ai_tool" <?php selected($tool['tool_type'] ?? '', 'ai_tool'); ?>>
                            <?php _e('AI Tool', 'btd-tools'); ?>
                        </option>
                        <option value="tracker" <?php selected($tool['tool_type'] ?? '', 'tracker'); ?>>
                            <?php _e('Tracker', 'btd-tools'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="tier_required"><?php _e('Subscription Tier Required', 'btd-tools'); ?> *</label></th>
                <td>
                    <select id="tier_required" name="tier_required" required>
                        <option value="free" <?php selected($tool['tier_required'] ?? 'free', 'free'); ?>>
                            <?php _e('Free', 'btd-tools'); ?>
                        </option>
                        <option value="starter" <?php selected($tool['tier_required'] ?? '', 'starter'); ?>>
                            <?php _e('Starter', 'btd-tools'); ?>
                        </option>
                        <option value="pro" <?php selected($tool['tier_required'] ?? '', 'pro'); ?>>
                            <?php _e('Professional', 'btd-tools'); ?>
                        </option>
                        <option value="business" <?php selected($tool['tier_required'] ?? '', 'business'); ?>>
                            <?php _e('Business', 'btd-tools'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="tool_icon"><?php _e('Tool Icon', 'btd-tools'); ?></label></th>
                <td>
                    <input type="text" 
                           id="tool_icon" 
                           name="tool_icon" 
                           value="<?php echo esc_attr($tool['tool_icon'] ?? 'dashicons-calculator'); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('Dashicon class (e.g., dashicons-calculator) or emoji', 'btd-tools'); ?>
                        <br>
                        <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">
                            <?php _e('Browse Dashicons', 'btd-tools'); ?>
                        </a>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th><label for="tool_color"><?php _e('Tool Color', 'btd-tools'); ?></label></th>
                <td>
                    <input type="text" 
                           id="tool_color" 
                           name="tool_color" 
                           value="<?php echo esc_attr($tool['tool_color'] ?? '#2563eb'); ?>" 
                           class="btd-color-picker">
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Render Tool Configuration Meta Box
     */
    public function renderConfigurationMetaBox($post) {
        $tool = $this->getToolData($post->ID);
        ?>
        <div class="btd-config-field">
            <label for="short_description"><strong><?php _e('Short Description', 'btd-tools'); ?></strong></label>
            <p class="description"><?php _e('Brief description shown in tool catalog', 'btd-tools'); ?></p>
            <?php
            wp_editor(
                $tool['short_description'] ?? '',
                'short_description',
                [
                    'textarea_name' => 'short_description',
                    'textarea_rows' => 5,
                    'media_buttons' => false,
                    'teeny' => true,
                ]
            );
            ?>
        </div>
        
        <div class="btd-config-field" style="margin-top: 20px;">
            <label for="how_to_use"><strong><?php _e('How to Use', 'btd-tools'); ?></strong></label>
            <p class="description"><?php _e('Step-by-step instructions for using this tool', 'btd-tools'); ?></p>
            <?php
            wp_editor(
                $tool['how_to_use'] ?? '',
                'how_to_use',
                [
                    'textarea_name' => 'how_to_use',
                    'textarea_rows' => 8,
                    'media_buttons' => false,
                ]
            );
            ?>
        </div>
        
        <div class="btd-config-field" style="margin-top: 20px;">
            <label for="use_cases"><strong><?php _e('Use Cases', 'btd-tools'); ?></strong></label>
            <p class="description"><?php _e('Common use cases and examples', 'btd-tools'); ?></p>
            <?php
            wp_editor(
                $tool['use_cases'] ?? '',
                'use_cases',
                [
                    'textarea_name' => 'use_cases',
                    'textarea_rows' => 8,
                    'media_buttons' => false,
                ]
            );
            ?>
        </div>
        <?php
    }
    
    /**
     * Render Display Options Meta Box
     */
    public function renderDisplayMetaBox($post) {
        $tool = $this->getToolData($post->ID);
        ?>
        <p>
            <label>
                <input type="checkbox" 
                       name="is_featured" 
                       value="1" 
                       <?php checked($tool['is_featured'] ?? false, true); ?>>
                <?php _e('Featured Tool', 'btd-tools'); ?>
            </label>
        </p>
        
        <p>
            <label>
                <input type="checkbox" 
                       name="is_popular" 
                       value="1" 
                       <?php checked($tool['is_popular'] ?? false, true); ?>>
                <?php _e('Popular Tool', 'btd-tools'); ?>
            </label>
        </p>
        
        <p>
            <label for="show_new_badge_days">
                <?php _e('Show "New" Badge (days)', 'btd-tools'); ?>
            </label>
            <input type="number" 
                   id="show_new_badge_days" 
                   name="show_new_badge_days" 
                   value="<?php echo esc_attr($tool['show_new_badge_days'] ?? 30); ?>" 
                   min="0" 
                   max="365" 
                   class="small-text">
        </p>
        <?php
    }
    
    /**
     * Render Related Tools Meta Box
     */
    public function renderRelatedToolsMetaBox($post) {
        $tool = $this->getToolData($post->ID);
        $related_ids = $tool['related_tool_ids'] ?? [];
        
        // Get all tools except current one
        $all_tools = Tool::where('id', '!=', $tool['id'] ?? 0)
            ->where('is_active', true)
            ->orderBy('title', 'asc')
            ->get();
        ?>
        <p class="description"><?php _e('Select up to 5 related tools', 'btd-tools'); ?></p>
        
        <select name="related_tools[]" multiple size="10" style="width: 100%; height: 200px;">
            <?php foreach ($all_tools as $t): ?>
                <option value="<?php echo $t->id; ?>" 
                        <?php echo in_array($t->id, $related_ids) ? 'selected' : ''; ?>>
                    <?php echo esc_html($t->title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <p class="description">
            <?php _e('Hold Ctrl (Cmd on Mac) to select multiple', 'btd-tools'); ?>
        </p>
        <?php
    }
    
    /**
     * Get tool data from Eloquent table
     */
    private function getToolData($post_id) {
        // Try to find existing tool by post_id (we'll store this as meta)
        $tool_id = get_post_meta($post_id, '_btd_tool_id', true);
        
        if ($tool_id) {
            $tool = Tool::find($tool_id);
            if ($tool) {
                $data = $tool->toArray();
                
                // Get related tool IDs
                $data['related_tool_ids'] = $tool->relatedTools()->pluck('id')->toArray();
                
                return $data;
            }
        }
        
        return [];
    }
    
    /**
     * Save tool data to Eloquent table
     */
    public function saveToolData($post_id, $post) {
        // Security checks
        if (!isset($_POST['btd_tool_nonce']) || !wp_verify_nonce($_POST['btd_tool_nonce'], 'btd_tool_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Get existing tool or create new
        $tool_id = get_post_meta($post_id, '_btd_tool_id', true);
        $tool = $tool_id ? Tool::find($tool_id) : new Tool();
        
        // Prepare data
        $data = [
            'title' => $post->post_title,
            'slug' => sanitize_title($_POST['tool_slug'] ?? $post->post_title),
            'description' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'tool_type' => $_POST['tool_type'] ?? 'calculator',
            'tier_required' => $_POST['tier_required'] ?? 'free',
            'tool_icon' => sanitize_text_field($_POST['tool_icon'] ?? 'dashicons-calculator'),
            'tool_color' => sanitize_hex_color($_POST['tool_color'] ?? '#2563eb'),
            'short_description' => wp_kses_post($_POST['short_description'] ?? ''),
            'how_to_use' => wp_kses_post($_POST['how_to_use'] ?? ''),
            'use_cases' => wp_kses_post($_POST['use_cases'] ?? ''),
            'is_featured' => isset($_POST['is_featured']),
            'is_popular' => isset($_POST['is_popular']),
            'is_active' => $post->post_status === 'publish',
            'show_new_badge_days' => intval($_POST['show_new_badge_days'] ?? 30),
            'menu_order' => intval($post->menu_order),
            'featured_image_url' => get_the_post_thumbnail_url($post_id, 'full') ?: null,
            'published_at' => $post->post_status === 'publish' ? ($post->post_date_gmt ?: now()) : null,
        ];
        
        // Save tool
        $tool->fill($data);
        $tool->save();
        
        // Store tool ID in post meta for future reference
        update_post_meta($post_id, '_btd_tool_id', $tool->id);
        
        // Sync categories
        $this->syncCategories($tool, $post_id);
        
        // Sync related tools
        $this->syncRelatedTools($tool, $_POST['related_tools'] ?? []);
    }
    
    /**
     * Sync categories from taxonomy to Eloquent
     */
    private function syncCategories($tool, $post_id) {
        $term_ids = wp_get_post_terms($post_id, 'btd_tool_category', ['fields' => 'ids']);
        
        // Get or create categories in Eloquent
        $category_ids = [];
        foreach ($term_ids as $term_id) {
            $term = get_term($term_id);
            if (!$term || is_wp_error($term)) {
                continue;
            }
            
            // Find or create category
            $category = ToolCategory::firstOrCreate(
                ['slug' => $term->slug],
                [
                    'name' => $term->name,
                    'description' => $term->description,
                    'parent_id' => $term->parent ? $this->getCategoryIdByTermId($term->parent) : null,
                ]
            );
            
            $category_ids[] = $category->id;
        }
        
        // Sync relationships
        $tool->categories()->sync($category_ids);
    }
    
    /**
     * Get Eloquent category ID from WP term ID
     */
    private function getCategoryIdByTermId($term_id) {
        $term = get_term($term_id);
        if (!$term || is_wp_error($term)) {
            return null;
        }
        
        $category = ToolCategory::where('slug', $term->slug)->first();
        return $category ? $category->id : null;
    }
    
    /**
     * Sync related tools
     */
    private function syncRelatedTools($tool, $related_ids) {
        $related_ids = array_map('intval', (array) $related_ids);
        $related_ids = array_slice($related_ids, 0, 5); // Max 5 related tools
        
        $tool->relatedTools()->sync($related_ids);
    }
}
