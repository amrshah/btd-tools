/**
 * =============================================================================
 * BTD Frontend JavaScript
 * =============================================================================
 * 
 * Location: wp-content/plugins/btd-tools/assets/js/frontend.js
 * 
 * This file handles all frontend interactions for BTD tools
 * =============================================================================
 */

(function($) {
    'use strict';
    
    const BTD = {
        
        /**
         * Initialize
         */
        init: function() {
            this.setupCalculatorForms();
            this.setupAITools();
            this.setupResultActions();
            this.setupUserDashboard();
        },
        
        /**
         * Setup calculator forms
         */
        setupCalculatorForms: function() {
            $('.btd-calculator-form').on('submit', function(e) {
                e.preventDefault();
                BTD.handleCalculatorSubmit($(this));
            });
        },
        
        /**
         * Handle calculator form submission
         */
        handleCalculatorSubmit: function($form) {
            const toolSlug = $form.data('tool');
            const $btn = $form.find('button[type="submit"]');
            const $results = $form.closest('.btd-tool-container').find('.btd-results');
            const originalBtnText = $btn.text();
            
            // Clear previous errors
            $form.find('.btd-field-error').text('');
            
            // Show loading state
            $btn.prop('disabled', true).text('Processing...');
            
            // Gather form data
            const formData = {
                action: 'btd_calculate_' + toolSlug.replace(/-/g, '_'),
                nonce: $form.find('[name="nonce"]').val(),
            };
            
            $form.find('input, select, textarea').each(function() {
                const $field = $(this);
                if ($field.attr('name') && $field.attr('name') !== 'nonce') {
                    formData[$field.attr('name')] = $field.val();
                }
            });
            
            // Submit AJAX request
            $.ajax({
                url: btdData.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        BTD.displayResults($results, response.results);
                        BTD.updateRemainingUses($form, response.remaining);
                        
                        // Scroll to results
                        $('html, body').animate({
                            scrollTop: $results.offset().top - 100
                        }, 500);
                        
                    } else {
                        BTD.handleError(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    BTD.showNotification('An error occurred. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalBtnText);
                }
            });
        },
        
        /**
         * Display calculation results
         */
        displayResults: function($resultsContainer, results) {
            $resultsContainer.find('[data-field]').each(function() {
                const $field = $(this);
                const fieldName = $field.data('field');
                const value = results[fieldName];
                
                if (value !== undefined) {
                    // Format value based on data-format attribute
                    const format = $field.data('format') || 'text';
                    const formattedValue = BTD.formatValue(value, format);
                    $field.text(formattedValue);
                }
            });
            
            $resultsContainer.slideDown();
        },
        
        /**
         * Format value based on type
         */
        formatValue: function(value, format) {
            switch (format) {
                case 'currency':
                    return '$' + parseFloat(value).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    
                case 'percentage':
                    return parseFloat(value).toFixed(2) + '%';
                    
                case 'number':
                    return parseFloat(value).toLocaleString('en-US');
                    
                case 'integer':
                    return parseInt(value).toLocaleString('en-US');
                    
                default:
                    return value;
            }
        },
        
        /**
         * Update remaining uses display
         */
        updateRemainingUses: function($form, remaining) {
            if (remaining === -1) {
                $form.find('.remaining-uses').text('Unlimited uses');
            } else if (remaining >= 0) {
                $form.find('.remaining-uses').text(
                    'Remaining uses today: ' + remaining
                );
                
                // Show warning if low
                if (remaining <= 2) {
                    $form.find('.remaining-uses').addClass('warning');
                }
            }
        },
        
        /**
         * Handle errors
         */
        handleError: function(response) {
            switch (response.error) {
                case 'upgrade_required':
                    BTD.showUpgradeModal();
                    break;
                    
                case 'rate_limit':
                    BTD.showNotification(response.message, 'warning');
                    break;
                    
                case 'validation':
                    BTD.showValidationErrors(response.errors);
                    break;
                    
                default:
                    BTD.showNotification('An error occurred. Please try again.', 'error');
            }
        },
        
        /**
         * Show validation errors
         */
        showValidationErrors: function(errors) {
            $.each(errors, function(field, message) {
                $('[name="' + field + '"]')
                    .closest('.btd-form-group')
                    .find('.btd-field-error')
                    .text(message);
            });
        },
        
        /**
         * Setup AI tools
         */
        setupAITools: function() {
            $('.btd-ai-tool-form').on('submit', function(e) {
                e.preventDefault();
                BTD.handleAIToolSubmit($(this));
            });
        },
        
        /**
         * Handle AI tool submission
         */
        handleAIToolSubmit: function($form) {
            const toolSlug = $form.data('tool');
            const $btn = $form.find('button[type="submit"]');
            const $output = $form.closest('.btd-tool-container').find('.btd-ai-output');
            
            $btn.prop('disabled', true).text('Generating...');
            
            const formData = {
                action: 'btd_ai_' + toolSlug.replace(/-/g, '_'),
                nonce: $form.find('[name="nonce"]').val(),
            };
            
            $form.find('input, select, textarea').each(function() {
                const $field = $(this);
                if ($field.attr('name') && $field.attr('name') !== 'nonce') {
                    formData[$field.attr('name')] = $field.val();
                }
            });
            
            $.ajax({
                url: btdData.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $output.find('.content').html(response.content);
                        $output.slideDown();
                        BTD.updateRemainingUses($form, response.remaining);
                    } else {
                        BTD.handleError(response);
                    }
                },
                error: function() {
                    BTD.showNotification('Generation failed. Please try again.', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Generate');
                }
            });
        },
        
        /**
         * Setup result actions
         */
        setupResultActions: function() {
            // Export PDF
            $(document).on('click', '[data-action="export-pdf"]', function(e) {
                e.preventDefault();
                BTD.exportPDF($(this));
            });
            
            // Save result
            $(document).on('click', '[data-action="save-result"]', function(e) {
                e.preventDefault();
                BTD.saveResult($(this));
            });
            
            // Share result
            $(document).on('click', '[data-action="share-result"]', function(e) {
                e.preventDefault();
                BTD.shareResult($(this));
            });
            
            // Reset calculator
            $(document).on('click', '[data-action="reset"]', function(e) {
                e.preventDefault();
                BTD.resetTool($(this));
            });
        },
        
        /**
         * Export to PDF
         */
        exportPDF: function($btn) {
            const $container = $btn.closest('.btd-tool-container');
            const calculationId = $container.data('calculation-id');
            
            if (!calculationId) {
                BTD.showNotification('Please calculate first', 'warning');
                return;
            }
            
            // TODO: Implement PDF generation
            window.location.href = btdData.ajaxUrl + '?action=btd_export_pdf&id=' + calculationId;
        },
        
        /**
         * Save result
         */
        saveResult: function($btn) {
            const $container = $btn.closest('.btd-tool-container');
            const calculationId = $container.data('calculation-id');
            
            if (!btdData.isLoggedIn) {
                BTD.showLoginPrompt();
                return;
            }
            
            const resultName = prompt('Name this result:');
            if (!resultName) return;
            
            $.ajax({
                url: btdData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'btd_save_result',
                    nonce: btdData.nonce,
                    calculation_id: calculationId,
                    result_name: resultName
                },
                success: function(response) {
                    if (response.success) {
                        BTD.showNotification('Result saved!', 'success');
                    }
                }
            });
        },
        
        /**
         * Share result
         */
        shareResult: function($btn) {
            const $container = $btn.closest('.btd-tool-container');
            const calculationId = $container.data('calculation-id');
            
            $.ajax({
                url: btdData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'btd_share_result',
                    nonce: btdData.nonce,
                    calculation_id: calculationId
                },
                success: function(response) {
                    if (response.success) {
                        BTD.showShareModal(response.share_url);
                    }
                }
            });
        },
        
        /**
         * Reset tool
         */
        resetTool: function($btn) {
            const $container = $btn.closest('.btd-tool-container');
            $container.find('form')[0].reset();
            $container.find('.btd-results').slideUp();
            $container.find('.btd-ai-output').slideUp();
            $container.find('.btd-field-error').text('');
        },
        
        /**
         * Setup user dashboard
         */
        setupUserDashboard: function() {
            // Load calculation history
            if ($('.btd-user-dashboard').length) {
                BTD.loadCalculationHistory();
            }
            
            // Load saved results
            if ($('.btd-saved-results').length) {
                BTD.loadSavedResults();
            }
        },
        
        /**
         * Load calculation history
         */
        loadCalculationHistory: function() {
            $.ajax({
                url: btdData.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'btd_get_calculation_history',
                    nonce: btdData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BTD.renderCalculationHistory(response.data);
                    }
                }
            });
        },
        
        /**
         * Render calculation history
         */
        renderCalculationHistory: function(calculations) {
            const $container = $('.btd-calculation-history');
            $container.empty();
            
            if (calculations.length === 0) {
                $container.html('<p>No calculations yet</p>');
                return;
            }
            
            calculations.forEach(function(calc) {
                const $item = $(`
                    <div class="btd-history-item">
                        <div class="tool-name">${calc.tool_slug}</div>
                        <div class="date">${calc.created_at}</div>
                        <div class="actions">
                            <button data-id="${calc.id}">View</button>
                        </div>
                    </div>
                `);
                $container.append($item);
            });
        },
        
        /**
         * Show notification
         */
        showNotification: function(message, type = 'info') {
            const $notification = $(`
                <div class="btd-notification btd-notification-${type}">
                    ${message}
                </div>
            `);
            
            $('body').append($notification);
            
            setTimeout(function() {
                $notification.addClass('show');
            }, 10);
            
            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        },
        
        /**
         * Show upgrade modal
         */
        showUpgradeModal: function() {
            const $modal = $(`
                <div class="btd-modal btd-upgrade-modal">
                    <div class="btd-modal-content">
                        <h2>Upgrade Required</h2>
                        <p>This tool requires a premium subscription.</p>
                        <div class="btd-pricing-tiers">
                            <div class="tier">
                                <h3>Starter</h3>
                                <div class="price">$29/mo</div>
                                <ul>
                                    <li>20+ core tools</li>
                                    <li>Unlimited calculations</li>
                                </ul>
                                <button>Choose Plan</button>
                            </div>
                            <div class="tier featured">
                                <h3>Professional</h3>
                                <div class="price">$79/mo</div>
                                <ul>
                                    <li>All 60+ tools</li>
                                    <li>AI-powered tools</li>
                                    <li>Team workspace</li>
                                </ul>
                                <button>Choose Plan</button>
                            </div>
                            <div class="tier">
                                <h3>Business</h3>
                                <div class="price">$199/mo</div>
                                <ul>
                                    <li>Everything in Pro</li>
                                    <li>Unlimited team members</li>
                                    <li>White-label options</li>
                                </ul>
                                <button>Choose Plan</button>
                            </div>
                        </div>
                        <button class="btd-modal-close">&times;</button>
                    </div>
                </div>
            `);
            
            $('body').append($modal);
            
            setTimeout(function() {
                $modal.addClass('show');
            }, 10);
            
            $modal.find('.btd-modal-close, .btd-modal').on('click', function(e) {
                if (e.target === this) {
                    $modal.removeClass('show');
                    setTimeout(function() {
                        $modal.remove();
                    }, 300);
                }
            });
        },
        
        /**
         * Show login prompt
         */
        showLoginPrompt: function() {
            if (confirm('Please log in to save results. Go to login page?')) {
                window.location.href = '/wp-login.php?redirect_to=' + 
                    encodeURIComponent(window.location.href);
            }
        },
        
        /**
         * Show share modal
         */
        showShareModal: function(shareUrl) {
            const $modal = $(`
                <div class="btd-modal btd-share-modal">
                    <div class="btd-modal-content">
                        <h2>Share Result</h2>
                        <p>Anyone with this link can view your result:</p>
                        <input type="text" value="${shareUrl}" readonly>
                        <button class="btd-copy-link">Copy Link</button>
                        <button class="btd-modal-close">&times;</button>
                    </div>
                </div>
            `);
            
            $('body').append($modal);
            $modal.addClass('show');
            
            $modal.find('.btd-copy-link').on('click', function() {
                $modal.find('input').select();
                document.execCommand('copy');
                BTD.showNotification('Link copied!', 'success');
            });
            
            $modal.find('.btd-modal-close').on('click', function() {
                $modal.remove();
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        BTD.init();
    });
    
    // Expose BTD globally
    window.BTD = BTD;
    
})(jQuery);

