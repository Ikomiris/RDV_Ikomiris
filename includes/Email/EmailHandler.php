<?php
namespace IBS\Email;

if (!defined('ABSPATH')) {
    exit;
}

class EmailHandler {

    /**
     * Récupère un setting email avec cache statique
     */
    private function get_email_setting($key, $default = '') {
        global $wpdb;
        static $cache = null;

        if ($cache === null) {
            $results = $wpdb->get_results(
                "SELECT setting_key, setting_value FROM {$wpdb->prefix}ibs_settings WHERE setting_key LIKE 'email_%'",
                OBJECT_K
            );
            $cache = is_array($results) ? $results : [];
        }

        return isset($cache[$key]) ? $cache[$key]->setting_value : $default;
    }

    /**
     * Génère le header HTML personnalisable
     */
    private function get_email_header($type, $site_name) {
        $logo_url = $this->get_email_setting('email_global_logo_url', '');
        $header_color = $this->get_email_setting("email_{$type}_header_color", $this->get_default_header_color($type));
        $title = $this->get_email_setting("email_{$type}_title", $this->get_default_title($type));

        ob_start();
        ?>
        <div class="header" style="background: <?php echo esc_attr($header_color); ?>; color: white; padding: 20px; text-align: center;">
            <?php if ($logo_url): ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" style="max-width: 200px; max-height: 60px; margin-bottom: 10px;">
            <?php else: ?>
                <h1 style="margin: 0; font-size: 24px;"><?php echo esc_html($site_name); ?></h1>
            <?php endif; ?>
            <p style="margin: 10px 0 0; font-size: 20px; font-weight: bold;"><?php echo esc_html($title); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Génère le footer HTML personnalisable
     */
    private function get_email_footer($type, $site_name) {
        $footer_text = $this->get_email_setting("email_{$type}_footer_text", $this->get_default_footer($type));

        ob_start();
        ?>
        <div class="footer" style="text-align: center; color: #7f8c8d; font-size: 12px; padding: 20px; background: #f5f5f5;">
            <p style="margin: 5px 0;"><strong><?php echo esc_html($site_name); ?></strong></p>
            <p style="margin: 5px 0;"><?php echo nl2br(esc_html($footer_text)); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Valeurs par défaut des couleurs de header
     */
    private function get_default_header_color($type) {
        $defaults = [
            'customer_confirmation' => '#3498db',
            'admin_notification' => '#27ae60',
            'reminder' => '#f39c12',
            'customer_cancellation' => '#e74c3c',
            'admin_cancellation' => '#e67e22',
        ];
        return $defaults[$type] ?? '#3498db';
    }

    /**
     * Valeurs par défaut des titres
     */
    private function get_default_title($type) {
        $defaults = [
            'customer_confirmation' => __('Confirmation de réservation', 'ikomiris-booking'),
            'admin_notification' => __('Nouvelle réservation reçue', 'ikomiris-booking'),
            'reminder' => __('Rappel de rendez-vous', 'ikomiris-booking'),
            'customer_cancellation' => __('Confirmation d\'annulation', 'ikomiris-booking'),
            'admin_cancellation' => __('Annulation de réservation', 'ikomiris-booking'),
        ];
        return $defaults[$type] ?? '';
    }

    /**
     * Valeurs par défaut des footers
     */
    private function get_default_footer($type) {
        $defaults = [
            'customer_confirmation' => __('Cet email a été envoyé automatiquement, merci de ne pas y répondre.', 'ikomiris-booking'),
            'admin_notification' => __('Notification automatique du système de réservation Ikomiris', 'ikomiris-booking'),
            'reminder' => __('Nous vous attendons avec plaisir !', 'ikomiris-booking'),
            'customer_cancellation' => __('Nous espérons vous revoir bientôt !', 'ikomiris-booking'),
            'admin_cancellation' => __('Notification automatique du système de réservation Ikomiris', 'ikomiris-booking'),
        ];
        return $defaults[$type] ?? '';
    }

    /**
     * Valeurs par défaut des couleurs de texte
     */
    private function get_default_text_color($type) {
        // Tous les types utilisent la même couleur de texte par défaut
        return '#333333';
    }

    /**
     * Envoie un email de confirmation au client
     *
     * @param int $booking_id ID de la réservation
     * @return bool Succès ou échec de l'envoi
     */
    public function send_customer_confirmation($booking_id) {
        global $wpdb;

        // Récupérer les détails de la réservation
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT b.*,
                   s.name as service_name,
                   s.duration as service_duration,
                   s.price as service_price,
                   st.name as store_name,
                   st.address as store_address,
                   st.phone as store_phone,
                   st.email as store_email
            FROM {$wpdb->prefix}ibs_bookings b
            LEFT JOIN {$wpdb->prefix}ibs_services s ON b.service_id = s.id
            LEFT JOIN {$wpdb->prefix}ibs_stores st ON b.store_id = st.id
            WHERE b.id = %d
        ", $booking_id));

        if (!$booking) {
            error_log('IBS Email: Réservation #' . $booking_id . ' introuvable');
            return false;
        }

        // Préparer les données pour le template
        $data = [
            'booking_id' => $booking->id,
            'customer_firstname' => $booking->customer_firstname,
            'customer_lastname' => $booking->customer_lastname,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'customer_gift_card_code' => $booking->customer_gift_card_code,
            'booking_date' => $this->format_date($booking->booking_date),
            'booking_time' => $this->format_time($booking->booking_time),
            'service_name' => $booking->service_name,
            'service_duration' => $booking->service_duration,
            'service_price' => $booking->service_price,
            'store_name' => $booking->store_name,
            'store_address' => $booking->store_address,
            'store_phone' => $booking->store_phone,
            'store_email' => $booking->store_email,
            'cancel_token' => $booking->cancel_token,
            'cancel_url' => $this->get_cancel_url($booking->cancel_token),
        ];

        // Sujet de l'email
        $subject = sprintf(
            __('Confirmation de votre réservation - %s', 'ikomiris-booking'),
            $booking->service_name
        );

        // Générer le contenu HTML
        $message = $this->get_customer_template($data);

        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $booking->store_name . ' <' . $booking->store_email . '>',
        ];

        // Envoyer l'email
        $sent = wp_mail($booking->customer_email, $subject, $message, $headers);

        if ($sent) {
            error_log('IBS Email: Email de confirmation envoyé au client pour réservation #' . $booking_id);
        } else {
            error_log('IBS Email: Échec de l\'envoi de l\'email au client pour réservation #' . $booking_id);
        }

        return $sent;
    }

    /**
     * Envoie un email de notification à l'administrateur du magasin
     *
     * @param int $booking_id ID de la réservation
     * @return bool Succès ou échec de l'envoi
     */
    public function send_admin_notification($booking_id) {
        global $wpdb;

        // Récupérer les détails de la réservation
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT b.*,
                   s.name as service_name,
                   s.duration as service_duration,
                   s.price as service_price,
                   st.name as store_name,
                   st.email as store_email
            FROM {$wpdb->prefix}ibs_bookings b
            LEFT JOIN {$wpdb->prefix}ibs_services s ON b.service_id = s.id
            LEFT JOIN {$wpdb->prefix}ibs_stores st ON b.store_id = st.id
            WHERE b.id = %d
        ", $booking_id));

        if (!$booking) {
            error_log('IBS Email: Réservation #' . $booking_id . ' introuvable pour notification admin');
            return false;
        }

        // Préparer les données pour le template
        $data = [
            'booking_id' => $booking->id,
            'customer_firstname' => $booking->customer_firstname,
            'customer_lastname' => $booking->customer_lastname,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'customer_message' => $booking->customer_message,
            'customer_gift_card_code' => $booking->customer_gift_card_code,
            'booking_date' => $this->format_date($booking->booking_date),
            'booking_time' => $this->format_time($booking->booking_time),
            'service_name' => $booking->service_name,
            'service_duration' => $booking->service_duration,
            'service_price' => $booking->service_price,
            'store_name' => $booking->store_name,
            'admin_url' => admin_url('admin.php?page=ibs-bookings'),
        ];

        // Sujet de l'email
        $subject = sprintf(
            __('Nouvelle réservation - %s le %s', 'ikomiris-booking'),
            $booking->service_name,
            $this->format_date($booking->booking_date)
        );

        // Générer le contenu HTML
        $message = $this->get_admin_template($data);

        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'Reply-To: ' . $booking->customer_firstname . ' ' . $booking->customer_lastname . ' <' . $booking->customer_email . '>',
        ];

        // Email de destination (magasin)
        $to = $booking->store_email;

        // Ajouter l'email admin WordPress si configuré
        $admin_email = get_option('admin_email');
        if ($admin_email && $admin_email !== $booking->store_email) {
            $headers[] = 'Cc: ' . $admin_email;
        }

        // Envoyer l'email
        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            error_log('IBS Email: Email de notification envoyé à l\'admin pour réservation #' . $booking_id);
        } else {
            error_log('IBS Email: Échec de l\'envoi de l\'email à l\'admin pour réservation #' . $booking_id);
        }

