<?php
/**
 * Configuration WordPress
 * 
 * Ce fichier contient les informations de configuration de votre installation WordPress.
 * Copiez ce fichier vers wp-config.php et remplissez les valeurs ci-dessous.
 */

// ** Réglages de la base de données ** //
/** Le nom de la base de données pour WordPress */
define( 'DB_NAME', 'votre_nom_de_base_de_donnees' );

/** Utilisateur de la base de données MySQL */
define( 'DB_USER', 'votre_utilisateur' );

/** Mot de passe de la base de données MySQL */
define( 'DB_PASSWORD', 'votre_mot_de_passe' );

/** Adresse de l'hébergement MySQL */
define( 'DB_HOST', 'localhost' );

/** Jeu de caractères à utiliser par la base de données lors de la création des tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** Le type de collation de la base de données. */
define( 'DB_COLLATE', '' );

/**#@+
 * Clés uniques d'authentification et salage.
 *
 * Remplacez les valeurs par défaut par des phrases uniques !
 * Vous pouvez générer des phrases aléatoires en utilisant {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * Vous pouvez changer ces phrases à n'importe quel moment, pour invalider tous les cookies existants.
 * Cela forcera également tous les utilisateurs à se reconnecter.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'mettez votre phrase unique ici' );
define( 'SECURE_AUTH_KEY',  'mettez votre phrase unique ici' );
define( 'LOGGED_IN_KEY',    'mettez votre phrase unique ici' );
define( 'NONCE_KEY',        'mettez votre phrase unique ici' );
define( 'AUTH_SALT',        'mettez votre phrase unique ici' );
define( 'SECURE_AUTH_SALT', 'mettez votre phrase unique ici' );
define( 'LOGGED_IN_SALT',   'mettez votre phrase unique ici' );
define( 'NONCE_SALT',       'mettez votre phrase unique ici' );

/**#@-*/

/**
 * Préfixe de base de données pour les tables WordPress.
 *
 * Vous pouvez installer plusieurs WordPress sur une seule base de données
 * si vous leur donnez chacune un préfixe unique. N'utilisez que des chiffres,
 * des lettres non-accentuées, et des caractères soulignés !
 */
$table_prefix = 'wp_';

/**
 * Pour les développeurs : le mode débogage de WordPress.
 *
 * En passant la valeur suivante à "true", vous activez l'affichage des
 * notifications d'erreurs pendant vos essais.
 * Il est fortement recommandé que les développeurs d'extensions et de thèmes
 * utilisent WP_DEBUG dans leur environnement de développement.
 *
 * Pour plus d'informations sur les autres constantes qui peuvent être utilisées
 * pour le débogage, rendez-vous sur le Codex.
 *
 * @link https://fr.wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* C'est tout, ne touchez pas à ce qui suit ! Bonne publication. */

/** Chemin absolu vers le dossier de WordPress. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Réglage des variables de WordPress et de ses fichiers inclus. */
require_once ABSPATH . 'wp-settings.php';


