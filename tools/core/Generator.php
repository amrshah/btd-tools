<?php

namespace BTD\Tools\Core;

use BTD\Models\Calculation;
use BTD\Tools\Core\Tool;



/**
 * Generator Base Class
 * 
 * For document/content generators
 */
abstract class Generator extends Tool {
    
    protected $template = ''; // Template content
    protected $placeholders = []; // Placeholder mapping
    
    /**
     * Generate document
     */
    protected function generate($inputs) {
        $content = $this->template;
        
        foreach ($this->placeholders as $placeholder => $callback) {
            $value = is_callable($callback) ? 
                     call_user_func($callback, $inputs) : 
                     ($inputs[$callback] ?? '');
            
            $content = str_replace('{{' . $placeholder . '}}', $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Process generator
     */
    public function process($inputs) {
        if (!$this->checkAccess()) {
            return [
                'success' => false,
                'error' => 'upgrade_required',
            ];
        }
        
        if (!$this->checkRateLimit()) {
            return [
                'success' => false,
                'error' => 'rate_limit',
            ];
        }
        
        try {
            $content = $this->generate($inputs);
            
            $this->trackUsage('generate');
            
            return [
                'success' => true,
                'content' => $content,
                'remaining' => $this->getRemainingUses(),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'generation',
                'message' => $e->getMessage(),
            ];
        }
    }
}