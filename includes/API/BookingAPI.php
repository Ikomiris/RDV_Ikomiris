<?php
namespace IBS\API;

if (!defined('ABSPATH')) {
    exit;
}

class BookingAPI {
    
    public function __construct() {
        // Actions AJAX pour les utilisateurs connectés et non connectés
        add_action('wp_ajax_ibs_get_stores', [$this, 'get_stores']);
        add_action('wp_ajax_nopriv_ibs_get_stores', [$this, 'get_stores']);
        
        add_action('wp_ajax_ibs_get_services', [$this, 'get_services']);
        add_action('wp_ajax_nopriv_ibs_get_services', [$this, 'get_services']);
        
        add_action('wp_ajax_ibs_get_available_slots', [$this, 'get_available_slots']);
        add_action('wp_ajax_nopriv_ibs_get_available_slots', [$this, 'get_available_slots']);
        
        add_action('wp_ajax_ibs_create_booking', [$this, 'create_booking']);
        add_action('wp_ajax_nopriv_ibs_create_booking', [$this, 'create_booking']);
        
        add_action('wp_ajax_ibs_cancel_booking', [$this, 'cancel_booking']);
        add_action('wp_ajax_nopriv_ibs_cancel_booking', [$this, 'cancel_booking']);
        
        // Actions AJAX pour l'admin
        add_action('wp_ajax_ibs_admin_save_store', [$this, 'admin_save_store']);
        add_action('wp_ajax_ibs_admin_delete_store', [$this, 'admin_delete_store']);
        add_action('wp_ajax_ibs_admin_save_service', [$this, 'admin_save_service']);
        add_action('wp_ajax_ibs_admin_delete_service', [$this, 'admin_delete_service']);
        add_action('wp_ajax_ibs_admin_save_schedule', [$this, 'admin_save_schedule']);
        add_action('wp_ajax_ibs_admin_delete_schedule', [$this, 'admin_delete_schedule']);
        add_action('wp_ajax_ibs_admin_save_exception', [$this, 'admin_save_exception']);
        add_action('wp_ajax_ibs_admin_delete_exception', [$this, 'admin_delete_exception']);
        add_action('wp_ajax_ibs_admin_update_booking_status', [$this, 'admin_update_booking_status']);
    }
    
    public function get_stores() {
        check_ajax_referer('ibs_frontend_nonce', 'nonce');
        
        global $wpdb;
        $table = $wpdb->prefix . 'ibs_stores';
        
        $stores = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY name ASC");
        
        wp_send_json_success($stores);
    }
    
