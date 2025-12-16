<?php
namespace BTD;

use BTD\Models\Tool;
use BTD\Models\ToolCategory;

/**
 * Tool Repository
 * 
 * Centralized tool query methods
 * Replaces PODSetup.php
 */
class ToolRepository {
    
    /**
     * Get tool by slug
     */
    public static function getToolBySlug($slug) {
        return Tool::getBySlug($slug);
    }
    
    /**
     * Get all tools with optional filters
     */
    public static function getAllTools($params = []) {
        return Tool::getAllTools($params);
    }
    
    /**
     * Get tools by category
     */
    public static function getToolsByCategory($category_slug, $limit = -1) {
        return Tool::getToolsByCategory($category_slug, $limit);
    }
    
    /**
     * Get featured tools
     */
    public static function getFeaturedTools($limit = 6) {
        return Tool::getFeaturedTools($limit);
    }
    
    /**
     * Get popular tools
     */
    public static function getPopularTools($limit = 6) {
        return Tool::getPopularTools($limit);
    }
    
    /**
     * Get tools by type
     */
    public static function getToolsByType($type, $limit = -1) {
        return Tool::getToolsByType($type, $limit);
    }
    
    /**
     * Search tools
     */
    public static function searchTools($query, $limit = -1) {
        $tools = Tool::active()
            ->search($query)
            ->ordered();
        
        return $limit > 0 ? $tools->limit($limit)->get() : $tools->get();
    }
    
    /**
     * Get all categories
     */
    public static function getAllCategories($hierarchical = false) {
        return ToolCategory::getAllCategories($hierarchical);
    }
    
    /**
     * Get category by slug
     */
    public static function getCategoryBySlug($slug) {
        return ToolCategory::getBySlug($slug);
    }
    
    /**
     * Get category tree
     */
    public static function getCategoryTree() {
        return ToolCategory::getTree();
    }
    
    /**
     * Get tool statistics
     */
    public static function getToolStats($tool_slug = null) {
        if ($tool_slug) {
            $tool = static::getToolBySlug($tool_slug);
            if (!$tool) {
                return null;
            }
            
            return [
                'total_calculations' => $tool->calculations()->count(),
                'unique_users' => $tool->calculations()->distinct('user_id')->count('user_id'),
                'last_30_days' => $tool->calculations()
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
            ];
        }
        
        // Overall stats
        return [
            'total_tools' => Tool::active()->count(),
            'total_categories' => ToolCategory::count(),
            'featured_tools' => Tool::active()->featured()->count(),
            'popular_tools' => Tool::active()->popular()->count(),
        ];
    }
}
