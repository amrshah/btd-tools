<?php
/**
 * Migration: Create Workspaces Tables
 * 
 * Enables team collaboration features
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function() {
        // Workspaces table
        Capsule::schema()->create('btd_workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('owner_id')->index();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            
            $table->index(['owner_id', 'is_active'], 'idx_owner_active');
        });
        
        // Workspace members table
        Capsule::schema()->create('btd_workspace_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->enum('role', ['owner', 'admin', 'member', 'viewer'])->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('last_active_at')->nullable();
            
            $table->unique(['workspace_id', 'user_id'], 'unique_workspace_member');
            $table->index(['user_id', 'role'], 'idx_user_role');
        });
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('btd_workspace_members');
        Capsule::schema()->dropIfExists('btd_workspaces');
    }
];