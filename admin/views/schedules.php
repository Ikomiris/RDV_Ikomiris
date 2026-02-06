<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        check_admin_referer('ibs_schedules_action');

        if ($_POST['action'] === 'save_schedule') {
            $store_id = intval($_POST['store_id']);
            $day_of_week = intval($_POST['day_of_week']);
            $time_start = sanitize_text_field($_POST['time_start']);
            $time_end = sanitize_text_field($_POST['time_end']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $data = [
                'store_id' => $store_id,
                'day_of_week' => $day_of_week,
                'time_start' => $time_start,
                'time_end' => $time_end,
                'is_active' => $is_active,
            ];

            if (!empty($_POST['schedule_id'])) {
                // Mise à jour
                $wpdb->update(
                    $wpdb->prefix . 'ibs_schedules',
                    $data,
                    ['id' => intval($_POST['schedule_id'])],
                    ['%d', '%d', '%s', '%s', '%d'],
                    ['%d']
                );
                echo '<div class="notice notice-success"><p>Horaire mis à jour avec succès.</p></div>';
            } else {
                // Insertion
                $wpdb->insert(
                    $wpdb->prefix . 'ibs_schedules',
                    $data,
                    ['%d', '%d', '%s', '%s', '%d']
                );
                echo '<div class="notice notice-success"><p>Horaire ajouté avec succès.</p></div>';
            }
        } elseif ($_POST['action'] === 'delete_schedule') {
            $schedule_id = intval($_POST['schedule_id']);
            $wpdb->delete(
                $wpdb->prefix . 'ibs_schedules',
                ['id' => $schedule_id],
                ['%d']
            );
            echo '<div class="notice notice-success"><p>Horaire supprimé avec succès.</p></div>';
        }
    }
}

// Récupérer les magasins
$stores = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ibs_stores ORDER BY name ASC");

// Magasin sélectionné
$selected_store = isset($_GET['store_id']) ? intval($_GET['store_id']) : (count($stores) > 0 ? $stores[0]->id : 0);

// Horaire en cours de modification
$edit_schedule = null;
if (isset($_GET['edit'])) {
    $edit_schedule = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ibs_schedules WHERE id = %d",
        intval($_GET['edit'])
    ));
}

// Récupérer les horaires du magasin sélectionné
$schedules = [];
if ($selected_store) {
    $schedules = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ibs_schedules WHERE store_id = %d ORDER BY day_of_week ASC, time_start ASC",
        $selected_store
    ));
}

