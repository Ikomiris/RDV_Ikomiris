-- Script SQL corrigé pour créer la table wp_ibs_bookings
-- Le problème était la double définition de la clé cancel_token

CREATE TABLE IF NOT EXISTS `wp_ibs_bookings` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `store_id` bigint(20) NOT NULL,
    `service_id` bigint(20) NOT NULL,
    `booking_date` date NOT NULL,
    `booking_time` time NOT NULL,
    `duration` int(11) NOT NULL COMMENT 'Durée en minutes',
    `customer_firstname` varchar(100) NOT NULL,
    `customer_lastname` varchar(100) NOT NULL,
    `customer_email` varchar(100) NOT NULL,
    `customer_phone` varchar(50) NOT NULL,
    `customer_message` text,
    `customer_gift_card_code` varchar(100),
    `status` varchar(20) DEFAULT 'pending' COMMENT 'pending, confirmed, cancelled, completed',
    `cancel_token` varchar(64),
    `google_event_id` varchar(255),
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `store_id` (`store_id`),
    KEY `service_id` (`service_id`),
    KEY `booking_date` (`booking_date`),
    KEY `status` (`status`),
    UNIQUE KEY `cancel_token` (`cancel_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

