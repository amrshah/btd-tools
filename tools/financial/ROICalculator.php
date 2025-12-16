<?php
/**
 * =============================================================================
 * ROI Calculator - Complete Working Example
 * =============================================================================
 * 
 * Location: wp-content/plugins/btd-tools/tools/financial/ROICalculator.php
 * 
 * This file includes:
 * 1. PHP Calculator Class
 * 2. AJAX Handler Registration
 * 3. Shortcode Registration
 * 4. React Component (in comments)
 * =============================================================================
 */

namespace BTD\Tools\Financial;

use BTD\Tools\Core\Calculator;

/**
 * ROI Calculator Tool
 */
class ROICalculator extends Calculator {
    
    public function __construct() {
        $this->slug = 'roi-calculator';
        $this->name = 'ROI Calculator';
        $this->description = 'Calculate your return on investment with detailed analysis';
        $this->category = 'financial';
        $this->tier = 'free';
        $this->icon = 'dashicons-chart-line';
        $this->color = '#10b981';
        
        // Define input fields
        $this->inputs = [
            'investment' => [
                'label' => 'Initial Investment ($)',
                'type' => 'number',
                'placeholder' => '10000',
                'min' => 0,
                'step' => 0.01,
                'required' => true,
                'help' => 'Amount of money invested initially',
            ],
            'final_value' => [
                'label' => 'Final Value ($)',
                'type' => 'number',
                'placeholder' => '15000',
                'min' => 0,
                'step' => 0.01,
                'required' => true,
                'help' => 'Current or final value of investment',
            ],
            'time_period' => [
                'label' => 'Time Period (months)',
                'type' => 'integer',
                'placeholder' => '12',
                'min' => 1,
                'max' => 600,
                'required' => true,
                'help' => 'Duration of investment in months',
            ],
        ];
        
        // Define output fields
        $this->outputs = [
            'roi_percent' => [
                'label' => 'ROI Percentage',
                'format' => 'percentage',
                'highlight' => true,
                'help' => 'Your total return on investment',
            ],
            'profit' => [
                'label' => 'Total Profit',
                'format' => 'currency',
                'help' => 'Net profit from investment',
            ],
            'roi_annual' => [
                'label' => 'Annualized ROI',
                'format' => 'percentage',
                'help' => 'ROI normalized to yearly basis',
            ],
            'monthly_return' => [
                'label' => 'Monthly Return',
                'format' => 'currency',
                'help' => 'Average profit per month',
            ],
        ];
        
        // Register AJAX handlers
        $this->registerAjaxHandlers();
        
        // Register shortcode
        $this->registerShortcode();
    }
    
    /**
     * Perform ROI calculation
     */
    protected function calculate($inputs) {
        $investment = floatval($inputs['investment']);
        $final_value = floatval($inputs['final_value']);
        $time_period = intval($inputs['time_period']);
        
        // Calculate profit
        $profit = $final_value - $investment;
        
        // Calculate ROI percentage
        $roi_percent = ($profit / $investment) * 100;
        
        // Calculate annualized ROI
        $years = $time_period / 12;
        $roi_annual = ($roi_percent / $years);
        
        // Calculate monthly return
        $monthly_return = $profit / $time_period;
        
        return [
            'roi_percent' => round($roi_percent, 2),
            'profit' => round($profit, 2),
            'roi_annual' => round($roi_annual, 2),
            'monthly_return' => round($monthly_return, 2),
            'investment' => $investment,
            'final_value' => $final_value,
            'time_period' => $time_period,
            // Additional analysis
            'is_profitable' => $profit > 0,
            'roi_rating' => $this->getRoiRating($roi_annual),
        ];
    }
    
    /**
     * Get ROI rating based on annualized return
     */
    private function getRoiRating($annual_roi) {
        if ($annual_roi < 0) return 'poor';
        if ($annual_roi < 5) return 'below_average';
        if ($annual_roi < 10) return 'average';
        if ($annual_roi < 15) return 'good';
        if ($annual_roi < 25) return 'excellent';
        return 'exceptional';
    }
    
