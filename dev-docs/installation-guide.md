# BTD Business Tools Suite - Installation Guide

## Complete Hybrid Architecture (POD + Eloquent)

---

## 📋 Prerequisites

- WordPress 6.0+
- PHP 8.1+
- MySQL 5.7+ or MySQL 8.0+
- Composer installed on server
- SSH/FTP access to server
- Pods Framework plugin (will be installed)

---

## 🚀 Installation Steps

### Step 1: Create Plugin Directory

```bash
cd /path/to/wordpress/wp-content/plugins/
mkdir btd-tools
cd btd-tools
```

### Step 2: Create Directory Structure

```bash
mkdir -p bootstrap
mkdir -p database/migrations
mkdir -p models
mkdir -p includes
mkdir -p tools/core
mkdir -p tools/financial
mkdir -p assets/css
mkdir -p assets/js
```

### Step 3: Copy Files

Copy all the provided files to their respective locations:

```
btd-tools/
├── btd-tools.php                          # Main plugin file
├── composer.json                          # Composer dependencies
├── bootstrap/
│   └── eloquent.php                       # Eloquent bootstrap
├── database/
│   └── migrations/
│       ├── 001_create_calculations_table.php
│       ├── 002_create_usage_logs_table.php
│       ├── 003_create_saved_results_table.php
│       ├── 004_create_workspaces_table.php
│       └── 005_create_rate_limits_table.php
├── models/
│   ├── Calculation.php                    # Eloquent models
│   └── UsageLog.php
├── includes/
│   ├── MigrationRunner.php
│   └── PODSetup.php
└── assets/
    ├── css/
    └── js/
```

### Step 4: Install Composer Dependencies

```bash
cd /path/to/wordpress/wp-content/plugins/btd-tools/
composer install --no-dev --optimize-autoloader
```

This will install:
- `illuminate/database` (~1.5MB)
- `illuminate/events` (~300KB)
- `illuminate/pagination` (~200KB)

### Step 5: Install Pods Framework

Option A - Via WordPress Admin:
1. Go to Plugins → Add New
2. Search for "Pods Framework"
3. Install and Activate

Option B - Via WP-CLI:
```bash
wp plugin install pods --activate
```

### Step 6: Activate BTD Plugin

Option A - Via WordPress Admin:
1. Go to Plugins
2. Find "BTD Business Tools Suite"
3. Click "Activate"

Option B - Via WP-CLI:
```bash
wp plugin activate btd-tools
```

**What happens on activation:**
- Database migrations run automatically
- POD custom post types registered
- Rewrite rules flushed

### Step 7: Verify Installation

1. **Check Plugin Status**
   - Go to BTD Tools → Settings
   - Verify all systems show "✓ Active"

2. **Check Database Tables**
   ```sql
   SHOW TABLES LIKE 'wp_btd_%';
   ```
   
   Should show:
   - `wp_btd_calculations`
   - `wp_btd_usage_logs`
   - `wp_btd_saved_results`
   - `wp_btd_workspaces`
   - `wp_btd_workspace_members`
   - `wp_btd_rate_limits`
   - `wp_btd_migrations`

3. **Check POD Setup**
   - Go to Pods Admin
   - Verify "Business Tools" post type exists
   - Verify "Tool Categories" taxonomy exists

---

## 🔧 Configuration

### 1. API Keys (for AI tools)

Go to BTD Tools → Settings and add:
- **Anthropic API Key**: Get from https://console.anthropic.com/

### 2. Create Tool Categories

Go to Business Tools → Tool Categories and create:
- Financial
- Marketing
- Operations
- HR & Team
- Sales
- Legal
- Content

### 3. Permalink Settings

Go to Settings → Permalinks and click "Save Changes" to flush rewrite rules.

Your tools will be accessible at:
- `yoursite.com/business-tools/` (archive)
- `yoursite.com/business-tools/roi-calculator/` (single tool)

---

## 📝 Creating Your First Tool (POD Entry)

### Via WordPress Admin:

