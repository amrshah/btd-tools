<?php


namespace BTD\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceMember extends Model {
    
    protected $table = 'btd_workspace_members';
    
    public $timestamps = false;
    
    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
    ];
    
    protected $casts = [
        'joined_at' => 'datetime',
        'last_active_at' => 'datetime',
    ];
    
    /**
     * Boot method
     */
    protected static function boot() {
        parent::boot();
        
        static::creating(function ($model) {
            if (!$model->joined_at) {
                $model->joined_at = now();
            }
        });
    }
    
    /**
     * Get the workspace
     */
    public function workspace() {
        return $this->belongsTo(Workspace::class);
    }
    
    /**
     * Get the user
     */
    public function user() {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT ID, user_login, user_email, display_name FROM {$wpdb->users} WHERE ID = %d",
            $this->user_id
        ));
    }
    
    /**
     * Update last active
     */
    public function updateLastActive() {
        $this->last_active_at = now();
        $this->save();
    }
    
    /**
     * Scope: By role
     */
    public function scopeByRole($query, $role) {
        return $query->where('role', $role);
    }
    
    /**
     * Static: Get user's workspaces
     */
    public static function getUserWorkspaces($userId) {
        return static::where('user_id', $userId)
            ->with('workspace')
            ->get()
            ->pluck('workspace');
    }
}