<?php
/**
 * Script de récupération des informations wp-config.php
 * 
 * Ce script essaie de récupérer les informations de configuration WordPress
 * depuis la base de données ou les fichiers existants.
 * 
 * Placez ce fichier à la racine de WordPress et accédez-y via :
 * https://votre-site.com/recover-wp-config.php
 * 
 * SUPPRIMEZ-LE APRÈS UTILISATION pour des raisons de sécurité !
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Récupération wp-config.php</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 15px; border: 1px solid #ddd; overflow-x: auto; }
        .warning { color: orange; font-weight: bold; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Récupération des informations wp-config.php</h1>
    
    <?php
    // Essayer de trouver wp-config-sample.php
    $wp_config_sample = __DIR__ . '/wp-config-sample.php';
    $wp_config_backup = __DIR__ . '/wp-config.php.backup';
    $wp_config_old = __DIR__ . '/wp-config.php.old';
    
    echo '<h2>1. Recherche de fichiers de configuration existants</h2>';
    
    $found_files = [];
    if (file_exists($wp_config_sample)) {
        $found_files[] = 'wp-config-sample.php';
        echo '<p class="success">✓ wp-config-sample.php trouvé</p>';
    }
    if (file_exists($wp_config_backup)) {
        $found_files[] = 'wp-config.php.backup';
        echo '<p class="success">✓ wp-config.php.backup trouvé</p>';
    }
    if (file_exists($wp_config_old)) {
        $found_files[] = 'wp-config.php.old';
        echo '<p class="success">✓ wp-config.php.old trouvé</p>';
    }
    
    if (empty($found_files)) {
        echo '<p class="warning">⚠ Aucun fichier de configuration de sauvegarde trouvé</p>';
    }
    
    echo '<h2>2. Informations à récupérer depuis votre hébergeur</h2>';
    echo '<p>Vous devez récupérer ces informations depuis votre panneau d\'hébergement (cPanel, Plesk, etc.) :</p>';
    echo '<ul>';
    echo '<li><strong>DB_NAME</strong> : Nom de votre base de données</li>';
    echo '<li><strong>DB_USER</strong> : Nom d\'utilisateur de la base de données</li>';
    echo '<li><strong>DB_PASSWORD</strong> : Mot de passe de la base de données</li>';
    echo '<li><strong>DB_HOST</strong> : Hôte de la base de données (généralement "localhost")</li>';
    echo '</ul>';
    
    echo '<h2>3. Template wp-config.php</h2>';
    echo '<p>Voici un template de base. <strong>Vous devez remplacer toutes les valeurs entre guillemets par vos vraies informations.</strong></p>';
    ?>
    
    <pre><?php
$template = <<<'EOT'
<?php
/**
 * Configuration WordPress
 */

// ** Réglages de la base de données ** //
define( 'DB_NAME', 'REMPLACEZ_PAR_VOTRE_NOM_DE_BASE' );
define( 'DB_USER', 'REMPLACEZ_PAR_VOTRE_UTILISATEUR' );
define( 'DB_PASSWORD', 'REMPLACEZ_PAR_VOTRE_MOT_DE_PASSE' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

/**#@+
 * Clés uniques d'authentification et salage.
 * Générez de nouvelles clés sur : https://api.wordpress.org/secret-key/1.1/salt/
 */
define( 'AUTH_KEY',         'REMPLACEZ_PAR_VOTRE_CLE' );
define( 'SECURE_AUTH_KEY',  'REMPLACEZ_PAR_VOTRE_CLE' );
define( 'LOGGED_IN_KEY',    'REMPLACEZ_PAR_VOTRE_CLE' );
define( 'NONCE_KEY',        'REMPLACEZ_PAR_VOTRE_CLE' );
define( 'AUTH_SALT',        'REMPLACEZ_PAR_VOTRE_CLE' );
define( 'SECURE_AUTH_SALT', 'REMPLACEZ_PAR_VOTRE_CLE' );
define( 'LOGGED_IN_SALT',   'REMPLACEZ_PAR_VOTRE_CLE' );
define( 'NONCE_SALT',       'REMPLACEZ_PAR_VOTRE_CLE' );
/**#@-*/

/**
 * Préfixe de base de données pour les tables WordPress.
 */
$table_prefix = 'wp_';

/**
 * Mode débogage
 */
define( 'WP_DEBUG', false );

/* C'est tout, ne touchez pas à ce qui suit ! */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
EOT;
echo htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
    ?></pre>
    
    <h2>4. Instructions</h2>
    <ol>
        <li>Récupérez vos informations de base de données depuis votre panneau d'hébergement</li>
        <li>Générez de nouvelles clés de sécurité sur : <a href="https://api.wordpress.org/secret-key/1.1/salt/" target="_blank">https://api.wordpress.org/secret-key/1.1/salt/</a></li>
        <li>Créez un fichier <code>wp-config.php</code> à la racine de WordPress</li>
        <li>Copiez le template ci-dessus et remplacez toutes les valeurs "REMPLACEZ_PAR_..." par vos vraies informations</li>
        <li>Téléversez le fichier sur votre serveur</li>
        <li><strong>Supprimez ce fichier (recover-wp-config.php) après utilisation</strong></li>
    </ol>
    
    <h2>5. Où trouver vos informations ?</h2>
    <ul>
        <li><strong>cPanel</strong> : Section "Bases de données MySQL" → "Bases de données MySQL"</li>
        <li><strong>Plesk</strong> : Section "Bases de données"</li>
        <li><strong>Autres</strong> : Contactez le support de votre hébergeur</li>
    </ul>
    
    <hr>
    <p class="warning"><strong>⚠ IMPORTANT : Supprimez ce fichier après utilisation pour des raisons de sécurité !</strong></p>
</body>
</html>


