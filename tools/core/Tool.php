<?php

namespace BTD\Tools\Core;

use BTD\Models\Calculation;
use BTD\Models\UsageLog;
use BTD\Models\RateLimit;
 
/**
 * Abstract Base Tool Class
 * 
 * All tools inherit from this class
 */
abstract class Tool {
    
    protected $slug;
    protected $name;
    protected $description;
    protected $category;
    protected $tier = 'free'; // free, starter, pro, business
    protected $icon = 'dashicons-calculator';
    protected $color = '#2563eb';
    
    // Rate limits (uses per period)
    protected $rate_limits = [
        'free' => ['day' => 10],
        'starter' => ['day' => 100],
        'pro' => ['day' => -1], // unlimited
        'business' => ['day' => -1],
    ];
    
    /**
     * Check if user has access to this tool
     */
    public function checkAccess() {
        // Free tools - everyone has access
        if ($this->tier === 'free') {
            return true;
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Check subscription tier
        return $this->hasRequiredSubscription();
    }
    
    /**
     * Check if user has required subscription tier
     */
    protected function hasRequiredSubscription() {
        $user_id = get_current_user_id();
        
        // Get user's active subscriptions (WooCommerce Subscriptions)
        if (function_exists('wcs_user_has_subscription')) {
            $tier_map = [
                'starter' => ['starter', 'pro', 'business'],
                'pro' => ['pro', 'business'],
                'business' => ['business'],
            ];
            
            $required_tiers = $tier_map[$this->tier] ?? [];
            
            foreach ($required_tiers as $tier) {
                // Check if user has this subscription (adjust product IDs as needed)
                if (wcs_user_has_subscription($user_id, '', 'active')) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check rate limit
     */
    public function checkRateLimit($period = 'day') {
        $user_id = get_current_user_id() ?: null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Get user's tier
        $user_tier = $this->getUserTier();
        $limit = $this->rate_limits[$user_tier][$period] ?? 10;
        
        // Unlimited for pro/business
        if ($limit === -1) {
            return true;
        }
        
        // Check current usage
        $identifier = $user_id ?: $ip_address;
        $reset_at = $this->getResetTime($period);
        
        global $wpdb;
        $table = $wpdb->prefix . 'btd_rate_limits';
        
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE tool_slug = %s 
             AND period = %s 
             AND (user_id = %d OR ip_address = %s)
             AND reset_at > NOW()",
            $this->slug,
            $period,
            $user_id,
            $ip_address
        ));
        
        if (!$current) {
            // First use in this period
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'ip_address' => $ip_address,
                'tool_slug' => $this->slug,
                'period' => $period,
                'count' => 1,
                'reset_at' => $reset_at,
            ]);
            return true;
        }
        
        if ($current->count >= $limit) {
            return false; // Rate limit exceeded
        }
        
        // Increment count
        $wpdb->update(
            $table,
            ['count' => $current->count + 1],
            ['id' => $current->id]
        );
        
        return true;
    }
    
    /**
     * Get reset time for rate limit period
     */
    protected function getResetTime($period) {
        switch ($period) {
            case 'hour':
                return date('Y-m-d H:00:00', strtotime('+1 hour'));
            case 'day':
                return date('Y-m-d 23:59:59');
            case 'week':
                return date('Y-m-d 23:59:59', strtotime('next sunday'));
            case 'month':
                return date('Y-m-t 23:59:59');
            default:
                return date('Y-m-d 23:59:59');
        }
    }
    
    /**
     * Get user's subscription tier
     */
    protected function getUserTier() {
        if (!is_user_logged_in()) {
            return 'free';
        }
        
        // Check WooCommerce subscriptions
        // Adjust based on your product IDs
        $user_id = get_current_user_id();
        
        if (function_exists('wcs_user_has_subscription')) {
            if ($this->hasSubscriptionProduct($user_id, 'business')) return 'business';
            if ($this->hasSubscriptionProduct($user_id, 'pro')) return 'pro';
            if ($this->hasSubscriptionProduct($user_id, 'starter')) return 'starter';
        }
        
        return 'free';
    }
    
    /**
     * Check if user has specific subscription product
     */
    protected function hasSubscriptionProduct($user_id, $tier) {
        // Get product IDs from settings
        $product_ids = [
            'starter' => btd_get_setting('starter_product_id', 0),
            'pro' => btd_get_setting('pro_product_id', 0),
            'business' => btd_get_setting('business_product_id', 0),
        ];
        
        $product_id = $product_ids[$tier] ?? 0;
        
        if (!$product_id) {
            return false;
        }
        
        return wcs_user_has_subscription($user_id, $product_id, 'active');
    }
    
    /**
     * Track usage
     */
    protected function trackUsage($action = 'use', $metadata = []) {
        UsageLog::log($this->slug, $action, $metadata);
    }
    
    /**
     * Get remaining uses for current user
     */
    public function getRemainingUses($period = 'day') {
        $user_tier = $this->getUserTier();
        $limit = $this->rate_limits[$user_tier][$period] ?? 10;
        
        if ($limit === -1) {
            return -1; // unlimited
        }
        
        $user_id = get_current_user_id() ?: null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        
        global $wpdb;
        $table = $wpdb->prefix . 'btd_rate_limits';
        
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT count FROM {$table} 
             WHERE tool_slug = %s 
             AND period = %s 
             AND (user_id = %d OR ip_address = %s)
             AND reset_at > NOW()",
            $this->slug,
            $period,
            $user_id,
            $ip_address
        ));
        
        $used = $current ?: 0;
        return max(0, $limit - $used);
    }
    
    /**
     * Abstract methods that tools must implement
     */
    abstract public function renderForm();
    abstract public function process($inputs);
    abstract public function renderResults($results);
    
    /**
     * Get tool metadata
     */
    public function getMetadata() {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'tier' => $this->tier,
            'icon' => $this->icon,
            'color' => $this->color,
        ];
    }
}