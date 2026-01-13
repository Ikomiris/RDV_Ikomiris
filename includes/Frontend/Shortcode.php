<?php
namespace IBS\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Shortcode {
    
    public function __construct() {
        add_shortcode('ikomiris_booking', [$this, 'render_booking_form']);
    }
    
    public function render_booking_form($atts) {
        $atts = shortcode_atts([
            'store_id' => '',
        ], $atts);
        
        ob_start();
        require IBS_PLUGIN_DIR . 'frontend/views/booking-form.php';
        return ob_get_clean();
    }
}
