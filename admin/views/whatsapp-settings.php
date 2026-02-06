<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'ibs_settings';

// Afficher le message de succes apres redirection
if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
    echo '<div class="notice notice-success is-dismissible"><p><strong>Parametres WhatsApp enregistres avec succes !</strong></p></div>';
}

// Sauvegarder les parametres
if (isset($_POST['ibs_save_whatsapp_settings']) && check_admin_referer('ibs_save_whatsapp_settings_nonce', '_wpnonce', false)) {

    $errors = [];
    $success_count = 0;

    // Parametres texte
    $text_settings = [
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_whatsapp_number',
        'whatsapp_confirmation_template',
        'whatsapp_cancellation_template',
        'whatsapp_reminder_template',
    ];

    // Parametres checkbox
    $checkbox_settings = [
        'whatsapp_enabled',
        'whatsapp_customer_confirmation',
        'whatsapp_customer_cancellation',
        'whatsapp_customer_reminder',
    ];

    // Traiter les parametres texte
    foreach ($text_settings as $key) {
        $value = isset($_POST[$key]) ? sanitize_textarea_field($_POST[$key]) : '';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE setting_key = %s",
            $key
        ));

        if ($existing) {
            $result = $wpdb->update(
                $table,
                ['setting_value' => $value],
                ['setting_key' => $key],
                ['%s'],
                ['%s']
            );
        } else {
            $result = $wpdb->insert(
                $table,
                [
                    'setting_key' => $key,
                    'setting_value' => $value
                ],
                ['%s', '%s']
            );
        }

        if ($result === false) {
            $errors[] = "Erreur lors de la sauvegarde de '$key'";
        } else {
            $success_count++;
        }
    }

    // Traiter les checkboxes
    foreach ($checkbox_settings as $key) {
        $value = isset($_POST[$key]) ? '1' : '0';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE setting_key = %s",
            $key
        ));

        if ($existing) {
            $result = $wpdb->update(
                $table,
                ['setting_value' => $value],
                ['setting_key' => $key],
                ['%s'],
                ['%s']
            );
        } else {
            $result = $wpdb->insert(
                $table,
                [
                    'setting_key' => $key,
                    'setting_value' => $value
                ],
                ['%s', '%s']
            );
        }

        if ($result === false) {
            $errors[] = "Erreur lors de la sauvegarde de '$key'";
        } else {
            $success_count++;
        }
    }

    if (!empty($errors)) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>Erreurs :</strong></p><ul>';
        foreach ($errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }

    if ($success_count > 0 && empty($errors)) {
        $redirect_url = add_query_arg('settings-updated', 'true', admin_url('admin.php?page=ikomiris-booking-whatsapp'));
        wp_redirect($redirect_url);
        exit;
    }
}

// Fonction pour recuperer un parametre
function get_whatsapp_setting($key, $default = '') {
    global $wpdb;
    static $settings_cache = null;

    if ($settings_cache === null) {
        $table = $wpdb->prefix . 'ibs_settings';
        $results = $wpdb->get_results("SELECT setting_key, setting_value FROM $table WHERE setting_key LIKE 'whatsapp_%' OR setting_key LIKE 'twilio_%'", OBJECT_K);
        $settings_cache = is_array($results) ? $results : [];
    }

    return isset($settings_cache[$key]) ? $settings_cache[$key]->setting_value : $default;
}

// Templates par defaut
$default_confirmation = "Bonjour {customer_firstname},\n\nVotre reservation a ete confirmee !\n\nService : {service_name}\nDate : {booking_date}\nHeure : {booking_time}\nLieu : {store_name}\n{store_address}\n\nA bientot !";
$default_cancellation = "Bonjour {customer_firstname},\n\nVotre reservation a bien ete annulee.\n\nService : {service_name}\nDate : {booking_date}\nHeure : {booking_time}\n\nNous esperons vous revoir bientot !";
$default_reminder = "Bonjour {customer_firstname},\n\nRappel : vous avez un rendez-vous demain !\n\nService : {service_name}\nDate : {booking_date}\nHeure : {booking_time}\nLieu : {store_name}\n{store_address}\n\nA demain !";
?>

