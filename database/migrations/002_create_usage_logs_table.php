<?php
/**
 * Migration: Create Usage Logs Table
 * 
 * Tracks every tool interaction for analytics
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function() {
        Capsule::schema()->create('btd_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('tool_slug', 100)->index();
            $table->string('action', 50)->index()->comment('calculate, export, save, share');
            $table->json('metadata')->nullable()->comment('Additional context');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id', 100)->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
            
            // Composite indexes for analytics queries
            $table->index(['tool_slug', 'created_at'], 'idx_tool_time');
            $table->index(['action', 'created_at'], 'idx_action_time');
            $table->index(['user_id', 'created_at'], 'idx_user_time');
        });
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('btd_usage_logs');
    }
];