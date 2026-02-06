<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Fonction helper pour récupérer un setting email
function get_email_setting($key, $default = '') {
    global $wpdb;
    static $cache = null;

    if ($cache === null) {
        $results = $wpdb->get_results(
            "SELECT setting_key, setting_value FROM {$wpdb->prefix}ibs_settings WHERE setting_key LIKE 'email_%'",
            OBJECT_K
        );
        $cache = is_array($results) ? $results : [];
    }

    return isset($cache[$key]) ? $cache[$key]->setting_value : $default;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_email_settings') {
    check_admin_referer('ibs_email_customization_action');

    $type = sanitize_text_field($_POST['email_type']);

    // Définir les clés à sauvegarder
    $keys_to_save = [
        "email_{$type}_header_color",
        "email_{$type}_button_color",
        "email_{$type}_background_color",
        "email_{$type}_text_color",
        "email_{$type}_title",
        "email_{$type}_intro_text",
        "email_{$type}_footer_text",
    ];

    // Sauvegarder le logo global
    if (isset($_POST['email_global_logo_url'])) {
        $logo_url = esc_url_raw($_POST['email_global_logo_url']);
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$wpdb->prefix}ibs_settings WHERE setting_key = %s",
            'email_global_logo_url'
        ));

        if ($existing !== null) {
            $wpdb->update(
                $wpdb->prefix . 'ibs_settings',
                ['setting_value' => $logo_url],
                ['setting_key' => 'email_global_logo_url'],
                ['%s'],
                ['%s']
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'ibs_settings',
                ['setting_key' => 'email_global_logo_url', 'setting_value' => $logo_url],
                ['%s', '%s']
            );
        }
    }

    // Sauvegarder chaque paramètre
    foreach ($keys_to_save as $key) {
        if (!isset($_POST[$key])) continue;

        $value = '';
        if (strpos($key, '_color') !== false) {
            $value = sanitize_hex_color($_POST[$key]);
        } else {
            $value = sanitize_textarea_field($_POST[$key]);
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$wpdb->prefix}ibs_settings WHERE setting_key = %s",
            $key
        ));

        if ($existing !== null) {
            $wpdb->update(
                $wpdb->prefix . 'ibs_settings',
                ['setting_value' => $value],
                ['setting_key' => $key],
                ['%s'],
                ['%s']
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'ibs_settings',
                ['setting_key' => $key, 'setting_value' => $value],
                ['%s', '%s']
            );
        }
    }

    echo '<div class="notice notice-success"><p>✅ Personnalisation enregistrée pour : ' . esc_html(ucfirst(str_replace('_', ' ', $type))) . '</p></div>';
}

// Types d'emails et leurs valeurs par défaut
$email_types = [
    'customer_confirmation' => [
        'label' => 'Confirmation client',
        'defaults' => [
            'header_color' => '#3498db',
            'button_color' => '#e74c3c',
            'background_color' => '#f9f9f9',
            'text_color' => '#333333',
            'title' => 'Confirmation de réservation',
            'intro_text' => 'Votre réservation a été confirmée avec succès !',
            'footer_text' => 'Cet email a été envoyé automatiquement, merci de ne pas y répondre.',
        ]
    ],
    'admin_notification' => [
        'label' => 'Notification admin',
        'defaults' => [
            'header_color' => '#27ae60',
            'button_color' => '#3498db',
            'background_color' => '#f9f9f9',
            'text_color' => '#333333',
            'title' => 'Nouvelle réservation reçue',
            'intro_text' => 'Une nouvelle réservation vient d\'être effectuée sur votre site.',
            'footer_text' => 'Notification automatique du système de réservation Ikomiris',
        ]
    ],
    'reminder' => [
        'label' => 'Rappel 24h',
        'defaults' => [
            'header_color' => '#f39c12',
            'button_color' => '#3498db',
            'background_color' => '#f9f9f9',
            'text_color' => '#333333',
            'title' => 'Rappel de rendez-vous',
            'intro_text' => 'Nous vous rappelons que vous avez un rendez-vous demain.',
            'footer_text' => 'Nous vous attendons avec plaisir !',
        ]
    ],
    'customer_cancellation' => [
        'label' => 'Confirmation d\'annulation (client)',
        'defaults' => [
            'header_color' => '#e74c3c',
            'button_color' => '#3498db',
            'background_color' => '#f9f9f9',
            'text_color' => '#333333',
            'title' => 'Confirmation d\'annulation',
            'intro_text' => 'Votre réservation a bien été annulée.',
            'footer_text' => 'Nous espérons vous revoir bientôt !',
        ]
    ],
    'admin_cancellation' => [
        'label' => 'Notification d\'annulation (admin)',
        'defaults' => [
            'header_color' => '#e67e22',
            'button_color' => '#3498db',
            'background_color' => '#f9f9f9',
            'text_color' => '#333333',
            'title' => 'Annulation de réservation',
            'intro_text' => 'Une réservation vient d\'être annulée par le client.',
            'footer_text' => 'Notification automatique du système de réservation Ikomiris',
        ]
    ],
];

