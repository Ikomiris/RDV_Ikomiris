<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Fonction helper pour récupérer un setting
function get_style_setting($key, $default = '') {
    global $wpdb;
    $value = $wpdb->get_var($wpdb->prepare(
        "SELECT setting_value FROM {$wpdb->prefix}ibs_settings WHERE setting_key = %s",
        $key
    ));
    return $value !== null ? $value : $default;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_style') {
    check_admin_referer('ibs_style_action');

    $style_settings = [
        // Couleurs générales
        'style_primary_color' => sanitize_hex_color($_POST['primary_color']),
        'style_secondary_color' => sanitize_hex_color($_POST['secondary_color']),
        'style_text_color' => sanitize_hex_color($_POST['text_color']),
        'style_background_color' => sanitize_hex_color($_POST['background_color']),
        'style_section_background' => sanitize_hex_color($_POST['section_background']),

        // Cartes
        'style_card_background' => sanitize_hex_color($_POST['card_background']),
        'style_card_border_radius' => intval($_POST['card_border_radius']),
        'style_card_shadow' => sanitize_text_field($_POST['card_shadow']),

        // Boutons
        'style_button_color' => sanitize_hex_color($_POST['button_color']),
        'style_button_text_color' => sanitize_hex_color($_POST['button_text_color']),
        'style_button_hover_color' => sanitize_hex_color($_POST['button_hover_color']),
        'style_button_border_radius' => intval($_POST['button_border_radius']),

        // Titres
        'style_title_color' => sanitize_hex_color($_POST['title_color']),
        'style_subtitle_color' => sanitize_hex_color($_POST['subtitle_color']),
    ];

    foreach ($style_settings as $key => $value) {
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

    echo '<div class="notice notice-success"><p>✅ Style sauvegardé avec succès ! Rafraîchissez la page frontend pour voir les changements.</p></div>';
}

// Valeurs par défaut
$defaults = [
    'primary_color' => '#3498db',
    'secondary_color' => '#2ecc71',
    'text_color' => '#333333',
    'background_color' => '#f5f5f5',
    'section_background' => '#ffffff',
    'card_background' => '#ffffff',
    'card_border_radius' => '8',
    'card_shadow' => '0 2px 8px rgba(0,0,0,0.1)',
    'button_color' => '#3498db',
    'button_text_color' => '#ffffff',
    'button_hover_color' => '#2980b9',
    'button_border_radius' => '4',
    'title_color' => '#2c3e50',
    'subtitle_color' => '#7f8c8d',
];

// Récupérer les valeurs actuelles
$current = [];
foreach ($defaults as $key => $default) {
    $current[$key] = get_style_setting('style_' . $key, $default);
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">🎨 Personnalisation du Style</h1>
    <hr class="wp-header-end">

    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 20px; margin-top: 20px;">

        <!-- Formulaire de personnalisation -->
        <div>
            <form method="post" action="" id="style-form">
                <?php wp_nonce_field('ibs_style_action'); ?>
                <input type="hidden" name="action" value="save_style">

                <!-- Couleurs générales -->
                <div class="postbox" style="margin-bottom: 20px;">
                    <div class="postbox-header">
                        <h2>🎨 Couleurs générales</h2>
                    </div>
                    <div class="inside" style="padding: 20px;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="primary_color">Couleur principale</label></th>
                                <td>
                                    <input type="text" name="primary_color" id="primary_color"
                                           value="<?php echo esc_attr($current['primary_color']); ?>"
                                           class="color-picker" data-default-color="<?php echo esc_attr($defaults['primary_color']); ?>">
                                    <p class="description">Utilisée pour les éléments importants</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="secondary_color">Couleur secondaire</label></th>
                                <td>
                                    <input type="text" name="secondary_color" id="secondary_color"
                                           value="<?php echo esc_attr($current['secondary_color']); ?>"
                                           class="color-picker" data-default-color="<?php echo esc_attr($defaults['secondary_color']); ?>">
                                    <p class="description">Pour les accents et validations</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="text_color">Couleur du texte</label></th>
                                <td>
                                    <input type="text" name="text_color" id="text_color"
                                           value="<?php echo esc_attr($current['text_color']); ?>"
                                           class="color-picker" data-default-color="<?php echo esc_attr($defaults['text_color']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="background_color">Couleur de fond de page</label></th>
                                <td>
                                    <input type="text" name="background_color" id="background_color"
                                           value="<?php echo esc_attr($current['background_color']); ?>"
                                           class="color-picker" data-default-color="<?php echo esc_attr($defaults['background_color']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="section_background">Couleur de fond des sections</label></th>
                                <td>
                                    <input type="text" name="section_background" id="section_background"
                                           value="<?php echo esc_attr($current['section_background']); ?>"
                                           class="color-picker" data-default-color="<?php echo esc_attr($defaults['section_background']); ?>">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Cartes -->
                <div class="postbox" style="margin-bottom: 20px;">
                    <div class="postbox-header">
                        <h2>🃏 Style des cartes</h2>
                    </div>
                    <div class="inside" style="padding: 20px;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="card_background">Fond des cartes</label></th>
                                <td>
                                    <input type="text" name="card_background" id="card_background"
                                           value="<?php echo esc_attr($current['card_background']); ?>"
                                           class="color-picker" data-default-color="<?php echo esc_attr($defaults['card_background']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="card_border_radius">Arrondi des angles (px)</label></th>
                                <td>
                                    <input type="range" name="card_border_radius" id="card_border_radius"
                                           min="0" max="30" step="1"
                                           value="<?php echo esc_attr($current['card_border_radius']); ?>"
                                           oninput="document.getElementById('card_border_radius_value').textContent = this.value + 'px'">
                                    <span id="card_border_radius_value"><?php echo esc_html($current['card_border_radius']); ?>px</span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="card_shadow">Ombre des cartes (CSS)</label></th>
                                <td>
                                    <select name="card_shadow" id="card_shadow">
                                        <option value="none" <?php selected($current['card_shadow'], 'none'); ?>>Aucune</option>
                                        <option value="0 1px 3px rgba(0,0,0,0.1)" <?php selected($current['card_shadow'], '0 1px 3px rgba(0,0,0,0.1)'); ?>>Légère</option>
                                        <option value="0 2px 8px rgba(0,0,0,0.1)" <?php selected($current['card_shadow'], '0 2px 8px rgba(0,0,0,0.1)'); ?>>Moyenne (par défaut)</option>
                                        <option value="0 4px 12px rgba(0,0,0,0.15)" <?php selected($current['card_shadow'], '0 4px 12px rgba(0,0,0,0.15)'); ?>>Prononcée</option>
                                        <option value="0 8px 20px rgba(0,0,0,0.2)" <?php selected($current['card_shadow'], '0 8px 20px rgba(0,0,0,0.2)'); ?>>Forte</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Boutons -->
                <div class="postbox" style="margin-bottom: 20px;">
                    <div class="postbox-header">
                        <h2>🔘 Style des boutons</h2>
                    </div>
                    <div class="inside" style="padding: 20px;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="button_color">Couleur des boutons</label></th>
                                <td>
                                    <input type="text" name="button_color" id="button_color"
                                           value="<?php echo esc_attr($current['button_color']); ?>"
                                           class="color-picker" data-default-color="<?php echo esc_attr($defaults['button_color']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="button_text_color">Couleur du texte des boutons</label></th>
                                <td>
                                    <input type="text" name="button_text_color" id="button_text_color"
                                           value="<?php echo esc_attr($current['button_text_color']); ?>"
                                           class="color-picker" data-default-color="<?php echo esc_attr($defaults['button_text_color']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="button_hover_color">Couleur au survol</label></th>
                                <td>
                                    <input type="text" name="button_hover_color" id="button_hover_color"
                                           value="<?php echo esc_attr($current['button_hover_color']); ?>"
                                           class="color-picker" data-default-color="<?php echo esc_attr($defaults['button_hover_color']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="button_border_radius">Arrondi des boutons (px)</label></th>
                                <td>
                                    <input type="range" name="button_border_radius" id="button_border_radius"
                                           min="0" max="30" step="1"
                                           value="<?php echo esc_attr($current['button_border_radius']); ?>"
                                           oninput="document.getElementById('button_border_radius_value').textContent = this.value + 'px'">
                                    <span id="button_border_radius_value"><?php echo esc_html($current['button_border_radius']); ?>px</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Typographie -->
                <div class="postbox" style="margin-bottom: 20px;">
                    <div class="postbox-header">
                        <h2>✍️ Couleurs de typographie</h2>
                    </div>
                    <div class="inside" style="padding: 20px;">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="title_color">Couleur des titres</label></th>
                                <td>
                                    <input type="text" name="title_color" id="title_color"
                                           value="<?php echo esc_attr($current['title_color']); ?>"
                                           class="color-picker" data-default-color="<?php echo esc_attr($defaults['title_color']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="subtitle_color">Couleur des sous-titres</label></th>
                                <td>
                                    <input type="text" name="subtitle_color" id="subtitle_color"
                                           value="<?php echo esc_attr($current['subtitle_color']); ?>"
                                           class="color-picker" data-default-color="<?php echo esc_attr($defaults['subtitle_color']); ?>">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large">💾 Enregistrer le style</button>
                    <button type="button" class="button button-secondary" onclick="resetToDefaults()">🔄 Réinitialiser par défaut</button>
                </p>
            </form>
        </div>

        <!-- Prévisualisation -->
        <div style="position: sticky; top: 32px;">
            <div class="postbox">
                <div class="postbox-header">
                    <h2>👁️ Aperçu</h2>
                </div>
                <div class="inside" id="preview-container" style="padding: 20px; background: <?php echo esc_attr($current['background_color']); ?>;">
                    <h3 style="color: <?php echo esc_attr($current['title_color']); ?>; margin-top: 0;">Exemple de titre</h3>
                    <p style="color: <?php echo esc_attr($current['subtitle_color']); ?>;">Sous-titre ou description</p>

                    <div id="preview-section" style="background: <?php echo esc_attr($current['section_background']); ?>; padding: 20px; border-radius: <?php echo esc_attr($current['card_border_radius']); ?>px; box-shadow: <?php echo esc_attr($current['card_shadow']); ?>; margin: 15px 0;">
                        <h4 style="color: <?php echo esc_attr($current['title_color']); ?>; margin-top: 0;">Section exemple</h4>
                        <div id="preview-card" style="background: <?php echo esc_attr($current['card_background']); ?>; padding: 20px; border-radius: <?php echo esc_attr($current['card_border_radius']); ?>px; box-shadow: <?php echo esc_attr($current['card_shadow']); ?>; margin: 10px 0 0;">
                            <p style="color: <?php echo esc_attr($current['text_color']); ?>; margin: 0 0 10px;">Exemple de contenu dans une carte.</p>
                            <button id="preview-button" type="button" style="background: <?php echo esc_attr($current['button_color']); ?>; color: <?php echo esc_attr($current['button_text_color']); ?>; border: none; padding: 10px 20px; border-radius: <?php echo esc_attr($current['button_border_radius']); ?>px; cursor: pointer;">Bouton exemple</button>
                        </div>
                    </div>

                    <p style="color: <?php echo esc_attr($current['text_color']); ?>; font-size: 12px; margin-top: 20px;">💡 Les changements seront visibles sur la page frontend après sauvegarde.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialiser les color pickers
    $('.color-picker').wpColorPicker({
        change: updatePreview,
        clear: updatePreview
    });

    // Mettre à jour l'aperçu en temps réel
    function updatePreview() {
        const preview = $('#preview-container');
        const section = $('#preview-section');
        const card = $('#preview-card');
        const button = $('#preview-button');

        // Couleurs générales
        preview.css('background', $('#background_color').val());
        preview.find('h3').css('color', $('#title_color').val());
        preview.find('p').first().css('color', $('#subtitle_color').val());

        // Section
        section.css({
            'background': $('#section_background').val(),
            'border-radius': $('#card_border_radius').val() + 'px',
            'box-shadow': $('#card_shadow').val()
        });
        section.find('h4').css('color', $('#title_color').val());

        // Carte
        card.css({
            'background': $('#card_background').val(),
            'border-radius': $('#card_border_radius').val() + 'px',
            'box-shadow': $('#card_shadow').val()
        });
        card.find('p').css('color', $('#text_color').val());

        // Bouton
        button.css({
            'background': $('#button_color').val(),
            'color': $('#button_text_color').val(),
            'border-radius': $('#button_border_radius').val() + 'px'
        });

        button.off('mouseenter mouseleave').hover(
            function() { $(this).css('background', $('#button_hover_color').val()); },
            function() { $(this).css('background', $('#button_color').val()); }
        );
    }

    // Écouteurs pour les ranges
    $('#card_border_radius, #button_border_radius').on('input', updatePreview);
    $('#card_shadow').on('change', updatePreview);

    // Mise à jour initiale
    updatePreview();
});

function resetToDefaults() {
    if (confirm('Voulez-vous vraiment réinitialiser tous les styles par défaut ?')) {
        const defaults = <?php echo json_encode($defaults); ?>;
        for (let key in defaults) {
            const input = document.querySelector('[name="' + key + '"]');
            if (input) {
                if (input.classList.contains('color-picker')) {
                    jQuery(input).wpColorPicker('color', defaults[key]);
                } else {
                    input.value = defaults[key];
                    if (input.type === 'range') {
                        document.getElementById(input.id + '_value').textContent = defaults[key] + 'px';
                    }
                }
            }
        }
        jQuery('.color-picker').trigger('change');
    }
}
</script>

<style>
.postbox-header h2 {
    font-size: 16px;
    padding: 12px;
}
</style>
