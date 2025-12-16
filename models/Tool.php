<?php
namespace BTD\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tool Model
 * 
 * Represents a business tool (calculator, generator, AI tool, etc.)
 * Replaces Pods custom post type
 */
class Tool extends Model {
    
    protected $table = 'btd_tools';
    
    protected $fillable = [
        'title',
        'slug',
        'description',
        'excerpt',
        'tool_type',
        'tier_required',
        'tool_icon',
        'tool_color',
        'short_description',
        'how_to_use',
        'use_cases',
        'is_featured',
        'is_popular',
        'is_active',
        'show_new_badge_days',
        'menu_order',
        'featured_image_url',
        'published_at',
    ];
    
    protected $casts = [
        'is_featured' => 'boolean',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'show_new_badge_days' => 'integer',
        'menu_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'published_at' => 'datetime',
    ];
    
    /**
     * Relationships
     */
    
    // Categories (many-to-many)
    public function categories() {
        return $this->belongsToMany(
            ToolCategory::class,
            'btd_tool_category_relationships',
            'tool_id',
            'category_id'
        );
    }
    
    // Related tools (many-to-many, self-referencing)
    public function relatedTools() {
        return $this->belongsToMany(
            Tool::class,
            'btd_tool_relationships',
            'tool_id',
            'related_tool_id'
        );
    }
    
    // Calculations (one-to-many)
    public function calculations() {
        return $this->hasMany(Calculation::class, 'tool_id');
    }
    
    /**
     * Scopes
     */
    
    public function scopeActive($query) {
        return $query->where('is_active', true);
    }
    
    public function scopeFeatured($query) {
        return $query->where('is_featured', true);
    }
    
    public function scopePopular($query) {
        return $query->where('is_popular', true);
    }
    
    public function scopeByType($query, $type) {
        return $query->where('tool_type', $type);
    }
    
    public function scopeByTier($query, $tier) {
        return $query->where('tier_required', $tier);
    }
    
    public function scopeByCategory($query, $categorySlug) {
        return $query->whereHas('categories', function($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }
    
    public function scopeSearch($query, $searchTerm) {
        return $query->where(function($q) use ($searchTerm) {
            $q->where('title', 'LIKE', "%{$searchTerm}%")
              ->orWhere('description', 'LIKE', "%{$searchTerm}%")
              ->orWhere('short_description', 'LIKE', "%{$searchTerm}%");
        });
    }
    
    public function scopeOrdered($query) {
        return $query->orderBy('menu_order', 'asc')
                     ->orderBy('title', 'asc');
    }
    
    /**
     * Accessors & Mutators
     */
    
    // Auto-generate slug from title if not provided
    public function setTitleAttribute($value) {
        $this->attributes['title'] = $value;
        
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
    
    private function generateSlug($title) {
        $slug = sanitize_title($title);
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
     * Check if tool is new (based on show_new_badge_days)
     */
    public function isNew() {
        if (!$this->published_at) {
            return false;
        }
        
        $daysOld = $this->published_at->diffInDays(now());
        return $daysOld <= $this->show_new_badge_days;
    }
    
    /**
     * Get primary category
     */
    public function getPrimaryCategory() {
        return $this->categories()->first();
    }
    
    /**
     * Static Helper Methods (replacing PODSetup methods)
     */
    
    public static function getBySlug($slug) {
        return static::where('slug', $slug)
            ->active()
            ->first();
    }
    
    public static function getAllTools($params = []) {
        $query = static::active();
        
        // Filter by category
        if (!empty($params['category'])) {
            $query->byCategory($params['category']);
        }
        
        // Filter by type
        if (!empty($params['type'])) {
            $query->byType($params['type']);
        }
        
        // Filter by tier
        if (!empty($params['tier'])) {
            $query->byTier($params['tier']);
        }
        
        // Search
        if (!empty($params['search'])) {
            $query->search($params['search']);
        }
        
        // Featured only
        if (!empty($params['featured'])) {
            $query->featured();
        }
        
        // Popular only
        if (!empty($params['popular'])) {
            $query->popular();
        }
        
        // Limit
        $limit = $params['limit'] ?? -1;
        
        // Order
        $query->ordered();
        
        return $limit > 0 ? $query->limit($limit)->get() : $query->get();
    }
    
    public static function getFeaturedTools($limit = 6) {
        return static::active()
            ->featured()
            ->ordered()
            ->limit($limit)
            ->get();
    }
    
    public static function getPopularTools($limit = 6) {
        return static::active()
            ->popular()
            ->ordered()
            ->limit($limit)
            ->get();
    }
    
    public static function getToolsByCategory($categorySlug, $limit = -1) {
        $query = static::active()
            ->byCategory($categorySlug)
            ->ordered();
        
        return $limit > 0 ? $query->limit($limit)->get() : $query->get();
    }
    
    public static function getToolsByType($type, $limit = -1) {
        $query = static::active()
            ->byType($type)
            ->ordered();
        
        return $limit > 0 ? $query->limit($limit)->get() : $query->get();
    }
}