    public function get_services() {
        check_ajax_referer('ibs_frontend_nonce', 'nonce');
        
        $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
        
        if (!$store_id) {
            wp_send_json_error(['message' => __('ID du magasin manquant', 'ikomiris-booking')]);
        }
        
        global $wpdb;
        $table_services = $wpdb->prefix . 'ibs_services';
        $table_store_services = $wpdb->prefix . 'ibs_store_services';
        
        $services = $wpdb->get_results($wpdb->prepare("
            SELECT s.* 
            FROM $table_services s
            INNER JOIN $table_store_services ss ON s.id = ss.service_id
            WHERE ss.store_id = %d AND s.is_active = 1
            ORDER BY s.display_order ASC, s.name ASC
        ", $store_id));
        
        wp_send_json_success($services);
    }
    
    public function get_available_slots() {
        check_ajax_referer('ibs_frontend_nonce', 'nonce');
        
        $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        
        if (!$store_id || !$service_id || !$date) {
            wp_send_json_error(['message' => __('Paramètres manquants', 'ikomiris-booking')]);
        }
        
        global $wpdb;
        
        // Récupérer la durée du service
        $service = $wpdb->get_row($wpdb->prepare("
            SELECT duration FROM {$wpdb->prefix}ibs_services WHERE id = %d
        ", $service_id));
        
        if (!$service) {
            wp_send_json_error(['message' => __('Service introuvable', 'ikomiris-booking')]);
        }
        
        $duration = intval($service->duration);
        
        // Récupérer les horaires du magasin pour ce jour
        $day_of_week = date('w', strtotime($date)); // 0 = dimanche, 6 = samedi
        
        $schedules = $wpdb->get_results($wpdb->prepare("
            SELECT time_start, time_end 
            FROM {$wpdb->prefix}ibs_schedules 
            WHERE store_id = %d AND day_of_week = %d AND is_active = 1
        ", $store_id, $day_of_week));
        
        if (empty($schedules)) {
            wp_send_json_success([]);
        }
        
        // Vérifier les exceptions
        $exception = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ibs_exceptions 
            WHERE store_id = %d AND exception_date = %s
        ", $store_id, $date));
        
        if ($exception) {
            if ($exception->exception_type === 'closed') {
                wp_send_json_success([]);
            } else {
                // Ouverture exceptionnelle
                $schedules = [(object)[
                    'time_start' => $exception->time_start,
                    'time_end' => $exception->time_end
                ]];
            }
        }
        
        // Récupérer les réservations existantes
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT booking_time, duration 
            FROM {$wpdb->prefix}ibs_bookings 
            WHERE store_id = %d AND booking_date = %s AND status IN ('pending', 'confirmed')
        ", $store_id, $date));
        
        // Générer les créneaux disponibles
        $slots = $this->generate_slots($schedules, $duration, $bookings);
        
        wp_send_json_success($slots);
    }
    
    private function generate_slots($schedules, $duration, $bookings) {
        $slots = [];
        
        foreach ($schedules as $schedule) {
            $start = strtotime($schedule->time_start);
            $end = strtotime($schedule->time_end);
            
            $current = $start;
            
            while ($current + ($duration * 60) <= $end) {
                $slot_time = date('H:i:s', $current);
                $slot_end = date('H:i:s', $current + ($duration * 60));
                
                // Vérifier si le créneau est disponible
                if ($this->is_slot_available($slot_time, $slot_end, $bookings)) {
                    $slots[] = date('H:i', $current);
                }
                
                // Incrémenter de 10 minutes
                $current += 600;
            }
        }
        
        return $slots;
    }
    
    private function is_slot_available($slot_start, $slot_end, $bookings) {
        foreach ($bookings as $booking) {
            $booking_start = $booking->booking_time;
            $booking_end = date('H:i:s', strtotime($booking->booking_time) + ($booking->duration * 60));
            
            // Vérifier le chevauchement
            if (
                ($slot_start >= $booking_start && $slot_start < $booking_end) ||
                ($slot_end > $booking_start && $slot_end <= $booking_end) ||
                ($slot_start <= $booking_start && $slot_end >= $booking_end)
            ) {
                return false;
            }
        }
        
        return true;
    }
    
    public function create_booking() {
        check_ajax_referer('ibs_frontend_nonce', 'nonce');
        
        global $wpdb;
        
        $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
        $firstname = isset($_POST['firstname']) ? sanitize_text_field($_POST['firstname']) : '';
        $lastname = isset($_POST['lastname']) ? sanitize_text_field($_POST['lastname']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        // Validation
        if (!$store_id || !$service_id || !$date || !$time || !$firstname || !$lastname || !$email || !$phone) {
            wp_send_json_error(['message' => __('Tous les champs sont obligatoires', 'ikomiris-booking')]);
        }
        
        if (!is_email($email)) {
            wp_send_json_error(['message' => __('Email invalide', 'ikomiris-booking')]);
        }
        
        // Récupérer la durée du service
        $service = $wpdb->get_row($wpdb->prepare("
            SELECT duration FROM {$wpdb->prefix}ibs_services WHERE id = %d
        ", $service_id));
        
        if (!$service) {
            wp_send_json_error(['message' => __('Service introuvable', 'ikomiris-booking')]);
        }
        
        // Générer un token d'annulation unique
        $cancel_token = bin2hex(random_bytes(32));
        
        // Insérer la réservation
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'ibs_bookings',
            [
                'store_id' => $store_id,
                'service_id' => $service_id,
                'booking_date' => $date,
                'booking_time' => $time,
                'duration' => $service->duration,
                'customer_firstname' => $firstname,
                'customer_lastname' => $lastname,
                'customer_email' => $email,
                'customer_phone' => $phone,
                'customer_message' => $message,
                'status' => 'confirmed',
                'cancel_token' => $cancel_token,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if (!$inserted) {
            wp_send_json_error(['message' => __('Erreur lors de la création de la réservation', 'ikomiris-booking')]);
        }
        
        $booking_id = $wpdb->insert_id;
        
        // Envoyer les emails de confirmation
        $this->send_confirmation_emails($booking_id);
        
        wp_send_json_success([
            'message' => __('Réservation confirmée !', 'ikomiris-booking'),
            'booking_id' => $booking_id
        ]);
    }
    
    private function send_confirmation_emails($booking_id) {
        // TODO: Implémenter l'envoi d'emails
        // Cela sera fait dans un fichier séparé pour gérer les templates d'emails
    }
    
    public function cancel_booking() {
        check_ajax_referer('ibs_frontend_nonce', 'nonce');
        
        global $wpdb;
        
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        
        if (!$token) {
            wp_send_json_error(['message' => __('Token manquant', 'ikomiris-booking')]);
        }
        
        $updated = $wpdb->update(
            $wpdb->prefix . 'ibs_bookings',
            ['status' => 'cancelled'],
            ['cancel_token' => $token],
            ['%s'],
            ['%s']
        );
        
        if (!$updated) {
            wp_send_json_error(['message' => __('Réservation introuvable', 'ikomiris-booking')]);
        }
        
        wp_send_json_success(['message' => __('Réservation annulée', 'ikomiris-booking')]);
    }
    
    // Méthodes admin (à compléter)
    public function admin_save_store() {
        check_ajax_referer('ibs_admin_nonce', 'nonce');
        // TODO
    }
    
    public function admin_delete_store() {
        check_ajax_referer('ibs_admin_nonce', 'nonce');
        // TODO
    }
    
    public function admin_save_service() {
        check_ajax_referer('ibs_admin_nonce', 'nonce');
        // TODO
    }
    
    public function admin_delete_service() {
        check_ajax_referer('ibs_admin_nonce', 'nonce');
        // TODO
    }
    
    public function admin_save_schedule() {
        check_ajax_referer('ibs_admin_nonce', 'nonce');
        // TODO
    }
    
    public function admin_delete_schedule() {
        check_ajax_referer('ibs_admin_nonce', 'nonce');
        // TODO
    }
    
    public function admin_save_exception() {
        check_ajax_referer('ibs_admin_nonce', 'nonce');
        // TODO
    }
    
    public function admin_delete_exception() {
        check_ajax_referer('ibs_admin_nonce', 'nonce');
        // TODO
    }
    
    public function admin_update_booking_status() {
        check_ajax_referer('ibs_admin_nonce', 'nonce');
        // TODO
    }
}
