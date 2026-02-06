<?php
namespace IBS;

if (!defined('ABSPATH')) {
    exit;
}

class Installer {
    
    public static function activate() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Table des magasins
        $table_stores = $wpdb->prefix . 'ibs_stores';
        $sql_stores = "CREATE TABLE $table_stores (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            address text,
            phone varchar(50),
            email varchar(100),
            description text,
            image_url varchar(500),
            google_calendar_id varchar(255),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql_stores);
        
        // Migration : Ajouter la colonne google_calendar_id si elle n'existe pas (installations existantes)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_stores LIKE 'google_calendar_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_stores ADD google_calendar_id varchar(255) AFTER image_url");
        }

        // Migration : Ajouter la colonne cancellation_hours si elle n'existe pas
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_stores LIKE 'cancellation_hours'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_stores ADD cancellation_hours int(11) DEFAULT 24 COMMENT 'Délai d annulation en heures' AFTER google_calendar_id");
        }
        
        // Table des services
        $table_services = $wpdb->prefix . 'ibs_services';
        $sql_services = "CREATE TABLE $table_services (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            duration int(11) NOT NULL COMMENT 'Durée en minutes',
            price decimal(10,2) DEFAULT NULL,
            image_url varchar(500),
            is_active tinyint(1) DEFAULT 1,
            display_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql_services);
        
        // Table de liaison services-magasins
        $table_store_services = $wpdb->prefix . 'ibs_store_services';
        $sql_store_services = "CREATE TABLE $table_store_services (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            store_id bigint(20) NOT NULL,
            service_id bigint(20) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY store_service (store_id, service_id),
            KEY store_id (store_id),
            KEY service_id (service_id)
        ) $charset_collate;";
        dbDelta($sql_store_services);
        
        // Table des horaires
        $table_schedules = $wpdb->prefix . 'ibs_schedules';
        $sql_schedules = "CREATE TABLE $table_schedules (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            store_id bigint(20) NOT NULL,
            day_of_week tinyint(1) NOT NULL COMMENT '0=Dimanche, 1=Lundi, ..., 6=Samedi',
            time_start time NOT NULL,
            time_end time NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY store_id (store_id),
            KEY day_of_week (day_of_week)
        ) $charset_collate;";
        dbDelta($sql_schedules);
        
        // Table des dates exceptionnelles
        $table_exceptions = $wpdb->prefix . 'ibs_exceptions';
        $sql_exceptions = "CREATE TABLE $table_exceptions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            store_id bigint(20) NOT NULL,
            exception_date date NOT NULL,
            exception_type varchar(20) NOT NULL COMMENT 'closed ou open',
            time_start time DEFAULT NULL,
            time_end time DEFAULT NULL,
            description varchar(255),
            PRIMARY KEY (id),
            KEY store_id (store_id),
            KEY exception_date (exception_date)
        ) $charset_collate;";
        dbDelta($sql_exceptions);
        
        // Table des rendez-vous
        $table_bookings = $wpdb->prefix . 'ibs_bookings';
        $sql_bookings = "CREATE TABLE $table_bookings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            store_id bigint(20) NOT NULL,
            service_id bigint(20) NOT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            duration int(11) NOT NULL COMMENT 'Durée en minutes',
            customer_firstname varchar(100) NOT NULL,
            customer_lastname varchar(100) NOT NULL,
            customer_email varchar(100) NOT NULL,
            customer_phone varchar(50) NOT NULL,
            customer_message text,
            customer_gift_card_code varchar(100),
            status varchar(20) DEFAULT 'pending' COMMENT 'pending, confirmed, cancelled, completed',
            cancel_token varchar(64) UNIQUE,
            google_event_id varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY store_id (store_id),
            KEY service_id (service_id),
            KEY booking_date (booking_date),
            KEY status (status),
            KEY cancel_token (cancel_token)
        ) $charset_collate;";
        dbDelta($sql_bookings);

        // Migration : Ajouter la colonne customer_gift_card_code si elle n'existe pas
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_bookings LIKE 'customer_gift_card_code'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_bookings ADD customer_gift_card_code varchar(100) AFTER customer_message");
        }

        // Migration : Ajouter la colonne cancelled_at si elle n'existe pas
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_bookings LIKE 'cancelled_at'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_bookings ADD cancelled_at datetime DEFAULT NULL AFTER google_event_id");
        }

        // Table des paramètres
        $table_settings = $wpdb->prefix . 'ibs_settings';
        $sql_settings = "CREATE TABLE $table_settings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        dbDelta($sql_settings);
        
        // Insérer les paramètres par défaut
        self::insert_default_settings();
        
        // Créer la page par défaut avec le shortcode
        self::create_default_page();
        
        update_option('ibs_version', IBS_VERSION);
        update_option('ibs_installed', true);
    }
    
    private static function insert_default_settings() {
        global $wpdb;
        $table = $wpdb->prefix . 'ibs_settings';
        
        $default_settings = [
            'min_booking_delay' => '2',
            'max_booking_delay' => '90',
            'slot_interval' => '10',
            'theme_color' => '#0073aa',
            'theme_secondary_color' => '#005177',
            'show_prices' => '1',
            'terms_conditions' => '',
            'confirmation_text' => 'Votre rendez-vous a été confirmé. Vous allez recevoir un email de confirmation.',
            'google_calendar_enabled' => '0',
            'google_client_id' => '',
            'google_client_secret' => '',
            'google_refresh_token' => '',
            'email_admin_notification' => '1',
            'email_admin_address' => get_option('admin_email'),
            'email_customer_confirmation' => '1',
            'email_customer_reminder' => '1',
            'email_reminder_hours' => '24',

            // Email customization - Logo global
            'email_global_logo_url' => '',

            // Email customization - Customer confirmation
            'email_customer_confirmation_header_color' => '#3498db',
            'email_customer_confirmation_button_color' => '#e74c3c',
            'email_customer_confirmation_background_color' => '#f9f9f9',
            'email_customer_confirmation_text_color' => '#333333',
            'email_customer_confirmation_title' => 'Confirmation de réservation',
            'email_customer_confirmation_intro_text' => 'Votre réservation a été confirmée avec succès !',
            'email_customer_confirmation_footer_text' => 'Cet email a été envoyé automatiquement, merci de ne pas y répondre.',

            // Email customization - Admin notification
            'email_admin_notification_header_color' => '#27ae60',
            'email_admin_notification_button_color' => '#3498db',
            'email_admin_notification_background_color' => '#f9f9f9',
            'email_admin_notification_text_color' => '#333333',
            'email_admin_notification_title' => 'Nouvelle réservation reçue',
            'email_admin_notification_intro_text' => 'Une nouvelle réservation vient d\'être effectuée sur votre site.',
            'email_admin_notification_footer_text' => 'Notification automatique du système de réservation Ikomiris',

            // Email customization - Reminder
            'email_reminder_header_color' => '#f39c12',
            'email_reminder_button_color' => '#3498db',
            'email_reminder_background_color' => '#f9f9f9',
            'email_reminder_text_color' => '#333333',
            'email_reminder_title' => 'Rappel de rendez-vous',
            'email_reminder_intro_text' => 'Nous vous rappelons que vous avez un rendez-vous demain.',
            'email_reminder_footer_text' => 'Nous vous attendons avec plaisir !',

            // Email customization - Customer cancellation
            'email_customer_cancellation_header_color' => '#e74c3c',
            'email_customer_cancellation_button_color' => '#3498db',
            'email_customer_cancellation_background_color' => '#f9f9f9',
            'email_customer_cancellation_text_color' => '#333333',
            'email_customer_cancellation_title' => 'Confirmation d\'annulation',
            'email_customer_cancellation_intro_text' => 'Votre réservation a bien été annulée.',
            'email_customer_cancellation_footer_text' => 'Nous espérons vous revoir bientôt !',

            // Email customization - Admin cancellation
            'email_admin_cancellation_header_color' => '#e67e22',
            'email_admin_cancellation_button_color' => '#3498db',
            'email_admin_cancellation_background_color' => '#f9f9f9',
            'email_admin_cancellation_text_color' => '#333333',
            'email_admin_cancellation_title' => 'Annulation de réservation',
            'email_admin_cancellation_intro_text' => 'Une réservation vient d\'être annulée par le client.',
            'email_admin_cancellation_footer_text' => 'Notification automatique du système de réservation Ikomiris',
        ];
        
        foreach ($default_settings as $key => $value) {
            $wpdb->insert(
                $table,
                [
                    'setting_key' => $key,
                    'setting_value' => $value
                ],
                ['%s', '%s']
            );
        }
    }
    
    private static function create_default_page() {
        // Page de réservation
        $page_title = 'Réservation';
        $page_content = '<!-- wp:shortcode -->[ikomiris_booking]<!-- /wp:shortcode -->';

        // Vérifier si la page existe déjà
        $page = get_page_by_title($page_title);

        if (!$page) {
            wp_insert_post([
                'post_title' => $page_title,
                'post_content' => $page_content,
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            ]);
        }

        // Page d'annulation
        $cancel_page_title = 'Annulation de réservation';
        $cancel_page_slug = 'reservation-annulation';

        // Vérifier si la page existe déjà
        $cancel_page = get_page_by_path($cancel_page_slug);

        if (!$cancel_page) {
            wp_insert_post([
                'post_title' => $cancel_page_title,
                'post_name' => $cancel_page_slug,
                'post_content' => '<p>Cette page permet aux clients d\'annuler leurs réservations via le lien reçu par email.</p>',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            ]);
        }
    }
    
    public static function deactivate() {
        // Nettoyer les cron jobs si nécessaire
        wp_clear_scheduled_hook('ibs_send_reminder_emails');
    }
}
