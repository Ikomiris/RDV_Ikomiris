<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'ibs_settings';

// Sauvegarder les paramètres
if (isset($_POST['ibs_save_settings'])) {
    check_admin_referer('ibs_save_settings_nonce');
    
    $settings = [
        'min_booking_delay',
        'max_booking_delay',
        'slot_interval',
        'theme_color',
        'theme_secondary_color',
        'show_prices',
        'terms_conditions',
        'confirmation_text',
        'google_calendar_enabled',
        'google_client_id',
        'google_client_secret',
        'email_admin_notification',
        'email_admin_address',
        'email_customer_confirmation',
        'email_customer_reminder',
        'email_reminder_hours',
    ];
    
    foreach ($settings as $key) {
        $value = isset($_POST[$key]) ? sanitize_textarea_field($_POST[$key]) : '';
        $wpdb->update(
            $table,
            ['setting_value' => $value],
            ['setting_key' => $key]
        );
    }
    
    echo '<div class="notice notice-success"><p>Paramètres enregistrés avec succès.</p></div>';
}

// Récupérer les paramètres
$settings = $wpdb->get_results("SELECT setting_key, setting_value FROM $table", OBJECT_K);
function get_setting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key]->setting_value : $default;
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
                    <input type="number" name="min_booking_delay" value="<?php echo get_setting('min_booking_delay', '2'); ?>" min="0">
                    <p class="description">Les clients ne pourront réserver qu'à partir de X heures dans le futur.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Délai maximum de réservation (jours)</label></th>
                <td>
                    <input type="number" name="max_booking_delay" value="<?php echo get_setting('max_booking_delay', '90'); ?>" min="1">
                    <p class="description">Les clients pourront réserver jusqu'à X jours à l'avance.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Intervalle des créneaux (minutes)</label></th>
                <td>
                    <input type="number" name="slot_interval" value="<?php echo get_setting('slot_interval', '10'); ?>" min="5" step="5">
                    <p class="description">Créneaux horaires proposés tous les X minutes (recommandé: 10).</p>
                </td>
            </tr>
        </table>
        
        <h2>Apparence</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Couleur principale</label></th>
                <td>
                    <input type="text" name="theme_color" value="<?php echo get_setting('theme_color', '#0073aa'); ?>" class="ibs-color-picker">
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
                    <textarea name="terms_conditions" rows="5" class="large-text"><?php echo get_setting('terms_conditions'); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Texte de confirmation</label></th>
                <td>
                    <textarea name="confirmation_text" rows="3" class="large-text"><?php echo get_setting('confirmation_text'); ?></textarea>
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
                    <input type="text" name="google_client_id" value="<?php echo get_setting('google_client_id'); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Client Secret</label></th>
                <td>
                    <input type="text" name="google_client_secret" value="<?php echo get_setting('google_client_secret'); ?>" class="regular-text">
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
                    <input type="email" name="email_admin_address" value="<?php echo get_setting('email_admin_address'); ?>" class="regular-text">
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
                    <input type="number" name="email_reminder_hours" value="<?php echo get_setting('email_reminder_hours', '24'); ?>" min="1">
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
