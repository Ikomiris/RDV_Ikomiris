<?php
namespace IBS\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class Assets {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_head', [$this, 'output_custom_styles']);
    }
    
    public function enqueue_scripts() {
        // CSS Frontend
        wp_enqueue_style(
            'ibs-frontend-css',
            IBS_PLUGIN_URL . 'frontend/assets/css/frontend.css',
            [],
            IBS_VERSION
        );
        
        // Flatpickr CSS (date picker)
        wp_enqueue_style(
            'flatpickr-css',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
            [],
            '4.6.13'
        );
        
        // Flatpickr JavaScript
        wp_enqueue_script(
            'flatpickr-js',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js',
            [],
            '4.6.13',
            true
        );
        
        // Flatpickr French locale
        wp_enqueue_script(
            'flatpickr-fr',
            'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js',
            ['flatpickr-js'],
            '4.6.13',
            true
        );
        
        // JavaScript Frontend
        wp_enqueue_script(
            'ibs-frontend-js',
            IBS_PLUGIN_URL . 'frontend/assets/js/frontend.js',
            ['jquery', 'flatpickr-js', 'flatpickr-fr'],
            IBS_VERSION,
            true
        );
        
        // Récupérer les paramètres de réservation
        global $wpdb;
        $min_booking_delay = $wpdb->get_var("SELECT setting_value FROM {$wpdb->prefix}ibs_settings WHERE setting_key = 'min_booking_delay'");
        $max_booking_delay = $wpdb->get_var("SELECT setting_value FROM {$wpdb->prefix}ibs_settings WHERE setting_key = 'max_booking_delay'");

        $min_booking_delay = $min_booking_delay !== null ? intval($min_booking_delay) : 2;
        $max_booking_delay = $max_booking_delay !== null ? intval($max_booking_delay) : 90;

        // Localisation
        wp_localize_script('ibs-frontend-js', 'ibsFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ibs_frontend_nonce'),
            'settings' => [
                'minBookingDelay' => $min_booking_delay, // Heures
                'maxBookingDelay' => $max_booking_delay, // Jours
            ],
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

    /**
     * Récupérer une valeur de style depuis la base de données
     */
    private function get_style_setting($key, $default = '') {
        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$wpdb->prefix}ibs_settings WHERE setting_key = %s",
            $key
        ));
        return $value !== null ? $value : $default;
    }

    /**
     * Générer et afficher le CSS personnalisé dans le header
     */
    public function output_custom_styles() {
        // Récupérer tous les paramètres de style
        $primary_color = $this->get_style_setting('style_primary_color', '#0073aa');
        $secondary_color = $this->get_style_setting('style_secondary_color', '#005177');
        $text_color = $this->get_style_setting('style_text_color', '#333333');
        $background_color = $this->get_style_setting('style_background_color', '#f5f5f5');
        $section_background = $this->get_style_setting('style_section_background', '#ffffff');
        $card_background = $this->get_style_setting('style_card_background', '#ffffff');
        $card_border_radius = $this->get_style_setting('style_card_border_radius', '8');
        $card_shadow = $this->get_style_setting('style_card_shadow', 'medium');
        $button_color = $this->get_style_setting('style_button_color', '#0073aa');
        $button_text_color = $this->get_style_setting('style_button_text_color', '#ffffff');
        $button_hover_color = $this->get_style_setting('style_button_hover_color', '#005177');
        $button_border_radius = $this->get_style_setting('style_button_border_radius', '4');
        $title_color = $this->get_style_setting('style_title_color', '#222222');
        $subtitle_color = $this->get_style_setting('style_subtitle_color', '#666666');

        // Générer les valeurs de box-shadow selon le preset
        $shadow_values = [
            'none' => 'none',
            'light' => '0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.08)',
            'medium' => '0 4px 6px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06)',
            'strong' => '0 10px 15px rgba(0,0,0,0.15), 0 4px 6px rgba(0,0,0,0.1)',
        ];
        $box_shadow = isset($shadow_values[$card_shadow]) ? $shadow_values[$card_shadow] : $shadow_values['medium'];

        // Afficher le CSS inline avec priorité maximale
        echo "\n<style id='ibs-custom-styles'>\n";
        echo "/* Ikomiris Booking System - Styles Personnalisés */\n\n";

        // Surcharger les variables CSS
        echo ":root {\n";
        echo "    --ibs-primary: {$primary_color} !important;\n";
        echo "    --ibs-primary-hover: {$button_hover_color} !important;\n";
        echo "    --ibs-text: {$text_color} !important;\n";
        echo "    --ibs-text-light: {$subtitle_color} !important;\n";
        echo "    --ibs-white: {$card_background} !important;\n";
        echo "    --ibs-section-bg: {$section_background} !important;\n";
        echo "    --ibs-radius: {$card_border_radius}px !important;\n";
        echo "    --ibs-shadow: {$box_shadow} !important;\n";
        echo "    --ibs-secondary: {$background_color} !important;\n";
        echo "}\n\n";

        // Conteneur principal
        echo ".ibs-booking-container {\n";
        echo "    background-color: {$background_color} !important;\n";
        echo "    color: {$text_color} !important;\n";
        echo "}\n\n";

        // Sections
        echo ".ibs-section {\n";
        echo "    background: {$section_background} !important;\n";
        echo "    border-radius: {$card_border_radius}px !important;\n";
        echo "    box-shadow: {$box_shadow} !important;\n";
        echo "}\n\n";

        // Titres
        echo ".ibs-section-title,\n";
        echo ".ibs-booking-container h1,\n";
        echo ".ibs-booking-container h2,\n";
        echo ".ibs-booking-container h3 {\n";
        echo "    color: {$title_color} !important;\n";
        echo "}\n\n";

        echo ".ibs-section-title {\n";
        echo "    border-bottom-color: {$primary_color} !important;\n";
        echo "}\n\n";

        // Cartes magasins
        echo ".ibs-store-card {\n";
        echo "    background: {$card_background} !important;\n";
        echo "    border-radius: {$card_border_radius}px !important;\n";
        echo "    box-shadow: {$box_shadow} !important;\n";
        echo "}\n\n";

        echo ".ibs-store-card:hover {\n";
        echo "    border-color: {$primary_color} !important;\n";
        echo "}\n\n";

        echo ".ibs-store-card.ibs-card-selected {\n";
        echo "    border-color: {$primary_color} !important;\n";
        echo "    border-width: 3px !important;\n";
        echo "}\n\n";

        echo ".ibs-store-name {\n";
        echo "    color: {$title_color} !important;\n";
        echo "}\n\n";

        echo ".ibs-store-address,\n";
        echo ".ibs-store-contact {\n";
        echo "    color: {$subtitle_color} !important;\n";
        echo "}\n\n";

        // Cartes services
        echo ".ibs-service-card {\n";
        echo "    background: {$card_background} !important;\n";
        echo "    border-radius: {$card_border_radius}px !important;\n";
        echo "    box-shadow: {$box_shadow} !important;\n";
        echo "}\n\n";

        echo ".ibs-service-card:hover {\n";
        echo "    border-color: {$primary_color} !important;\n";
        echo "}\n\n";

        echo ".ibs-service-card.ibs-card-selected {\n";
        echo "    border-color: {$primary_color} !important;\n";
        echo "    border-width: 3px !important;\n";
        echo "}\n\n";

        echo ".ibs-service-name {\n";
        echo "    color: {$title_color} !important;\n";
        echo "}\n\n";

        echo ".ibs-service-description {\n";
        echo "    color: {$subtitle_color} !important;\n";
        echo "}\n\n";

        echo ".ibs-service-duration {\n";
        echo "    color: {$primary_color} !important;\n";
        echo "}\n\n";

        echo ".ibs-service-price {\n";
        echo "    color: {$text_color} !important;\n";
        echo "}\n\n";

        // Bouton de date
        echo ".ibs-date-btn {\n";
        echo "    background: {$button_color} !important;\n";
        echo "    color: {$button_text_color} !important;\n";
        echo "    border-radius: {$button_border_radius}px !important;\n";
        echo "}\n\n";

        echo ".ibs-date-btn:hover:not(:disabled) {\n";
        echo "    background: {$button_hover_color} !important;\n";
        echo "}\n\n";

        // Selected date display
        echo ".ibs-selected-date-display {\n";
        echo "    color: {$primary_color} !important;\n";
        echo "    background: {$background_color} !important;\n";
        echo "    border-radius: {$card_border_radius}px !important;\n";
        echo "}\n\n";

        echo ".ibs-change-date-btn {\n";
        echo "    color: {$primary_color} !important;\n";
        echo "    border-color: {$primary_color} !important;\n";
        echo "    background: {$card_background} !important;\n";
        echo "}\n\n";

        echo ".ibs-change-date-btn:hover {\n";
        echo "    background: {$primary_color} !important;\n";
        echo "    color: {$button_text_color} !important;\n";
        echo "}\n\n";

        // Slots horaires
        echo ".ibs-slot-btn {\n";
        echo "    background: {$card_background} !important;\n";
        echo "    border-radius: {$card_border_radius}px !important;\n";
        echo "    color: {$text_color} !important;\n";
        echo "}\n\n";

        echo ".ibs-slot-btn:hover {\n";
        echo "    border-color: {$primary_color} !important;\n";
        echo "    background: {$primary_color} !important;\n";
        echo "    color: {$button_text_color} !important;\n";
        echo "}\n\n";

        echo ".ibs-slot-btn.ibs-slot-selected {\n";
        echo "    border-color: {$primary_color} !important;\n";
        echo "    border-width: 3px !important;\n";
        echo "    background: {$primary_color} !important;\n";
        echo "    color: {$button_text_color} !important;\n";
        echo "}\n\n";

        // Récapitulatif
        echo ".ibs-booking-summary {\n";
        echo "    background: {$background_color} !important;\n";
        echo "    border-radius: {$card_border_radius}px !important;\n";
        echo "}\n\n";

        echo ".ibs-booking-summary h3 {\n";
        echo "    color: {$title_color} !important;\n";
        echo "}\n\n";

        echo ".ibs-summary-label {\n";
        echo "    color: {$text_color} !important;\n";
        echo "}\n\n";

        echo ".ibs-summary-value {\n";
        echo "    color: {$subtitle_color} !important;\n";
        echo "}\n\n";

        // Formulaire - labels
        echo ".ibs-form-group label {\n";
        echo "    color: {$text_color} !important;\n";
        echo "}\n\n";

        // Formulaire - inputs
        echo ".ibs-form-group input,\n";
        echo ".ibs-form-group textarea {\n";
        echo "    border-radius: {$card_border_radius}px !important;\n";
        echo "}\n\n";

        echo ".ibs-form-group input:focus,\n";
        echo ".ibs-form-group textarea:focus {\n";
        echo "    border-color: {$primary_color} !important;\n";
        echo "}\n\n";

        // Bouton submit principal
        echo ".ibs-submit-btn {\n";
        echo "    background: {$button_color} !important;\n";
        echo "    color: {$button_text_color} !important;\n";
        echo "    border-radius: {$button_border_radius}px !important;\n";
        echo "}\n\n";

        echo ".ibs-submit-btn:hover {\n";
        echo "    background: {$button_hover_color} !important;\n";
        echo "}\n\n";

        // Bouton nouvelle réservation
        echo ".ibs-new-booking-btn {\n";
        echo "    background: {$button_color} !important;\n";
        echo "    color: {$button_text_color} !important;\n";
        echo "    border-radius: {$button_border_radius}px !important;\n";
        echo "}\n\n";

        echo ".ibs-new-booking-btn:hover {\n";
        echo "    background: {$button_hover_color} !important;\n";
        echo "}\n\n";

        // Textes confirmation
        echo ".ibs-confirmation-message p {\n";
        echo "    color: {$subtitle_color} !important;\n";
        echo "}\n\n";

        echo "</style>\n";
    }
}
