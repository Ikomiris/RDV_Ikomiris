<?php
namespace IBS\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class AdminMenu {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_ibs_delete_booking', [$this, 'handle_delete_booking']);
        add_action('wp_ajax_ibs_test_twilio_connection', [$this, 'test_twilio_connection']);
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
        
        // Sous-menu : Style
        add_submenu_page(
            'ikomiris-booking',
            __('Style', 'ikomiris-booking'),
            __('Style', 'ikomiris-booking'),
            'manage_options',
            'ikomiris-booking-style',
            [$this, 'render_style_page']
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

        // Sous-menu : Emails
        add_submenu_page(
            'ikomiris-booking',
            __('Emails', 'ikomiris-booking'),
            __('Emails', 'ikomiris-booking'),
            'manage_options',
            'ikomiris-booking-emails',
            [$this, 'render_emails_page']
        );

        // Sous-menu : WhatsApp
        add_submenu_page(
            'ikomiris-booking',
            __('WhatsApp', 'ikomiris-booking'),
            __('WhatsApp', 'ikomiris-booking'),
            'manage_options',
            'ikomiris-booking-whatsapp',
            [$this, 'render_whatsapp_page']
        );

        // Sous-menu : Test Google Calendar
        add_submenu_page(
            'ikomiris-booking',
            __('Test Google Calendar', 'ikomiris-booking'),
            __('Test Google Calendar', 'ikomiris-booking'),
            'manage_options',
            'ikomiris-booking-google-test',
            [$this, 'render_google_test_page']
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

    public function render_style_page() {
        require_once IBS_PLUGIN_DIR . 'admin/views/style.php';
    }

    public function render_settings_page() {
        require_once IBS_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function render_emails_page() {
        require_once IBS_PLUGIN_DIR . 'admin/views/email-customization.php';
    }

    public function render_whatsapp_page() {
        require_once IBS_PLUGIN_DIR . 'admin/views/whatsapp-settings.php';
    }

    public function render_google_test_page() {
        require_once IBS_PLUGIN_DIR . 'admin/views/google-test.php';
    }

    public function handle_delete_booking() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Vous n’avez pas les permissions nécessaires.', 'ikomiris-booking'));
        }

        $booking_id = isset($_GET['booking_id']) ? absint($_GET['booking_id']) : 0;
        if (!$booking_id) {
            $this->redirect_after_delete(false);
        }

        check_admin_referer('ibs_delete_booking_' . $booking_id);

        global $wpdb;
        $table_bookings = $wpdb->prefix . 'ibs_bookings';

        $deleted = (bool) $wpdb->delete($table_bookings, ['id' => $booking_id], ['%d']);

        $this->redirect_after_delete($deleted);
    }

    private function redirect_after_delete($success) {
        $redirect_url = wp_get_referer();
        if (!$redirect_url) {
            $redirect_url = admin_url('admin.php?page=ikomiris-booking');
        }

        $redirect_url = add_query_arg('ibs_booking_deleted', $success ? '1' : '0', $redirect_url);
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Test de connexion Twilio via AJAX
     */
    public function test_twilio_connection() {
        check_ajax_referer('ibs_test_twilio', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
        }

        $account_sid = isset($_POST['account_sid']) ? sanitize_text_field($_POST['account_sid']) : '';
        $auth_token = isset($_POST['auth_token']) ? sanitize_text_field($_POST['auth_token']) : '';

        if (empty($account_sid) || empty($auth_token)) {
            wp_send_json_error('Account SID et Auth Token requis');
        }

        // Test de l'API Twilio - recuperer les informations du compte
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $account_sid . '.json';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $account_sid . ':' . $auth_token);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            wp_send_json_error('Erreur cURL : ' . $error);
        }

        if ($http_code === 200) {
            $data = json_decode($response, true);
            wp_send_json_success('Compte Twilio : ' . ($data['friendly_name'] ?? 'OK'));
        } elseif ($http_code === 401) {
            wp_send_json_error('Identifiants invalides');
        } else {
            wp_send_json_error('Erreur HTTP ' . $http_code);
        }
    }
}