        return $sent;
    }

    /**
     * Template d'email pour le client
     */
    private function get_customer_template($data) {
        $site_name = get_bloginfo('name');
        $type = 'customer_confirmation';

        // Récupérer les paramètres personnalisés
        $header_color = $this->get_email_setting("email_{$type}_header_color", $this->get_default_header_color($type));
        $button_color = $this->get_email_setting("email_{$type}_button_color", '#e74c3c');
        $background_color = $this->get_email_setting("email_{$type}_background_color", '#f9f9f9');
        $text_color = $this->get_email_setting("email_{$type}_text_color", $this->get_default_text_color($type));
        $intro_text = $this->get_email_setting("email_{$type}_intro_text", __('Votre réservation a été confirmée avec succès !', 'ikomiris-booking'));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: <?php echo esc_attr($text_color); ?>; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .content { background: <?php echo esc_attr($background_color); ?>; padding: 30px; margin: 20px 0; color: <?php echo esc_attr($text_color); ?>; }
                .booking-details { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid <?php echo esc_attr($header_color); ?>; }
                .detail-row { margin: 10px 0; }
                .label { font-weight: bold; color: <?php echo esc_attr($text_color); ?>; }
                .button { display: inline-block; background: <?php echo esc_attr($button_color); ?>; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <?php echo $this->get_email_header($type, $site_name); ?>

                <div class="content">
                    <h2><?php printf(__('Bonjour %s,', 'ikomiris-booking'), esc_html($data['customer_firstname'])); ?></h2>

                    <p><?php echo nl2br(esc_html($intro_text)); ?></p>

                    <div class="booking-details">
                        <h3><?php _e('Détails de votre réservation', 'ikomiris-booking'); ?></h3>

                        <div class="detail-row">
                            <span class="label"><?php _e('Numéro de réservation:', 'ikomiris-booking'); ?></span>
                            #<?php echo esc_html($data['booking_id']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Service:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['service_name']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Date:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['booking_date']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Heure:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['booking_time']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Durée:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['service_duration']); ?> minutes
                        </div>

                        <?php if (!empty($data['customer_gift_card_code'])): ?>
                        <div class="detail-row">
                            <span class="label"><?php _e('Carte cadeau:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['customer_gift_card_code']); ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($data['service_price'])): ?>
                        <div class="detail-row">
                            <span class="label"><?php _e('Prix:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html(number_format($data['service_price'], 2)); ?> €
                        </div>
                        <?php endif; ?>

                        <div class="detail-row">
                            <span class="label"><?php _e('Magasin:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['store_name']); ?>
                        </div>

                        <?php if (!empty($data['store_address'])): ?>
                        <div class="detail-row">
                            <span class="label"><?php _e('Adresse:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['store_address']); ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($data['store_phone'])): ?>
                        <div class="detail-row">
                            <span class="label"><?php _e('Téléphone:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['store_phone']); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <p><?php _e('Nous vous attendons avec plaisir !', 'ikomiris-booking'); ?></p>

                    <p><strong><?php _e('Besoin d\'annuler votre réservation ?', 'ikomiris-booking'); ?></strong></p>
                    <a href="<?php echo esc_url($data['cancel_url']); ?>" class="button">
                        <?php _e('Annuler ma réservation', 'ikomiris-booking'); ?>
                    </a>
                </div>

                <?php echo $this->get_email_footer($type, $site_name); ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Template d'email pour l'administrateur
     */
    private function get_admin_template($data) {
        $site_name = get_bloginfo('name');
        $type = 'admin_notification';

        // Récupérer les paramètres personnalisés
        $header_color = $this->get_email_setting("email_{$type}_header_color", $this->get_default_header_color($type));
        $button_color = $this->get_email_setting("email_{$type}_button_color", '#3498db');
        $background_color = $this->get_email_setting("email_{$type}_background_color", '#f9f9f9');
        $text_color = $this->get_email_setting("email_{$type}_text_color", $this->get_default_text_color($type));
        $intro_text = $this->get_email_setting("email_{$type}_intro_text", __('Une nouvelle réservation vient d\'être effectuée sur votre site.', 'ikomiris-booking'));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: <?php echo esc_attr($text_color); ?>; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .content { background: <?php echo esc_attr($background_color); ?>; padding: 30px; margin: 20px 0; color: <?php echo esc_attr($text_color); ?>; }
                .booking-details { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid <?php echo esc_attr($header_color); ?>; }
                .detail-row { margin: 10px 0; }
                .label { font-weight: bold; color: <?php echo esc_attr($text_color); ?>; }
                .button { display: inline-block; background: <?php echo esc_attr($button_color); ?>; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <?php echo $this->get_email_header($type, $site_name); ?>

                <div class="content">
                    <h2><?php _e('Nouvelle réservation', 'ikomiris-booking'); ?></h2>

                    <p><?php echo nl2br(esc_html($intro_text)); ?></p>

                    <div class="booking-details">
                        <h3><?php _e('Détails de la réservation', 'ikomiris-booking'); ?></h3>

                        <div class="detail-row">
                            <span class="label"><?php _e('Numéro:', 'ikomiris-booking'); ?></span>
                            #<?php echo esc_html($data['booking_id']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Client:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['customer_firstname'] . ' ' . $data['customer_lastname']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Email:', 'ikomiris-booking'); ?></span>
                            <a href="mailto:<?php echo esc_attr($data['customer_email']); ?>">
                                <?php echo esc_html($data['customer_email']); ?>
                            </a>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Téléphone:', 'ikomiris-booking'); ?></span>
                            <a href="tel:<?php echo esc_attr($data['customer_phone']); ?>">
                                <?php echo esc_html($data['customer_phone']); ?>
                            </a>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Service:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['service_name']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Date:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['booking_date']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Heure:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['booking_time']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Durée:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['service_duration']); ?> minutes
                        </div>

                        <?php if (!empty($data['service_price'])): ?>
                        <div class="detail-row">
                            <span class="label"><?php _e('Prix:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html(number_format($data['service_price'], 2)); ?> €
                        </div>
                        <?php endif; ?>

                        <div class="detail-row">
                            <span class="label"><?php _e('Magasin:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['store_name']); ?>
                        </div>

                        <?php if (!empty($data['customer_gift_card_code'])): ?>
                        <div class="detail-row">
                            <span class="label"><?php _e('Carte cadeau:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['customer_gift_card_code']); ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($data['customer_message'])): ?>
                        <div class="detail-row">
                            <span class="label"><?php _e('Message du client:', 'ikomiris-booking'); ?></span><br>
                            <?php echo nl2br(esc_html($data['customer_message'])); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <a href="<?php echo esc_url($data['admin_url']); ?>" class="button">
                        <?php _e('Voir dans l\'administration', 'ikomiris-booking'); ?>
                    </a>
                </div>

                <?php echo $this->get_email_footer($type, $site_name); ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Formate une date pour l'affichage
     */
    private function format_date($date) {
        $timestamp = strtotime($date);
        return date_i18n(get_option('date_format'), $timestamp);
    }

    /**
     * Formate une heure pour l'affichage
     */
    private function format_time($time) {
        $timestamp = strtotime($time);
        return date_i18n(get_option('time_format'), $timestamp);
    }

    /**
     * Formate une date et heure pour l'affichage
     */
    private function format_datetime($datetime) {
        if (empty($datetime)) {
            return '';
        }
        $timestamp = strtotime($datetime);
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    /**
     * Génère l'URL d'annulation
     */
    private function get_cancel_url($token) {
        return home_url('/reservation-annulation/?token=' . $token);
    }

    /**
     * Envoie un email de confirmation d'annulation au client
     *
     * @param int $booking_id ID de la réservation
     * @return bool Succès ou échec de l'envoi
     */
    public function send_cancellation_confirmation($booking_id) {
        global $wpdb;

        // Récupérer les détails de la réservation
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT b.*,
                   s.name as service_name,
                   st.name as store_name,
                   st.email as store_email
            FROM {$wpdb->prefix}ibs_bookings b
            LEFT JOIN {$wpdb->prefix}ibs_services s ON b.service_id = s.id
            LEFT JOIN {$wpdb->prefix}ibs_stores st ON b.store_id = st.id
            WHERE b.id = %d
        ", $booking_id));

        if (!$booking) {
            error_log('IBS Email: Réservation #' . $booking_id . ' introuvable pour confirmation d\'annulation');
            return false;
        }

        // Préparer les données pour le template
        $data = [
            'booking_id' => $booking->id,
            'customer_firstname' => $booking->customer_firstname,
            'customer_lastname' => $booking->customer_lastname,
            'booking_date' => $this->format_date($booking->booking_date),
            'booking_time' => $this->format_time($booking->booking_time),
            'service_name' => $booking->service_name,
            'store_name' => $booking->store_name,
            'cancelled_at' => $this->format_datetime($booking->cancelled_at),
        ];

        // Sujet de l'email
        $subject = sprintf(
            __('Confirmation d\'annulation - %s', 'ikomiris-booking'),
            $booking->service_name
        );

        // Générer le contenu HTML
        $message = $this->get_cancellation_confirmation_template($data);

        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $booking->store_name . ' <' . $booking->store_email . '>',
        ];

        // Envoyer l'email
        $sent = wp_mail($booking->customer_email, $subject, $message, $headers);

        if ($sent) {
            error_log('IBS Email: Email de confirmation d\'annulation envoyé au client pour réservation #' . $booking_id);
        } else {
            error_log('IBS Email: Échec de l\'envoi de l\'email de confirmation d\'annulation pour réservation #' . $booking_id);
        }

        return $sent;
    }

    /**
     * Envoie un email de notification d'annulation à l'administrateur
     *
     * @param int $booking_id ID de la réservation
     * @return bool Succès ou échec de l'envoi
     */
    public function send_cancellation_notification($booking_id) {
        global $wpdb;

        // Récupérer les détails de la réservation
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT b.*,
                   s.name as service_name,
                   st.name as store_name,
                   st.email as store_email
            FROM {$wpdb->prefix}ibs_bookings b
            LEFT JOIN {$wpdb->prefix}ibs_services s ON b.service_id = s.id
            LEFT JOIN {$wpdb->prefix}ibs_stores st ON b.store_id = st.id
            WHERE b.id = %d
        ", $booking_id));

        if (!$booking) {
            error_log('IBS Email: Réservation #' . $booking_id . ' introuvable pour notification d\'annulation admin');
            return false;
        }

        // Préparer les données pour le template
        $data = [
            'booking_id' => $booking->id,
            'customer_firstname' => $booking->customer_firstname,
            'customer_lastname' => $booking->customer_lastname,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'booking_date' => $this->format_date($booking->booking_date),
            'booking_time' => $this->format_time($booking->booking_time),
            'service_name' => $booking->service_name,
            'store_name' => $booking->store_name,
            'cancelled_at' => $this->format_datetime($booking->cancelled_at),
            'admin_url' => admin_url('admin.php?page=ikomiris-booking'),
        ];

        // Sujet de l'email
        $subject = sprintf(
            __('Annulation de réservation - %s le %s', 'ikomiris-booking'),
            $booking->service_name,
            $this->format_date($booking->booking_date)
        );

        // Générer le contenu HTML
        $message = $this->get_cancellation_notification_template($data);

        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        // Email de destination (magasin)
        $to = $booking->store_email;

        // Ajouter l'email admin WordPress si configuré
        $admin_email = get_option('admin_email');
        if ($admin_email && $admin_email !== $booking->store_email) {
            $headers[] = 'Cc: ' . $admin_email;
        }

        // Envoyer l'email
        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            error_log('IBS Email: Email de notification d\'annulation envoyé à l\'admin pour réservation #' . $booking_id);
        } else {
            error_log('IBS Email: Échec de l\'envoi de l\'email de notification d\'annulation pour réservation #' . $booking_id);
        }

        return $sent;
    }

    /**
     * Envoie un email de rappel 24h avant la réservation
     *
     * @param int $booking_id ID de la réservation
     * @return bool Succès ou échec de l'envoi
     */
    public function send_reminder($booking_id) {
        global $wpdb;

        // Récupérer les détails de la réservation
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

        if (!$booking) {
            return false;
        }

        $data = [
            'customer_firstname' => $booking->customer_firstname,
            'booking_date' => $this->format_date($booking->booking_date),
            'booking_time' => $this->format_time($booking->booking_time),
            'service_name' => $booking->service_name,
            'store_name' => $booking->store_name,
            'store_address' => $booking->store_address,
            'store_phone' => $booking->store_phone,
            'cancel_url' => $this->get_cancel_url($booking->cancel_token),
        ];

        $subject = sprintf(
            __('Rappel - Votre rendez-vous demain - %s', 'ikomiris-booking'),
            $booking->service_name
        );

        $message = $this->get_reminder_template($data);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        return wp_mail($booking->customer_email, $subject, $message, $headers);
    }

    /**
     * Template d'email de rappel
     */
    private function get_reminder_template($data) {
        $site_name = get_bloginfo('name');
        $type = 'reminder';

        // Récupérer les paramètres personnalisés
        $header_color = $this->get_email_setting("email_{$type}_header_color", $this->get_default_header_color($type));
        $background_color = $this->get_email_setting("email_{$type}_background_color", '#f9f9f9');
        $text_color = $this->get_email_setting("email_{$type}_text_color", $this->get_default_text_color($type));
        $intro_text = $this->get_email_setting("email_{$type}_intro_text", __('Nous vous rappelons que vous avez un rendez-vous demain :', 'ikomiris-booking'));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: <?php echo esc_attr($text_color); ?>; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .content { background: <?php echo esc_attr($background_color); ?>; padding: 30px; margin: 20px 0; color: <?php echo esc_attr($text_color); ?>; }
                .highlight { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid <?php echo esc_attr($header_color); ?>; font-size: 18px; }
            </style>
        </head>
        <body>
            <div class="container">
                <?php echo $this->get_email_header($type, $site_name); ?>

                <div class="content">
                    <h2><?php printf(__('Bonjour %s,', 'ikomiris-booking'), esc_html($data['customer_firstname'])); ?></h2>

                    <p><?php echo nl2br(esc_html($intro_text)); ?></p>

                    <div class="highlight">
                        <strong><?php echo esc_html($data['service_name']); ?></strong><br>
                        📅 <?php echo esc_html($data['booking_date']); ?><br>
                        🕐 <?php echo esc_html($data['booking_time']); ?><br>
                        📍 <?php echo esc_html($data['store_name']); ?><br>
                        <?php if ($data['store_address']): ?>
                            <?php echo esc_html($data['store_address']); ?>
                        <?php endif; ?>
                    </div>

                    <p><?php _e('Nous vous attendons avec plaisir !', 'ikomiris-booking'); ?></p>

                    <?php if ($data['store_phone']): ?>
                    <p><?php printf(__('Pour toute question, contactez-nous au %s', 'ikomiris-booking'), esc_html($data['store_phone'])); ?></p>
                    <?php endif; ?>

                    <p><small><a href="<?php echo esc_url($data['cancel_url']); ?>"><?php _e('Annuler ce rendez-vous', 'ikomiris-booking'); ?></a></small></p>
                </div>

                <?php echo $this->get_email_footer($type, $site_name); ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Template d'email de confirmation d'annulation pour le client
     */
    private function get_cancellation_confirmation_template($data) {
        $site_name = get_bloginfo('name');
        $type = 'customer_cancellation';

        // Récupérer les paramètres personnalisés
        $header_color = $this->get_email_setting("email_{$type}_header_color", $this->get_default_header_color($type));
        $background_color = $this->get_email_setting("email_{$type}_background_color", '#f9f9f9');
        $text_color = $this->get_email_setting("email_{$type}_text_color", $this->get_default_text_color($type));
        $intro_text = $this->get_email_setting("email_{$type}_intro_text", __('Votre réservation a bien été annulée.', 'ikomiris-booking'));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: <?php echo esc_attr($text_color); ?>; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .content { background: <?php echo esc_attr($background_color); ?>; padding: 30px; margin: 20px 0; color: <?php echo esc_attr($text_color); ?>; }
                .booking-details { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid <?php echo esc_attr($header_color); ?>; }
                .detail-row { margin: 10px 0; }
                .label { font-weight: bold; color: <?php echo esc_attr($text_color); ?>; }
            </style>
        </head>
        <body>
            <div class="container">
                <?php echo $this->get_email_header($type, $site_name); ?>

                <div class="content">
                    <h2><?php printf(__('Bonjour %s,', 'ikomiris-booking'), esc_html($data['customer_firstname'])); ?></h2>

                    <p><?php echo nl2br(esc_html($intro_text)); ?></p>

                    <div class="booking-details">
                        <h3><?php _e('Détails de la réservation annulée', 'ikomiris-booking'); ?></h3>

                        <div class="detail-row">
                            <span class="label"><?php _e('Numéro de réservation:', 'ikomiris-booking'); ?></span>
                            #<?php echo esc_html($data['booking_id']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Service:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['service_name']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Date:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['booking_date']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Heure:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['booking_time']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Magasin:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['store_name']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Annulé le:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['cancelled_at']); ?>
                        </div>
                    </div>
                </div>

                <?php echo $this->get_email_footer($type, $site_name); ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Template d'email de notification d'annulation pour l'administrateur
     */
    private function get_cancellation_notification_template($data) {
        $site_name = get_bloginfo('name');
        $type = 'admin_cancellation';

        // Récupérer les paramètres personnalisés
        $header_color = $this->get_email_setting("email_{$type}_header_color", $this->get_default_header_color($type));
        $button_color = $this->get_email_setting("email_{$type}_button_color", '#3498db');
        $background_color = $this->get_email_setting("email_{$type}_background_color", '#f9f9f9');
        $text_color = $this->get_email_setting("email_{$type}_text_color", $this->get_default_text_color($type));
        $intro_text = $this->get_email_setting("email_{$type}_intro_text", __('Une réservation vient d\'être annulée par le client.', 'ikomiris-booking'));

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: <?php echo esc_attr($text_color); ?>; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .content { background: <?php echo esc_attr($background_color); ?>; padding: 30px; margin: 20px 0; color: <?php echo esc_attr($text_color); ?>; }
                .booking-details { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid <?php echo esc_attr($header_color); ?>; }
                .detail-row { margin: 10px 0; }
                .label { font-weight: bold; color: <?php echo esc_attr($text_color); ?>; }
                .button { display: inline-block; background: <?php echo esc_attr($button_color); ?>; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <?php echo $this->get_email_header($type, $site_name); ?>

                <div class="content">
                    <h2><?php _e('Réservation annulée', 'ikomiris-booking'); ?></h2>

                    <p><?php echo nl2br(esc_html($intro_text)); ?></p>

                    <div class="booking-details">
                        <h3><?php _e('Détails de la réservation annulée', 'ikomiris-booking'); ?></h3>

                        <div class="detail-row">
                            <span class="label"><?php _e('Numéro:', 'ikomiris-booking'); ?></span>
                            #<?php echo esc_html($data['booking_id']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Client:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['customer_firstname'] . ' ' . $data['customer_lastname']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Email:', 'ikomiris-booking'); ?></span>
                            <a href="mailto:<?php echo esc_attr($data['customer_email']); ?>">
                                <?php echo esc_html($data['customer_email']); ?>
                            </a>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Téléphone:', 'ikomiris-booking'); ?></span>
                            <a href="tel:<?php echo esc_attr($data['customer_phone']); ?>">
                                <?php echo esc_html($data['customer_phone']); ?>
                            </a>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Service:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['service_name']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Date:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['booking_date']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Heure:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['booking_time']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Magasin:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['store_name']); ?>
                        </div>

                        <div class="detail-row">
                            <span class="label"><?php _e('Annulé le:', 'ikomiris-booking'); ?></span>
                            <?php echo esc_html($data['cancelled_at']); ?>
                        </div>
                    </div>

                    <a href="<?php echo esc_url($data['admin_url']); ?>" class="button">
                        <?php _e('Voir dans l\'administration', 'ikomiris-booking'); ?>
                    </a>
                </div>

                <?php echo $this->get_email_footer($type, $site_name); ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
