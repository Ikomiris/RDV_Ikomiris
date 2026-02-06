<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        check_admin_referer('ibs_exceptions_action');

        if ($_POST['action'] === 'save_exception') {
            $store_id = intval($_POST['store_id']);
            $exception_date = sanitize_text_field($_POST['exception_date']);
            $exception_type = sanitize_text_field($_POST['exception_type']);
            $time_start = !empty($_POST['time_start']) ? sanitize_text_field($_POST['time_start']) : null;
            $time_end = !empty($_POST['time_end']) ? sanitize_text_field($_POST['time_end']) : null;
            $description = sanitize_textarea_field($_POST['description']);

            $data = [
                'store_id' => $store_id,
                'exception_date' => $exception_date,
                'exception_type' => $exception_type,
                'time_start' => $time_start,
                'time_end' => $time_end,
                'description' => $description,
            ];

            if (!empty($_POST['exception_id'])) {
                // Mise à jour
                $wpdb->update(
                    $wpdb->prefix . 'ibs_exceptions',
                    $data,
                    ['id' => intval($_POST['exception_id'])],
                    ['%d', '%s', '%s', '%s', '%s', '%s'],
                    ['%d']
                );
                echo '<div class="notice notice-success"><p>Exception mise à jour avec succès.</p></div>';
            } else {
                // Insertion
                $wpdb->insert(
                    $wpdb->prefix . 'ibs_exceptions',
                    $data,
                    ['%d', '%s', '%s', '%s', '%s', '%s']
                );
                echo '<div class="notice notice-success"><p>Exception ajoutée avec succès.</p></div>';
            }
        } elseif ($_POST['action'] === 'delete_exception') {
            $exception_id = intval($_POST['exception_id']);
            $wpdb->delete(
                $wpdb->prefix . 'ibs_exceptions',
                ['id' => $exception_id],
                ['%d']
            );
            echo '<div class="notice notice-success"><p>Exception supprimée avec succès.</p></div>';
        }
    }
}

// Récupérer les magasins
$stores = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ibs_stores ORDER BY name ASC");

// Magasin sélectionné
$selected_store = isset($_GET['store_id']) ? intval($_GET['store_id']) : (count($stores) > 0 ? $stores[0]->id : 0);

// Exception en cours de modification
$edit_exception = null;
if (isset($_GET['edit'])) {
    $edit_exception = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ibs_exceptions WHERE id = %d",
        intval($_GET['edit'])
    ));
}

