<?php

namespace BTD\Tools\Core;

use BTD\Models\Calculation;
use BTD\Tools\Core\Tool;



/**
 * AI Tool Base Class
 * 
 * For AI-powered content generation tools
 */
abstract class AITool extends Tool {
    
    protected $ai_provider = 'anthropic'; // or 'openai'
    protected $model = 'claude-sonnet-4-20250514';
    protected $max_tokens = 1500;
    
    /**
     * Call AI API
     */
    protected function callAI($prompt, $system_prompt = null) {
        $api_key = get_option('btd_anthropic_api_key');
        
        if (empty($api_key)) {
            throw new \Exception('API key not configured');
        }
        
        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];
        
        $body = [
            'model' => $this->model,
            'max_tokens' => $this->max_tokens,
            'messages' => $messages,
        ];
        
        if ($system_prompt) {
            $body['system'] = $system_prompt;
        }
        
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => json_encode($body),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['error'])) {
            throw new \Exception($data['error']['message'] ?? 'API Error');
        }
        
        return $data['content'][0]['text'] ?? '';
    }
    
    /**
     * Process AI tool
     */
    public function process($inputs) {
        // Check access
        if (!$this->checkAccess()) {
            return [
                'success' => false,
                'error' => 'upgrade_required',
                'message' => __('Upgrade to access AI tools', 'btd-tools'),
            ];
        }
        
        // Check rate limit
        if (!$this->checkRateLimit()) {
            return [
                'success' => false,
                'error' => 'rate_limit',
                'message' => __('Daily AI generation limit reached', 'btd-tools'),
                'remaining' => 0,
            ];
        }
        
        try {
            // Build prompt
            $prompt = $this->buildPrompt($inputs);
            
            // Call AI
            $content = $this->callAI($prompt);
            
            // Parse response
            $results = $this->parseResponse($content);
            
            // Save to database
            $this->saveGeneration($inputs, $results);
            
            // Track usage
            $this->trackUsage('generate');
            
            return [
                'success' => true,
                'content' => $results,
                'remaining' => $this->getRemainingUses(),
            ];
            
        } catch (\Exception $e) {
            error_log('BTD AI Tool Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'generation',
                'message' => __('AI generation failed', 'btd-tools'),
            ];
        }
    }
    
    /**
     * Save generation to database
     */
    protected function saveGeneration($inputs, $results) {
        $tool = \BTD\PODSetup::getToolBySlug($this->slug);
        
        return Calculation::create([
            'user_id' => get_current_user_id() ?: 0,
            'tool_id' => $tool ? $tool->id() : null,
            'tool_slug' => $this->slug,
            'input_data' => $inputs,
            'result_data' => ['content' => $results],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }
    
    /**
     * Build prompt (must be implemented by child class)
     */
    abstract protected function buildPrompt($inputs);
    
    /**
     * Parse AI response (can be overridden)
     */
    protected function parseResponse($content) {
        return $content;
    }
}