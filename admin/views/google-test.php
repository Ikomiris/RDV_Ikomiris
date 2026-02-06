<?php
/**
 * Page de test et diagnostic Google Calendar
 */

if (!defined('ABSPATH')) {
    exit;
}

// Vérifier les permissions admin
if (!current_user_can('manage_options')) {
    wp_die('Accès non autorisé');
}

global $wpdb;
$table = $wpdb->prefix . 'ibs_settings';

// Récupérer les credentials Google
$google_enabled = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM $table WHERE setting_key = %s", 'google_calendar_enabled'));
$client_id = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM $table WHERE setting_key = %s", 'google_client_id'));
$client_secret = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM $table WHERE setting_key = %s", 'google_client_secret'));
$refresh_token = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM $table WHERE setting_key = %s", 'google_refresh_token'));

$has_credentials = !empty($client_id) && !empty($client_secret) && !empty($refresh_token);

// Test de connexion si demandé
$test_result = null;
if (isset($_POST['test_google_auth']) && wp_verify_nonce($_POST['_wpnonce'], 'ibs_google_test')) {
    $test_result = test_google_connection($client_id, $client_secret, $refresh_token);
}

// Effacer le cache du token si demandé
if (isset($_POST['clear_token_cache']) && wp_verify_nonce($_POST['_wpnonce'], 'ibs_google_test')) {
    delete_transient('ibs_google_access_token');
    echo '<div class="notice notice-success"><p>✅ Cache du token effacé avec succès</p></div>';
}

// Test de création d'événement si demandé (traiter AVANT l'affichage)
$event_creation_result = null;
if (isset($_POST['test_create_event']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'ibs_google_create_event')) {
    $stores_table = $wpdb->prefix . 'ibs_stores';
    $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
    $store = $wpdb->get_row($wpdb->prepare("SELECT google_calendar_id, name FROM $stores_table WHERE id = %d", $store_id));

    if (!$store || empty($store->google_calendar_id)) {
        $event_creation_result = ['success' => false, 'message' => 'Magasin introuvable ou Calendar ID non configuré'];
    } else {
        // Initialiser Google Calendar
        $google = new \IBS\Integrations\GoogleCalendar();

        if (!$google->is_configured()) {
            $event_creation_result = ['success' => false, 'message' => 'Google Calendar non configuré'];
        } else {
            // Créer un événement de test pour demain à 10h
            $test_date = date('Y-m-d', strtotime('+1 day'));
            $test_time = '10:00:00';
            $test_end_time = '10:30:00';

            $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(get_option('timezone_string') ?: 'UTC');
            $start_datetime = new \DateTime($test_date . ' ' . $test_time, $timezone);
            $end_datetime = new \DateTime($test_date . ' ' . $test_end_time, $timezone);

            // Convertir en UTC
            $start_datetime_utc = clone $start_datetime;
            $end_datetime_utc = clone $end_datetime;
            $start_datetime_utc->setTimezone(new \DateTimeZone('UTC'));
            $end_datetime_utc->setTimezone(new \DateTimeZone('UTC'));

            $event_data = [
                'summary' => '🧪 Test Réservation - ' . $store->name,
                'description' => 'Événement de test créé depuis l\'interface admin.\n\nSi vous voyez cet événement dans votre calendrier Google, la synchronisation fonctionne parfaitement !',
                'start' => $start_datetime_utc->format('Y-m-d\TH:i:s\Z'),
                'end' => $end_datetime_utc->format('Y-m-d\TH:i:s\Z'),
            ];

            $event_id = $google->create_event($store->google_calendar_id, $event_data);

            if ($event_id) {
                $event_creation_result = [
                    'success' => true,
                    'message' => 'Événement créé avec succès !',
                    'event_id' => $event_id,
                    'store_name' => $store->name,
                    'calendar_id' => $store->google_calendar_id,
                    'start_local' => $start_datetime->format('Y-m-d H:i:s T (P)'),
                    'end_local' => $end_datetime->format('Y-m-d H:i:s T (P)'),
                    'start_utc' => $start_datetime_utc->format('Y-m-d H:i:s T (P)'),
                    'end_utc' => $end_datetime_utc->format('Y-m-d H:i:s T (P)'),
                ];
            } else {
                $event_creation_result = ['success' => false, 'message' => 'Échec de la création de l\'événement. Consultez les logs WordPress (wp-content/debug.log ou wp-content/ibs-booking-debug.log)'];
            }
        }
    }
}