// Récupérer les exceptions du magasin sélectionné
$exceptions = [];
if ($selected_store) {
    $exceptions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ibs_exceptions WHERE store_id = %d ORDER BY exception_date DESC",
        $selected_store
    ));
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Dates Exceptionnelles</h1>
    <a href="?page=ikomiris-booking-exceptions&store_id=<?php echo $selected_store; ?>&add=1" class="page-title-action">Ajouter une exception</a>
    <hr class="wp-header-end">

    <?php if (empty($stores)): ?>
        <div class="notice notice-warning">
            <p>Aucun magasin disponible. <a href="?page=ikomiris-booking-stores">Créer un magasin</a> avant de définir les exceptions.</p>
        </div>
    <?php else: ?>

    <!-- Sélecteur de magasin -->
    <div class="ibs-store-selector" style="margin: 20px 0;">
        <label for="store-selector" style="font-weight: 600; margin-right: 10px;">Magasin :</label>
        <select id="store-selector" onchange="window.location.href='?page=ikomiris-booking-exceptions&store_id=' + this.value">
            <?php foreach ($stores as $store): ?>
                <option value="<?php echo $store->id; ?>" <?php selected($selected_store, $store->id); ?>>
                    <?php echo esc_html($store->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Formulaire d'ajout/modification -->
    <?php if (isset($_GET['add']) || $edit_exception): ?>
        <div class="ibs-form-card" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 4px;">
            <h2><?php echo $edit_exception ? 'Modifier l\'exception' : 'Ajouter une exception'; ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('ibs_exceptions_action'); ?>
                <input type="hidden" name="action" value="save_exception">
                <input type="hidden" name="store_id" value="<?php echo $selected_store; ?>">
                <?php if ($edit_exception): ?>
                    <input type="hidden" name="exception_id" value="<?php echo $edit_exception->id; ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="exception_date">Date *</label></th>
                        <td>
                            <input type="date" name="exception_date" id="exception_date"
                                   value="<?php echo $edit_exception ? esc_attr($edit_exception->exception_date) : date('Y-m-d'); ?>"
                                   required>
                            <p class="description">Date de l'exception (jour férié, fermeture exceptionnelle, etc.)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exception_type">Type *</label></th>
                        <td>
                            <select name="exception_type" id="exception_type" required onchange="toggleExceptionHours(this.value)">
                                <option value="closed" <?php selected($edit_exception ? $edit_exception->exception_type : '', 'closed'); ?>>
                                    Fermé (Pas de réservation possible)
                                </option>
                                <option value="open" <?php selected($edit_exception ? $edit_exception->exception_type : '', 'open'); ?>>
                                    Ouverture exceptionnelle (Horaires spécifiques)
                                </option>
                            </select>
                            <p class="description">
                                <strong>Fermé :</strong> Aucune réservation possible ce jour<br>
                                <strong>Ouverture exceptionnelle :</strong> Le magasin est ouvert avec des horaires différents
                            </p>
                        </td>
                    </tr>
                    <tr id="exception-hours" style="<?php echo ($edit_exception && $edit_exception->exception_type === 'open') ? '' : 'display: none;'; ?>">
                        <th scope="row"><label>Horaires exceptionnels</label></th>
                        <td>
                            <div style="margin-bottom: 10px;">
                                <label for="time_start">Ouverture : </label>
                                <input type="time" name="time_start" id="time_start"
                                       value="<?php echo $edit_exception ? esc_attr(substr($edit_exception->time_start, 0, 5)) : '09:00'; ?>">
                            </div>
                            <div>
                                <label for="time_end">Fermeture : </label>
                                <input type="time" name="time_end" id="time_end"
                                       value="<?php echo $edit_exception ? esc_attr(substr($edit_exception->time_end, 0, 5)) : '18:00'; ?>">
                            </div>
                            <p class="description">Définir les horaires si le magasin est ouvert exceptionnellement</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description">Description</label></th>
                        <td>
                            <textarea name="description" id="description" rows="3" class="large-text"><?php echo $edit_exception ? esc_textarea($edit_exception->description) : ''; ?></textarea>
                            <p class="description">Raison de l'exception (ex: "Noël", "Inventaire annuel", "Salon professionnel")</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $edit_exception ? 'Mettre à jour' : 'Ajouter'; ?>
                    </button>
                    <a href="?page=ikomiris-booking-exceptions&store_id=<?php echo $selected_store; ?>" class="button">Annuler</a>
                </p>
            </form>
        </div>

        <script>
        function toggleExceptionHours(type) {
            const hoursRow = document.getElementById('exception-hours');
            if (type === 'open') {
                hoursRow.style.display = '';
            } else {
                hoursRow.style.display = 'none';
            }
        }
        </script>
    <?php endif; ?>

    <!-- Liste des exceptions -->
    <div class="ibs-exceptions-list" style="margin-top: 30px;">
        <h2>Exceptions configurées</h2>

        <?php if (empty($exceptions)): ?>
            <p>Aucune exception configurée pour ce magasin. <a href="?page=ikomiris-booking-exceptions&store_id=<?php echo $selected_store; ?>&add=1">Ajouter une exception</a></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Horaires</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exceptions as $exception): ?>
                        <?php
                        $date_formatted = date_i18n(get_option('date_format'), strtotime($exception->exception_date));
                        $is_past = strtotime($exception->exception_date) < strtotime('today');
                        ?>
                        <tr <?php echo $is_past ? 'style="opacity: 0.6;"' : ''; ?>>
                            <td>
                                <strong><?php echo esc_html($date_formatted); ?></strong>
                                <?php if ($is_past): ?>
                                    <br><span style="color: #999; font-size: 12px;">(Passée)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($exception->exception_type === 'closed'): ?>
                                    <span style="color: #dc3232;">🔒 Fermé</span>
                                <?php else: ?>
                                    <span style="color: #46b450;">✓ Ouvert (exceptionnel)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($exception->exception_type === 'open' && $exception->time_start && $exception->time_end): ?>
                                    <?php echo esc_html(substr($exception->time_start, 0, 5)); ?> - <?php echo esc_html(substr($exception->time_end, 0, 5)); ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($exception->description ?: '-'); ?></td>
                            <td>
                                <a href="?page=ikomiris-booking-exceptions&store_id=<?php echo $selected_store; ?>&edit=<?php echo $exception->id; ?>" class="button button-small">Modifier</a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette exception ?');">
                                    <?php wp_nonce_field('ibs_exceptions_action'); ?>
                                    <input type="hidden" name="action" value="delete_exception">
                                    <input type="hidden" name="exception_id" value="<?php echo $exception->id; ?>">
                                    <button type="submit" class="button button-small button-link-delete">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Aide -->
    <div class="ibs-help" style="margin-top: 30px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa;">
        <h3>💡 Comment ça marche ?</h3>
        <ul>
            <li><strong>Fermetures :</strong> Utilisez le type "Fermé" pour les jours fériés, congés, etc. Aucune réservation ne sera possible</li>
            <li><strong>Ouvertures exceptionnelles :</strong> Utilisez ce type si vous ouvrez exceptionnellement avec des horaires différents (ex: samedi matin pour un événement)</li>
            <li><strong>Priorité :</strong> Les exceptions ont la priorité sur les horaires normaux pour la date concernée</li>
            <li><strong>Dates passées :</strong> Les exceptions passées sont conservées pour l'historique mais ne sont plus appliquées</li>
        </ul>

        <h4>Exemples d'utilisation :</h4>
        <ul>
            <li>25 décembre → Fermé → "Noël"</li>
            <li>1er janvier → Fermé → "Jour de l'an"</li>
            <li>Samedi 15 mars → Ouvert (10h-16h) → "Journée portes ouvertes"</li>
            <li>15 août → Fermé → "Congés annuels"</li>
        </ul>
    </div>

    <?php endif; ?>
</div>
