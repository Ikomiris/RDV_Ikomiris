-- ============================================
-- Migration Google Calendar pour Ikomiris Booking System
-- Version: 1.0.0
-- Date: Janvier 2026
-- ============================================

-- Description:
-- Ce script ajoute le champ google_calendar_id à la table ibs_stores
-- et le paramètre google_refresh_token à la table ibs_settings

-- ============================================
-- 1. Ajouter la colonne google_calendar_id à la table ibs_stores
-- ============================================

-- Vérifier si la colonne existe déjà
SET @table_name = CONCAT(
    (SELECT CONCAT(table_schema, '.', table_name) 
     FROM information_schema.tables 
     WHERE table_name LIKE '%ibs_stores' 
     LIMIT 1)
);

-- Ajouter la colonne si elle n'existe pas
ALTER TABLE `wp_ibs_stores` 
ADD COLUMN IF NOT EXISTS `google_calendar_id` varchar(255) DEFAULT NULL 
AFTER `image_url`;

-- ============================================
-- 2. Ajouter le paramètre google_refresh_token
-- ============================================

-- Insérer le paramètre s'il n'existe pas déjà
INSERT INTO `wp_ibs_settings` (`setting_key`, `setting_value`)
SELECT 'google_refresh_token', ''
WHERE NOT EXISTS (
    SELECT 1 FROM `wp_ibs_settings` WHERE `setting_key` = 'google_refresh_token'
);

-- ============================================
-- Vérification
-- ============================================

-- Afficher les colonnes de la table ibs_stores pour vérifier
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
FROM information_schema.COLUMNS
WHERE TABLE_NAME LIKE '%ibs_stores'
  AND COLUMN_NAME = 'google_calendar_id';

-- Afficher le paramètre google_refresh_token
SELECT * FROM `wp_ibs_settings` WHERE `setting_key` = 'google_refresh_token';

-- ============================================
-- Notes importantes
-- ============================================

-- ATTENTION: Ce script utilise le préfixe par défaut 'wp_'
-- Si votre installation WordPress utilise un préfixe différent,
-- remplacez 'wp_' par votre préfixe personnalisé avant d'exécuter le script.

-- Exemples de remplacement:
-- wp_ibs_stores -> monprefix_ibs_stores
-- wp_ibs_settings -> monprefix_ibs_settings

-- Pour trouver votre préfixe, vérifiez le fichier wp-config.php:
-- $table_prefix = 'votre_prefixe_';

-- ============================================
-- Rollback (Annulation de la migration)
-- ============================================

-- Si vous souhaitez annuler cette migration, exécutez les commandes suivantes:

-- ALTER TABLE `wp_ibs_stores` DROP COLUMN IF EXISTS `google_calendar_id`;
-- DELETE FROM `wp_ibs_settings` WHERE `setting_key` = 'google_refresh_token';

-- ============================================
-- Fin du script
-- ============================================

