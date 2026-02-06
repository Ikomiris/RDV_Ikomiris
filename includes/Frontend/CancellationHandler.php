<?php
namespace IBS\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

class CancellationHandler {

    public function __construct() {
        add_action('init', [$this, 'handle_cancellation_request']);
        add_action('template_redirect', [$this, 'handle_cancellation_page']);
        add_action('wp_ajax_ibs_cancel_booking', [$this, 'process_cancellation_ajax']);
        add_action('wp_ajax_nopriv_ibs_cancel_booking', [$this, 'process_cancellation_ajax']);
    }

    /**
     * Gère la requête d'annulation via URL
     */
    public function handle_cancellation_request() {
        // Vérifier si c'est une requête d'annulation AJAX
        if (isset($_POST['action']) && $_POST['action'] === 'ibs_cancel_booking') {
            $this->process_cancellation_ajax();
        }
    }

    /**
     * Traite l'annulation en AJAX
     */
    public function process_cancellation_ajax() {
        // Vérifier le nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ibs_cancel_booking_nonce')) {
            wp_send_json_error(['message' => __('Sécurité : requête invalide', 'ikomiris-booking')]);
            return;
        }

        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';

        if (empty($token)) {
            wp_send_json_error(['message' => __('Token d\'annulation manquant', 'ikomiris-booking')]);
            return;
        }

        $result = $this->cancel_booking($token);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Gère l'affichage de la page d'annulation
     */
    public function handle_cancellation_page() {
        // Vérifier si on est sur la page d'annulation
        if (!is_page('reservation-annulation')) {
            return;
        }

        // Récupérer le token depuis l'URL
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        if (empty($token)) {
            return;
        }

        // Charger le template d'annulation
        $this->load_cancellation_template($token);
        exit;
    }

