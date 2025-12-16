<?php
namespace BTD\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Usage Log Model
 * 
 * Tracks all tool interactions for analytics
 */
class UsageLog extends Model {
    
    protected $table = 'btd_usage_logs';
    
    public $timestamps = false; // Only has created_at
    
    protected $fillable = [
        'user_id',
        'tool_slug',
        'action',
        'metadata',
        'ip_address',
        'user_agent',
        'session_id',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
    
    /**
     * Boot method
     */
    protected static function boot() {
        parent::boot();
        
        // Automatically set created_at
        static::creating(function ($model) {
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }
    
    /**
     * Scope: By tool
     */
    public function scopeByTool($query, $toolSlug) {
        return $query->where('tool_slug', $toolSlug);
    }
    
    /**
     * Scope: By action
     */
    public function scopeByAction($query, $action) {
        return $query->where('action', $action);
    }
    
    /**
     * Scope: By user
     */
    public function scopeByUser($query, $userId) {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Scope: Time range
     */
    public function scopeDateRange($query, $start, $end) {
        return $query->whereBetween('created_at', [$start, $end]);
    }
    
    /**
     * Static: Log an action
     */
    public static function log($toolSlug, $action, $metadata = []) {
        return static::create([
            'user_id' => get_current_user_id() ?: null,
            'tool_slug' => $toolSlug,
            'action' => $action,
            'metadata' => $metadata,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'session_id' => session_id() ?: wp_get_session_token(),
        ]);
    }
    
    /**
     * Static: Get daily usage for a tool
     */
    public static function getDailyUsage($toolSlug, $days = 30) {
        return static::byTool($toolSlug)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
    
    /**
     * Static: Get popular tools
     */
    public static function getPopularTools($days = 30, $limit = 10) {
        return static::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('tool_slug, COUNT(*) as usage_count')
            ->groupBy('tool_slug')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get();
    }
}