1. Go to Business Tools → Add New
2. Fill in:
   - **Title**: ROI Calculator
   - **Content**: Detailed description
   - **Tool Slug**: `roi-calculator` (auto-generated)
   - **Tool Type**: Calculator
   - **Tier Required**: Free
   - **Tool Icon**: `dashicons-chart-line`
   - **Tool Color**: `#2563eb`
   - **Short Description**: Calculate your return on investment
   - **Category**: Financial

3. Click Publish

### Via Code (for bulk import):

```php
// Import tools programmatically
$tool_id = wp_insert_post([
    'post_type' => 'btd_tool',
    'post_title' => 'ROI Calculator',
    'post_content' => 'Full description here...',
    'post_status' => 'publish',
]);

// Set POD fields
$pod = pods('btd_tool', $tool_id);
$pod->save([
    'tool_slug' => 'roi-calculator',
    'tool_type' => 'calculator',
    'tier_required' => 'free',
    'tool_icon' => 'dashicons-chart-line',
    'tool_color' => '#2563eb',
    'short_description' => 'Calculate your return on investment',
]);
```

---

## 🧪 Testing the Installation

### Test 1: Create a Test Calculation (Eloquent)

```php
// Add this to a test page or functions.php temporarily
use BTD\Models\Calculation;

$calc = Calculation::create([
    'user_id' => get_current_user_id(),
    'tool_slug' => 'roi-calculator',
    'input_data' => [
        'investment' => 10000,
        'return' => 15000,
        'period' => 12
    ],
    'result_data' => [
        'roi_percent' => 50,
        'profit' => 5000
    ]
]);

var_dump($calc->id); // Should show new ID
```

### Test 2: Query Calculations

```php
use BTD\Models\Calculation;

// Get all calculations
$all = Calculation::count();
echo "Total calculations: " . $all;

// Get today's calculations
$today = Calculation::today()->count();
echo "Today: " . $today;

// Get user's calculations
$user_calcs = Calculation::byUser(1)->get();
foreach ($user_calcs as $calc) {
    echo $calc->tool_slug . " - " . $calc->created_at->format('Y-m-d');
}
```

### Test 3: Get Tool from POD

```php
use BTD\PODSetup;

// Get tool by slug
$tool = PODSetup::getToolBySlug('roi-calculator');
if ($tool->exists()) {
    echo $tool->field('post_title');
    echo $tool->field('tool_type');
    echo $tool->field('tier_required');
}
```

---

## 🛠️ Development Workflow

### Creating a New Tool

1. **Create POD Entry** (via admin or code)
   - Defines tool metadata, category, tier

2. **Create Tool Class** (PHP)
   - Location: `tools/financial/ROICalculator.php`
   - Extends base `Tool` class

3. **Create Frontend Interface** (React/HTML)
   - Use shortcode or template

4. **Test & Deploy**

### Example Tool Class Structure:

```php
<?php
namespace BTD\Tools\Financial;

use BTD\Tools\Core\Calculator;
use BTD\Models\Calculation;
use BTD\Models\UsageLog;

class ROICalculator extends Calculator {
    
    public function __construct() {
        $this->slug = 'roi-calculator';
        $this->name = 'ROI Calculator';
        $this->tier = 'free';
    }
    
    public function calculate($inputs) {
        // Validation
        $investment = floatval($inputs['investment']);
        $final_value = floatval($inputs['final_value']);
        $period = intval($inputs['period']);
        
        // Calculation
        $profit = $final_value - $investment;
        $roi_percent = ($profit / $investment) * 100;
        $annual_roi = ($roi_percent / $period) * 12;
        
        $results = [
            'profit' => round($profit, 2),
            'roi_percent' => round($roi_percent, 2),
            'annual_roi' => round($annual_roi, 2),
        ];
        
        // Save to database (Eloquent)
        Calculation::create([
            'user_id' => get_current_user_id(),
            'tool_slug' => $this->slug,
            'input_data' => $inputs,
            'result_data' => $results,
        ]);
        
        // Log usage
        UsageLog::log($this->slug, 'calculate');
        
        return $results;
    }
}
```

---

## 📊 Accessing Data

### Using Eloquent (Application Data)

