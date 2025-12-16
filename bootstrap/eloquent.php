<?php
/**
 * Eloquent ORM Bootstrap
 * 
 * This file initializes Eloquent ORM for use in WordPress
 */

// Load Composer autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

// Boot Eloquent only once
if (!isset($GLOBALS['btd_eloquent_booted'])) {
    
    $capsule = new Capsule;
    
    // Get WordPress database credentials
    global $wpdb;
    
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => DB_HOST,
        'database' => DB_NAME,
        'username' => DB_USER,
        'password' => DB_PASSWORD,
        'charset' => $wpdb->charset ?? 'utf8mb4',
        'collation' => $wpdb->collate ?? 'utf8mb4_unicode_ci',
        'prefix' => $wpdb->prefix,
        'strict' => false,
        'engine' => null,
    ]);
    
    // Set up event dispatcher
    $capsule->setEventDispatcher(new Dispatcher(new Container));
    
    // Make Capsule globally available
    $capsule->setAsGlobal();
    
    // Boot Eloquent
    $capsule->bootEloquent();
    
    // Mark as booted
    $GLOBALS['btd_eloquent_booted'] = true;
    
    // Helper function to get Capsule instance
    if (!function_exists('btd_db')) {
        function btd_db() {
            return Capsule::connection();
        }
    }
}

// Add helper for Carbon dates
if (!function_exists('now')) {
    function now() {
        return \Illuminate\Support\Carbon::now();
    }
}

if (!function_exists('today')) {
    function today() {
        return \Illuminate\Support\Carbon::today();
    }
}