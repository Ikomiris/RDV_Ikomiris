<?php
namespace IBS\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class AdminMenu {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
    }
    
    public function add_menu() {
        // Menu principal
        add_menu_page(
            __('Réservations', 'ikomiris-booking'),
            __('Réservations', 'ikomiris-booking'),
            'manage_options',
            'ikomiris-booking',
            [$this, 'render_bookings_page'],
            'dashicons-calendar-alt',
            30
        );
        
        // Sous-menu : Réservations (par défaut)
        add_submenu_page(
            'ikomiris-booking',
            __('Toutes les réservations', 'ikomiris-booking'),
            __('Toutes les réservations', 'ikomiris-booking'),
            'manage_options',
            'ikomiris-booking',
            [$this, 'render_bookings_page']
        );
        
        // Sous-menu : Magasins
        add_submenu_page(
            'ikomiris-booking',
            __('Magasins', 'ikomiris-booking'),
            __('Magasins', 'ikomiris-booking'),
            'manage_options',
            'ikomiris-booking-stores',
            [$this, 'render_stores_page']
        );
        
        // Sous-menu : Services
        add_submenu_page(
            'ikomiris-booking',
            __('Services', 'ikomiris-booking'),
            __('Services', 'ikomiris-booking'),
            'manage_options',
            'ikomiris-booking-services',
            [$this, 'render_services_page']
        );
        
        // Sous-menu : Horaires
        add_submenu_page(
            'ikomiris-booking',
            __('Horaires', 'ikomiris-booking'),
            __('Horaires', 'ikomiris-booking'),
            'manage_options',
            'ikomiris-booking-schedules',
            [$this, 'render_schedules_page']
        );
        
        // Sous-menu : Dates exceptionnelles
        add_submenu_page(
            'ikomiris-booking',
            __('Dates exceptionnelles', 'ikomiris-booking'),
            __('Dates exceptionnelles', 'ikomiris-booking'),
            'manage_options',
            'ikomiris-booking-exceptions',
            [$this, 'render_exceptions_page']
        );
        
        // Sous-menu : Paramètres
        add_submenu_page(
            'ikomiris-booking',
            __('Paramètres', 'ikomiris-booking'),
            __('Paramètres', 'ikomiris-booking'),
            'manage_options',
            'ikomiris-booking-settings',
            [$this, 'render_settings_page']
        );
    }
    
    public function render_bookings_page() {
        require_once IBS_PLUGIN_DIR . 'admin/views/bookings.php';
    }
    
    public function render_stores_page() {
        require_once IBS_PLUGIN_DIR . 'admin/views/stores.php';
    }
    
    public function render_services_page() {
        require_once IBS_PLUGIN_DIR . 'admin/views/services.php';
    }
    
    public function render_schedules_page() {
        require_once IBS_PLUGIN_DIR . 'admin/views/schedules.php';
    }
    
    public function render_exceptions_page() {
        require_once IBS_PLUGIN_DIR . 'admin/views/exceptions.php';
    }
    
    public function render_settings_page() {
        require_once IBS_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
