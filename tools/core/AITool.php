<?php
namespace BTD\Tools\Core;

use BTD\Models\Calculation;
use BTD\Tools\Core\Tool;



/**
 * AI Tool Base Class
 * 
 * For AI-powered content generation tools
 * Supports multiple AI providers: Gemini (default), OpenAI, Anthropic
 */
abstract class AITool extends Tool {
    
    /**
     * Call AI API (auto-selects provider from settings)
     */
    protected function callAI($prompt, $system_prompt = null) {
        // Get AI provider from settings
        $provider = btd_get_setting('ai_provider', 'gemini');
        
        // Call appropriate provider
        switch ($provider) {
            case 'gemini':
                return $this->callGemini($prompt, $system_prompt);
            case 'openai':
                return $this->callOpenAI($prompt, $system_prompt);
            case 'anthropic':
                return $this->callAnthropic($prompt, $system_prompt);
            default:
                throw new \Exception('Invalid AI provider configured');
        }
    }
    
    /**
     * Call Google Gemini API
     */
    protected function callGemini($prompt, $system_prompt = null) {
        $api_key = btd_get_setting('gemini_api_key');
        
        if (empty($api_key)) {
            throw new \Exception('Gemini API key not configured');
        }
        
        $model = btd_get_setting('gemini_model', 'gemini-pro');
        $max_tokens = btd_get_setting('max_tokens', 4096);
        $temperature = btd_get_setting('temperature', 0.7);
        
        // Build content parts
        $parts = [];
        if ($system_prompt) {
            $parts[] = ['text' => $system_prompt];
        }
        $parts[] = ['text' => $prompt];
        
        $body = [
            'contents' => [
                ['parts' => $parts]
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $max_tokens,
            ]
        ];
        
        $response = wp_remote_post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}", [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => btd_get_setting('api_timeout', 30),
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['error'])) {
            throw new \Exception($data['error']['message'] ?? 'Gemini API Error');
        }
        
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
    
    /**
     * Call OpenAI API
     */
    protected function callOpenAI($prompt, $system_prompt = null) {
        $api_key = btd_get_setting('openai_api_key');
        
        if (empty($api_key)) {
            throw new \Exception('OpenAI API key not configured');
        }
        
        $model = btd_get_setting('openai_model', 'gpt-4');
        $max_tokens = btd_get_setting('max_tokens', 4096);
        $temperature = btd_get_setting('temperature', 0.7);
        
        $messages = [];
        if ($system_prompt) {
            $messages[] = ['role' => 'system', 'content' => $system_prompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];
        
        $body = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
        ];
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => json_encode($body),
            'timeout' => btd_get_setting('api_timeout', 30),
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['error'])) {
            throw new \Exception($data['error']['message'] ?? 'OpenAI API Error');
        }
        
        return $data['choices'][0]['message']['content'] ?? '';
    }
    
    /**
     * Call Anthropic Claude API
     */
    protected function callAnthropic($prompt, $system_prompt = null) {
        $api_key = btd_get_setting('anthropic_api_key');
        
        if (empty($api_key)) {
            throw new \Exception('Anthropic API key not configured');
        }
        
        $model = btd_get_setting('anthropic_model', 'claude-3-sonnet-20240229');
        $max_tokens = btd_get_setting('max_tokens', 4096);
        $temperature = btd_get_setting('temperature', 0.7);
        
        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];
        
        $body = [
            'model' => $model,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
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
            'timeout' => btd_get_setting('api_timeout', 30),
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['error'])) {
            throw new \Exception($data['error']['message'] ?? 'Anthropic API Error');
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
        $tool = \BTD\Models\Tool::getBySlug($this->slug);
        
        return Calculation::create([
            'user_id' => get_current_user_id() ?: 0,
            'tool_id' => $tool ? $tool->id : null,
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