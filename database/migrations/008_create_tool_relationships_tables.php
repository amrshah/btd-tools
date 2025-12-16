<?php
/**
 * Migration: Create Tool Relationship Tables
 * 
 * Many-to-many relationships for categories and related tools
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function() {
        // Tool-Category relationship (many-to-many)
        Capsule::schema()->create('btd_tool_category_relationships', function (Blueprint $table) {
            $table->unsignedBigInteger('tool_id');
            $table->unsignedBigInteger('category_id');
            
            // Composite primary key
            $table->primary(['tool_id', 'category_id'], 'pk_tool_category');
            
            // Foreign keys
            $table->foreign('tool_id')
                ->references('id')
                ->on('btd_tools')
                ->onDelete('cascade');
            
            $table->foreign('category_id')
                ->references('id')
                ->on('btd_tool_categories')
                ->onDelete('cascade');
            
            // Indexes for reverse lookups
            $table->index('tool_id', 'idx_tool');
            $table->index('category_id', 'idx_category');
        });
        
        // Tool-Tool relationship (related tools, many-to-many)
        Capsule::schema()->create('btd_tool_relationships', function (Blueprint $table) {
            $table->unsignedBigInteger('tool_id')
                ->comment('Source tool');
            $table->unsignedBigInteger('related_tool_id')
                ->comment('Related tool');
            
            // Composite primary key
            $table->primary(['tool_id', 'related_tool_id'], 'pk_tool_related');
            
            // Foreign keys
            $table->foreign('tool_id')
                ->references('id')
                ->on('btd_tools')
                ->onDelete('cascade');
            
            $table->foreign('related_tool_id')
                ->references('id')
                ->on('btd_tools')
                ->onDelete('cascade');
            
            // Indexes
            $table->index('tool_id', 'idx_tool');
            $table->index('related_tool_id', 'idx_related');
        });
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('btd_tool_relationships');
        Capsule::schema()->dropIfExists('btd_tool_category_relationships');
    }
];
