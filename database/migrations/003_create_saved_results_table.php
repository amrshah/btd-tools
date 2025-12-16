<?php
/**
 * Migration: Create Saved Results Table
 * 
 * Stores user's saved/bookmarked calculations
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function() {
        Capsule::schema()->create('btd_saved_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('calculation_id')->nullable()->index();
            $table->string('tool_slug', 100)->index();
            $table->string('result_name', 255)->nullable();
            $table->json('result_data');
            $table->boolean('is_favorite')->default(false)->index();
            $table->boolean('is_shared')->default(false);
            $table->string('share_token', 32)->nullable()->unique();
            $table->timestamps();
            
            // Composite indexes
            $table->index(['user_id', 'tool_slug'], 'idx_user_tool');
            $table->index(['user_id', 'is_favorite'], 'idx_user_favorite');
            $table->index(['user_id', 'created_at'], 'idx_user_created');
        });
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('btd_saved_results');
    }
];