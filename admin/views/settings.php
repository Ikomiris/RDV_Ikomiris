<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'ibs_settings';

// Vérifier que la table existe
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;

if (!$table_exists) {
    echo '<div class="notice notice-error"><p><strong>Erreur :</strong> La table des paramètres n\'existe pas. Veuillez désactiver puis réactiver le plugin.</p></div>';
}

// Afficher le message de succès après redirection
if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
    echo '<div class="notice notice-success is-dismissible"><p><strong>Paramètres enregistrés avec succès !</strong></p></div>';
}

// Sauvegarder les paramètres
if (isset($_POST['ibs_save_settings']) && check_admin_referer('ibs_save_settings_nonce', '_wpnonce', false)) {
    
    if (!$table_exists) {
        echo '<div class="notice notice-error"><p><strong>Erreur :</strong> Impossible d\'enregistrer les paramètres - la table n\'existe pas.</p></div>';
    } else {
        $errors = [];
        $success_count = 0;
        
        // Paramètres texte/nombre
        $text_settings = [
            'min_booking_delay',
            'max_booking_delay',
            'slot_interval',
            'theme_color',
            'theme_secondary_color',
            'terms_conditions',
            'confirmation_text',
            'google_client_id',
            'google_client_secret',
            'email_admin_address',
            'email_reminder_hours',
        ];
        
        // Paramètres checkbox
        $checkbox_settings = [
            'show_prices',
            'google_calendar_enabled',
            'email_admin_notification',
            'email_customer_confirmation',
            'email_customer_reminder',
        ];
        
        // Traiter les paramètres texte
        foreach ($text_settings as $key) {
            $value = isset($_POST[$key]) ? sanitize_textarea_field($_POST[$key]) : '';
            
            // Vérifier si le paramètre existe déjà
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE setting_key = %s",
                $key
            ));
            
            if ($existing) {
                // Mettre à jour
                $result = $wpdb->update(
                    $table,
                    ['setting_value' => $value],
                    ['setting_key' => $key],
                    ['%s'],
                    ['%s']
                );
                
                if ($result === false) {
                    $errors[] = "Erreur lors de la mise à jour de '$key' : " . $wpdb->last_error;
                } else {
                    $success_count++;
                }
            } else {
                // Insérer
                $result = $wpdb->insert(
                    $table,
                    [
                        'setting_key' => $key,
                        'setting_value' => $value
                    ],
                    ['%s', '%s']
                );
                
                if ($result === false) {
                    $errors[] = "Erreur lors de l'insertion de '$key' : " . $wpdb->last_error;
                } else {
                    $success_count++;
                }
            }
        }
        
        // Traiter les checkboxes (0 si non cochée, 1 si cochée)
        foreach ($checkbox_settings as $key) {
            $value = isset($_POST[$key]) ? '1' : '0';
            
            // Vérifier si le paramètre existe déjà
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE setting_key = %s",
                $key
            ));
            
            if ($existing) {
                // Mettre à jour
                $result = $wpdb->update(
                    $table,
                    ['setting_value' => $value],
                    ['setting_key' => $key],
                    ['%s'],
                    ['%s']
                );
                
                if ($result === false) {
                    $errors[] = "Erreur lors de la mise à jour de '$key' : " . $wpdb->last_error;
                } else {
                    $success_count++;
                }
            } else {
                // Insérer
                $result = $wpdb->insert(
                    $table,
                    [
                        'setting_key' => $key,
                        'setting_value' => $value
                    ],
                    ['%s', '%s']
                );
                
                if ($result === false) {
                    $errors[] = "Erreur lors de l'insertion de '$key' : " . $wpdb->last_error;
                } else {
                    $success_count++;
                }
            }
        }
        
        // Afficher les messages
        if (!empty($errors)) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Erreurs détectées :</strong></p><ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
        }
        
        if ($success_count > 0) {
            // Redirection pour afficher les nouvelles valeurs et éviter la resoumission du formulaire
            $redirect_url = add_query_arg('settings-updated', 'true', admin_url('admin.php?page=ikomiris-booking-settings'));
            wp_redirect($redirect_url);
            exit;
        }
    }
}

