<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_services = $wpdb->prefix . 'ibs_services';
$table_store_services = $wpdb->prefix . 'ibs_store_services';
$table_stores = $wpdb->prefix . 'ibs_stores';

// Traitement des actions
if (isset($_POST['ibs_save_service'])) {
    check_admin_referer('ibs_save_service_nonce');
    
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'description' => sanitize_textarea_field($_POST['description']),
        'duration' => intval($_POST['duration']),
        'price' => isset($_POST['price']) && $_POST['price'] !== '' ? floatval($_POST['price']) : null,
        'image_url' => esc_url_raw($_POST['image_url']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'display_order' => intval($_POST['display_order']),
    ];
    
    if ($service_id) {
        $wpdb->update($table_services, $data, ['id' => $service_id]);
    } else {
        $wpdb->insert($table_services, $data);
        $service_id = $wpdb->insert_id;
    }
    
    // Gérer les liaisons avec les magasins
    $wpdb->delete($table_store_services, ['service_id' => $service_id]);
    
    if (isset($_POST['stores']) && is_array($_POST['stores'])) {
        foreach ($_POST['stores'] as $store_id) {
            $wpdb->insert($table_store_services, [
                'store_id' => intval($store_id),
                'service_id' => $service_id
            ]);
        }
    }
    
    echo '<div class="notice notice-success"><p>Service enregistré avec succès.</p></div>';
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    check_admin_referer('ibs_delete_service_' . $_GET['id']);
    $service_id = intval($_GET['id']);
    $wpdb->delete($table_store_services, ['service_id' => $service_id]);
    $wpdb->delete($table_services, ['id' => $service_id]);
    echo '<div class="notice notice-success"><p>Service supprimé avec succès.</p></div>';
}

// Récupérer tous les services
$services = $wpdb->get_results("SELECT * FROM $table_services ORDER BY display_order ASC, name ASC");

// Récupérer tous les magasins
$stores = $wpdb->get_results("SELECT * FROM $table_stores WHERE is_active = 1 ORDER BY name ASC");

