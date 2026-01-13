<?php
namespace IBS\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class Assets {
    
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    public function enqueue_scripts($hook) {
        // Charger uniquement sur nos pages
        if (strpos($hook, 'ikomiris-booking') === false) {
            return;
        }
        
        // CSS Admin
        wp_enqueue_style(
            'ibs-admin-css',
            IBS_PLUGIN_URL . 'admin/assets/css/admin.css',
            [],
            IBS_VERSION
        );
        
        // JavaScript Admin
        wp_enqueue_script(
            'ibs-admin-js',
            IBS_PLUGIN_URL . 'admin/assets/js/admin.js',
            ['jquery', 'wp-color-picker'],
            IBS_VERSION,
            true
        );
        
        // Color Picker
        wp_enqueue_style('wp-color-picker');
        
        // Média Uploader
        wp_enqueue_media();
        
        // Localisation
        wp_localize_script('ibs-admin-js', 'ibsAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ibs_admin_nonce'),
            'strings' => [
                'confirmDelete' => __('Êtes-vous sûr de vouloir supprimer cet élément ?', 'ikomiris-booking'),
                'error' => __('Une erreur est survenue', 'ikomiris-booking'),
                'success' => __('Opération réussie', 'ikomiris-booking'),
            ]
        ]);
    }
}
