<?php

namespace BTD\Models;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model {
    
    protected $table = 'btd_workspaces';
    
    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'settings',
        'is_active',
    ];
    
    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get workspace members
     */
    public function members() {
        return $this->hasMany(WorkspaceMember::class);
    }
    
    /**
     * Get workspace owner
     */
    public function owner() {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT ID, user_login, user_email, display_name FROM {$wpdb->users} WHERE ID = %d",
            $this->owner_id
        ));
    }
    
    /**
     * Scope: Active workspaces
     */
    public function scopeActive($query) {
        return $query->where('is_active', true);
    }
    
    /**
     * Scope: By owner
     */
    public function scopeByOwner($query, $ownerId) {
        return $query->where('owner_id', $ownerId);
    }
    
    /**
     * Check if user is member
     */
    public function hasMember($userId) {
        return $this->members()
            ->where('user_id', $userId)
            ->exists();
    }
    
    /**
     * Add member
     */
    public function addMember($userId, $role = 'member') {
        return WorkspaceMember::create([
            'workspace_id' => $this->id,
            'user_id' => $userId,
            'role' => $role,
        ]);
    }
    
    /**
     * Remove member
     */
    public function removeMember($userId) {
        return $this->members()
            ->where('user_id', $userId)
            ->delete();
    }
    
    /**
     * Get member role
     */
    public function getMemberRole($userId) {
        $member = $this->members()
            ->where('user_id', $userId)
            ->first();
        
        return $member ? $member->role : null;
    }
    
    /**
     * Check if user has permission
     */
    public function userCan($userId, $permission) {
        // Owner has all permissions
        if ($this->owner_id == $userId) {
            return true;
        }
        
        $role = $this->getMemberRole($userId);
        
        $permissions = [
            'owner' => ['manage', 'invite', 'remove', 'edit', 'view'],
            'admin' => ['invite', 'edit', 'view'],
            'member' => ['edit', 'view'],
            'viewer' => ['view'],
        ];
        
        return in_array($permission, $permissions[$role] ?? []);
    }
}