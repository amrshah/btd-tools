<?php
namespace BTD\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Calculation Model
 * 
 * Represents a single calculation/result from any tool
 */
class Calculation extends Model {
    
    protected $table = 'btd_calculations';
    
    protected $fillable = [
        'user_id',
        'tool_id',
        'tool_slug',
        'input_data',
        'result_data',
        'ip_address',
        'user_agent',
    ];
    
    protected $casts = [
        'input_data' => 'array',
        'result_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the user (WordPress user)
     * Note: This returns a stdClass object, not a full WP_User
     */
    public function user() {
        global $wpdb;
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT ID, user_login, user_email, display_name FROM {$wpdb->users} WHERE ID = %d",
            $this->user_id
        ));
        
        return $user;
    }
    
    /**
     * Get the tool (POD)
     */
    public function getTool() {
        if (!function_exists('pods')) {
            return null;
        }
        
        return pods('btd_tool', $this->tool_id);
    }
    
    /**
     * Scope: Filter by tool slug
     */
    public function scopeByTool($query, $toolSlug) {
        return $query->where('tool_slug', $toolSlug);
    }
    
    /**
     * Scope: Recent calculations (default 30 days)
     */
    public function scopeRecent($query, $days = 30) {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
    
    /**
     * Scope: By user
     */
    public function scopeByUser($query, $userId) {
        return $query->where('user_id', $userId);
    }
    
    /**
     * Scope: Today's calculations
     */
    public function scopeToday($query) {
        return $query->whereDate('created_at', today());
    }
    
    /**
     * Scope: This week
     */
    public function scopeThisWeek($query) {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }
    
    /**
     * Scope: This month
     */
    public function scopeThisMonth($query) {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }
    
    /**
     * Accessor: Get specific result value
     */
    public function getResultValue($key, $default = null) {
        return $this->result_data[$key] ?? $default;
    }
    
    /**
     * Static: Get tool statistics
     */
    public static function getToolStats($toolSlug, $days = 30) {
        $query = static::byTool($toolSlug)->recent($days);
        
        return [
            'total_uses' => $query->count(),
            'unique_users' => $query->distinct('user_id')->count('user_id'),
            'avg_per_user' => $query->count() / max($query->distinct('user_id')->count('user_id'), 1),
            'today' => static::byTool($toolSlug)->today()->count(),
            'this_week' => static::byTool($toolSlug)->thisWeek()->count(),
            'this_month' => static::byTool($toolSlug)->thisMonth()->count(),
        ];
    }
    
    /**
     * Static: Get user's calculation history
     */
    public static function getUserHistory($userId, $limit = 20) {
        return static::byUser($userId)
            ->latest()
            ->limit($limit)
            ->get();
    }
}