// Type sélectionné (par défaut : customer_confirmation)
$selected_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'customer_confirmation';
if (!isset($email_types[$selected_type])) {
    $selected_type = 'customer_confirmation';
}

$current_type = $email_types[$selected_type];
$defaults = $current_type['defaults'];

// Récupérer les valeurs actuelles
$current = [];
foreach ($defaults as $key => $default) {
    $current[$key] = get_email_setting("email_{$selected_type}_{$key}", $default);
}

// Logo global
$global_logo = get_email_setting('email_global_logo_url', '');
?>

<div class="wrap">
    <h1 class="wp-heading-inline">📧 Personnalisation des Emails</h1>
    <hr class="wp-header-end">

    <p class="description">Personnalisez l'apparence et les textes de vos emails de réservation. Les modifications s'appliquent immédiatement aux nouveaux emails envoyés.</p>

    <!-- Sélecteur de type d'email -->
    <div style="background: white; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;">
        <label for="email-type-selector" style="font-weight: 600; margin-right: 10px;">Type d'email à personnaliser :</label>
        <select id="email-type-selector" style="min-width: 300px;">
            <?php foreach ($email_types as $type_key => $type_data): ?>
                <option value="<?php echo esc_attr($type_key); ?>" <?php selected($selected_type, $type_key); ?>>
                    <?php echo esc_html($type_data['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 500px; gap: 30px; margin-top: 20px;">

        <!-- Formulaire de personnalisation -->
        <div>
            <form method="post" action="" id="email-form">
                <?php wp_nonce_field('ibs_email_customization_action'); ?>
                <input type="hidden" name="action" value="save_email_settings">
                <input type="hidden" name="email_type" value="<?php echo esc_attr($selected_type); ?>">

                <!-- Logo global -->
                <div class="postbox" style="margin-bottom: 20px;">
                    <div class="postbox-header">
                        <h2>🖼️ Logo (global pour tous les emails)</h2>
                    </div>
                    <div class="inside" style="padding: 20px;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="email_global_logo_url">URL du logo</label></th>
                                <td>
                                    <input type="text" name="email_global_logo_url" id="email_global_logo_url"
                                           value="<?php echo esc_attr($global_logo); ?>"
                                           class="regular-text" placeholder="https://...">
                                    <button type="button" class="button ibs-upload-logo">Choisir une image</button>
                                    <p class="description">Logo affiché en haut de tous les emails (recommandé : 200x60px)</p>
                                    <div id="logo-preview" style="margin-top: 10px;">
                                        <?php if ($global_logo): ?>
                                            <img src="<?php echo esc_url($global_logo); ?>" style="max-width: 200px; max-height: 80px;">
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Couleurs -->
                <div class="postbox" style="margin-bottom: 20px;">
                    <div class="postbox-header">
                        <h2>🎨 Couleurs</h2>
                    </div>
                    <div class="inside" style="padding: 20px;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="header_color">Couleur du header</label></th>
                                <td>
                                    <input type="text" name="email_<?php echo $selected_type; ?>_header_color"
                                           id="header_color"
                                           value="<?php echo esc_attr($current['header_color']); ?>"
                                           class="color-picker">
                                    <p class="description">Bandeau supérieur de l'email</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="button_color">Couleur du bouton</label></th>
                                <td>
                                    <input type="text" name="email_<?php echo $selected_type; ?>_button_color"
                                           id="button_color"
                                           value="<?php echo esc_attr($current['button_color']); ?>"
                                           class="color-picker">
                                    <p class="description">Bouton d'action principal</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="background_color">Couleur de fond</label></th>
                                <td>
                                    <input type="text" name="email_<?php echo $selected_type; ?>_background_color"
                                           id="background_color"
                                           value="<?php echo esc_attr($current['background_color']); ?>"
                                           class="color-picker">
                                    <p class="description">Fond de la section principale</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="text_color">Couleur du texte</label></th>
                                <td>
                                    <input type="text" name="email_<?php echo $selected_type; ?>_text_color"
                                           id="text_color"
                                           value="<?php echo esc_attr($current['text_color']); ?>"
                                           class="color-picker">
                                    <p class="description">Couleur du texte dans le contenu</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Textes -->
                <div class="postbox" style="margin-bottom: 20px;">
                    <div class="postbox-header">
                        <h2>✍️ Textes personnalisables</h2>
                    </div>
                    <div class="inside" style="padding: 20px;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="title">Titre</label></th>
                                <td>
                                    <input type="text" name="email_<?php echo $selected_type; ?>_title"
                                           id="title"
                                           value="<?php echo esc_attr($current['title']); ?>"
                                           class="large-text">
                                    <p class="description">Titre principal du header</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="intro_text">Texte d'introduction</label></th>
                                <td>
                                    <textarea name="email_<?php echo $selected_type; ?>_intro_text"
                                              id="intro_text"
                                              rows="3"
                                              class="large-text"><?php echo esc_textarea($current['intro_text']); ?></textarea>
                                    <p class="description">Paragraphe après le salut client</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="footer_text">Texte du footer</label></th>
                                <td>
                                    <textarea name="email_<?php echo $selected_type; ?>_footer_text"
                                              id="footer_text"
                                              rows="2"
                                              class="large-text"><?php echo esc_textarea($current['footer_text']); ?></textarea>
                                    <p class="description">Texte en bas de l'email</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large">💾 Enregistrer la personnalisation</button>
                    <button type="button" class="button button-secondary" onclick="resetToDefaults()">🔄 Réinitialiser</button>
                </p>
            </form>
        </div>

        <!-- Aperçu -->
        <div style="position: sticky; top: 32px;">
            <div class="postbox">
                <div class="postbox-header">
                    <h2>👁️ Aperçu en temps réel</h2>
                </div>
                <div class="inside" style="padding: 0;">
                    <div id="email-preview" style="font-family: Arial, sans-serif; line-height: 1.6; color: <?php echo esc_attr($current['text_color']); ?>; max-width: 500px; margin: 0 auto;">
                        <!-- Header -->
                        <div id="preview-header" style="background: <?php echo esc_attr($current['header_color']); ?>; color: white; padding: 20px; text-align: center;">
                            <div id="preview-logo" style="margin-bottom: 10px;">
                                <?php if ($global_logo): ?>
                                    <img src="<?php echo esc_url($global_logo); ?>" alt="Logo" style="max-width: 150px; max-height: 50px;">
                                <?php endif; ?>
                            </div>
                            <h2 id="preview-title" style="margin: 0; font-size: 20px;"><?php echo esc_html($current['title']); ?></h2>
                        </div>

                        <!-- Content -->
                        <div id="preview-content" style="background: <?php echo esc_attr($current['background_color']); ?>; color: <?php echo esc_attr($current['text_color']); ?>; padding: 30px; margin: 0;">
                            <h3 style="margin-top: 0;">Bonjour [Prénom],</h3>
                            <p id="preview-intro"><?php echo nl2br(esc_html($current['intro_text'])); ?></p>

                            <div style="background: white; padding: 20px; margin: 20px 0; border-left: 4px solid <?php echo esc_attr($current['header_color']); ?>;">
                                <h4 style="margin-top: 0;">Détails de la réservation</h4>
                                <p style="margin: 5px 0;"><strong>Service:</strong> Exemple de service</p>
                                <p style="margin: 5px 0;"><strong>Date:</strong> 15 février 2026</p>
                                <p style="margin: 5px 0;"><strong>Heure:</strong> 14:30</p>
                            </div>

                            <a href="#" id="preview-button" style="display: inline-block; background: <?php echo esc_attr($current['button_color']); ?>; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0;">Bouton d'action</a>
                        </div>

                        <!-- Footer -->
                        <div id="preview-footer" style="text-align: center; color: #7f8c8d; font-size: 12px; padding: 20px; background: #f5f5f5;">
                            <p id="preview-footer-text" style="margin: 5px 0;"><?php echo nl2br(esc_html($current['footer_text'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Sélecteur de type d'email
    $('#email-type-selector').on('change', function() {
        const type = $(this).val();
        window.location.href = '?page=ikomiris-booking-emails&type=' + type;
    });

    // Media Uploader pour le logo
    $('.ibs-upload-logo').on('click', function(e) {
        e.preventDefault();

        const logoField = $('#email_global_logo_url');
        const logoPreview = $('#logo-preview');

        const mediaUploader = wp.media({
            title: 'Choisir un logo',
            button: {
                text: 'Utiliser ce logo'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            logoField.val(attachment.url);
            logoPreview.html('<img src="' + attachment.url + '" style="max-width: 200px; max-height: 80px;">');
            updatePreview();
        });

        mediaUploader.open();
    });

    // Color pickers
    $('.color-picker').wpColorPicker({
        change: updatePreview,
        clear: updatePreview
    });

    // Mise à jour en temps réel
    $('#title, #intro_text, #footer_text, #email_global_logo_url').on('input', updatePreview);

    function updatePreview() {
        // Header
        const headerColor = $('#header_color').val();
        $('#preview-header').css('background', headerColor);

        // Logo
        const logoUrl = $('#email_global_logo_url').val();
        if (logoUrl) {
            $('#preview-logo').html('<img src="' + logoUrl + '" alt="Logo" style="max-width: 150px; max-height: 50px;">');
        } else {
            $('#preview-logo').html('');
        }

        // Titre
        const title = $('#title').val();
        $('#preview-title').text(title);

        // Background
        const bgColor = $('#background_color').val();
        $('#preview-content').css('background', bgColor);

        // Text color
        const textColor = $('#text_color').val();
        $('#email-preview').css('color', textColor);
        $('#preview-content').css('color', textColor);

        // Intro
        const intro = $('#intro_text').val();
        $('#preview-intro').html(intro.replace(/\n/g, '<br>'));

        // Button
        const buttonColor = $('#button_color').val();
        $('#preview-button').css('background', buttonColor);

        // Border
        $('#preview-content > div').css('border-left-color', headerColor);

        // Footer
        const footer = $('#footer_text').val();
        $('#preview-footer-text').html(footer.replace(/\n/g, '<br>'));
    }

    updatePreview();
});

function resetToDefaults() {
    if (confirm('Voulez-vous vraiment réinitialiser aux valeurs par défaut ?')) {
        const defaults = <?php echo json_encode($defaults); ?>;

        jQuery('#header_color').wpColorPicker('color', defaults.header_color);
        jQuery('#button_color').wpColorPicker('color', defaults.button_color);
        jQuery('#background_color').wpColorPicker('color', defaults.background_color);
        jQuery('#text_color').wpColorPicker('color', defaults.text_color);
        jQuery('#title').val(defaults.title);
        jQuery('#intro_text').val(defaults.intro_text);
        jQuery('#footer_text').val(defaults.footer_text);

        jQuery('.color-picker').trigger('change');
        jQuery('#title, #intro_text, #footer_text').trigger('input');
    }
}
</script>

<style>
.postbox-header h2 {
    font-size: 16px;
    padding: 12px;
}
#email-preview {
    border: 1px solid #ddd;
}
</style>
