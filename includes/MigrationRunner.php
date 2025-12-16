<?php
namespace BTD;

/**
 * Database Migration Runner
 * 
 * Handles running and tracking database migrations
 */
class MigrationRunner {
    
    private $migrations_path;
    private $migrations_table = 'btd_migrations';
    
    public function __construct($migrations_path) {
        $this->migrations_path = $migrations_path;
        $this->createMigrationsTable();
    }
    
    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable() {
        global $wpdb;
        $table = $wpdb->prefix . $this->migrations_table;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INT UNSIGNED NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_batch (batch)
        ) {$charset_collate}";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Run all pending migrations
     */
    public function runPending() {
        $files = glob($this->migrations_path . '/*.php');
        
        if (empty($files)) {
            return;
        }
        
        sort($files);
        
        $ran = $this->getRanMigrations();
        $batch = $this->getNextBatch();
        $count = 0;
        
        foreach ($files as $file) {
            $migration = basename($file, '.php');
            
            if (!in_array($migration, $ran)) {
                $this->runMigration($file, $migration, $batch);
                $count++;
            }
        }
        
        if ($count > 0) {
            error_log("BTD: Ran {$count} migrations in batch {$batch}");
        }
    }
    
    /**
     * Run a single migration
     */
    private function runMigration($file, $migration, $batch) {
        try {
            $config = require $file;
            
            if (!isset($config['up']) || !is_callable($config['up'])) {
                throw new \Exception("Migration {$migration} does not have a valid 'up' method");
            }
            
            // Execute the migration
            $config['up']();
            
            // Log the migration
            $this->logMigration($migration, $batch);
            
            error_log("BTD: ✓ Migrated: {$migration}");
            
        } catch (\Exception $e) {
            error_log("BTD: ✗ Migration failed: {$migration} - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Rollback last batch of migrations
     */
    public function rollback() {
        global $wpdb;
        $table = $wpdb->prefix . $this->migrations_table;
        
        $last_batch = $wpdb->get_var("SELECT MAX(batch) FROM {$table}");
        
        if (!$last_batch) {
            error_log("BTD: No migrations to rollback");
            return;
        }
        
        $migrations = $wpdb->get_col(
            $wpdb->prepare("SELECT migration FROM {$table} WHERE batch = %d ORDER BY id DESC", $last_batch)
        );
        
        foreach ($migrations as $migration) {
            $file = $this->migrations_path . '/' . $migration . '.php';
            
            if (file_exists($file)) {
                $config = require $file;
                
                if (isset($config['down']) && is_callable($config['down'])) {
                    try {
                        $config['down']();
                        $wpdb->delete($table, ['migration' => $migration]);
                        error_log("BTD: ✓ Rolled back: {$migration}");
                    } catch (\Exception $e) {
                        error_log("BTD: ✗ Rollback failed: {$migration} - " . $e->getMessage());
                    }
                }
            }
        }
    }
    
    /**
     * Get list of ran migrations
     */
    private function getRanMigrations() {
        global $wpdb;
        $table = $wpdb->prefix . $this->migrations_table;
        
        return $wpdb->get_col("SELECT migration FROM {$table}");
    }
    
    /**
     * Get next batch number
     */
    private function getNextBatch() {
        global $wpdb;
        $table = $wpdb->prefix . $this->migrations_table;
        
        $max = $wpdb->get_var("SELECT MAX(batch) FROM {$table}");
        return $max ? $max + 1 : 1;
    }
    
    /**
     * Log a migration
     */
    private function logMigration($migration, $batch) {
        global $wpdb;
        $table = $wpdb->prefix . $this->migrations_table;
        
        $wpdb->insert($table, [
            'migration' => $migration,
            'batch' => $batch
        ]);
    }
    
    /**
     * Get migration status
     */
    public function status() {
        global $wpdb;
        $table = $wpdb->prefix . $this->migrations_table;
        
        $ran = $this->getRanMigrations();
        $files = glob($this->migrations_path . '/*.php');
        
        $status = [];
        
        foreach ($files as $file) {
            $migration = basename($file, '.php');
            $status[] = [
                'migration' => $migration,
                'ran' => in_array($migration, $ran),
                'batch' => in_array($migration, $ran) ? 
                    $wpdb->get_var($wpdb->prepare("SELECT batch FROM {$table} WHERE migration = %s", $migration)) : 
                    null
            ];
        }
        
        return $status;
    }
}