<?php

namespace BTD\Models;

use Illuminate\Database\Eloquent\Model;

class SavedResult extends Model {
    
    protected $table = 'btd_saved_results';
    
    protected $fillable = [
        'user_id',
        'calculation_id',
        'tool_slug',
        'result_name',
        'result_data',
        'is_favorite',
        'is_shared',
        'share_token',
    ];
    
    protected $casts = [
        'result_data' => 'array',
        'is_favorite' => 'boolean',
        'is_shared' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the calculation
     */
    public function calculation() {
        return $this->belongsTo(Calculation::class);
    }
    
    /**
     * Scope: By user
     */
    public function scopeByUser($query, $userId) {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Scope: Favorites
     */
    public function scopeFavorites($query) {
        return $query->where('is_favorite', true);
    }
    
    /**
     * Scope: By tool
     */
    public function scopeByTool($query, $toolSlug) {
        return $query->where('tool_slug', $toolSlug);
    }
    
    /**
     * Generate share token
     */
    public function generateShareToken() {
        $this->share_token = bin2hex(random_bytes(16));
        $this->is_shared = true;
        $this->save();
        
        return $this->share_token;
    }
    
    /**
     * Get share URL
     */
    public function getShareUrl() {
        if (!$this->is_shared || !$this->share_token) {
            return null;
        }
        
        return home_url('/shared-result/' . $this->share_token);
    }
    
    /**
     * Static: Find by share token
     */
    public static function findByShareToken($token) {
        return static::where('share_token', $token)
            ->where('is_shared', true)
            ->first();
    }
}