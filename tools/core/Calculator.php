<?php
/**
 * Calculator Base Class
 * 
 * For mathematical calculation tools
 */
namespace BTD\Tools\Core;

use BTD\Models\Calculation;
use BTD\Tools\Core\Tool;
abstract class Calculator extends Tool {
    
    protected $inputs = []; // Input field definitions
    protected $outputs = []; // Output field definitions
    
    /**
     * Validate inputs
     */
    protected function validateInputs($inputs) {
        $errors = [];
        
        foreach ($this->inputs as $field => $config) {
            $required = $config['required'] ?? true;
            $type = $config['type'] ?? 'number';
            $min = $config['min'] ?? null;
            $max = $config['max'] ?? null;
            
            // Check required
            if ($required && empty($inputs[$field])) {
                $errors[$field] = sprintf(
                    __('%s is required', 'btd-tools'),
                    $config['label'] ?? $field
                );
                continue;
            }
            
            // Type validation
            switch ($type) {
                case 'number':
                    if (!is_numeric($inputs[$field])) {
                        $errors[$field] = __('Must be a number', 'btd-tools');
                    }
                    break;
                    
                case 'integer':
                    if (!filter_var($inputs[$field], FILTER_VALIDATE_INT)) {
                        $errors[$field] = __('Must be a whole number', 'btd-tools');
                    }
                    break;
                    
                case 'email':
                    if (!filter_var($inputs[$field], FILTER_VALIDATE_EMAIL)) {
                        $errors[$field] = __('Must be a valid email', 'btd-tools');
                    }
                    break;
            }
            
            // Range validation
            if ($min !== null && $inputs[$field] < $min) {
                $errors[$field] = sprintf(__('Must be at least %s', 'btd-tools'), $min);
            }
            
            if ($max !== null && $inputs[$field] > $max) {
                $errors[$field] = sprintf(__('Must be no more than %s', 'btd-tools'), $max);
            }
        }
        
        return $errors;
    }
    
    /**
     * Save calculation to database
     */
    protected function saveCalculation($inputs, $results) {
        $tool = \BTD\PODSetup::getToolBySlug($this->slug);
        
        return Calculation::create([
            'user_id' => get_current_user_id() ?: 0,
            'tool_id' => $tool ? $tool->id() : null,
            'tool_slug' => $this->slug,
            'input_data' => $inputs,
            'result_data' => $results,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }
    
    /**
     * Process calculation (template method)
     */
    public function process($inputs) {
        // Check access
        if (!$this->checkAccess()) {
            return [
                'success' => false,
                'error' => 'upgrade_required',
                'message' => __('Upgrade your plan to access this tool', 'btd-tools'),
            ];
        }
        
        // Check rate limit
        if (!$this->checkRateLimit()) {
            return [
                'success' => false,
                'error' => 'rate_limit',
                'message' => __('Daily limit reached. Upgrade for unlimited access.', 'btd-tools'),
                'remaining' => 0,
            ];
        }
         
        // Validate inputs
        $errors = $this->validateInputs($inputs);
        if (!empty($errors)) {
            return [
                'success' => false,
                'error' => 'validation',
                'errors' => $errors,
            ];
        }
        
        // Perform calculation
        try {
            $results = $this->calculate($inputs);
            
            // Save to database
            $calculation = $this->saveCalculation($inputs, $results);
            
            // Track usage
            $this->trackUsage('calculate');
            
            return [
                'success' => true,
                'results' => $results,
                'calculation_id' => $calculation->id,
                'remaining' => $this->getRemainingUses(),
            ];
            
        } catch (\Exception $e) {
            error_log('BTD Calculator Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'calculation',
                'message' => __('An error occurred during calculation', 'btd-tools'),
            ];
        }
    }
    
    /**
     * Perform the actual calculation (must be implemented by child class)
     */
    abstract protected function calculate($inputs);
    
    /**
     * Default form renderer
     */
    public function renderForm() {
        ?>
        <form class="btd-calculator-form" data-tool="<?php echo esc_attr($this->slug); ?>">
            <?php wp_nonce_field('btd_tool_' . $this->slug, 'nonce'); ?>
            
            <div class="btd-form-fields">
                <?php foreach ($this->inputs as $field => $config): ?>
                    <div class="btd-form-group">
                        <label for="<?php echo esc_attr($field); ?>">
                            <?php echo esc_html($config['label']); ?>
                            <?php if ($config['required'] ?? true): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php if ($config['type'] === 'select'): ?>
                            <select 
                                id="<?php echo esc_attr($field); ?>" 
                                name="<?php echo esc_attr($field); ?>"
                                <?php echo ($config['required'] ?? true) ? 'required' : ''; ?>
                            >
                                <?php foreach ($config['options'] as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>">
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input 
                                type="<?php echo esc_attr($config['type'] ?? 'number'); ?>" 
                                id="<?php echo esc_attr($field); ?>" 
                                name="<?php echo esc_attr($field); ?>"
                                placeholder="<?php echo esc_attr($config['placeholder'] ?? ''); ?>"
                                <?php echo isset($config['min']) ? 'min="' . esc_attr($config['min']) . '"' : ''; ?>
                                <?php echo isset($config['max']) ? 'max="' . esc_attr($config['max']) . '"' : ''; ?>
                                <?php echo isset($config['step']) ? 'step="' . esc_attr($config['step']) . '"' : ''; ?>
                                <?php echo ($config['required'] ?? true) ? 'required' : ''; ?>
                            >
                        <?php endif; ?>
                        
                        <?php if (isset($config['help'])): ?>
                            <p class="btd-field-help"><?php echo esc_html($config['help']); ?></p>
                        <?php endif; ?>
                        
                        <div class="btd-field-error"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="btd-form-actions">
                <button type="submit" class="btd-btn btd-btn-primary">
                    <?php _e('Calculate', 'btd-tools'); ?>
                </button>
            </div>
            
            <div class="btd-rate-limit-info">
                <span class="remaining-uses"></span>
            </div>
        </form>
        <?php
    }
    
    /**
     * Default results renderer
     */
    public function renderResults($results) {
        ?>
        <div class="btd-results" style="display: none;">
            <h3><?php _e('Results', 'btd-tools'); ?></h3>
            
            <div class="btd-results-grid">
                <?php foreach ($this->outputs as $field => $config): ?>
                    <div class="btd-result-card <?php echo $config['highlight'] ?? false ? 'highlight' : ''; ?>">
                        <div class="btd-result-label">
                            <?php echo esc_html($config['label']); ?>
                        </div>
                        <div class="btd-result-value" data-field="<?php echo esc_attr($field); ?>">
                            --
                        </div>
                        <?php if (isset($config['help'])): ?>
                            <div class="btd-result-help">
                                <?php echo esc_html($config['help']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="btd-results-actions">
                <button class="btd-btn btd-btn-secondary" onclick="btdExportPDF(this)">
                    <?php _e('Export PDF', 'btd-tools'); ?>
                </button>
                <button class="btd-btn btd-btn-secondary" onclick="btdSaveResult(this)">
                    <?php _e('Save Result', 'btd-tools'); ?>
                </button>
                <button class="btd-btn btd-btn-outline" onclick="btdResetCalculator(this)">
                    <?php _e('New Calculation', 'btd-tools'); ?>
                </button>
            </div>
        </div>
        <?php
    }
}
