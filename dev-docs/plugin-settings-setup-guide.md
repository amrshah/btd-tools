# Plugin Settings System - Setup Guide

## Quick Start

### 1. Installation

Copy the entire settings system code into your plugin's `includes` folder:

```
your-plugin/
├── includes/
│   └── class-plugin-settings.php
└── your-plugin.php
```

### 2. Initialize in Your Main Plugin File

```php
// your-plugin.php

// Include the settings classes
require_once plugin_dir_path(__FILE__) . 'includes/class-plugin-settings.php';

// Initialize on plugin load
function your_plugin_init() {
    global $plugin_settings;
    
    // Initialize database
    $db = new Plugin_Settings_DB();
    
    // Create table on activation
    register_activation_hook(__FILE__, [$db, 'create_table']);
    
    // Initialize manager
    $manager = new Plugin_Settings_Manager($db);
    
    // Initialize admin UI (only in admin)
    if (is_admin()) {
        new Plugin_Settings_Admin($manager);
    }
    
    // Make available globally
    $plugin_settings = $manager;
}
add_action('plugins_loaded', 'your_plugin_init');
```

### 3. Database Table Creation

The custom table is automatically created on plugin activation. Structure:

```sql
wp_plugin_settings
├── id (bigint, primary key)
├── setting_key (varchar, unique)
├── setting_value (longtext)
├── setting_group (varchar)
├── autoload (tinyint)
└── updated_at (datetime)
```

## Usage

### Get Settings

```php
// Get single setting
$api_key = $plugin_settings->get('api_key');

// Get with default value
$max_items = $plugin_settings->get('max_items', 10);

// Get all settings in a group
$api_settings = $plugin_settings->get_group('api');
```

### Set Settings

```php
// Set single setting
$plugin_settings->set('api_key', 'abc123', 'api');

// Update multiple settings
$plugin_settings->update_group('general', [
    'plugin_name' => 'My Plugin',
    'enable_plugin' => 1
]);
```

### Delete Settings

```php
// Delete single setting
$plugin_settings->delete('old_setting');

// Delete entire group
$plugin_settings->db->delete_group('deprecated_group');
```

## Admin Interface

### Accessing Settings

Navigate to: **WordPress Admin → Plugin Settings**

### Interface Structure

```
Sidebar Navigation → Card Layout → Internal Tabs
     ↓                    ↓              ↓
  5 Sections        Settings Groups   Sub-sections
```

**Available Sections:**
1. **General** - Basic, Display, Advanced
2. **API** - Keys, Endpoints, Authentication  
3. **Appearance** - Colors, Typography, Layout
4. **Email** - General, SMTP, Templates
5. **Advanced** - Performance, Security, Maintenance
6. **Tools** - Import/Export, Presets, Reset

## Import/Export

### Export Settings

1. Navigate to **Tools** section
2. Click **"Export Settings"**
3. JSON file downloads automatically
4. Share with clients or backup

### Import Settings

1. Navigate to **Tools** section
2. Click **"Import Settings"**
3. Select your JSON file
4. Settings applied automatically
5. Page reloads with new config

### File Format

```json
{
  "version": "1.0.0",
  "exported_at": "2024-01-15 10:30:00",
  "site_url": "https://example.com",
  "settings": [
    {
      "key": "api_key",
      "value": "abc123",
      "group": "api"
    }
  ]
}
```

## Presets

### Available Presets

**Development**
- Debug enabled
- Caching disabled  
- Local endpoints
- Extended timeouts

**Staging**
- Debug enabled
- Moderate caching
- Staging endpoints
- Test-safe settings

**Production**
- Debug disabled
- Full caching
- Optimized performance
- Security hardened

### Apply Preset

1. Go to **Tools → Configuration Presets**
2. Click **"Choose Preset"**
3. Select environment
4. Click **"Apply Preset"**
5. Settings updated instantly

### Create Custom Preset

Add to `Plugin_Settings_Manager::get_presets()`:

```php
'custom' => [
    'name' => 'Custom Config',
    'description' => 'Your custom preset',
    'settings' => [
        'general' => [
            'plugin_name' => 'Custom Name',
            'enable_plugin' => 1
        ],
        'api' => [
            'api_base_url' => 'https://api.custom.com'
        ]
    ]
]
```

