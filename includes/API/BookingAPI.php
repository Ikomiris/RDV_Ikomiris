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

        // Récupérer les événements Google Calendar du magasin
        $google_bookings = $this->get_google_calendar_bookings($store_id, $date);

        // Fusionner les réservations WordPress et Google Calendar
        $all_bookings = array_merge($bookings, $google_bookings);

        // Générer les créneaux disponibles (avec filtre min_booking_delay)
        $slots = $this->generate_slots($schedules, $duration, $all_bookings, $date);

        wp_send_json_success($slots);
    }
    
    private function generate_slots($schedules, $duration, $bookings, $date = null) {
        $slots = [];

        // Récupérer le délai minimum de réservation
        $min_booking_delay = intval($this->get_setting('min_booking_delay', 2));
        $now = current_time('timestamp');
        $min_allowed_datetime = $now + ($min_booking_delay * 3600);

        foreach ($schedules as $schedule) {
            $start = strtotime($schedule->time_start);
            $end = strtotime($schedule->time_end);

            $current = $start;

            while ($current + ($duration * 60) <= $end) {
                $slot_time = date('H:i:s', $current);
                $slot_end = date('H:i:s', $current + ($duration * 60));

                // Si une date est fournie, vérifier le délai minimum
                if ($date) {
                    $slot_datetime = strtotime($date . ' ' . $slot_time);

                    // Ignorer les créneaux trop proches dans le temps
                    if ($slot_datetime < $min_allowed_datetime) {
                        $current += 600;
                        continue;
                    }
                }

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
    
    private function time_to_minutes($time) {
        if (empty($time)) {
            return 0;
        }
        
        $parts = explode(':', $time);
        $hours = isset($parts[0]) ? intval($parts[0]) : 0;
        $minutes = isset($parts[1]) ? intval($parts[1]) : 0;
        $seconds = isset($parts[2]) ? intval($parts[2]) : 0;
        
        return ($hours * 60) + $minutes + intval(floor($seconds / 60));
    }

    private function debug_log($message) {
        if (!defined('WP_CONTENT_DIR')) {
            return;
        }
        
        $path = WP_CONTENT_DIR . '/ibs-booking-debug.log';
        $line = '[' . gmdate('c') . '] ' . $message . PHP_EOL;
        error_log($line, 3, $path);
    }

    private function is_offset_timezone_string($timezone_name) {
        if (empty($timezone_name)) {
            return true;
        }

        if ($timezone_name === 'UTC') {
            return true;
        }

        if (preg_match('/^UTC[+-]\d{1,2}(:\d{2})?$/', $timezone_name)) {
            return true;
        }

        if (preg_match('/^[+-]\d{2}:\d{2}$/', $timezone_name)) {
            return true;
        }

        return false;
    }

    /**
     * Détermine le meilleur timezone pour la synchronisation Google Calendar
     * Priorité: Calendrier Google > WordPress (si valide) > UTC
     *
     * @param \IBS\Integrations\GoogleCalendar $google Instance Google Calendar
     * @param string $calendar_id ID du calendrier Google
     * @return \DateTimeZone Le timezone à utiliser
     */
    private function get_best_timezone_for_sync($google, $calendar_id) {
        // 1. Essayer de récupérer le timezone du calendrier Google (le plus fiable)
        $calendar_timezone = $google->get_calendar_timezone_for_id($calendar_id);
        if (!empty($calendar_timezone)) {
            try {
                $tz = new \DateTimeZone($calendar_timezone);
                error_log('IBS: Utilisation du timezone du calendrier Google - ' . $calendar_timezone);
                return $tz;
            } catch (\Exception $e) {
                error_log('IBS: Timezone calendrier Google invalide (' . $calendar_timezone . ') - ' . $e->getMessage());
            }
        }

        // 2. Récupérer le timezone WordPress
        $wp_timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $wp_timezone_name = $wp_timezone->getName();

        // 3. Vérifier si WordPress utilise un offset fixe (problématique car ne gère pas été/hiver)
        if ($this->is_offset_timezone_string($wp_timezone_name)) {
            error_log('IBS: ATTENTION - WordPress utilise un offset fixe (' . $wp_timezone_name . ') au lieu d\'un timezone nommé. Cela peut causer des décalages horaires!');
            error_log('IBS: Recommandation - Configurez un timezone nommé dans Réglages > Généraux (ex: Europe/Paris)');

            // Essayer de deviner le timezone approprié basé sur l'offset (solution de secours)
            $guessed_timezone = $this->guess_timezone_from_offset($wp_timezone_name);
            if ($guessed_timezone) {
                error_log('IBS: Utilisation du timezone deviné - ' . $guessed_timezone->getName());
                return $guessed_timezone;
            }

            // Si on ne peut pas deviner, utiliser UTC pour éviter les erreurs
            error_log('IBS: Utilisation de UTC comme fallback sûr');
            return new \DateTimeZone('UTC');
        }

        // 4. WordPress utilise un timezone nommé valide - l'utiliser
        error_log('IBS: Utilisation du timezone WordPress - ' . $wp_timezone_name);
        return $wp_timezone;
    }

    /**
     * Essaie de deviner un timezone nommé approprié basé sur un offset
     *
     * @param string $offset Offset comme '+02:00' ou 'UTC+2'
     * @return \DateTimeZone|null Timezone deviné ou null si impossible
     */
    private function guess_timezone_from_offset($offset) {
        // Extraire le nombre d'heures de l'offset
        if (preg_match('/([+-])(\d{1,2})/', $offset, $matches)) {
            $sign = $matches[1];
            $hours = intval($matches[2]);
            $offset_seconds = ($sign === '+' ? 1 : -1) * $hours * 3600;

            // Carte des offsets communs vers des timezones (Europe principalement)
            // Note: Ces timezones gèrent automatiquement le changement d'heure été/hiver
            $offset_to_timezone = [
                0 => 'Europe/London',      // UTC+0 (hiver) / UTC+1 (été)
                3600 => 'Europe/Paris',    // UTC+1 (hiver) / UTC+2 (été)
                7200 => 'Europe/Athens',   // UTC+2 (hiver) / UTC+3 (été)
                10800 => 'Europe/Moscow',  // UTC+3
                -18000 => 'America/New_York', // UTC-5 (hiver) / UTC-4 (été)
                -21600 => 'America/Chicago',  // UTC-6 (hiver) / UTC-5 (été)
                -25200 => 'America/Denver',   // UTC-7 (hiver) / UTC-6 (été)
                -28800 => 'America/Los_Angeles', // UTC-8 (hiver) / UTC-7 (été)
            ];

            // Chercher un timezone correspondant
            if (isset($offset_to_timezone[$offset_seconds])) {
                $timezone_name = $offset_to_timezone[$offset_seconds];
                try {
                    return new \DateTimeZone($timezone_name);
                } catch (\Exception $e) {
                    error_log('IBS: Impossible de créer le timezone deviné - ' . $e->getMessage());
                }
            }
        }

        return null;
    }
    
    private function is_slot_available($slot_start, $slot_end, $bookings) {
        $slot_start_min = $this->time_to_minutes($slot_start);
        $slot_end_min = $this->time_to_minutes($slot_end);
        
        foreach ($bookings as $booking) {
            $booking_start_min = $this->time_to_minutes($booking->booking_time);
            $duration = isset($booking->duration) ? intval($booking->duration) : 0;
            $booking_end_min = $booking_start_min + $duration;
            
            // Vérifier le chevauchement
            if ($slot_start_min < $booking_end_min && $slot_end_min > $booking_start_min) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Récupère les événements Google Calendar pour un magasin et une date
     * 
     * @param int $store_id ID du magasin
     * @param string $date Date au format Y-m-d
     * @return array Tableau d'objets avec booking_time et duration
     */
    private function get_google_calendar_bookings($store_id, $date) {
        // Récupérer le google_calendar_id du magasin
        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT google_calendar_id FROM {$wpdb->prefix}ibs_stores WHERE id = %d
        ", $store_id));
        
        if (!$store || empty($store->google_calendar_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('IBS Google Calendar: Aucun calendar_id pour store_id=' . $store_id);
            }
            $this->debug_log('store_id=' . $store_id . ' sans calendar_id');
            return [];
        }
        
        // Initialiser Google Calendar et récupérer les événements
        $google = new \IBS\Integrations\GoogleCalendar();
        
        if (!$google->is_configured()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('IBS Google Calendar: Non configuré lors du fetch (store_id=' . $store_id . ')');
            }
            $this->debug_log('store_id=' . $store_id . ' google non configure');
            return [];
        }
        
        try {
            $events = $google->get_events_for_date($store->google_calendar_id, $date);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('IBS Google Calendar: store_id=' . $store_id . ', date=' . $date . ', events=' . count($events));
            }
            $this->debug_log('store_id=' . $store_id . ', date=' . $date . ', events=' . count($events));
            return $events;
        } catch (\Exception $e) {
            error_log('IBS: Erreur lors de la récupération des événements Google Calendar - ' . $e->getMessage());
            $this->debug_log('store_id=' . $store_id . ', exception=' . $e->getMessage());
            return [];
        }
    }
    
    public function create_booking() {
        check_ajax_referer('ibs_frontend_nonce', 'nonce');

        // Vérifier le rate limiting
        $rate_limiter = new \IBS\Security\RateLimiter();
        $fingerprint = $rate_limiter->get_client_fingerprint();

        // Vérifier si l'identifiant est bloqué
        if ($rate_limiter->is_blocked($fingerprint)) {
            wp_send_json_error([
                'message' => __('Votre accès a été temporairement bloqué. Veuillez réessayer plus tard.', 'ikomiris-booking')
            ]);
        }

        // Vérifier les limites de taux pour les réservations
        $rate_check = $rate_limiter->check_booking_rate_limit();

        if (!$rate_check['allowed']) {
            $message = $rate_limiter->get_rate_limit_message($rate_check);
            wp_send_json_error(['message' => $message]);
        }

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
        $gift_card_code = isset($_POST['gift_card_code']) ? sanitize_text_field($_POST['gift_card_code']) : '';
        $has_gift_card = isset($_POST['has_gift_card']) ? sanitize_text_field($_POST['has_gift_card']) : '0';
        $age_confirm = isset($_POST['age_confirm']) ? sanitize_text_field($_POST['age_confirm']) : '0';
        $terms = isset($_POST['terms']) ? sanitize_text_field($_POST['terms']) : '0';
        if (!empty($gift_card_code)) {
            $gift_card_code = substr($gift_card_code, 0, 100);
        }

        // Validation des champs obligatoires
        if (!$store_id || !$service_id || !$date || !$time || !$firstname || !$lastname || !$email || !$phone) {
            wp_send_json_error(['message' => __('Tous les champs sont obligatoires', 'ikomiris-booking')]);
        }

        if (!is_email($email)) {
            wp_send_json_error(['message' => __('Email invalide', 'ikomiris-booking')]);
        }

        if ($age_confirm !== '1') {
            wp_send_json_error(['message' => __('Veuillez confirmer que toutes les personnes photographiées ont au moins 6 ans.', 'ikomiris-booking')]);
        }

        if ($terms !== '1') {
            wp_send_json_error(['message' => __('Veuillez accepter les conditions générales d\'utilisation.', 'ikomiris-booking')]);
        }

        if ($has_gift_card === '1' && empty($gift_card_code)) {
            wp_send_json_error(['message' => __('Veuillez saisir le code de votre carte cadeau.', 'ikomiris-booking')]);
        }

        // Validation du délai minimum de réservation
        $min_booking_delay = intval($this->get_setting('min_booking_delay', 2));
        $booking_datetime = strtotime($date . ' ' . $time);
        $now = current_time('timestamp');
        $min_allowed_datetime = $now + ($min_booking_delay * 3600); // Convertir heures en secondes

        if ($booking_datetime < $min_allowed_datetime) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Vous devez réserver au moins %d heures à l\'avance.', 'ikomiris-booking'),
                    $min_booking_delay
                )
            ]);
        }

        // Validation du délai maximum de réservation
        $max_booking_delay = intval($this->get_setting('max_booking_delay', 90));
        $max_allowed_datetime = $now + ($max_booking_delay * 86400); // Convertir jours en secondes

        if ($booking_datetime > $max_allowed_datetime) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Vous ne pouvez pas réserver plus de %d jours à l\'avance.', 'ikomiris-booking'),
                    $max_booking_delay
                )
            ]);
        }

        // Vérifier que le créneau est toujours disponible
        if (!$this->verify_slot_availability($store_id, $service_id, $date, $time)) {
            wp_send_json_error(['message' => __('Ce créneau n\'est plus disponible. Veuillez en choisir un autre.', 'ikomiris-booking')]);
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
                'customer_gift_card_code' => $gift_card_code,
                'status' => 'confirmed',
                'cancel_token' => $cancel_token,
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if (!$inserted) {
            wp_send_json_error(['message' => __('Erreur lors de la création de la réservation', 'ikomiris-booking')]);
        }
        
        $booking_id = $wpdb->insert_id;
        
        // Synchroniser avec Google Calendar
        $this->sync_to_google_calendar($booking_id, $store_id, $service_id, $date, $time, $firstname, $lastname, $email, $phone, $message, $gift_card_code, $service->duration);
        
        // Envoyer les emails de confirmation
        $this->send_confirmation_emails($booking_id);

        // Envoyer les notifications WhatsApp
        $this->send_whatsapp_notifications($booking_id);

        // Envoyer les informations du client au CRM
        $this->send_to_crm($store_id, $firstname, $lastname, $email, $phone);

        wp_send_json_success([
            'message' => __('Réservation confirmée !', 'ikomiris-booking'),
            'booking_id' => $booking_id
        ]);
    }
    
    /**
     * Synchronise une réservation avec Google Calendar
     * 
     * @param int $booking_id ID de la réservation
     * @param int $store_id ID du magasin
     * @param int $service_id ID du service
     * @param string $date Date de réservation (Y-m-d)
     * @param string $time Heure de réservation (H:i:s)
     * @param string $firstname Prénom du client
     * @param string $lastname Nom du client
     * @param string $email Email du client
     * @param string $phone Téléphone du client
     * @param string $message Message du client
     * @param string $gift_card_code Code carte cadeau
     * @param int $duration Durée du service en minutes
     */
    private function sync_to_google_calendar($booking_id, $store_id, $service_id, $date, $time, $firstname, $lastname, $email, $phone, $message, $gift_card_code, $duration) {
        global $wpdb;

        // Récupérer le google_calendar_id du magasin
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT google_calendar_id, name FROM {$wpdb->prefix}ibs_stores WHERE id = %d
        ", $store_id));

        // Vérifier si le magasin a un calendrier Google associé
        if (!$store || empty($store->google_calendar_id)) {
            error_log('IBS: Magasin #' . $store_id . ' sans google_calendar_id - pas de synchronisation Google');
            return;
        }

        // Récupérer le nom du service
        $service = $wpdb->get_row($wpdb->prepare("
            SELECT name, color FROM {$wpdb->prefix}ibs_services WHERE id = %d
        ", $service_id));

        // Initialiser Google Calendar
        $google = new \IBS\Integrations\GoogleCalendar();

        if (!$google->is_configured()) {
            error_log('IBS: Google Calendar non configuré - pas de synchronisation');
            return;
        }

        // Déterminer le timezone à utiliser (priorité: calendrier Google > WordPress > UTC)
        $timezone = $this->get_best_timezone_for_sync($google, $store->google_calendar_id);
        $timezone_name = $timezone->getName();

        // Créer les objets DateTime dans le timezone déterminé
        // IMPORTANT: on travaille avec l'heure LOCALE (telle que saisie par l'utilisateur)
        $start_datetime = new \DateTime($date . ' ' . $time, $timezone);
        $end_datetime = clone $start_datetime;
        $end_datetime->modify('+' . intval($duration) . ' minutes');

        // Log avant conversion pour debug
        error_log('IBS: Réservation locale - Date: ' . $date . ' ' . $time . ', Timezone: ' . $timezone_name);
        error_log('IBS: DateTime local - Start: ' . $start_datetime->format('Y-m-d H:i:s T (P)') . ', End: ' . $end_datetime->format('Y-m-d H:i:s T (P)'));

        // Convertir en UTC pour Google Calendar (format RFC3339 avec Z)
        $start_datetime->setTimezone(new \DateTimeZone('UTC'));
        $end_datetime->setTimezone(new \DateTimeZone('UTC'));

        // Log après conversion UTC
        error_log('IBS: DateTime UTC - Start: ' . $start_datetime->format('Y-m-d H:i:s T (P)') . ', End: ' . $end_datetime->format('Y-m-d H:i:s T (P)'));

        // Formater en RFC3339 UTC (avec Z à la fin)
        $start_formatted = $start_datetime->format('Y-m-d\TH:i:s\Z');
        $end_formatted = $end_datetime->format('Y-m-d\TH:i:s\Z');

        // Préparer les données de l'événement
        $cancel_token = $wpdb->get_var($wpdb->prepare("
            SELECT cancel_token FROM {$wpdb->prefix}ibs_bookings WHERE id = %d
        ", $booking_id));
        $cancel_url = $cancel_token ? home_url('/reservation-annulation/?token=' . $cancel_token) : '';
        $cancel_line = $cancel_url ? "Lien d'annulation : " . $cancel_url . "\n" : '';
        $gift_card_line = $gift_card_code ? "Carte cadeau : " . $gift_card_code . "\n" : '';
        $message_block = $message ? "Message :\n" . $message : '';

        $event_data = [
            'summary' => $firstname . ' ' . $lastname . ' - ' . ($service ? $service->name : 'Réservation'),
            'description' =>
                "Réservation Ikomiris Booking System\n\n" .
                "Client : " . $firstname . " " . $lastname . "\n" .
                "Email : " . $email . "\n" .
                "Téléphone : " . $phone . "\n" .
                $gift_card_line .
                $cancel_line .
                "Magasin : " . $store->name . "\n\n" .
                $message_block,
            'start' => $start_formatted,
            'end' => $end_formatted,
        ];

        if ($service && !empty($service->color)) {
            $event_data['color'] = $service->color;
            error_log('IBS: Service #' . $service_id . ' couleur = ' . $service->color);
        } else {
            error_log('IBS: Service #' . $service_id . ' sans couleur définie (service=' . ($service ? 'trouvé' : 'null') . ', color=' . ($service ? $service->color : 'n/a') . ')');
        }

        // Créer l'événement dans Google Calendar
        $event_id = $google->create_event($store->google_calendar_id, $event_data);
        
        if ($event_id) {
            // Sauvegarder l'event_id dans la réservation
            $wpdb->update(
                $wpdb->prefix . 'ibs_bookings',
                ['google_event_id' => $event_id],
                ['id' => $booking_id],
                ['%s'],
                ['%d']
            );
            
            error_log('IBS: Réservation #' . $booking_id . ' synchronisée avec Google Calendar (event_id: ' . $event_id . ')');
        } else {
            error_log('IBS: Échec de la synchronisation de la réservation #' . $booking_id . ' avec Google Calendar');
        }
    }
    
    /**
     * Envoie les emails de confirmation (client + admin)
     *
     * @param int $booking_id ID de la réservation
     * @return void
     */
    private function send_confirmation_emails($booking_id) {
        $email_handler = new \IBS\Email\EmailHandler();

        $send_customer = $this->get_setting('email_customer_confirmation', '1') === '1';
        $send_admin = $this->get_setting('email_admin_notification', '1') === '1';

        // Envoyer l'email au client
        $customer_sent = $send_customer ? $email_handler->send_customer_confirmation($booking_id) : null;

        // Envoyer l'email à l'admin du magasin
        $admin_sent = $send_admin ? $email_handler->send_admin_notification($booking_id) : null;

        if ($send_customer || $send_admin) {
            $customer_ok = $send_customer ? $customer_sent : true;
            $admin_ok = $send_admin ? $admin_sent : true;

            if ($customer_ok && $admin_ok) {
                error_log('IBS: Emails de confirmation envoyés avec succès pour réservation #' . $booking_id);
            } else {
                if ($send_customer && !$customer_sent) {
                    error_log('IBS: Échec de l\'envoi de l\'email client pour réservation #' . $booking_id);
                }
                if ($send_admin && !$admin_sent) {
                    error_log('IBS: Échec de l\'envoi de l\'email admin pour réservation #' . $booking_id);
                }
            }
        }
    }

    /**
     * Envoie les notifications WhatsApp via Twilio
     *
     * @param int $booking_id ID de la réservation
     * @return void
     */
    private function send_whatsapp_notifications($booking_id) {
        $whatsapp = new \IBS\Integrations\WhatsAppHandler();

        if (!$whatsapp->is_configured()) {
            return;
        }

        // Envoyer la confirmation WhatsApp au client
        $sent = $whatsapp->send_customer_confirmation($booking_id);

        if ($sent) {
            error_log('IBS: Notification WhatsApp envoyée avec succès pour réservation #' . $booking_id);
        } else {
            error_log('IBS: Échec de l\'envoi de la notification WhatsApp pour réservation #' . $booking_id);
        }
    }

    /**
     * Envoie les informations du client au CRM
     *
     * @param int $store_id ID du magasin
     * @param string $firstname Prénom du client
     * @param string $lastname Nom du client
     * @param string $email Email du client
     * @param string $phone Téléphone du client
     * @return void
     */
    private function send_to_crm($store_id, $firstname, $lastname, $email, $phone) {
        $crm = new \IBS\Integrations\CRM();

        $customer_data = [
            'first_name' => $firstname,
            'last_name' => $lastname,
            'email' => $email,
            'phone' => $phone
        ];

        $result = $crm->send_customer_to_crm($store_id, $customer_data);

        if ($result) {
            error_log('IBS: Client envoyé au CRM avec succès');
        } else {
            error_log('IBS: Le client n\'a pas été envoyé au CRM (non configuré ou erreur)');
        }
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

    /**
     * Récupère un paramètre depuis la table ibs_settings
     *
     * @param string $key Clé du paramètre
     * @param mixed $default Valeur par défaut
     * @return mixed Valeur du paramètre
     */
    private function get_setting($key, $default = '') {
        global $wpdb;

        $value = $wpdb->get_var($wpdb->prepare("
            SELECT setting_value FROM {$wpdb->prefix}ibs_settings
            WHERE setting_key = %s
        ", $key));

        return $value !== null ? $value : $default;
    }

    /**
     * Vérifie qu'un créneau est toujours disponible avant de créer la réservation
     *
     * @param int $store_id ID du magasin
     * @param int $service_id ID du service
     * @param string $date Date de réservation (Y-m-d)
     * @param string $time Heure de réservation (H:i)
     * @return bool True si disponible, False sinon
     */
    private function verify_slot_availability($store_id, $service_id, $date, $time) {
        global $wpdb;

        // Récupérer la durée du service
        $service = $wpdb->get_row($wpdb->prepare("
            SELECT duration FROM {$wpdb->prefix}ibs_services WHERE id = %d
        ", $service_id));

        if (!$service) {
            return false;
        }

        $duration = intval($service->duration);
        $time_with_seconds = strlen($time) === 5 ? $time . ':00' : $time;

        // Récupérer les réservations existantes
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT booking_time, duration
            FROM {$wpdb->prefix}ibs_bookings
            WHERE store_id = %d AND booking_date = %s AND status IN ('pending', 'confirmed')
        ", $store_id, $date));

        // Récupérer les événements Google Calendar
        $google_bookings = $this->get_google_calendar_bookings($store_id, $date);

        // Fusionner
        $all_bookings = array_merge($bookings, $google_bookings);

        // Calculer les minutes du créneau demandé
        $slot_start_min = $this->time_to_minutes($time_with_seconds);
        $slot_end_min = $slot_start_min + $duration;

        // Vérifier qu'il n'y a pas de chevauchement
        foreach ($all_bookings as $booking) {
            $booking_start_min = $this->time_to_minutes($booking->booking_time);
            $booking_duration = isset($booking->duration) ? intval($booking->duration) : 0;
            $booking_end_min = $booking_start_min + $booking_duration;

            // Chevauchement détecté
            if ($slot_start_min < $booking_end_min && $slot_end_min > $booking_start_min) {
                return false;
            }
        }

        return true;
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