    /**
     * Register AJAX handlers
     */
    private function registerAjaxHandlers() {
        add_action('wp_ajax_btd_calculate_roi', [$this, 'ajaxHandler']);
        add_action('wp_ajax_nopriv_btd_calculate_roi', [$this, 'ajaxHandler']);
    }
    
    /**
     * AJAX handler
     */
    public function ajaxHandler() {
        check_ajax_referer('btd_tool_roi-calculator', 'nonce');
        
        $inputs = [
            'investment' => $_POST['investment'] ?? '',
            'final_value' => $_POST['final_value'] ?? '',
            'time_period' => $_POST['time_period'] ?? '',
        ];
        
        $result = $this->process($inputs);
        
        wp_send_json($result);
    }
    
    /**
     * Register shortcode
     */
    private function registerShortcode() {
        add_shortcode('btd_roi_calculator', [$this, 'shortcode']);
    }
    
    /**
     * Shortcode callback
     */
    public function shortcode($atts) {
        $atts = shortcode_atts([
            'style' => 'default', // default, compact, inline
        ], $atts);
        
        ob_start();
        ?>
        <div class="btd-tool-container btd-roi-calculator" data-style="<?php echo esc_attr($atts['style']); ?>">
            <div class="btd-tool-inner">
                <div class="btd-tool-content">
                    <?php $this->renderForm(); ?>
                    <?php $this->renderResults([]); ?>
                </div>
                
                <div class="btd-tool-sidebar">
                    <div class="btd-info-box">
                        <h4><?php _e('How to Use', 'btd-tools'); ?></h4>
                        <ol>
                            <li><?php _e('Enter your initial investment amount', 'btd-tools'); ?></li>
                            <li><?php _e('Enter the final value of your investment', 'btd-tools'); ?></li>
                            <li><?php _e('Specify the time period in months', 'btd-tools'); ?></li>
                            <li><?php _e('Click Calculate to see your ROI', 'btd-tools'); ?></li>
                        </ol>
                    </div>
                    
                    <div class="btd-info-box">
                        <h4><?php _e('What is ROI?', 'btd-tools'); ?></h4>
                        <p><?php _e('Return on Investment (ROI) measures the profitability of an investment relative to its cost. A positive ROI indicates profit, while negative ROI indicates a loss.', 'btd-tools'); ?></p>
                    </div>
                    
                    <div class="btd-info-box">
                        <h4><?php _e('ROI Benchmarks', 'btd-tools'); ?></h4>
                        <ul>
                            <li>< 5% - <?php _e('Below Average', 'btd-tools'); ?></li>
                            <li>5-10% - <?php _e('Average', 'btd-tools'); ?></li>
                            <li>10-15% - <?php _e('Good', 'btd-tools'); ?></li>
                            <li>15-25% - <?php _e('Excellent', 'btd-tools'); ?></li>
                            <li>> 25% - <?php _e('Exceptional', 'btd-tools'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.btd-roi-calculator form').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $btn = $form.find('button[type="submit"]');
                const $results = $form.closest('.btd-tool-container').find('.btd-results');
                
                // Show loading state
                $btn.prop('disabled', true).text('<?php _e('Calculating...', 'btd-tools'); ?>');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'btd_calculate_roi',
                        nonce: $form.find('[name="nonce"]').val(),
                        investment: $form.find('[name="investment"]').val(),
                        final_value: $form.find('[name="final_value"]').val(),
                        time_period: $form.find('[name="time_period"]').val(),
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update result values
                            const results = response.results;
                            
                            $results.find('[data-field="roi_percent"]').text(
                                results.roi_percent + '%'
                            );
                            $results.find('[data-field="profit"]').text(
                                '$' + results.profit.toLocaleString()
                            );
                            $results.find('[data-field="roi_annual"]').text(
                                results.roi_annual + '%'
                            );
                            $results.find('[data-field="monthly_return"]').text(
                                '$' + results.monthly_return.toLocaleString()
                            );
                            
                            // Show results
                            $results.slideDown();
                            
                            // Update remaining uses
                            if (response.remaining >= 0) {
                                $form.find('.remaining-uses').text(
                                    '<?php _e('Remaining uses today:', 'btd-tools'); ?> ' + response.remaining
                                );
                            }
                            
                            // Scroll to results
                            $('html, body').animate({
                                scrollTop: $results.offset().top - 100
                            }, 500);
                            
                        } else {
                            // Handle errors
                            if (response.error === 'upgrade_required') {
                                showUpgradeModal();
                            } else if (response.error === 'rate_limit') {
                                alert(response.message);
                            } else {
                                alert('<?php _e('An error occurred. Please try again.', 'btd-tools'); ?>');
                            }
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred. Please try again.', 'btd-tools'); ?>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php _e('Calculate', 'btd-tools'); ?>');
                    }
                });
            });
        });
        
        function btdExportPDF(btn) {
            // TODO: Implement PDF export
            alert('<?php _e('PDF export coming soon!', 'btd-tools'); ?>');
        }
        
        function btdSaveResult(btn) {
            // TODO: Implement save result
            alert('<?php _e('Save feature coming soon!', 'btd-tools'); ?>');
        }
        
        function btdResetCalculator(btn) {
            const $container = $(btn).closest('.btd-tool-container');
            $container.find('form')[0].reset();
            $container.find('.btd-results').slideUp();
        }
        
        function showUpgradeModal() {
            // TODO: Implement upgrade modal
            alert('<?php _e('Please upgrade your plan to access this tool.', 'btd-tools'); ?>');
        }
        </script>
        <?php
        return ob_get_clean();
    }
}