<div class="wrap">
    <h1>Notifications WhatsApp (Twilio)</h1>

    <div class="notice notice-info">
        <p><strong>Configuration WhatsApp via Twilio</strong></p>
        <p>Pour envoyer des notifications WhatsApp, vous devez :</p>
        <ol>
            <li>Creer un compte sur <a href="https://www.twilio.com/" target="_blank">Twilio</a></li>
            <li>Activer le canal WhatsApp dans votre console Twilio</li>
            <li>Connecter votre numero WhatsApp Business (ou utiliser le sandbox Twilio pour les tests)</li>
            <li>Recuperer votre Account SID, Auth Token et numero WhatsApp</li>
        </ol>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('ibs_save_whatsapp_settings_nonce'); ?>

        <h2>Configuration Twilio</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Activer WhatsApp</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="whatsapp_enabled" value="1" <?php checked(get_whatsapp_setting('whatsapp_enabled', '0'), '1'); ?>>
                        Envoyer des notifications WhatsApp aux clients
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Account SID</label></th>
                <td>
                    <input type="text" name="twilio_account_sid" value="<?php echo esc_attr(get_whatsapp_setting('twilio_account_sid')); ?>" class="regular-text">
                    <p class="description">Votre Account SID Twilio (commence par AC...)</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Auth Token</label></th>
                <td>
                    <input type="password" name="twilio_auth_token" value="<?php echo esc_attr(get_whatsapp_setting('twilio_auth_token')); ?>" class="regular-text">
                    <p class="description">Votre Auth Token Twilio (garder secret !)</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Numero WhatsApp</label></th>
                <td>
                    <input type="text" name="twilio_whatsapp_number" value="<?php echo esc_attr(get_whatsapp_setting('twilio_whatsapp_number')); ?>" class="regular-text" placeholder="+14155238886">
                    <p class="description">
                        Votre numero WhatsApp Business au format international (ex: +33612345678)<br>
                        Pour les tests, utilisez le numero sandbox Twilio : +14155238886
                    </p>
                </td>
            </tr>
        </table>

        <h2>Types de notifications</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Confirmation de reservation</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="whatsapp_customer_confirmation" value="1" <?php checked(get_whatsapp_setting('whatsapp_customer_confirmation', '1'), '1'); ?>>
                        Envoyer un message WhatsApp de confirmation au client
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Annulation de reservation</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="whatsapp_customer_cancellation" value="1" <?php checked(get_whatsapp_setting('whatsapp_customer_cancellation', '1'), '1'); ?>>
                        Envoyer un message WhatsApp lors de l'annulation
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Rappel de rendez-vous</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="whatsapp_customer_reminder" value="1" <?php checked(get_whatsapp_setting('whatsapp_customer_reminder', '1'), '1'); ?>>
                        Envoyer un rappel WhatsApp avant le rendez-vous
                    </label>
                </td>
            </tr>
        </table>

        <h2>Modeles de messages</h2>
        <p class="description">
            Variables disponibles : <code>{customer_firstname}</code>, <code>{customer_lastname}</code>, <code>{service_name}</code>,
            <code>{booking_date}</code>, <code>{booking_time}</code>, <code>{store_name}</code>, <code>{store_address}</code>,
            <code>{store_phone}</code>, <code>{booking_id}</code>
        </p>

        <table class="form-table">
            <tr>
                <th scope="row"><label>Message de confirmation</label></th>
                <td>
                    <textarea name="whatsapp_confirmation_template" rows="8" class="large-text"><?php echo esc_textarea(get_whatsapp_setting('whatsapp_confirmation_template', $default_confirmation)); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Message d'annulation</label></th>
                <td>
                    <textarea name="whatsapp_cancellation_template" rows="6" class="large-text"><?php echo esc_textarea(get_whatsapp_setting('whatsapp_cancellation_template', $default_cancellation)); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Message de rappel</label></th>
                <td>
                    <textarea name="whatsapp_reminder_template" rows="8" class="large-text"><?php echo esc_textarea(get_whatsapp_setting('whatsapp_reminder_template', $default_reminder)); ?></textarea>
                </td>
            </tr>
        </table>

        <h2>Test de connexion</h2>
        <p>
            <button type="button" id="ibs-test-whatsapp" class="button button-secondary">Tester la connexion Twilio</button>
            <span id="ibs-test-whatsapp-result" style="margin-left: 10px;"></span>
        </p>

        <p class="submit">
            <button type="submit" name="ibs_save_whatsapp_settings" class="button button-primary">Enregistrer les parametres</button>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#ibs-test-whatsapp').on('click', function() {
        var $button = $(this);
        var $result = $('#ibs-test-whatsapp-result');

        var accountSid = $('input[name="twilio_account_sid"]').val();
        var authToken = $('input[name="twilio_auth_token"]').val();

        if (!accountSid || !authToken) {
            $result.html('<span style="color: red;">Veuillez remplir le Account SID et Auth Token</span>');
            return;
        }

        $button.prop('disabled', true);
        $result.html('<span style="color: blue;">Test en cours...</span>');

        // Test AJAX pour verifier les credentials Twilio
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ibs_test_twilio_connection',
                account_sid: accountSid,
                auth_token: authToken,
                nonce: '<?php echo wp_create_nonce('ibs_test_twilio'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color: green;">Connexion reussie !</span>');
                } else {
                    $result.html('<span style="color: red;">Erreur : ' + response.data + '</span>');
                }
            },
            error: function() {
                $result.html('<span style="color: red;">Erreur de connexion</span>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
});
</script>
