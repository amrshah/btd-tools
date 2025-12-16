<?php

/**
 * Rate Limit Model
 */

namespace BTD\Models;

use Illuminate\Database\Eloquent\Model;

class RateLimit extends Model {
    
    protected $table = 'btd_rate_limits';
    
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'ip_address',
        'tool_slug',
        'period',
        'count',
        'reset_at',
    ];
    
    protected $casts = [
        'reset_at' => 'datetime',
        'created_at' => 'datetime',
    ];
    
    /**
     * Boot method
     */
    protected static function boot() {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }
    
    /**
     * Check if rate limit reached
     */
    public static function checkLimit($toolSlug, $limit, $period = 'day') {
        $userId = get_current_user_id() ?: null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $resetAt = static::getResetTime($period);
        
        // Find or create rate limit record
        $record = static::where('tool_slug', $toolSlug)
            ->where('period', $period)
            ->where(function($query) use ($userId, $ipAddress) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('ip_address', $ipAddress);
                }
            })
            ->where('reset_at', '>', now())
            ->first();
        
        if (!$record) {
            // Create new record
            static::create([
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'tool_slug' => $toolSlug,
                'period' => $period,
                'count' => 1,
                'reset_at' => $resetAt,
            ]);
            return true;
        }
        
        if ($record->count >= $limit) {
            return false;
        }
        
        $record->increment('count');
        return true;
    }
    
    /**
     * Get remaining uses
     */
    public static function getRemainingUses($toolSlug, $limit, $period = 'day') {
        $userId = get_current_user_id() ?: null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $record = static::where('tool_slug', $toolSlug)
            ->where('period', $period)
            ->where(function($query) use ($userId, $ipAddress) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('ip_address', $ipAddress);
                }
            })
            ->where('reset_at', '>', now())
            ->first();
        
        if (!$record) {
            return $limit;
        }
        
        return max(0, $limit - $record->count);
    }
    
    /**
     * Get reset time
     */
    private static function getResetTime($period) {
        switch ($period) {
            case 'hour':
                return now()->addHour()->startOfHour();
            case 'day':
                return now()->endOfDay();
            case 'week':
                return now()->endOfWeek();
            case 'month':
                return now()->endOfMonth();
            default:
                return now()->endOfDay();
        }
    }
    
    /**
     * Clean up expired records
     */
    public static function cleanup() {
        return static::where('reset_at', '<', now())->delete();
    }
}