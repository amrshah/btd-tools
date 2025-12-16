<?php
namespace BTD\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tool Category Model
 * 
 * Represents a tool category with hierarchical support
 * Replaces Pods taxonomy
 */
class ToolCategory extends Model {
    
    protected $table = 'btd_tool_categories';
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'menu_order',
        'count',
    ];
    
    protected $casts = [
        'parent_id' => 'integer',
        'menu_order' => 'integer',
        'count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Relationships
     */
    
    // Tools in this category (many-to-many)
    public function tools() {
        return $this->belongsToMany(
            Tool::class,
            'btd_tool_category_relationships',
            'category_id',
            'tool_id'
        );
    }
    
    // Parent category (self-referencing)
    public function parent() {
        return $this->belongsTo(ToolCategory::class, 'parent_id');
    }
    
    // Child categories (self-referencing)
    public function children() {
        return $this->hasMany(ToolCategory::class, 'parent_id')
            ->orderBy('menu_order', 'asc')
            ->orderBy('name', 'asc');
    }
    
    /**
     * Scopes
     */
    
    public function scopeRootCategories($query) {
        return $query->whereNull('parent_id');
    }
    
    public function scopeOrdered($query) {
        return $query->orderBy('menu_order', 'asc')
                     ->orderBy('name', 'asc');
    }
    
    /**
     * Accessors & Mutators
     */
    
    // Auto-generate slug from name if not provided
    public function setNameAttribute($value) {
        $this->attributes['name'] = $value;
        
        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = $this->generateSlug($value);
        }
    }
    
    // Ensure slug is URL-friendly
    public function setSlugAttribute($value) {
        $this->attributes['slug'] = sanitize_title($value);
    }
    
    /**
     * Helper Methods
     */
    
    private function generateSlug($name) {
        $slug = sanitize_title($name);
        $original = $slug;
        $counter = 1;
        
        // Ensure unique slug
        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $original . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Check if this is a root category
     */
    public function isRoot() {
        return $this->parent_id === null;
    }
    
    /**
     * Get all ancestors (parent, grandparent, etc.)
     */
    public function getAncestors() {
        $ancestors = collect();
        $current = $this->parent;
        
        while ($current) {
            $ancestors->push($current);
            $current = $current->parent;
        }
        
        return $ancestors->reverse();
    }
    
    /**
     * Get breadcrumb trail
     */
    public function getBreadcrumb($separator = ' > ') {
        $ancestors = $this->getAncestors();
        $ancestors->push($this);
        
        return $ancestors->pluck('name')->implode($separator);
    }
    
    /**
     * Update tool count
     */
    public function updateCount() {
        $this->count = $this->tools()->count();
        $this->save();
    }
    
    /**
     * Static Helper Methods
     */
    
    public static function getBySlug($slug) {
        return static::where('slug', $slug)->first();
    }
    
    public static function getAllCategories($hierarchical = false) {
        if ($hierarchical) {
            return static::rootCategories()
                ->ordered()
                ->with('children')
                ->get();
        }
        
        return static::ordered()->get();
    }
    
    public static function getRootCategories() {
        return static::rootCategories()
            ->ordered()
            ->get();
    }
    
    /**
     * Build hierarchical tree structure
     */
    public static function getTree() {
        $categories = static::with('children')->rootCategories()->ordered()->get();
        
        return $categories->map(function($category) {
            return static::buildTreeNode($category);
        });
    }
    
    private static function buildTreeNode($category) {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'count' => $category->count,
            'children' => $category->children->map(function($child) {
                return static::buildTreeNode($child);
            })->toArray(),
        ];
    }
}
