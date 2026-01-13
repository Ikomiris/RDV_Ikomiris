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
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql_stores);
        
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
    }
    
    public static function deactivate() {
        // Nettoyer les cron jobs si nécessaire
        wp_clear_scheduled_hook('ibs_send_reminder_emails');
    }
}
