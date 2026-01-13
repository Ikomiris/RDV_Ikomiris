<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'ibs_stores';

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
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Adresse</th>
                            <th>Téléphone</th>
                            <th>Email</th>
                            <th>Statut</th>
                            <th>Actions</th>
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
