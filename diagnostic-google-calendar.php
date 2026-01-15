<?php
/**
 * Outil de diagnostic Google Calendar
 * 
 * À placer à la racine du plugin et accéder via :
 * https://votre-site.com/wp-content/plugins/ikomiris-booking-system/diagnostic-google-calendar.php
 */

// Charger WordPress
require_once('../../../wp-load.php');

// Vérifier les permissions admin
if (!current_user_can('manage_options')) {
    wp_die('Accès refusé. Vous devez être administrateur.');
}

// Charger la classe GoogleCalendar
require_once __DIR__ . '/includes/Integrations/GoogleCalendar.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Google Calendar - Ikomiris Booking System</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            margin: 40px;
            background: #f0f0f1;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            border-bottom: 2px solid #2271b1;
            padding-bottom: 10px;
        }
        h2 {
            color: #2271b1;
            margin-top: 30px;
        }
        .success {
            background: #d7f0db;
            border-left: 4px solid #00a32a;
            padding: 12px;
            margin: 10px 0;
        }
        .error {
            background: #fcf0f1;
            border-left: 4px solid #d63638;
            padding: 12px;
            margin: 10px 0;
        }
        .warning {
            background: #fcf9e8;
            border-left: 4px solid #dba617;
            padding: 12px;
            margin: 10px 0;
        }
        .info {
            background: #e5f5fa;
            border-left: 4px solid #2271b1;
            padding: 12px;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f6f7f7;
            font-weight: 600;
        }
        code {
            background: #f6f7f7;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: Consolas, Monaco, monospace;
            font-size: 13px;
        }
        pre {
            background: #f6f7f7;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 13px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success {
            background: #00a32a;
            color: white;
        }
        .badge-error {
            background: #d63638;
            color: white;
        }
        .test-form {
            background: #f6f7f7;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .test-form input, .test-form select {
            padding: 8px;
            margin: 5px 0;
            width: 100%;
            max-width: 400px;
        }
        .test-form button {
            background: #2271b1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            margin-top: 10px;
        }
        .test-form button:hover {
            background: #135e96;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnostic Google Calendar - Ikomiris Booking System</h1>
        
        <?php
        global $wpdb;
        
        // 1. Vérifier les paramètres
        echo '<h2>1. Configuration des Paramètres</h2>';
        
        $settings = $wpdb->get_results(
            "SELECT setting_key, setting_value FROM {$wpdb->prefix}ibs_settings 
             WHERE setting_key IN ('google_calendar_enabled', 'google_client_id', 'google_client_secret', 'google_refresh_token')",
            OBJECT_K
        );
        
        $enabled = isset($settings['google_calendar_enabled']) && $settings['google_calendar_enabled']->setting_value === '1';
        $client_id = isset($settings['google_client_id']) ? $settings['google_client_id']->setting_value : '';
        $client_secret = isset($settings['google_client_secret']) ? $settings['google_client_secret']->setting_value : '';
        $refresh_token = isset($settings['google_refresh_token']) ? $settings['google_refresh_token']->setting_value : '';
        
        echo '<table>';
        echo '<tr><th>Paramètre</th><th>Valeur</th><th>État</th></tr>';
        
        echo '<tr>';
        echo '<td><strong>Google Calendar Activé</strong></td>';
        echo '<td>' . ($enabled ? 'Oui' : 'Non') . '</td>';
        echo '<td>' . ($enabled ? '<span class="badge badge-success">✓ OK</span>' : '<span class="badge badge-error">✗ DÉSACTIVÉ</span>') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Client ID</strong></td>';
        echo '<td>' . (strlen($client_id) > 0 ? substr($client_id, 0, 30) . '...' : '<em>Non configuré</em>') . '</td>';
        echo '<td>' . (strlen($client_id) > 0 ? '<span class="badge badge-success">✓ OK</span>' : '<span class="badge badge-error">✗ MANQUANT</span>') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Client Secret</strong></td>';
        echo '<td>' . (strlen($client_secret) > 0 ? str_repeat('*', 20) : '<em>Non configuré</em>') . '</td>';
        echo '<td>' . (strlen($client_secret) > 0 ? '<span class="badge badge-success">✓ OK</span>' : '<span class="badge badge-error">✗ MANQUANT</span>') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Refresh Token</strong></td>';
        echo '<td>' . (strlen($refresh_token) > 0 ? str_repeat('*', 20) : '<em>Non configuré</em>') . '</td>';
        echo '<td>' . (strlen($refresh_token) > 0 ? '<span class="badge badge-success">✓ OK</span>' : '<span class="badge badge-error">✗ MANQUANT</span>') . '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        // 2. Vérifier les magasins
        echo '<h2>2. Configuration des Magasins</h2>';
        
        $stores = $wpdb->get_results("SELECT id, name, google_calendar_id FROM {$wpdb->prefix}ibs_stores WHERE is_active = 1");
        
        if (empty($stores)) {
            echo '<div class="warning">⚠️ Aucun magasin actif trouvé.</div>';
        } else {
            echo '<table>';
            echo '<tr><th>ID</th><th>Nom</th><th>Calendar ID</th><th>État</th></tr>';
            
            foreach ($stores as $store) {
                echo '<tr>';
                echo '<td>' . $store->id . '</td>';
                echo '<td>' . esc_html($store->name) . '</td>';
                echo '<td>' . (empty($store->google_calendar_id) ? '<em>Non configuré</em>' : '<code>' . esc_html($store->google_calendar_id) . '</code>') . '</td>';
                echo '<td>' . (empty($store->google_calendar_id) ? '<span class="badge badge-error">✗ MANQUANT</span>' : '<span class="badge badge-success">✓ OK</span>') . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
        }
        
        // 3. Test de connexion
        echo '<h2>3. Test de Connexion Google Calendar</h2>';
        
        $google = new \IBS\Integrations\GoogleCalendar();
        
        if (!$google->is_configured()) {
            echo '<div class="error">❌ <strong>Google Calendar n\'est pas configuré correctement.</strong><br>';
            echo 'Veuillez vérifier que tous les paramètres sont renseignés dans <a href="' . admin_url('admin.php?page=ikomiris-booking-settings') . '">Paramètres > Google Agenda</a></div>';
        } else {
            echo '<div class="success">✅ <strong>Configuration détectée.</strong> Test de connexion en cours...</div>';
            
            // Tester l'obtention d'un access token
            $access_token = $google->get_access_token();
            
            if ($access_token) {
                echo '<div class="success">✅ <strong>Access Token obtenu avec succès !</strong><br>';
                echo 'Token (premiers caractères) : <code>' . substr($access_token, 0, 30) . '...</code></div>';
                
                // Vérifier le cache
                $cached = get_transient('ibs_google_access_token');
                if ($cached) {
                    echo '<div class="info">ℹ️ Access Token mis en cache (expire dans ' . (get_option('_transient_timeout_ibs_google_access_token') - time()) . ' secondes)</div>';
                }
            } else {
                echo '<div class="error">❌ <strong>Impossible d\'obtenir un Access Token.</strong><br>';
                echo 'Vérifiez les logs dans <code>wp-content/debug.log</code><br>';
                echo 'Causes possibles :<br>';
                echo '• Refresh Token invalide ou expiré<br>';
                echo '• Client ID ou Client Secret incorrect<br>';
                echo '• Problème de connexion réseau</div>';
            }
        }
        
        // 4. Test de récupération d'événements
        if ($google->is_configured() && !empty($stores)) {
            echo '<h2>4. Test de Récupération d\'Événements</h2>';
            
            echo '<div class="test-form">';
            echo '<form method="GET">';
            echo '<label><strong>Sélectionnez un magasin :</strong></label><br>';
            echo '<select name="test_store_id">';
            echo '<option value="">-- Choisir un magasin --</option>';
            foreach ($stores as $store) {
                if (!empty($store->google_calendar_id)) {
                    $selected = isset($_GET['test_store_id']) && $_GET['test_store_id'] == $store->id ? 'selected' : '';
                    echo '<option value="' . $store->id . '" ' . $selected . '>' . esc_html($store->name) . ' (' . esc_html($store->google_calendar_id) . ')</option>';
                }
            }
            echo '</select><br><br>';
            
            echo '<label><strong>Date à tester :</strong></label><br>';
            echo '<input type="date" name="test_date" value="' . (isset($_GET['test_date']) ? $_GET['test_date'] : date('Y-m-d')) . '"><br><br>';
            
            echo '<button type="submit">🔍 Tester la Récupération</button>';
            echo '</form>';
            echo '</div>';
            
            if (isset($_GET['test_store_id']) && !empty($_GET['test_store_id'])) {
                $test_store_id = intval($_GET['test_store_id']);
                $test_date = isset($_GET['test_date']) ? sanitize_text_field($_GET['test_date']) : date('Y-m-d');
                
                $test_store = $wpdb->get_row($wpdb->prepare(
                    "SELECT google_calendar_id, name FROM {$wpdb->prefix}ibs_stores WHERE id = %d",
                    $test_store_id
                ));
                
                if ($test_store && !empty($test_store->google_calendar_id)) {
                    echo '<div class="info">🔄 <strong>Test en cours...</strong><br>';
                    echo 'Magasin : ' . esc_html($test_store->name) . '<br>';
                    echo 'Calendar ID : <code>' . esc_html($test_store->google_calendar_id) . '</code><br>';
                    echo 'Date : ' . esc_html($test_date) . '</div>';
                    
                    $events = $google->get_events_for_date($test_store->google_calendar_id, $test_date);
                    
                    if ($events === false || $events === null) {
                        echo '<div class="error">❌ <strong>Erreur lors de la récupération.</strong> Consultez <code>wp-content/debug.log</code></div>';
                    } elseif (empty($events)) {
                        echo '<div class="warning">⚠️ <strong>Aucun événement trouvé pour cette date.</strong><br>';
                        echo 'Vérifiez que des événements existent dans votre calendrier Google pour le ' . esc_html($test_date) . '</div>';
                    } else {
                        echo '<div class="success">✅ <strong>' . count($events) . ' événement(s) récupéré(s) !</strong></div>';
                        
                        echo '<table>';
                        echo '<tr><th>Heure</th><th>Durée (minutes)</th></tr>';
                        foreach ($events as $event) {
                            echo '<tr>';
                            echo '<td><code>' . esc_html($event->booking_time) . '</code></td>';
                            echo '<td>' . esc_html($event->duration) . ' min</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                }
            }
        }
        
        // 5. Logs récents
        echo '<h2>5. Logs Récents</h2>';
        
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file)) {
            $logs = file($log_file);
            $google_logs = array_filter($logs, function($line) {
                return strpos($line, 'IBS Google') !== false;
            });
            
            if (empty($google_logs)) {
                echo '<div class="info">ℹ️ Aucun log Google Calendar trouvé.</div>';
            } else {
                $recent_logs = array_slice($google_logs, -10);
                echo '<pre>' . esc_html(implode('', $recent_logs)) . '</pre>';
            }
        } else {
            echo '<div class="warning">⚠️ Fichier de log non trouvé. Activez WP_DEBUG_LOG dans wp-config.php</div>';
        }
        
        // Recommendations
        echo '<h2>📋 Recommandations</h2>';
        
        if (!$enabled) {
            echo '<div class="error">❌ Activez Google Calendar dans <a href="' . admin_url('admin.php?page=ikomiris-booking-settings') . '">Paramètres > Google Agenda</a></div>';
        }
        
        if (empty($client_id) || empty($client_secret) || empty($refresh_token)) {
            echo '<div class="error">❌ Configurez tous les credentials Google dans <a href="' . admin_url('admin.php?page=ikomiris-booking-settings') . '">Paramètres</a></div>';
        }
        
        $stores_without_calendar = array_filter($stores, function($store) {
            return empty($store->google_calendar_id);
        });
        
        if (!empty($stores_without_calendar)) {
            echo '<div class="warning">⚠️ ' . count($stores_without_calendar) . ' magasin(s) sans Calendar ID. Configurez-les dans <a href="' . admin_url('admin.php?page=ikomiris-booking-stores') . '">Gestion des Magasins</a></div>';
        }
        
        if (!file_exists($log_file)) {
            echo '<div class="warning">⚠️ Activez les logs pour faciliter le débogage :<br><code>define(\'WP_DEBUG\', true);<br>define(\'WP_DEBUG_LOG\', true);<br>define(\'WP_DEBUG_DISPLAY\', false);</code></div>';
        }
        ?>
        
        <hr>
        <p><a href="<?php echo admin_url('admin.php?page=ikomiris-booking-settings'); ?>">&larr; Retour aux paramètres</a></p>
    </div>
</body>
</html>