// Fonction pour récupérer un paramètre
function get_setting($key, $default = '') {
    global $wpdb;
    static $settings_cache = null;
    
    // Charger tous les paramètres une seule fois
    if ($settings_cache === null) {
        $table = $wpdb->prefix . 'ibs_settings';
        $results = $wpdb->get_results("SELECT setting_key, setting_value FROM $table", OBJECT_K);
        $settings_cache = is_array($results) ? $results : [];
        
        // Debug mode
        if (current_user_can('manage_options') && isset($_GET['debug'])) {
            echo '<div class="notice notice-info"><p>🔍 Debug : ' . count($settings_cache) . ' paramètres chargés depuis la base</p></div>';
        }
    }
    
    $value = isset($settings_cache[$key]) ? $settings_cache[$key]->setting_value : $default;
    
    // Debug mode - afficher chaque valeur récupérée
    if (current_user_can('manage_options') && isset($_GET['debug'])) {
        error_log("get_setting('$key') = '$value' (default: '$default')");
    }
    
    return $value;
}
?>

<div class="wrap">
    <h1>Paramètres du Plugin</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('ibs_save_settings_nonce'); ?>
        
        <h2>Paramètres de réservation</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Délai minimum de réservation (heures)</label></th>
                <td>
                    <input type="number" name="min_booking_delay" value="<?php echo esc_attr(get_setting('min_booking_delay', '2')); ?>" min="0">
                    <p class="description">Les clients ne pourront réserver qu'à partir de X heures dans le futur.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Délai maximum de réservation (jours)</label></th>
                <td>
                    <input type="number" name="max_booking_delay" value="<?php echo esc_attr(get_setting('max_booking_delay', '90')); ?>" min="1">
                    <p class="description">Les clients pourront réserver jusqu'à X jours à l'avance.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Intervalle des créneaux (minutes)</label></th>
                <td>
                    <input type="number" name="slot_interval" value="<?php echo esc_attr(get_setting('slot_interval', '10')); ?>" min="5" step="5">
                    <p class="description">Créneaux horaires proposés tous les X minutes (recommandé: 10).</p>
                </td>
            </tr>
        </table>
        
        <h2>Apparence</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Couleur principale</label></th>
                <td>
                    <input type="text" name="theme_color" value="<?php echo esc_attr(get_setting('theme_color', '#0073aa')); ?>" class="ibs-color-picker">
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Afficher les prix</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="show_prices" value="1" <?php checked(get_setting('show_prices', '1'), '1'); ?>>
                        Afficher les prix des services
                    </label>
                </td>
            </tr>
        </table>
        
        <h2>Textes personnalisés</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Conditions générales</label></th>
                <td>
                    <textarea name="terms_conditions" rows="5" class="large-text"><?php echo esc_textarea(get_setting('terms_conditions')); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Texte de confirmation</label></th>
                <td>
                    <textarea name="confirmation_text" rows="3" class="large-text"><?php echo esc_textarea(get_setting('confirmation_text')); ?></textarea>
                </td>
            </tr>
        </table>
        
        <h2>Google Agenda</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Activer Google Agenda</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="google_calendar_enabled" value="1" <?php checked(get_setting('google_calendar_enabled'), '1'); ?>>
                        Synchroniser automatiquement les réservations avec Google Agenda
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Client ID</label></th>
                <td>
                    <input type="text" name="google_client_id" value="<?php echo esc_attr(get_setting('google_client_id')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Client Secret</label></th>
                <td>
                    <input type="text" name="google_client_secret" value="<?php echo esc_attr(get_setting('google_client_secret')); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        
        <h2>Notifications Email</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Notification admin</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="email_admin_notification" value="1" <?php checked(get_setting('email_admin_notification', '1'), '1'); ?>>
                        Recevoir un email à chaque nouvelle réservation
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Email admin</label></th>
                <td>
                    <input type="email" name="email_admin_address" value="<?php echo esc_attr(get_setting('email_admin_address')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Confirmation client</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="email_customer_confirmation" value="1" <?php checked(get_setting('email_customer_confirmation', '1'), '1'); ?>>
                        Envoyer un email de confirmation au client
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Rappel client</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="email_customer_reminder" value="1" <?php checked(get_setting('email_customer_reminder', '1'), '1'); ?>>
                        Envoyer un email de rappel au client
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Délai du rappel (heures)</label></th>
                <td>
                    <input type="number" name="email_reminder_hours" value="<?php echo esc_attr(get_setting('email_reminder_hours', '24')); ?>" min="1">
                    <p class="description">Envoyer le rappel X heures avant le rendez-vous.</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" name="ibs_save_settings" class="button button-primary">Enregistrer les paramètres</button>
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('.ibs-color-picker').wpColorPicker();
});
</script>