// Mode édition
$edit_mode = false;
$edit_service = null;
$assigned_stores = [];
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_mode = true;
    $service_id = intval($_GET['id']);
    $edit_service = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_services WHERE id = %d", $service_id));
    $assigned_stores = $wpdb->get_col($wpdb->prepare("SELECT store_id FROM $table_store_services WHERE service_id = %d", $service_id));
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Gestion des Services</h1>
    <a href="?page=ikomiris-booking-services&action=new" class="page-title-action">Ajouter un service</a>
    <hr class="wp-header-end">
    
    <?php if (isset($_GET['action']) && in_array($_GET['action'], ['new', 'edit'])): ?>
        
        <!-- Formulaire d'ajout/édition -->
        <div class="ibs-form-container">
            <h2><?php echo $edit_mode ? 'Modifier le service' : 'Nouveau service'; ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('ibs_save_service_nonce'); ?>
                <input type="hidden" name="service_id" value="<?php echo $edit_mode ? $edit_service->id : ''; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="name">Nom du service *</label></th>
                        <td>
                            <input type="text" name="name" id="name" class="regular-text" 
                                   value="<?php echo $edit_mode ? esc_attr($edit_service->name) : ''; ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="description">Description</label></th>
                        <td>
                            <textarea name="description" id="description" class="large-text" rows="4"><?php echo $edit_mode ? esc_textarea($edit_service->description) : ''; ?></textarea>
                            <p class="description">Décrivez brièvement ce service pour vos clients.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="duration">Durée (minutes) *</label></th>
                        <td>
                            <input type="number" name="duration" id="duration" min="5" step="5" 
                                   value="<?php echo $edit_mode ? $edit_service->duration : '30'; ?>" required>
                            <p class="description">Durée du rendez-vous en minutes (ex: 30, 60, 90...)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="price">Prix (€)</label></th>
                        <td>
                            <input type="number" name="price" id="price" min="0" step="0.01" 
                                   value="<?php echo $edit_mode && $edit_service->price ? $edit_service->price : ''; ?>">
                            <p class="description">Prix du service (optionnel). Laissez vide si vous ne souhaitez pas afficher le prix.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="image_url">Image du service *</label></th>
                        <td>
                            <input type="text" name="image_url" id="image_url" class="regular-text" 
                                   value="<?php echo $edit_mode ? esc_url($edit_service->image_url) : ''; ?>" required>
                            <button type="button" class="button ibs-upload-image">Choisir une image</button>
                            <div id="image-preview" style="margin-top: 10px;">
                                <?php if ($edit_mode && $edit_service->image_url): ?>
                                    <img src="<?php echo esc_url($edit_service->image_url); ?>" style="max-width: 300px;">
                                <?php endif; ?>
                            </div>
                            <p class="description">Une image représentative du service (recommandé: 800x600px minimum).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="display_order">Ordre d'affichage</label></th>
                        <td>
                            <input type="number" name="display_order" id="display_order" min="0" 
                                   value="<?php echo $edit_mode ? $edit_service->display_order : '0'; ?>">
                            <p class="description">Plus le nombre est petit, plus le service apparaît en premier (0 = premier).</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Magasins associés *</th>
                        <td>
                            <?php if (empty($stores)): ?>
                                <p><strong>Aucun magasin actif.</strong> <a href="?page=ikomiris-booking-stores&action=new">Créez d'abord un magasin</a>.</p>
                            <?php else: ?>
                                <?php foreach ($stores as $store): ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" name="stores[]" value="<?php echo $store->id; ?>"
                                               <?php checked(in_array($store->id, $assigned_stores)); ?>>
                                        <?php echo esc_html($store->name); ?>
                                    </label>
                                <?php endforeach; ?>
                                <p class="description">Sélectionnez les magasins où ce service est disponible.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Actif</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" value="1" 
                                       <?php checked($edit_mode ? $edit_service->is_active : 1, 1); ?>>
                                Service actif et visible pour les clients
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="ibs_save_service" class="button button-primary" <?php echo empty($stores) ? 'disabled' : ''; ?>>
                        <?php echo $edit_mode ? 'Mettre à jour' : 'Créer le service'; ?>
                    </button>
                    <a href="?page=ikomiris-booking-services" class="button">Annuler</a>
                </p>
            </form>
        </div>
        
    <?php else: ?>
        
        <!-- Liste des services -->
        <div class="ibs-table-container">
            <?php if (empty($services)): ?>
                <p>Aucun service créé. <a href="?page=ikomiris-booking-services&action=new">Créer votre premier service</a></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Image</th>
                            <th>Nom</th>
                            <th>Durée</th>
                            <th>Prix</th>
                            <th>Magasins</th>
                            <th>Ordre</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <?php
                            // Récupérer les magasins associés
                            $service_stores = $wpdb->get_col($wpdb->prepare("
                                SELECT s.name FROM $table_stores s
                                INNER JOIN $table_store_services ss ON s.id = ss.store_id
                                WHERE ss.service_id = %d
                            ", $service->id));
                            ?>
                            <tr>
                                <td>
                                    <?php if ($service->image_url): ?>
                                        <img src="<?php echo esc_url($service->image_url); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo esc_html($service->name); ?></strong></td>
                                <td><?php echo $service->duration; ?> min</td>
                                <td><?php echo $service->price ? number_format($service->price, 2) . '€' : '-'; ?></td>
                                <td><?php echo empty($service_stores) ? '-' : implode(', ', $service_stores); ?></td>
                                <td><?php echo $service->display_order; ?></td>
                                <td>
                                    <?php if ($service->is_active): ?>
                                        <span class="ibs-status-active">Actif</span>
                                    <?php else: ?>
                                        <span class="ibs-status-inactive">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?page=ikomiris-booking-services&action=edit&id=<?php echo $service->id; ?>" 
                                       class="button button-small">Modifier</a>
                                    <a href="?page=ikomiris-booking-services&action=delete&id=<?php echo $service->id; ?>&_wpnonce=<?php echo wp_create_nonce('ibs_delete_service_' . $service->id); ?>" 
                                       class="button button-small button-link-delete"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce service ?');">Supprimer</a>
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