## Customization

### Add New Setting Section

**1. Add Sidebar Item:**

```php
<a class="plugin-settings-nav-item" data-target="card-custom">
    <span class="plugin-settings-nav-item-icon">✔</span>
    <span>Custom Section</span>
</a>
```

**2. Add Card:**

```php
<div class="plugin-settings-card" id="card-custom">
    <div class="plugin-settings-card-header">
        <h3 class="plugin-settings-card-title">
            <span class="plugin-settings-card-title-icon">✔</span>
            Custom Settings
        </h3>
        <button class="plugin-settings-save-btn" data-group="custom">
            Save Changes
        </button>
    </div>
    
    <form>
        <div style="padding: 24px;">
            <!-- Your form fields here -->
        </div>
    </form>
</div>
```

### Add New Field Type

Extend `Plugin_Settings_Manager::sanitize()`:

```php
case 'custom_type':
    $value = your_custom_sanitization($value);
    break;
```

### Modify Styling

All CSS is inline in `Plugin_Settings_Admin::get_inline_css()`. 

Key classes:
- `.plugin-settings-wrapper` - Main container
- `.plugin-settings-sidebar` - Navigation
- `.plugin-settings-card` - Setting cards
- `.plugin-settings-tab` - Tabs within cards

## Best Practices

### 1. Use Setting Groups

Organize related settings together:

```php
'general' // Basic plugin settings
'api'     // API configurations
'email'   // Email settings
```

### 2. Provide Defaults

Always set defaults when getting values:

```php
$value = $plugin_settings->get('key', 'default_value');
```

### 3. Sanitize Input

Settings are auto-sanitized, but add custom validation:

```php
$manager->sanitize($value, 'email');  // Built-in types
```

### 4. Use Presets for Clients

Create environment-specific presets:
- Development (local testing)
- Staging (pre-production)
- Production (live site)

### 5. Regular Exports

Backup settings before major changes:
1. Export current settings
2. Make changes
3. Import if needed to rollback

## Troubleshooting

### Settings Not Saving

**Check:**
- User has `manage_options` capability
- AJAX nonce is valid
- Database table exists
- No JavaScript errors in console

### Import Failed

**Verify:**
- JSON format is valid
- File contains `settings` array
- Keys don't have special characters

### Reset Not Working

**Ensure:**
- User confirmed action
- Database connection active
- Table has write permissions

## Support & Customization

### Adding Validation

```php
// In Plugin_Settings_Manager
public function validate_setting($key, $value) {
    switch($key) {
        case 'email':
            return is_email($value);
        case 'url':
            return filter_var($value, FILTER_VALIDATE_URL);
        default:
            return true;
    }
}
```

### Custom AJAX Handlers

Add new handlers in `Plugin_Settings_Admin::__construct()`:

```php
add_action('wp_ajax_custom_action', [$this, 'ajax_custom_action']);
```

### Hooks for Developers

Add before/after save hooks:

```php
// Before save
do_action('plugin_settings_before_save', $group, $settings);

// After save  
do_action('plugin_settings_after_save', $group, $settings);
```

## Example Implementations

### E-commerce Preset

```php
'ecommerce' => [
    'name' => 'E-commerce Setup',
    'description' => 'Optimized for online stores',
    'settings' => [
        'general' => [
            'items_per_page' => 24,
            'enable_plugin' => 1
        ],
        'advanced' => [
            'enable_caching' => 1,
            'lazy_loading' => 1
        ]
    ]
]
```

### Membership Site

```php
'membership' => [
    'name' => 'Membership Site',
    'description' => 'For member-only content',
    'settings' => [
        'general' => [
            'enable_plugin' => 1
        ],
        'advanced' => [
            'enable_2fa' => 1,
            'enforce_https' => 1
        ]
    ]
]
```

---

## Quick Reference

| Action | Method |
|--------|--------|
| Get setting | `$plugin_settings->get('key')` |
| Set setting | `$plugin_settings->set('key', 'value', 'group')` |
| Export | Click Export button in Tools |
| Import | Click Import button, select file |
| Apply preset | Tools → Choose Preset |
| Reset | Tools → Reset All Settings |

**Documentation Version:** 1.0.0  
**Last Updated:** 2024