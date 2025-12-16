 * ============================================================================
 * BTD Business Tools Suite - Hybrid Architecture Implementation
 * POD Framework + Eloquent ORM
 * ============================================================================
 * 
 * Directory Structure:
 * 
 * btd-tools/
 * ├── btd-tools.php (main plugin file - THIS FILE)
 * ├── composer.json
 * ├── bootstrap/
 * │   └── eloquent.php
 * ├── database/
 * │   └── migrations/
 * │       ├── 001_create_calculations_table.php
 * │       ├── 002_create_usage_logs_table.php
 * │       ├── 003_create_saved_results_table.php
 * │       ├── 004_create_workspaces_table.php
 * │       └── 005_create_rate_limits_table.php
 * ├── models/
 * │   ├── Calculation.php
 * │   ├── UsageLog.php
 * │   ├── SavedResult.php
 * │   ├── Workspace.php
 * │   ├── WorkspaceMember.php
 * │   └── RateLimit.php
 * ├── includes/
 * │   ├── MigrationRunner.php
 * │   ├── ToolRegistry.php
 * │   └── PODSetup.php
 * └── tools/
 *     ├── core/
 *     │   ├── Tool.php
 *     │   ├── Calculator.php
 *     │   ├── AITool.php
 *     │   └── Generator.php
 *     └── financial/
 *         ├── ROICalculator.php
 *         └── InvoiceGenerator.php
