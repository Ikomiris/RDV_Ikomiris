<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'ibs_stores';

// Assurer la présence de la colonne google_calendar_id (installations existantes)
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'google_calendar_id'");
if (empty($column_exists)) {
    $altered = $wpdb->query("ALTER TABLE $table ADD google_calendar_id varchar(255) AFTER image_url");
    if ($altered === false) {
        echo '<div class="notice notice-error"><p><strong>Erreur :</strong> Impossible d\'ajouter la colonne Google Calendar. Détails : ' . esc_html($wpdb->last_error) . '</p></div>';
    }
}

// Traitement des actions
if (isset($_POST['ibs_save_store'])) {
    check_admin_referer('ibs_save_store_nonce');
    
    $store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'address' => sanitize_textarea_field($_POST['address']),
        'phone' => sanitize_text_field($_POST['phone']),
        'email' => sanitize_email($_POST['email']),
        'description' => sanitize_textarea_field($_POST['description']),
        'image_url' => esc_url_raw($_POST['image_url']),
        'google_calendar_id' => sanitize_text_field($_POST['google_calendar_id']),
        'cancellation_hours' => intval($_POST['cancellation_hours']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    
    if ($store_id) {
        $wpdb->update($table, $data, ['id' => $store_id]);
        echo '<div class="notice notice-success"><p>Magasin mis à jour avec succès.</p></div>';
    } else {
        $wpdb->insert($table, $data);
        echo '<div class="notice notice-success"><p>Magasin créé avec succès.</p></div>';
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    check_admin_referer('ibs_delete_store_' . $_GET['id']);
    $wpdb->delete($table, ['id' => intval($_GET['id'])]);
    echo '<div class="notice notice-success"><p>Magasin supprimé avec succès.</p></div>';
}

// Récupérer tous les magasins
$stores = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");

// Mode édition
$edit_mode = false;
$edit_store = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_mode = true;
    $edit_store = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($_GET['id'])));
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Gestion des Magasins</h1>
    <a href="?page=ikomiris-booking-stores&action=new" class="page-title-action">Ajouter un magasin</a>
    <hr class="wp-header-end">
    
    <?php if (isset($_GET['action']) && in_array($_GET['action'], ['new', 'edit'])): ?>
        
        <!-- Formulaire d'ajout/édition -->
        <div class="ibs-form-container">
            <h2><?php echo $edit_mode ? 'Modifier le magasin' : 'Nouveau magasin'; ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('ibs_save_store_nonce'); ?>
                <input type="hidden" name="store_id" value="<?php echo $edit_mode ? $edit_store->id : ''; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="name">Nom du magasin *</label></th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" 
                                   value="<?php echo $edit_mode ? esc_attr($edit_store->name) : ''; ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="address">Adresse</label></th>
                        <td>
                            <textarea name="address" id="address" class="large-text" rows="3"><?php echo $edit_mode ? esc_textarea($edit_store->address) : ''; ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="phone">Téléphone</label></th>
                        <td>
                            <input type="text" name="phone" id="phone" class="regular-text" 
                                   value="<?php echo $edit_mode ? esc_attr($edit_store->phone) : ''; ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="email">Email</label></th>
                        <td>
                            <input type="email" name="email" id="email" class="regular-text" 
                                   value="<?php echo $edit_mode ? esc_attr($edit_store->email) : ''; ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="description">Description</label></th>
                        <td>
                            <textarea name="description" id="description" class="large-text" rows="5"><?php echo $edit_mode ? esc_textarea($edit_store->description) : ''; ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="image_url">Image</label></th>
                        <td>
                            <input type="text" name="image_url" id="image_url" class="regular-text" 
                                   value="<?php echo $edit_mode ? esc_url($edit_store->image_url) : ''; ?>">
                            <button type="button" class="button ibs-upload-image">Choisir une image</button>
                            <div id="image-preview" style="margin-top: 10px;">
                                <?php if ($edit_mode && $edit_store->image_url): ?>
                                    <img src="<?php echo esc_url($edit_store->image_url); ?>" style="max-width: 300px;">
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="google_calendar_id">ID Google Calendar</label></th>
                        <td>
                            <input type="text" name="google_calendar_id" id="google_calendar_id" class="large-text"
                                   value="<?php echo $edit_mode ? esc_attr($edit_store->google_calendar_id) : ''; ?>"
                                   placeholder="votre.email@gmail.com ou c_xxxxxxxxx@group.calendar.google.com">
                            <p class="description">
                                <strong>🔑 L'ID du calendrier Google spécifique à ce magasin</strong><br>
                                <br>
                                <strong>Format attendu :</strong><br>
                                • Calendrier principal : <code>votre.email@gmail.com</code><br>
                                • Calendrier secondaire : <code>c_xxxxxxxxx@group.calendar.google.com</code><br>
                                <br>
                                <strong>📍 Comment le trouver :</strong><br>
                                1. Ouvrez <a href="https://calendar.google.com" target="_blank">Google Calendar</a><br>
                                2. Cliquez sur <strong>⋮</strong> (3 points) à côté du calendrier souhaité<br>
                                3. Cliquez sur <strong>"Paramètres et partage"</strong><br>
                                4. Copiez <strong>"ID de l'agenda"</strong> dans la section "Intégrer l'agenda"<br>
                                <br>
                                <strong>⚠️ Important :</strong> Un ID différent = un calendrier différent.<br>
                                Si vous laissez vide, aucune synchronisation Google Calendar pour ce magasin.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="cancellation_hours">Délai d'annulation (heures)</label></th>
                        <td>
                            <input type="number" name="cancellation_hours" id="cancellation_hours"
                                   class="small-text" min="1" max="168" step="1"
                                   value="<?php echo $edit_mode && isset($edit_store->cancellation_hours) ? intval($edit_store->cancellation_hours) : 24; ?>">
                            heures avant le rendez-vous
                            <p class="description">
                                <strong>⏰ Délai minimum pour annuler une réservation</strong><br>
                                <br>
                                Les clients pourront annuler leur réservation jusqu'à ce délai avant l'heure du rendez-vous.<br>
                                <br>
                                <strong>Exemples :</strong><br>
                                • <strong>24 heures</strong> (recommandé) : Le client peut annuler jusqu'à la veille du rendez-vous<br>
                                • <strong>48 heures</strong> : Annulation possible jusqu'à 2 jours avant<br>
                                • <strong>2 heures</strong> : Annulation possible jusqu'à 2h avant le rendez-vous<br>
                                <br>
                                <strong>💡 Conseil :</strong> Un délai de 24h est un bon compromis entre flexibilité client et gestion du planning.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Actif</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" value="1" 
                                       <?php checked($edit_mode ? $edit_store->is_active : 1, 1); ?>>
                                Magasin actif et visible pour les clients
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="ibs_save_store" class="button button-primary">
                        <?php echo $edit_mode ? 'Mettre à jour' : 'Créer le magasin'; ?>
                    </button>
                    <a href="?page=ikomiris-booking-stores" class="button">Annuler</a>
                </p>
            </form>
        </div>
        
    <?php else: ?>
        
        <!-- Liste des magasins -->
        <div class="ibs-table-container">
            <?php if (empty($stores)): ?>
                <p>Aucun magasin créé. <a href="?page=ikomiris-booking-stores&action=new">Créer votre premier magasin</a></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 5%;">ID</th>
                            <th style="width: 15%;">Nom</th>
                            <th style="width: 15%;">Adresse</th>
                            <th style="width: 10%;">Téléphone</th>
                            <th style="width: 10%;">Email</th>
                            <th style="width: 20%;">Google Calendar</th>
                            <th style="width: 10%;">Statut</th>
                            <th style="width: 15%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stores as $store): ?>
                            <tr>
                                <td><?php echo $store->id; ?></td>
                                <td><strong><?php echo esc_html($store->name); ?></strong></td>
                                <td><?php echo esc_html($store->address); ?></td>
                                <td><?php echo esc_html($store->phone); ?></td>
                                <td><?php echo esc_html($store->email); ?></td>
                                <td>
                                    <?php if (!empty($store->google_calendar_id)): ?>
                                        <span style="color: #46b450;">✓ Configuré</span><br>
                                        <code style="font-size: 11px; display: inline-block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr($store->google_calendar_id); ?>">
                                            <?php echo esc_html($store->google_calendar_id); ?>
                                        </code>
                                    <?php else: ?>
                                        <span style="color: #dc3232;">✗ Non configuré</span><br>
                                        <small>Pas de synchro Google</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($store->is_active): ?>
                                        <span class="ibs-status-active">Actif</span>
                                    <?php else: ?>
                                        <span class="ibs-status-inactive">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=ikomiris-booking-stores&action=edit&id=<?php echo $store->id; ?>"
                                       class="button button-small">Modifier</a>
                                    <a href="?page=ikomiris-booking-stores&action=delete&id=<?php echo $store->id; ?>&_wpnonce=<?php echo wp_create_nonce('ibs_delete_store_' . $store->id); ?>"
                                       class="button button-small button-link-delete"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce magasin ?');">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Media Uploader
    $('.ibs-upload-image').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var imageField = $('#image_url');
        var imagePreview = $('#image-preview');
        
        var mediaUploader = wp.media({
            title: 'Choisir une image',
            button: {
                text: 'Utiliser cette image'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            imageField.val(attachment.url);
            imagePreview.html('<img src="' + attachment.url + '" style="max-width: 300px;">');
        });
        
        mediaUploader.open();
    });
});
</script>
