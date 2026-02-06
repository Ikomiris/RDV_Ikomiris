<?php
namespace IBS\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

class WhatsAppHandler {

    private $account_sid;
    private $auth_token;
    private $from_number;
    private $enabled;

    public function __construct() {
        $this->account_sid = $this->get_setting('twilio_account_sid', '');
        $this->auth_token = $this->get_setting('twilio_auth_token', '');
        $this->from_number = $this->get_setting('twilio_whatsapp_number', '');
        $this->enabled = $this->get_setting('whatsapp_enabled', '0') === '1';
    }

    /**
     * Verifie si WhatsApp est configure et active
     *
     * @return bool
     */
    public function is_configured() {
        return $this->enabled
            && !empty($this->account_sid)
            && !empty($this->auth_token)
            && !empty($this->from_number);
    }

    /**
     * Recupere un parametre depuis la table ibs_settings
     *
     * @param string $key Cle du parametre
     * @param mixed $default Valeur par defaut
     * @return mixed
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
     * Formate un numero de telephone au format international
     *
     * @param string $phone Numero de telephone
     * @return string Numero formate
     */
    private function format_phone_number($phone) {
        // Supprimer tous les caracteres non numeriques sauf le +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Si le numero commence par 0, le remplacer par +33 (France par defaut)
        if (strpos($phone, '0') === 0) {
            $phone = '+33' . substr($phone, 1);
        }

        // Si le numero ne commence pas par +, ajouter +33
        if (strpos($phone, '+') !== 0) {
            $phone = '+33' . $phone;
        }

        return $phone;
    }

    /**
     * Envoie un message WhatsApp via Twilio
     *
     * @param string $to Numero de telephone du destinataire
     * @param string $message Contenu du message
     * @return bool Succes ou echec
     */
    public function send_message($to, $message) {
        if (!$this->is_configured()) {
            error_log('IBS WhatsApp: Non configure - message non envoye');
            return false;
        }

        $to = $this->format_phone_number($to);

        // URL de l'API Twilio
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $this->account_sid . '/Messages.json';

        // Donnees a envoyer
        $data = [
            'From' => 'whatsapp:' . $this->from_number,
            'To' => 'whatsapp:' . $to,
            'Body' => $message
        ];

        // Initialiser cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->account_sid . ':' . $this->auth_token);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('IBS WhatsApp: Erreur cURL - ' . $error);
            return false;
        }

        $response_data = json_decode($response, true);

