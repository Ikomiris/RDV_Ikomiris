<?php
namespace IBS\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Assets {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    public function enqueue_scripts() {
        // CSS Frontend
        wp_enqueue_style(
            'ibs-frontend-css',
            IBS_PLUGIN_URL . 'frontend/assets/css/frontend.css',
            [],
            IBS_VERSION
        );
        
        // JavaScript Frontend
        wp_enqueue_script(
            'ibs-frontend-js',
            IBS_PLUGIN_URL . 'frontend/assets/js/frontend.js',
            ['jquery'],
            IBS_VERSION,
            true
        );
        
        // Localisation
        wp_localize_script('ibs-frontend-js', 'ibsFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ibs_frontend_nonce'),
            'strings' => [
                'loading' => __('Chargement...', 'ikomiris-booking'),
                'error' => __('Une erreur est survenue', 'ikomiris-booking'),
                'selectStore' => __('Veuillez sélectionner un magasin', 'ikomiris-booking'),
                'selectService' => __('Veuillez sélectionner un service', 'ikomiris-booking'),
                'selectDate' => __('Veuillez sélectionner une date', 'ikomiris-booking'),
                'selectTime' => __('Veuillez sélectionner un créneau horaire', 'ikomiris-booking'),
                'noSlots' => __('Aucun créneau disponible pour cette date', 'ikomiris-booking'),
                'fillForm' => __('Veuillez remplir tous les champs obligatoires', 'ikomiris-booking'),
                'invalidEmail' => __('Adresse email invalide', 'ikomiris-booking'),
                'bookingSuccess' => __('Votre réservation a été confirmée !', 'ikomiris-booking'),
            ]
        ]);
    }
}