```php
use BTD\Models\Calculation;
use BTD\Models\UsageLog;

// Complex queries
$stats = Calculation::where('user_id', $user_id)
    ->where('created_at', '>=', now()->subDays(30))
    ->selectRaw('tool_slug, COUNT(*) as count')
    ->groupBy('tool_slug')
    ->get();

// Relationships
$calc = Calculation::find(1);
$user = $calc->user();

// Analytics
$popular = UsageLog::getPopularTools(30);
```

### Using POD (Content Data)

```php
use BTD\PODSetup;

// Get all tools
$tools = PODSetup::getAllTools();

// Get tools by category
$financial = PODSetup::getToolsByCategory('financial');

// Get featured tools
$featured = PODSetup::getFeaturedTools(6);

// Loop through tools
while ($tools->fetch()) {
    echo $tools->display('post_title');
    echo $tools->display('short_description');
    echo $tools->display('tool_icon');
}
```

---

## 🔄 Migrations

### Running Migrations Manually

```php
// In wp-admin or via WP-CLI
$runner = new \BTD\MigrationRunner(
    WP_PLUGIN_DIR . '/btd-tools/database/migrations'
);

// Run pending
$runner->runPending();

// Check status
$status = $runner->status();
print_r($status);

// Rollback last batch
$runner->rollback();
```

### Creating New Migration

Create file: `database/migrations/006_create_your_table.php`

```php
<?php
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return [
    'up' => function() {
        Capsule::schema()->create('btd_your_table', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    },
    
    'down' => function() {
        Capsule::schema()->dropIfExists('btd_your_table');
    }
];
```

Then run: `$runner->runPending();`

---

## 🐛 Troubleshooting

### Issue: "Class 'Illuminate\Database\Capsule\Manager' not found"

**Solution**: Run `composer install` in plugin directory

### Issue: "POD not found"

**Solution**: Install and activate Pods Framework plugin

### Issue: "Table doesn't exist"

**Solution**: 
1. Deactivate plugin
2. Reactivate plugin (runs migrations)
3. Or run migrations manually

### Issue: Permalinks not working

**Solution**: Go to Settings → Permalinks → Save Changes

### Issue: Can't write to database

**Solution**: Check WordPress database credentials in wp-config.php

---

## 📈 Performance Optimization

### Enable Object Cache

Install Redis or Memcached for better performance:

```bash
# Install Redis plugin
wp plugin install redis-cache --activate
wp redis enable
```

### Add Indexes (if needed)

```php
// In a new migration
Capsule::schema()->table('btd_calculations', function (Blueprint $table) {
    $table->index(['user_id', 'tool_slug', 'created_at']);
});
```

### Query Optimization

```php
// Use eager loading
$calcs = Calculation::with('tool')->get();

// Use select to limit columns
$calcs = Calculation::select(['id', 'tool_slug', 'created_at'])->get();

// Use pagination
$calcs = Calculation::paginate(20);
```

---

## 🔐 Security Checklist

- [ ] Keep WordPress core updated
- [ ] Keep all plugins updated
- [ ] Use strong database password
- [ ] Limit login attempts (Wordfence)
- [ ] Enable 2FA for admin accounts
- [ ] Regular database backups
- [ ] Use HTTPS/SSL
- [ ] Sanitize all inputs
- [ ] Escape all outputs
- [ ] Use nonces for forms

---

## 📦 Next Steps

1. **Create First Tool Interface**
   - Build calculator UI (React or HTML/JS)
   - Connect to calculation endpoint
   - Test end-to-end

2. **Set Up WooCommerce**
   - Create subscription products
   - Configure payment gateway
   - Set up email sequences

3. **Build Dashboard**
   - User tool history
   - Saved calculations
   - Usage analytics

4. **Add More Tools**
   - Follow the same pattern
   - POD entry + Tool class + Frontend

---

## 🆘 Support

For issues or questions:
1. Check error logs: `wp-content/debug.log`
2. Enable WP_DEBUG in wp-config.php
3. Check migration status
4. Verify all dependencies installed

---

## 📚 Additional Resources

- [Eloquent Documentation](https://laravel.com/docs/10.x/eloquent)
- [Pods Framework Documentation](https://docs.pods.io/)
- [WordPress Plugin Development](https://developer.wordpress.org/plugins/)

---

**Installation complete! 🎉**

You now have a hybrid POD + Eloquent architecture ready for building your business tools suite.