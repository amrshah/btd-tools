/**
 * BTD Tool Admin JavaScript
 * 
 * Handles admin UI interactions for tool editing
 */

jQuery(document).ready(function($) {
    
    // Initialize color picker
    if ($.fn.wpColorPicker) {
        $('.btd-color-picker').wpColorPicker();
    }
    
    // Auto-generate slug from title
    $('#title').on('blur', function() {
        var title = $(this).val();
        var slugField = $('#tool_slug');
        
        // Only auto-generate if slug is empty
        if (!slugField.val() && title) {
            var slug = title
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            
            slugField.val(slug);
        }
    });
    
    // Validate slug format
    $('#tool_slug').on('blur', function() {
        var slug = $(this).val();
        var cleaned = slug
            .toLowerCase()
            .replace(/[^a-z0-9-]+/g, '-')
            .replace(/^-+|-+$/g, '');
        
        if (slug !== cleaned) {
            $(this).val(cleaned);
        }
    });
    
    // Limit related tools selection to 5
    $('select[name="related_tools[]"]').on('change', function() {
        var selected = $(this).find('option:selected');
        if (selected.length > 5) {
            alert('You can select a maximum of 5 related tools.');
            selected.last().prop('selected', false);
        }
    });
    
});