    /**
     * Annule une réservation
     *
     * @param string $token Token d'annulation
     * @return array Résultat de l'annulation
     */
    public function cancel_booking($token) {
        global $wpdb;

        // Récupérer la réservation par token
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT b.*,
                   s.name as service_name,
                   st.name as store_name,
                   st.google_calendar_id,
                   st.cancellation_hours
            FROM {$wpdb->prefix}ibs_bookings b
            LEFT JOIN {$wpdb->prefix}ibs_services s ON b.service_id = s.id
            LEFT JOIN {$wpdb->prefix}ibs_stores st ON b.store_id = st.id
            WHERE b.cancel_token = %s
        ", $token));

        if (!$booking) {
            return [
                'success' => false,
                'message' => __('Réservation introuvable. Le lien d\'annulation est peut-être invalide.', 'ikomiris-booking')
            ];
        }

        // Vérifier si la réservation est déjà annulée
        if ($booking->status === 'cancelled') {
            return [
                'success' => false,
                'message' => __('Cette réservation a déjà été annulée.', 'ikomiris-booking'),
                'already_cancelled' => true,
                'cancelled_at' => $booking->cancelled_at
            ];
        }

        // Vérifier si la réservation est déjà passée
        $booking_datetime = strtotime($booking->booking_date . ' ' . $booking->booking_time);
        $now = current_time('timestamp');

        if ($booking_datetime < $now) {
            return [
                'success' => false,
                'message' => __('Impossible d\'annuler une réservation passée.', 'ikomiris-booking')
            ];
        }

        // Vérifier le délai d'annulation
        $cancellation_hours = !empty($booking->cancellation_hours) ? intval($booking->cancellation_hours) : 24;
        $cancellation_deadline = $booking_datetime - ($cancellation_hours * 3600);

        if ($now > $cancellation_deadline) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Le délai d\'annulation est dépassé. Vous devez annuler au moins %d heures avant votre rendez-vous.', 'ikomiris-booking'),
                    $cancellation_hours
                )
            ];
        }

        // Mettre à jour le statut de la réservation
        $updated = $wpdb->update(
            $wpdb->prefix . 'ibs_bookings',
            [
                'status' => 'cancelled',
                'cancelled_at' => current_time('mysql')
            ],
            ['id' => $booking->id],
            ['%s', '%s'],
            ['%d']
        );

        if (!$updated) {
            error_log('IBS: Échec de la mise à jour du statut de la réservation #' . $booking->id);
            return [
                'success' => false,
                'message' => __('Erreur lors de l\'annulation de la réservation. Veuillez réessayer.', 'ikomiris-booking')
            ];
        }

        // Supprimer l'événement de Google Calendar
        if (!empty($booking->google_event_id) && !empty($booking->google_calendar_id)) {
            $this->delete_google_calendar_event($booking->google_calendar_id, $booking->google_event_id);
        }

        // Envoyer les emails de notification
        $this->send_cancellation_emails($booking->id);

        // Envoyer la notification WhatsApp
        $this->send_cancellation_whatsapp($booking->id);

        error_log('IBS: Réservation #' . $booking->id . ' annulée avec succès par le client');

        return [
            'success' => true,
            'message' => __('Votre réservation a été annulée avec succès.', 'ikomiris-booking'),
            'booking_id' => $booking->id,
            'booking_date' => $booking->booking_date,
            'booking_time' => $booking->booking_time,
            'service_name' => $booking->service_name,
            'store_name' => $booking->store_name
        ];
    }

    /**
     * Supprime un événement de Google Calendar
     *
     * @param string $calendar_id ID du calendrier Google
     * @param string $event_id ID de l'événement Google
     * @return bool Succès ou échec
     */
    private function delete_google_calendar_event($calendar_id, $event_id) {
        try {
            $google = new \IBS\Integrations\GoogleCalendar();

            if (!$google->is_configured()) {
                error_log('IBS: Google Calendar non configuré - impossible de supprimer l\'événement');
                return false;
            }

            $deleted = $google->delete_event($calendar_id, $event_id);

            if ($deleted) {
                error_log('IBS: Événement Google Calendar supprimé avec succès (event_id: ' . $event_id . ')');
                return true;
            } else {
                error_log('IBS: Échec de la suppression de l\'événement Google Calendar (event_id: ' . $event_id . ')');
                return false;
            }
        } catch (\Exception $e) {
            error_log('IBS: Exception lors de la suppression de l\'événement Google Calendar: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie les emails de notification d'annulation
     *
     * @param int $booking_id ID de la réservation
     */
    private function send_cancellation_emails($booking_id) {
        $email_handler = new \IBS\Email\EmailHandler();

        // Envoyer l'email de confirmation au client
        $customer_sent = $email_handler->send_cancellation_confirmation($booking_id);

        // Envoyer l'email de notification à l'admin
        $admin_sent = $email_handler->send_cancellation_notification($booking_id);

        if ($customer_sent && $admin_sent) {
            error_log('IBS: Emails d\'annulation envoyés avec succès pour réservation #' . $booking_id);
        } else {
            if (!$customer_sent) {
                error_log('IBS: Échec de l\'envoi de l\'email client d\'annulation pour réservation #' . $booking_id);
            }
            if (!$admin_sent) {
                error_log('IBS: Échec de l\'envoi de l\'email admin d\'annulation pour réservation #' . $booking_id);
            }
        }
    }

    /**
     * Envoie la notification WhatsApp d'annulation
     *
     * @param int $booking_id ID de la réservation
     */
    private function send_cancellation_whatsapp($booking_id) {
        $whatsapp = new \IBS\Integrations\WhatsAppHandler();

        if (!$whatsapp->is_configured()) {
            return;
        }

        $sent = $whatsapp->send_cancellation_confirmation($booking_id);

        if ($sent) {
            error_log('IBS: Notification WhatsApp d\'annulation envoyée pour réservation #' . $booking_id);
        } else {
            error_log('IBS: Échec de l\'envoi de la notification WhatsApp d\'annulation pour réservation #' . $booking_id);
        }
    }

    /**
     * Charge le template d'annulation
     *
     * @param string $token Token d'annulation
     */
    private function load_cancellation_template($token) {
        global $wpdb;

        // Récupérer les détails de la réservation
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT b.*,
                   s.name as service_name,
                   s.duration,
                   st.name as store_name,
                   st.address as store_address,
                   st.cancellation_hours
            FROM {$wpdb->prefix}ibs_bookings b
            LEFT JOIN {$wpdb->prefix}ibs_services s ON b.service_id = s.id
            LEFT JOIN {$wpdb->prefix}ibs_stores st ON b.store_id = st.id
            WHERE b.cancel_token = %s
        ", $token));

        // Charger le header WordPress
        get_header();

        // Afficher le contenu
        include IBS_PLUGIN_DIR . 'frontend/views/cancellation-page.php';

        // Charger le footer WordPress
        get_footer();
    }
}
