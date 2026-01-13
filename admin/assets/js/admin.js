(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Media Uploader déjà géré dans les vues
        
        // Color Picker
        if ($.fn.wpColorPicker) {
            $('.ibs-color-picker').wpColorPicker();
        }
        
        // Confirmation de suppression
        $('.button-link-delete').on('click', function(e) {
            if (!confirm(ibsAdmin.strings.confirmDelete)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
})(jQuery);
