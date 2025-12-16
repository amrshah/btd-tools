<?php
/**
 * Migration: Create Rate Limits Table
 * 
 * Tracks usage limits for free tier users
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function() {
        Capsule::schema()->create('btd_rate_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('tool_slug', 100)->index();
            $table->enum('period', ['hour', 'day', 'week', 'month'])->default('day');
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('reset_at')->index();
            $table->timestamp('created_at')->useCurrent();
            
            // Composite unique index
            $table->unique(['user_id', 'tool_slug', 'period'], 'unique_user_tool_period');
            $table->index(['ip_address', 'tool_slug', 'period'], 'idx_ip_tool_period');
        });
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('btd_rate_limits');
    }
];