function test_google_connection($client_id, $client_secret, $refresh_token) {
    $result = [
        'success' => false,
        'messages' => [],
    ];

    // 1. Vérifier la présence des credentials
    if (empty($client_id)) {
        $result['messages'][] = ['type' => 'error', 'text' => 'Client ID manquant'];
        return $result;
    }
    if (empty($client_secret)) {
        $result['messages'][] = ['type' => 'error', 'text' => 'Client Secret manquant'];
        return $result;
    }
    if (empty($refresh_token)) {
        $result['messages'][] = ['type' => 'error', 'text' => 'Refresh Token manquant'];
        return $result;
    }

    $result['messages'][] = ['type' => 'success', 'text' => '✅ Credentials présents'];

    // 2. Tester la récupération d'un access token
    $result['messages'][] = ['type' => 'info', 'text' => '🔄 Test de récupération d\'un access token...'];

    $response = wp_remote_post('https://oauth2.googleapis.com/token', [
        'body' => [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token',
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        $result['messages'][] = ['type' => 'error', 'text' => '❌ Erreur de connexion : ' . $response->get_error_message()];
        return $result;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if ($status_code !== 200) {
        $result['messages'][] = ['type' => 'error', 'text' => '❌ Erreur HTTP ' . $status_code];

        if (isset($data['error'])) {
            $result['messages'][] = ['type' => 'error', 'text' => 'Erreur : ' . $data['error']];
        }
        if (isset($data['error_description'])) {
            $result['messages'][] = ['type' => 'error', 'text' => 'Description : ' . $data['error_description']];
        }

        // Messages d'aide selon le type d'erreur
        if (isset($data['error'])) {
            if ($data['error'] === 'invalid_grant') {
                $result['messages'][] = ['type' => 'warning', 'text' => '⚠️ Le Refresh Token est invalide ou a expiré. Vous devez générer un nouveau token.'];
            } elseif ($data['error'] === 'invalid_client') {
                $result['messages'][] = ['type' => 'warning', 'text' => '⚠️ Le Client ID ou Client Secret est incorrect. Vérifiez vos credentials dans Google Cloud Console.'];
            }
        }

        $result['messages'][] = ['type' => 'info', 'text' => 'Réponse complète : ' . $body];
        return $result;
    }

    if (!isset($data['access_token'])) {
        $result['messages'][] = ['type' => 'error', 'text' => '❌ Access token manquant dans la réponse'];
        return $result;
    }

    $access_token = $data['access_token'];
    $result['messages'][] = ['type' => 'success', 'text' => '✅ Access token obtenu avec succès'];
    $result['messages'][] = ['type' => 'info', 'text' => 'Token : ' . substr($access_token, 0, 20) . '...' . substr($access_token, -10)];

    // 3. Tester une requête API simple (liste des calendriers)
    $result['messages'][] = ['type' => 'info', 'text' => '🔄 Test d\'une requête API (liste des calendriers)...'];

    $api_response = wp_remote_get('https://www.googleapis.com/calendar/v3/users/me/calendarList', [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Accept' => 'application/json',
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($api_response)) {
        $result['messages'][] = ['type' => 'error', 'text' => '❌ Erreur lors de l\'appel API : ' . $api_response->get_error_message()];
        return $result;
    }

    $api_status = wp_remote_retrieve_response_code($api_response);
    $api_body = wp_remote_retrieve_body($api_response);
    $api_data = json_decode($api_body, true);

    if ($api_status !== 200) {
        $result['messages'][] = ['type' => 'error', 'text' => '❌ Erreur API HTTP ' . $api_status];

        if (isset($api_data['error']['message'])) {
            $result['messages'][] = ['type' => 'error', 'text' => 'Message : ' . $api_data['error']['message']];
        }

        $result['messages'][] = ['type' => 'info', 'text' => 'Réponse API : ' . $api_body];
        return $result;
    }

    $result['messages'][] = ['type' => 'success', 'text' => '✅ API Google Calendar accessible'];

    if (isset($api_data['items']) && is_array($api_data['items'])) {
        $calendar_count = count($api_data['items']);
        $result['messages'][] = ['type' => 'success', 'text' => '✅ ' . $calendar_count . ' calendrier(s) trouvé(s)'];

        // Afficher les calendriers
        foreach ($api_data['items'] as $calendar) {
            $cal_id = isset($calendar['id']) ? $calendar['id'] : 'N/A';
            $cal_name = isset($calendar['summary']) ? $calendar['summary'] : 'Sans nom';
            $cal_access = isset($calendar['accessRole']) ? $calendar['accessRole'] : 'N/A';

            $result['messages'][] = [
                'type' => 'info',
                'text' => '📅 ' . $cal_name . ' (ID: ' . $cal_id . ', Accès: ' . $cal_access . ')'
            ];
        }
    }

    $result['success'] = true;
    return $result;
}

?>

<div class="wrap">
    <h1>🔧 Test Google Calendar</h1>

    <?php if ($event_creation_result !== null): ?>
        <?php if ($event_creation_result['success']): ?>
            <div class="notice notice-success" style="margin-bottom: 20px;">
                <p>
                    <strong>✅ <?php echo esc_html($event_creation_result['message']); ?></strong><br>
                    Event ID : <code><?php echo esc_html($event_creation_result['event_id']); ?></code><br>
                    Magasin : <strong><?php echo esc_html($event_creation_result['store_name']); ?></strong><br>
                    Calendar ID : <code><?php echo esc_html($event_creation_result['calendar_id']); ?></code><br><br>

                    <strong>Horaires :</strong><br>
                    Date/Heure de début (local) : <?php echo esc_html($event_creation_result['start_local']); ?><br>
                    Date/Heure de fin (local) : <?php echo esc_html($event_creation_result['end_local']); ?><br>
                    Date/Heure de début (UTC) : <?php echo esc_html($event_creation_result['start_utc']); ?><br>
                    Date/Heure de fin (UTC) : <?php echo esc_html($event_creation_result['end_utc']); ?><br><br>

                    <strong>Vérification :</strong><br>
                    1. Allez sur <a href="https://calendar.google.com" target="_blank">Google Calendar</a><br>
                    2. Cherchez le calendrier : <strong><?php echo esc_html($event_creation_result['store_name']); ?></strong><br>
                    3. Vous devriez voir l'événement : <strong>🧪 Test Réservation</strong> pour <strong>demain à 10h00</strong>
                </p>
            </div>
        <?php else: ?>
            <div class="notice notice-error" style="margin-bottom: 20px;">
                <p><strong>❌ <?php echo esc_html($event_creation_result['message']); ?></strong></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px;">
        <h2>État de la configuration</h2>

        <table class="widefat" style="max-width: 800px;">
            <tr>
                <td style="width: 250px;"><strong>Google Calendar activé</strong></td>
                <td><?php echo $google_enabled === '1' ? '✅ Oui' : '❌ Non'; ?></td>
            </tr>
            <tr>
                <td><strong>Client ID</strong></td>
                <td><?php echo !empty($client_id) ? '✅ Configuré (' . substr($client_id, 0, 20) . '...)' : '❌ Manquant'; ?></td>
            </tr>
            <tr>
                <td><strong>Client Secret</strong></td>
                <td><?php echo !empty($client_secret) ? '✅ Configuré (' . substr($client_secret, 0, 10) . '...)' : '❌ Manquant'; ?></td>
            </tr>
            <tr>
                <td><strong>Refresh Token</strong></td>
                <td><?php echo !empty($refresh_token) ? '✅ Configuré (' . substr($refresh_token, 0, 20) . '...)' : '❌ Manquant'; ?></td>
            </tr>
            <tr>
                <td><strong>Cache access token</strong></td>
                <td>
                    <?php
                    $cached = get_transient('ibs_google_access_token');
                    echo $cached ? '✅ En cache' : '❌ Pas de cache';
                    ?>
                </td>
            </tr>
        </table>
    </div>

    <?php if ($has_credentials): ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px;">
            <h2>Tester la connexion</h2>
            <p>Cliquez sur le bouton ci-dessous pour tester l'authentification avec Google Calendar.</p>

            <form method="post" style="display: inline;">
                <?php wp_nonce_field('ibs_google_test'); ?>
                <button type="submit" name="test_google_auth" class="button button-primary">🔍 Tester la connexion Google</button>
            </form>

            <form method="post" style="display: inline; margin-left: 10px;">
                <?php wp_nonce_field('ibs_google_test'); ?>
                <button type="submit" name="clear_token_cache" class="button">🗑️ Effacer le cache du token</button>
            </form>
        </div>

        <!-- Test de création d'événement -->
        <?php if ($google_enabled === '1'): ?>
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px;">
                <h2>🧪 Test de création d'événement</h2>
                <p>Testez la création d'un événement dans vos calendriers Google pour vérifier que la synchronisation fonctionne.</p>

                <?php
                // Récupérer les magasins
                $stores_table = $wpdb->prefix . 'ibs_stores';
                $stores = $wpdb->get_results("SELECT id, name, google_calendar_id FROM $stores_table WHERE is_active = 1");
                ?>

                <?php if (!empty($stores)): ?>
                    <p><strong>Sélectionnez un magasin pour créer un événement de test :</strong></p>
                    <?php foreach ($stores as $store): ?>
                        <?php if (!empty($store->google_calendar_id)): ?>
                            <form method="post" style="margin: 10px 0;">
                                <?php wp_nonce_field('ibs_google_create_event'); ?>
                                <input type="hidden" name="store_id" value="<?php echo $store->id; ?>">
                                <button type="submit" name="test_create_event" class="button">
                                    🧪 Créer un événement test dans "<?php echo esc_html($store->name); ?>"
                                </button>
                                <span style="color: #666; margin-left: 10px;">
                                    (Calendar ID: <?php echo esc_html(substr($store->google_calendar_id, 0, 30)) . '...'; ?>)
                                </span>
                            </form>
                        <?php else: ?>
                            <p style="color: #999; margin: 10px 0;">
                                ⚠️ <?php echo esc_html($store->name); ?> - Calendar ID non configuré
                                (<a href="<?php echo admin_url('admin.php?page=ikomiris-booking-stores&action=edit&id=' . $store->id); ?>">Configurer</a>)
                            </p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="notice notice-warning"><p>Aucun magasin actif trouvé. <a href="<?php echo admin_url('admin.php?page=ikomiris-booking-stores'); ?>">Créer un magasin</a></p></div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 20px;">
                <h2>⚠️ Google Calendar désactivé</h2>
                <p style="color: #d63638;"><strong>L'intégration Google Calendar est actuellement désactivée.</strong></p>
                <p>Pour activer la synchronisation automatique des réservations :</p>
                <ol>
                    <li>Allez dans <a href="<?php echo admin_url('admin.php?page=ikomiris-booking-settings'); ?>">Paramètres → Google Agenda</a></li>
                    <li>Cochez la case <strong>"Synchroniser automatiquement les réservations avec Google Agenda"</strong></li>
                    <li>Enregistrez les paramètres</li>
                    <li>Revenez sur cette page pour tester la création d'événement</li>
                </ol>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="notice notice-warning" style="padding: 15px;">
            <p><strong>⚠️ Configuration incomplète</strong></p>
            <p>Veuillez d'abord configurer les credentials Google dans <a href="<?php echo admin_url('admin.php?page=ikomiris-booking-settings'); ?>">Paramètres → Google Agenda</a></p>
        </div>
    <?php endif; ?>

    <?php if ($test_result !== null): ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
            <h2>Résultats du test</h2>

            <?php foreach ($test_result['messages'] as $message): ?>
                <?php
                $color = 'black';
                $bg_color = '#f0f0f0';

                if ($message['type'] === 'error') {
                    $color = '#d63638';
                    $bg_color = '#fcf0f1';
                } elseif ($message['type'] === 'success') {
                    $color = '#00a32a';
                    $bg_color = '#f0f6fc';
                } elseif ($message['type'] === 'warning') {
                    $color = '#dba617';
                    $bg_color = '#fcf9e8';
                } elseif ($message['type'] === 'info') {
                    $color = '#2271b1';
                    $bg_color = '#f0f6fc';
                }
                ?>
                <div style="padding: 10px; margin: 5px 0; background: <?php echo $bg_color; ?>; border-left: 4px solid <?php echo $color; ?>; color: <?php echo $color; ?>;">
                    <?php echo esc_html($message['text']); ?>
                </div>
            <?php endforeach; ?>

            <?php if (!$test_result['success']): ?>
                <div style="margin-top: 20px; padding: 15px; background: #fcf9e8; border: 1px solid #dba617; border-radius: 5px;">
                    <h3 style="margin-top: 0;">📚 Comment résoudre ?</h3>

                    <h4>1. Générer un nouveau Refresh Token</h4>
                    <ol>
                        <li>Allez sur <a href="https://developers.google.com/oauthplayground/" target="_blank">OAuth 2.0 Playground</a></li>
                        <li>Cliquez sur l'icône ⚙️ en haut à droite</li>
                        <li>Cochez "Use your own OAuth credentials"</li>
                        <li>Entrez votre Client ID et Client Secret</li>
                        <li>Dans "Step 1", cherchez "Calendar API v3"</li>
                        <li>Sélectionnez au minimum :
                            <ul>
                                <li><code>https://www.googleapis.com/auth/calendar</code> (pour créer des événements)</li>
                                <li><code>https://www.googleapis.com/auth/calendar.events</code> (pour gérer les événements)</li>
                            </ul>
                        </li>
                        <li>Cliquez sur "Authorize APIs"</li>
                        <li>Connectez-vous avec le compte Google qui possède les calendriers</li>
                        <li>Cliquez sur "Exchange authorization code for tokens"</li>
                        <li>Copiez le <strong>Refresh token</strong></li>
                        <li>Collez-le dans <a href="<?php echo admin_url('admin.php?page=ikomiris-booking-settings'); ?>">Paramètres → Google Agenda</a></li>
                    </ol>

                    <h4>2. Vérifier les permissions dans Google Cloud Console</h4>
                    <ol>
                        <li>Allez sur <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                        <li>Sélectionnez votre projet</li>
                        <li>Menu : APIs et services → Écran de consentement OAuth</li>
                        <li>Vérifiez que les scopes suivants sont autorisés :
                            <ul>
                                <li><code>https://www.googleapis.com/auth/calendar</code></li>
                                <li><code>https://www.googleapis.com/auth/calendar.events</code></li>
                            </ul>
                        </li>
                        <li>Menu : APIs et services → API activées</li>
                        <li>Vérifiez que "Google Calendar API" est activée</li>
                    </ol>
                </div>
            <?php else: ?>
                <div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border: 1px solid #00a32a; border-radius: 5px;">
                    <h3 style="margin-top: 0; color: #00a32a;">✅ Connexion réussie !</h3>
                    <p>L'authentification Google Calendar fonctionne correctement. Les réservations devraient être synchronisées.</p>
                    <p>Si les réservations n'apparaissent toujours pas dans Google Calendar, vérifiez que :</p>
                    <ul>
                        <li>Le bon Calendar ID est configuré dans chaque magasin</li>
                        <li>L'intégration Google Calendar est activée dans les paramètres</li>
                        <li>Les logs WordPress ne montrent pas d'autres erreurs</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
