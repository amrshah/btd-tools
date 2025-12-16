<?php
/**
 * Migration: Create Tools Table
 * 
 * Replaces Pods custom post type with dedicated Eloquent table
 */

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function() {
        Capsule::schema()->create('btd_tools', function (Blueprint $table) {
            $table->id();
            
            // Core fields (replacing post fields)
            $table->string('title', 255)->comment('Tool name');
            $table->string('slug', 100)->unique()->comment('URL-friendly identifier');
            $table->longText('description')->nullable()->comment('Full description (post_content)');
            $table->text('excerpt')->nullable()->comment('Short excerpt');
            
            // Tool-specific fields (from Pods custom fields)
            $table->enum('tool_type', ['calculator', 'generator', 'ai_tool', 'tracker'])
                ->comment('Type of tool');
            $table->enum('tier_required', ['free', 'starter', 'pro', 'business'])
                ->default('free')
                ->comment('Minimum subscription tier');
            $table->string('tool_icon', 100)->default('dashicons-calculator')
                ->comment('Dashicon class or emoji');
            $table->string('tool_color', 7)->default('#2563eb')
                ->comment('Hex color code');
            
            // Content fields (from Pods WYSIWYG fields)
            $table->text('short_description')->nullable()
                ->comment('Brief description for catalog');
            $table->longText('how_to_use')->nullable()
                ->comment('Step-by-step instructions');
            $table->longText('use_cases')->nullable()
                ->comment('Common use cases and examples');
            
            // Display flags (from Pods boolean fields)
            $table->boolean('is_featured')->default(false)->index()
                ->comment('Show in featured section');
            $table->boolean('is_popular')->default(false)->index()
                ->comment('Mark as popular');
            $table->boolean('is_active')->default(true)->index()
                ->comment('Published/active status');
            
            // Display settings
            $table->integer('show_new_badge_days')->default(30)
                ->comment('Days to show NEW badge');
            $table->integer('menu_order')->default(0)->index()
                ->comment('Sort order');
            $table->string('featured_image_url', 500)->nullable()
                ->comment('Featured image URL');
            
            // Timestamps
            $table->timestamp('published_at')->nullable()
                ->comment('When tool was published');
            $table->timestamps();
            
            // Indexes for common queries
            $table->index('tool_type', 'idx_tool_type');
            $table->index('tier_required', 'idx_tier');
            $table->index(['is_active', 'is_featured'], 'idx_active_featured');
            $table->index(['is_active', 'menu_order'], 'idx_active_order');
        });
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('btd_tools');
    }
];
