<?php
/**
 * Migration: Create Calculations Table
 * 
 * Stores all user calculations/results from tools
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function() {
        Capsule::schema()->create('btd_calculations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index()->comment('WordPress user ID');
            $table->unsignedBigInteger('tool_id')->nullable()->index()->comment('POD tool post ID');
            $table->string('tool_slug', 100)->index()->comment('Tool identifier');
            $table->json('input_data')->comment('User inputs');
            $table->json('result_data')->comment('Calculation results');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            // Composite indexes for common queries
            $table->index(['user_id', 'created_at'], 'idx_user_created');
            $table->index(['tool_slug', 'created_at'], 'idx_tool_created');
            $table->index(['user_id', 'tool_slug'], 'idx_user_tool');
        });
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('btd_calculations');
    }
];