        if ($http_code >= 200 && $http_code < 300) {
            error_log('IBS WhatsApp: Message envoye avec succes a ' . $to . ' (SID: ' . ($response_data['sid'] ?? 'N/A') . ')');
            return true;
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'Erreur inconnue';
            error_log('IBS WhatsApp: Echec envoi - HTTP ' . $http_code . ' - ' . $error_message);
            return false;
        }
    }

    /**
     * Envoie une notification de confirmation de reservation au client
     *
     * @param int $booking_id ID de la reservation
     * @return bool
     */
    public function send_customer_confirmation($booking_id) {
        global $wpdb;

        if (!$this->is_configured()) {
            return false;
        }

        // Verifier si les notifications WhatsApp client sont activees
        if ($this->get_setting('whatsapp_customer_confirmation', '1') !== '1') {
            return false;
        }

        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT b.*,
                   s.name as service_name,
                   s.duration as service_duration,
                   s.price as service_price,
                   st.name as store_name,
                   st.address as store_address,
                   st.phone as store_phone
            FROM {$wpdb->prefix}ibs_bookings b
            LEFT JOIN {$wpdb->prefix}ibs_services s ON b.service_id = s.id
            LEFT JOIN {$wpdb->prefix}ibs_stores st ON b.store_id = st.id
            WHERE b.id = %d
        ", $booking_id));

        if (!$booking || empty($booking->customer_phone)) {
            return false;
        }

        // Formater la date et l'heure
        $date = date_i18n(get_option('date_format'), strtotime($booking->booking_date));
        $time = date_i18n(get_option('time_format'), strtotime($booking->booking_time));

        // Construire le message
        $message = $this->get_setting('whatsapp_confirmation_template', $this->get_default_confirmation_template());

        // Remplacer les placeholders
        $message = str_replace(
            [
                '{customer_firstname}',
                '{customer_lastname}',
                '{service_name}',
                '{booking_date}',
                '{booking_time}',
                '{store_name}',
                '{store_address}',
                '{store_phone}',
                '{booking_id}'
            ],
            [
                $booking->customer_firstname,
                $booking->customer_lastname,
                $booking->service_name,
                $date,
                $time,
                $booking->store_name,
                $booking->store_address ?? '',
                $booking->store_phone ?? '',
                $booking->id
            ],
            $message
        );

        return $this->send_message($booking->customer_phone, $message);
    }

    /**
     * Envoie une notification d'annulation au client
     *
     * @param int $booking_id ID de la reservation
     * @return bool
     */
    public function send_cancellation_confirmation($booking_id) {
        global $wpdb;

        if (!$this->is_configured()) {
            return false;
        }

        // Verifier si les notifications WhatsApp d'annulation sont activees
        if ($this->get_setting('whatsapp_customer_cancellation', '1') !== '1') {
            return false;
        }

        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT b.*,
                   s.name as service_name,
                   st.name as store_name
            FROM {$wpdb->prefix}ibs_bookings b
            LEFT JOIN {$wpdb->prefix}ibs_services s ON b.service_id = s.id
            LEFT JOIN {$wpdb->prefix}ibs_stores st ON b.store_id = st.id
            WHERE b.id = %d
        ", $booking_id));

        if (!$booking || empty($booking->customer_phone)) {
            return false;
        }

        $date = date_i18n(get_option('date_format'), strtotime($booking->booking_date));
        $time = date_i18n(get_option('time_format'), strtotime($booking->booking_time));

        $message = $this->get_setting('whatsapp_cancellation_template', $this->get_default_cancellation_template());

        $message = str_replace(
            [
                '{customer_firstname}',
                '{service_name}',
                '{booking_date}',
                '{booking_time}',
                '{store_name}'
            ],
            [
                $booking->customer_firstname,
                $booking->service_name,
                $date,
                $time,
                $booking->store_name
            ],
            $message
        );

        return $this->send_message($booking->customer_phone, $message);
    }

    /**
     * Envoie un rappel de rendez-vous au client
     *
     * @param int $booking_id ID de la reservation
     * @return bool
     */
    public function send_reminder($booking_id) {
        global $wpdb;

        if (!$this->is_configured()) {
            return false;
        }

        // Verifier si les rappels WhatsApp sont actives
        if ($this->get_setting('whatsapp_customer_reminder', '1') !== '1') {
            return false;
        }

        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT b.*,
                   s.name as service_name,
                   st.name as store_name,
                   st.address as store_address,
                   st.phone as store_phone
            FROM {$wpdb->prefix}ibs_bookings b
            LEFT JOIN {$wpdb->prefix}ibs_services s ON b.service_id = s.id
            LEFT JOIN {$wpdb->prefix}ibs_stores st ON b.store_id = st.id
            WHERE b.id = %d AND b.status = 'confirmed'
        ", $booking_id));

        if (!$booking || empty($booking->customer_phone)) {
            return false;
        }

        $date = date_i18n(get_option('date_format'), strtotime($booking->booking_date));
        $time = date_i18n(get_option('time_format'), strtotime($booking->booking_time));

        $message = $this->get_setting('whatsapp_reminder_template', $this->get_default_reminder_template());

        $message = str_replace(
            [
                '{customer_firstname}',
                '{service_name}',
                '{booking_date}',
                '{booking_time}',
                '{store_name}',
                '{store_address}',
                '{store_phone}'
            ],
            [
                $booking->customer_firstname,
                $booking->service_name,
                $date,
                $time,
                $booking->store_name,
                $booking->store_address ?? '',
                $booking->store_phone ?? ''
            ],
            $message
        );

        return $this->send_message($booking->customer_phone, $message);
    }

    /**
     * Template par defaut pour la confirmation
     */
    private function get_default_confirmation_template() {
        return "Bonjour {customer_firstname},\n\n" .
            "Votre reservation a ete confirmee !\n\n" .
            "Service : {service_name}\n" .
            "Date : {booking_date}\n" .
            "Heure : {booking_time}\n" .
            "Lieu : {store_name}\n" .
            "{store_address}\n\n" .
            "A bientot !";
    }

    /**
     * Template par defaut pour l'annulation
     */
    private function get_default_cancellation_template() {
        return "Bonjour {customer_firstname},\n\n" .
            "Votre reservation a bien ete annulee.\n\n" .
            "Service : {service_name}\n" .
            "Date : {booking_date}\n" .
            "Heure : {booking_time}\n\n" .
            "Nous esperons vous revoir bientot !";
    }

    /**
     * Template par defaut pour le rappel
     */
    private function get_default_reminder_template() {
        return "Bonjour {customer_firstname},\n\n" .
            "Rappel : vous avez un rendez-vous demain !\n\n" .
            "Service : {service_name}\n" .
            "Date : {booking_date}\n" .
            "Heure : {booking_time}\n" .
            "Lieu : {store_name}\n" .
            "{store_address}\n\n" .
            "A demain !";
    }
}