// Initialize the tool
new ROICalculator();

/**
 * =============================================================================
 * REACT VERSION (Optional - for modern UI)
 * =============================================================================
 * 
 * Save this as: assets/js/tools/ROICalculator.jsx
 * 
 * Then compile with: npm run build
 * 
 * ```jsx
 * import React, { useState } from 'react';
 * 
 * export default function ROICalculator() {
 *   const [inputs, setInputs] = useState({
 *     investment: '',
 *     final_value: '',
 *     time_period: ''
 *   });
 *   
 *   const [results, setResults] = useState(null);
 *   const [loading, setLoading] = useState(false);
 *   const [remaining, setRemaining] = useState(null);
 *   
 *   const handleSubmit = async (e) => {
 *     e.preventDefault();
 *     setLoading(true);
 *     
 *     const formData = new FormData();
 *     formData.append('action', 'btd_calculate_roi');
 *     formData.append('nonce', btdData.nonce);
 *     Object.keys(inputs).forEach(key => {
 *       formData.append(key, inputs[key]);
 *     });
 *     
 *     try {
 *       const response = await fetch(btdData.ajaxUrl, {
 *         method: 'POST',
 *         body: formData
 *       });
 *       
 *       const data = await response.json();
 *       
 *       if (data.success) {
 *         setResults(data.results);
 *         setRemaining(data.remaining);
 *       } else {
 *         if (data.error === 'upgrade_required') {
 *           // Show upgrade modal
 *         } else if (data.error === 'rate_limit') {
 *           alert(data.message);
 *         }
 *       }
 *     } catch (error) {
 *       console.error('Error:', error);
 *       alert('An error occurred');
 *     } finally {
 *       setLoading(false);
 *     }
 *   };
 *   
 *   return (
 *     <div className="btd-roi-calculator max-w-6xl mx-auto p-6">
 *       <div className="grid md:grid-cols-2 gap-8">
 *         {/* Input Section *\/}
 *         <div className="bg-white rounded-lg shadow-lg p-6">
 *           <h2 className="text-2xl font-bold mb-6">ROI Calculator</h2>
 *           
 *           <form onSubmit={handleSubmit} className="space-y-4">
 *             <div>
 *               <label className="block text-sm font-medium mb-2">
 *                 Initial Investment ($)
 *               </label>
 *               <input
 *                 type="number"
 *                 value={inputs.investment}
 *                 onChange={(e) => setInputs({...inputs, investment: e.target.value})}
 *                 className="w-full px-4 py-2 border rounded-lg"
 *                 placeholder="10000"
 *                 required
 *               />
 *             </div>
 *             
 *             <div>
 *               <label className="block text-sm font-medium mb-2">
 *                 Final Value ($)
 *               </label>
 *               <input
 *                 type="number"
 *                 value={inputs.final_value}
 *                 onChange={(e) => setInputs({...inputs, final_value: e.target.value})}
 *                 className="w-full px-4 py-2 border rounded-lg"
 *                 placeholder="15000"
 *                 required
 *               />
 *             </div>
 *             
 *             <div>
 *               <label className="block text-sm font-medium mb-2">
 *                 Time Period (months)
 *               </label>
 *               <input
 *                 type="number"
 *                 value={inputs.time_period}
 *                 onChange={(e) => setInputs({...inputs, time_period: e.target.value})}
 *                 className="w-full px-4 py-2 border rounded-lg"
 *                 placeholder="12"
 *                 required
 *               /> 
 *             </div>
 *             
 *             <button
 *               type="submit"
 *               disabled={loading}
 *               className="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 disabled:bg-gray-400"
 *             >
 *               {loading ? 'Calculating...' : 'Calculate ROI'}
 *             </button>
 *             
 *             {remaining !== null && remaining >= 0 && (
 *               <p className="text-sm text-gray-600 text-center">
 *                 {remaining} uses remaining today
 *               </p>
 *             )}
 *           </form>
 *         </div>
 *         
 *         {/* Results Section *\/}
 *         <div className="bg-white rounded-lg shadow-lg p-6">
 *           <h2 className="text-2xl font-bold mb-6">Results</h2>
 *           
 *           {results ? (
 *             <div className="space-y-4">
 *               <div className="bg-green-50 p-4 rounded-lg">
 *                 <div className="text-sm text-gray-600">ROI Percentage</div>
 *                 <div className="text-4xl font-bold text-green-600">
 *                   {results.roi_percent}%
 *                 </div>
 *               </div>
 *               
 *               <div className="grid grid-cols-2 gap-4">
 *                 <div className="bg-gray-50 p-4 rounded-lg">
 *                   <div className="text-sm text-gray-600">Total Profit</div>
 *                   <div className="text-2xl font-bold">
 *                     ${results.profit.toLocaleString()}
 *                   </div>
 *                 </div>
 *                 
 *                 <div className="bg-gray-50 p-4 rounded-lg">
 *                   <div className="text-sm text-gray-600">Annualized ROI</div>
 *                   <div className="text-2xl font-bold">
 *                     {results.roi_annual}%
 *                   </div>
 *                 </div>
 *                 
 *                 <div className="bg-gray-50 p-4 rounded-lg">
 *                   <div className="text-sm text-gray-600">Monthly Return</div>
 *                   <div className="text-2xl font-bold">
 *                     ${results.monthly_return.toLocaleString()}
 *                   </div>
 *                 </div>
 *               </div>
 *               
 *               <div className="flex gap-2">
 *                 <button className="flex-1 bg-gray-200 py-2 rounded hover:bg-gray-300">
 *                   Export PDF
 *                 </button>
 *                 <button className="flex-1 bg-gray-200 py-2 rounded hover:bg-gray-300">
 *                   Save Result
 *                 </button>
 *               </div>
 *             </div>
 *           ) : (
 *             <div className="text-center text-gray-400 py-12">
 *               Enter values and calculate to see results
 *             </div>
 *           )}
 *         </div>
 *       </div>
 *     </div>
 *   );
 * }
 * ```
 * 
 * =============================================================================
 */