$days = [
    0 => __('Dimanche', 'ikomiris-booking'),
    1 => __('Lundi', 'ikomiris-booking'),
    2 => __('Mardi', 'ikomiris-booking'),
    3 => __('Mercredi', 'ikomiris-booking'),
    4 => __('Jeudi', 'ikomiris-booking'),
    5 => __('Vendredi', 'ikomiris-booking'),
    6 => __('Samedi', 'ikomiris-booking'),
];
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Gestion des Horaires</h1>
    <a href="?page=ikomiris-booking-schedules&store_id=<?php echo $selected_store; ?>&add=1" class="page-title-action">Ajouter un horaire</a>
    <hr class="wp-header-end">

    <?php if (empty($stores)): ?>
        <div class="notice notice-warning">
            <p>Aucun magasin disponible. <a href="?page=ikomiris-booking-stores">Créer un magasin</a> avant de définir les horaires.</p>
        </div>
    <?php else: ?>

    <!-- Sélecteur de magasin -->
    <div class="ibs-store-selector" style="margin: 20px 0;">
        <label for="store-selector" style="font-weight: 600; margin-right: 10px;">Magasin :</label>
        <select id="store-selector" onchange="window.location.href='?page=ikomiris-booking-schedules&store_id=' + this.value">
            <?php foreach ($stores as $store): ?>
                <option value="<?php echo $store->id; ?>" <?php selected($selected_store, $store->id); ?>>
                    <?php echo esc_html($store->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Formulaire d'ajout/modification -->
    <?php if (isset($_GET['add']) || $edit_schedule): ?>
        <div class="ibs-form-card" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 4px;">
            <h2><?php echo $edit_schedule ? 'Modifier l\'horaire' : 'Ajouter un horaire'; ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('ibs_schedules_action'); ?>
                <input type="hidden" name="action" value="save_schedule">
                <input type="hidden" name="store_id" value="<?php echo $selected_store; ?>">
                <?php if ($edit_schedule): ?>
                    <input type="hidden" name="schedule_id" value="<?php echo $edit_schedule->id; ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="day_of_week">Jour de la semaine *</label></th>
                        <td>
                            <select name="day_of_week" id="day_of_week" required>
                                <?php
                                $default_day = isset($_GET['day']) ? intval($_GET['day']) : ($edit_schedule ? $edit_schedule->day_of_week : 1);
                                // Afficher dans l'ordre Lundi à Dimanche
                                $day_order = [1, 2, 3, 4, 5, 6, 0];
                                foreach ($day_order as $num):
                                ?>
                                    <option value="<?php echo $num; ?>" <?php selected($default_day, $num); ?>>
                                        <?php echo esc_html($days[$num]); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="time_start">Heure d'ouverture *</label></th>
                        <td>
                            <input type="time" name="time_start" id="time_start"
                                   value="<?php echo $edit_schedule ? esc_attr(substr($edit_schedule->time_start, 0, 5)) : '09:00'; ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="time_end">Heure de fermeture *</label></th>
                        <td>
                            <input type="time" name="time_end" id="time_end"
                                   value="<?php echo $edit_schedule ? esc_attr(substr($edit_schedule->time_end, 0, 5)) : '18:00'; ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="is_active">Actif</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" id="is_active" value="1"
                                       <?php checked($edit_schedule ? $edit_schedule->is_active : 1, 1); ?>>
                                Activer cet horaire
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $edit_schedule ? 'Mettre à jour' : 'Ajouter'; ?>
                    </button>
                    <a href="?page=ikomiris-booking-schedules&store_id=<?php echo $selected_store; ?>" class="button">Annuler</a>
                </p>
            </form>
        </div>
    <?php endif; ?>

    <!-- Liste des horaires -->
    <div class="ibs-schedules-list" style="margin-top: 30px;">
        <h2>Horaires de la semaine</h2>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 15%;">Jour</th>
                    <th style="width: 60%;">Horaires</th>
                    <th style="width: 25%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Grouper les horaires par jour
                $schedules_by_day = [];
                foreach ($schedules as $schedule) {
                    if (!isset($schedules_by_day[$schedule->day_of_week])) {
                        $schedules_by_day[$schedule->day_of_week] = [];
                    }
                    $schedules_by_day[$schedule->day_of_week][] = $schedule;
                }

                // Afficher tous les jours de Lundi (1) à Dimanche (0)
                $day_order = [1, 2, 3, 4, 5, 6, 0]; // Lundi à Dimanche
                foreach ($day_order as $day_num):
                    $day_schedules = isset($schedules_by_day[$day_num]) ? $schedules_by_day[$day_num] : [];
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($days[$day_num]); ?></strong></td>
                        <td>
                            <?php if (empty($day_schedules)): ?>
                                <span style="color: #999;">Fermé</span>
                            <?php else: ?>
                                <?php foreach ($day_schedules as $schedule): ?>
                                    <div style="margin-bottom: 5px;">
                                        <?php echo esc_html(substr($schedule->time_start, 0, 5)); ?> -
                                        <?php echo esc_html(substr($schedule->time_end, 0, 5)); ?>
                                        <?php if ($schedule->is_active): ?>
                                            <span style="color: #46b450; margin-left: 10px;">✓ Actif</span>
                                        <?php else: ?>
                                            <span style="color: #dc3232; margin-left: 10px;">✗ Inactif</span>
                                        <?php endif; ?>
                                        <span style="margin-left: 10px;">
                                            <a href="?page=ikomiris-booking-schedules&store_id=<?php echo $selected_store; ?>&edit=<?php echo $schedule->id; ?>" style="text-decoration: none;">✏️ Modifier</a>
                                            |
                                            <a href="#" onclick="if(confirm('Supprimer cet horaire ?')) { document.getElementById('delete-form-<?php echo $schedule->id; ?>').submit(); } return false;" style="color: #dc3232; text-decoration: none;">🗑️ Supprimer</a>
                                        </span>
                                        <form id="delete-form-<?php echo $schedule->id; ?>" method="post" style="display: none;">
                                            <?php wp_nonce_field('ibs_schedules_action'); ?>
                                            <input type="hidden" name="action" value="delete_schedule">
                                            <input type="hidden" name="schedule_id" value="<?php echo $schedule->id; ?>">
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?page=ikomiris-booking-schedules&store_id=<?php echo $selected_store; ?>&add=1&day=<?php echo $day_num; ?>" class="button button-small">
                                <?php echo empty($day_schedules) ? '+ Ajouter' : '+ Ajouter un autre créneau'; ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Aide -->
    <div class="ibs-help" style="margin-top: 30px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa;">
        <h3>💡 Comment ça marche ?</h3>
        <ul>
            <li><strong>Jour de la semaine :</strong> Définissez les horaires pour chaque jour (0 = Dimanche, 6 = Samedi)</li>
            <li><strong>Plusieurs plages horaires :</strong> Vous pouvez créer plusieurs horaires pour le même jour (ex: 9h-12h et 14h-18h)</li>
            <li><strong>Fermeture :</strong> Si un jour n'a pas d'horaire configuré, le magasin sera considéré comme fermé</li>
            <li><strong>Créneaux disponibles :</strong> Les clients ne pourront réserver que pendant les horaires actifs</li>
        </ul>
    </div>

    <?php endif; ?>
</div>
