/**
 * BTD Settings Page JavaScript
 */

jQuery(document).ready(function ($) {

    // Tab switching
    $('.nav-tab').on('click', function (e) {
        e.preventDefault();

        var target = $(this).attr('href');

        // Update tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Update content
        $('.btd-settings-tab-content').removeClass('active');
        $(target).addClass('active');
    });

    // Form submission
    $('#btd-settings-form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var formData = $form.serialize();

        // Disable button
        $button.prop('disabled', true).text('Saving...');

        // AJAX save
        $.ajax({
            url: btdSettings.ajaxUrl,
            type: 'POST',
            data: formData + '&action=save_btd_settings&nonce=' + btdSettings.nonce,
            success: function (response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                } else {
                    showMessage(response.data.message || 'Error saving settings', 'error');
                }
            },
            error: function () {
                showMessage('Error saving settings', 'error');
            },
            complete: function () {
                $button.prop('disabled', false).text('Save Settings');
            }
        });
    });

    // Show message
    function showMessage(message, type) {
        var $message = $('<div class="btd-settings-message ' + type + '">' + message + '</div>');

        $('.btd-settings-page h1').after($message);

        setTimeout(function () {
            $message.addClass('show');
        }, 100);

        setTimeout(function () {
            $message.removeClass('show');
            setTimeout(function () {
                $message.remove();
            }, 300);
        }, 3000);
    }

});
