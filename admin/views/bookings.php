<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_bookings = $wpdb->prefix . 'ibs_bookings';
$table_stores = $wpdb->prefix . 'ibs_stores';
$table_services = $wpdb->prefix . 'ibs_services';

// Filtres
$filter_store = isset($_GET['filter_store']) ? intval($_GET['filter_store']) : 0;
$filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
$filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
$filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';

// Construire la requête
$where = ['1=1'];
if ($filter_store) {
    $where[] = $wpdb->prepare('b.store_id = %d', $filter_store);
}
if ($filter_status) {
    $where[] = $wpdb->prepare('b.status = %s', $filter_status);
}
if ($filter_date_from) {
    $where[] = $wpdb->prepare('b.booking_date >= %s', $filter_date_from);
}
if ($filter_date_to) {
    $where[] = $wpdb->prepare('b.booking_date <= %s', $filter_date_to);
}

$where_clause = implode(' AND ', $where);

$bookings = $wpdb->get_results("
    SELECT b.*, 
           st.name as store_name, 
           se.name as service_name
    FROM $table_bookings b
    LEFT JOIN $table_stores st ON b.store_id = st.id
    LEFT JOIN $table_services se ON b.service_id = se.id
    WHERE $where_clause
    ORDER BY b.booking_date DESC, b.booking_time DESC
");

$stores = $wpdb->get_results("SELECT id, name FROM $table_stores ORDER BY name ASC");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Réservations</h1>
    <hr class="wp-header-end">
    
    <!-- Filtres -->
    <div class="ibs-filters" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 4px;">
        <form method="get" action="">
            <input type="hidden" name="page" value="ikomiris-booking">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div>
                    <label>Magasin</label>
                    <select name="filter_store" class="regular-text">
                        <option value="">Tous les magasins</option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo $store->id; ?>" <?php selected($filter_store, $store->id); ?>>
                                <?php echo esc_html($store->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>Statut</label>
                    <select name="filter_status" class="regular-text">
                        <option value="">Tous les statuts</option>
                        <option value="pending" <?php selected($filter_status, 'pending'); ?>>En attente</option>
                        <option value="confirmed" <?php selected($filter_status, 'confirmed'); ?>>Confirmé</option>
                        <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>Annulé</option>
                        <option value="completed" <?php selected($filter_status, 'completed'); ?>>Terminé</option>
                    </select>
                </div>
                
                <div>
                    <label>Date de début</label>
                    <input type="date" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>" class="regular-text">
                </div>
                
                <div>
                    <label>Date de fin</label>
                    <input type="date" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>" class="regular-text">
                </div>
            </div>
            
            <p style="margin-top: 15px;">
                <button type="submit" class="button button-primary">Filtrer</button>
                <a href="?page=ikomiris-booking" class="button">Réinitialiser</a>
            </p>
        </form>
    </div>
    
    <!-- Liste des réservations -->
    <div class="ibs-table-container">
        <?php if (empty($bookings)): ?>
            <p>Aucune réservation trouvée.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date & Heure</th>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Magasin</th>
                        <th>Service</th>
                        <th>Durée</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo $booking->id; ?></td>
                            <td>
                                <strong><?php echo date('d/m/Y', strtotime($booking->booking_date)); ?></strong><br>
                                <?php echo date('H:i', strtotime($booking->booking_time)); ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html($booking->customer_firstname . ' ' . $booking->customer_lastname); ?></strong>
                                <?php if ($booking->customer_message): ?>
                                    <br><small title="<?php echo esc_attr($booking->customer_message); ?>">💬 Message</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($booking->customer_email); ?><br>
                                <?php echo esc_html($booking->customer_phone); ?>
                            </td>
                            <td><?php echo esc_html($booking->store_name); ?></td>
                            <td><?php echo esc_html($booking->service_name); ?></td>
                            <td><?php echo $booking->duration; ?> min</td>
                            <td>
                                <?php
                                $status_classes = [
                                    'pending' => 'ibs-status-pending',
                                    'confirmed' => 'ibs-status-confirmed',
                                    'cancelled' => 'ibs-status-cancelled',
                                    'completed' => 'ibs-status-completed'
                                ];
                                $status_labels = [
                                    'pending' => 'En attente',
                                    'confirmed' => 'Confirmé',
                                    'cancelled' => 'Annulé',
                                    'completed' => 'Terminé'
                                ];
                                ?>
                                <span class="<?php echo $status_classes[$booking->status] ?? ''; ?>">
                                    <?php echo $status_labels[$booking->status] ?? $booking->status; ?>
                                </span>
                            </td>
                            <td>
                                <button class="button button-small" onclick="alert('Détails: ID <?php echo $booking->id; ?>')">Détails</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Statistiques -->
    <div style="margin-top: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <?php
        $total = count($bookings);
        $confirmed = count(array_filter($bookings, fn($b) => $b->status === 'confirmed'));
        $cancelled = count(array_filter($bookings, fn($b) => $b->status === 'cancelled'));
        ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px; text-align: center;">
            <div style="font-size: 32px; font-weight: bold; color: #0073aa;"><?php echo $total; ?></div>
            <div>Total réservations</div>
        </div>
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px; text-align: center;">
            <div style="font-size: 32px; font-weight: bold; color: #28a745;"><?php echo $confirmed; ?></div>
            <div>Confirmées</div>
        </div>
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px; text-align: center;">
            <div style="font-size: 32px; font-weight: bold; color: #dc3545;"><?php echo $cancelled; ?></div>
            <div>Annulées</div>
        </div>
    </div>
</div>

<style>
.ibs-status-pending { background: #ffc107; color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
.ibs-status-confirmed { background: #28a745; color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
.ibs-status-cancelled { background: #dc3545; color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
.ibs-status-completed { background: #6c757d; color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 12px; }
</style>
