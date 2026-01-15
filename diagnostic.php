<?php
/**
 * Script de diagnostic pour WordPress
 * Placez ce fichier à la racine de votre installation WordPress
 * Accédez-y via : https://votre-site.com/diagnostic.php
 * SUPPRIMEZ-LE APRÈS UTILISATION pour des raisons de sécurité !
 */

// Charger WordPress
require_once('wp-load.php');

// Vérifier que l'utilisateur est admin
if (!current_user_can('manage_options')) {
    die('Accès refusé. Vous devez être administrateur.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnostic WordPress</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <h1>Diagnostic WordPress</h1>
    
    <h2>1. Vérification des plugins actifs</h2>
    <?php
    $active_plugins = get_option('active_plugins');
    if (empty($active_plugins)) {
        echo '<p class="success">✓ Aucun plugin actif</p>';
    } else {
        echo '<p class="warning">⚠ Plugins actifs :</p><ul>';
        foreach ($active_plugins as $plugin) {
            echo '<li>' . esc_html($plugin) . '</li>';
        }
        echo '</ul>';
    }
    ?>
    
    <h2>2. Vérification de la base de données</h2>
    <?php
    global $wpdb;
    if ($wpdb) {
        echo '<p class="success">✓ Connexion à la base de données OK</p>';
        echo '<p>Préfixe des tables : ' . esc_html($wpdb->prefix) . '</p>';
    } else {
        echo '<p class="error">✗ Erreur de connexion à la base de données</p>';
    }
    ?>
    
    <h2>3. Vérification des erreurs PHP</h2>
    <?php
    $error_log = ini_get('error_log');
    if ($error_log && file_exists($error_log)) {
        $errors = file_get_contents($error_log);
        $recent_errors = array_slice(explode("\n", $errors), -50);
        echo '<p>Dernières erreurs PHP (50 dernières lignes) :</p>';
        echo '<pre>' . esc_html(implode("\n", $recent_errors)) . '</pre>';
    } else {
        echo '<p class="warning">⚠ Fichier de log PHP non trouvé : ' . esc_html($error_log ?: 'non défini') . '</p>';
    }
    ?>
    
    <h2>4. Vérification du mode debug WordPress</h2>
    <?php
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo '<p class="success">✓ WP_DEBUG est activé</p>';
    } else {
        echo '<p class="warning">⚠ WP_DEBUG n\'est pas activé</p>';
    }
    
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        echo '<p class="success">✓ WP_DEBUG_LOG est activé</p>';
        $debug_log = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log)) {
            $debug_content = file_get_contents($debug_log);
            $recent_debug = array_slice(explode("\n", $debug_content), -50);
            echo '<p>Dernières entrées du debug.log (50 dernières lignes) :</p>';
            echo '<pre>' . esc_html(implode("\n", $recent_debug)) . '</pre>';
        } else {
            echo '<p class="warning">⚠ Fichier debug.log non trouvé</p>';
        }
    } else {
        echo '<p class="warning">⚠ WP_DEBUG_LOG n\'est pas activé</p>';
    }
    ?>
    
    <h2>5. Vérification des permissions</h2>
    <?php
    $wp_content = WP_CONTENT_DIR;
    if (is_writable($wp_content)) {
        echo '<p class="success">✓ wp-content est accessible en écriture</p>';
    } else {
        echo '<p class="error">✗ wp-content n\'est pas accessible en écriture</p>';
    }
    ?>
    
    <h2>6. Test d'accès à l'admin</h2>
    <?php
    $admin_url = admin_url();
    echo '<p><a href="' . esc_url($admin_url) . '" target="_blank">Tester l\'accès à l\'admin WordPress</a></p>';
    ?>
    
    <hr>
    <p><strong>⚠ IMPORTANT : Supprimez ce fichier après utilisation pour des raisons de sécurité !</strong></p>
</body>
</html>

