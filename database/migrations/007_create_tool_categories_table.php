<?php
/**
 * Migration: Create Tool Categories Table
 * 
 * Replaces Pods taxonomy with dedicated Eloquent table
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function() {
        Capsule::schema()->create('btd_tool_categories', function (Blueprint $table) {
            $table->id();
            
            // Core fields (replacing term fields)
            $table->string('name', 255)->comment('Category name');
            $table->string('slug', 100)->unique()->comment('URL-friendly identifier');
            $table->text('description')->nullable()->comment('Category description');
            
            // Hierarchical support
            $table->unsignedBigInteger('parent_id')->nullable()->index()
                ->comment('Parent category ID for hierarchy');
            
            // Display settings
            $table->integer('menu_order')->default(0)->index()
                ->comment('Sort order');
            $table->integer('count')->default(0)
                ->comment('Number of tools in category');
            
            // Timestamps
            $table->timestamps();
            
            // Foreign key for parent relationship
            $table->foreign('parent_id')
                ->references('id')
                ->on('btd_tool_categories')
                ->onDelete('set null');
            
            // Indexes
            $table->index('slug', 'idx_slug');
            $table->index(['parent_id', 'menu_order'], 'idx_parent_order');
        });
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('btd_tool_categories');
    }
];
