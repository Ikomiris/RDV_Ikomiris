<?php
/**
 * Template de page d'annulation de réservation
 *
 * Variables disponibles :
 * - $token : Token d'annulation
 * - $booking : Objet contenant les détails de la réservation
 */

if (!defined('ABSPATH')) {
    exit;
}

// Enqueue les styles et scripts frontend
wp_enqueue_style('ibs-frontend-css');
wp_enqueue_script('jquery');

// Créer un nonce pour la sécurité AJAX
$cancel_nonce = wp_create_nonce('ibs_cancel_booking_nonce');
?>

<div class="ibs-cancellation-page">
    <div class="ibs-cancellation-container">

        <?php if (!$booking): ?>
            <!-- Réservation introuvable -->
            <div class="ibs-cancellation-error">
                <div class="ibs-error-icon">❌</div>
                <h1><?php _e('Réservation introuvable', 'ikomiris-booking'); ?></h1>
                <p><?php _e('Le lien d\'annulation est invalide ou a expiré.', 'ikomiris-booking'); ?></p>
                <p><?php _e('Si vous avez besoin d\'aide, veuillez nous contacter directement.', 'ikomiris-booking'); ?></p>
                <a href="<?php echo home_url(); ?>" class="ibs-btn ibs-btn-primary">
                    <?php _e('Retour à l\'accueil', 'ikomiris-booking'); ?>
                </a>
            </div>

        <?php elseif ($booking->status === 'cancelled'): ?>
            <!-- Réservation déjà annulée -->
            <div class="ibs-cancellation-already">
                <div class="ibs-info-icon">ℹ️</div>
                <h1><?php _e('Réservation déjà annulée', 'ikomiris-booking'); ?></h1>
                <p><?php _e('Cette réservation a déjà été annulée.', 'ikomiris-booking'); ?></p>

                <div class="ibs-booking-summary">
                    <h3><?php _e('Détails de la réservation', 'ikomiris-booking'); ?></h3>
                    <table class="ibs-details-table">
                        <tr>
                            <td><strong><?php _e('Numéro :', 'ikomiris-booking'); ?></strong></td>
                            <td>#<?php echo esc_html($booking->id); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Service :', 'ikomiris-booking'); ?></strong></td>
                            <td><?php echo esc_html($booking->service_name); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Date :', 'ikomiris-booking'); ?></strong></td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($booking->booking_date)); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Heure :', 'ikomiris-booking'); ?></strong></td>
                            <td><?php echo date_i18n(get_option('time_format'), strtotime($booking->booking_time)); ?></td>
                        </tr>
                        <?php if (!empty($booking->cancelled_at)): ?>
                        <tr>
                            <td><strong><?php _e('Annulé le :', 'ikomiris-booking'); ?></strong></td>
                            <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->cancelled_at)); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <a href="<?php echo home_url(); ?>" class="ibs-btn ibs-btn-primary">
                    <?php _e('Retour à l\'accueil', 'ikomiris-booking'); ?>
                </a>
            </div>

        <?php else: ?>
            <!-- Formulaire d'annulation -->
            <div class="ibs-cancellation-form">
                <div class="ibs-warning-icon">⚠️</div>
                <h1><?php _e('Annuler votre réservation', 'ikomiris-booking'); ?></h1>
                <p><?php _e('Êtes-vous sûr de vouloir annuler cette réservation ?', 'ikomiris-booking'); ?></p>

                <div class="ibs-booking-summary">
                    <h3><?php _e('Détails de la réservation', 'ikomiris-booking'); ?></h3>
                    <table class="ibs-details-table">
                        <tr>
                            <td><strong><?php _e('Numéro :', 'ikomiris-booking'); ?></strong></td>
                            <td>#<?php echo esc_html($booking->id); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Client :', 'ikomiris-booking'); ?></strong></td>
                            <td><?php echo esc_html($booking->customer_firstname . ' ' . $booking->customer_lastname); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Service :', 'ikomiris-booking'); ?></strong></td>
                            <td><?php echo esc_html($booking->service_name); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Durée :', 'ikomiris-booking'); ?></strong></td>
                            <td><?php echo esc_html($booking->duration); ?> minutes</td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Date :', 'ikomiris-booking'); ?></strong></td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($booking->booking_date)); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Heure :', 'ikomiris-booking'); ?></strong></td>
                            <td><?php echo date_i18n(get_option('time_format'), strtotime($booking->booking_time)); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Magasin :', 'ikomiris-booking'); ?></strong></td>
                            <td>
                                <?php echo esc_html($booking->store_name); ?>
                                <?php if (!empty($booking->store_address)): ?>
                                    <br><small><?php echo esc_html($booking->store_address); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php
                $cancellation_hours = !empty($booking->cancellation_hours) ? intval($booking->cancellation_hours) : 24;
                $booking_datetime = strtotime($booking->booking_date . ' ' . $booking->booking_time);
                $now = current_time('timestamp');
                $cancellation_deadline = $booking_datetime - ($cancellation_hours * 3600);
                $hours_remaining = ($cancellation_deadline - $now) / 3600;
                ?>

                <?php if ($now > $cancellation_deadline): ?>
                    <!-- Délai d'annulation dépassé -->
                    <div class="ibs-cancellation-deadline-passed">
                        <p class="ibs-error-message">
                            <?php printf(
                                __('Le délai d\'annulation est dépassé. Vous devez annuler au moins %d heures avant votre rendez-vous.', 'ikomiris-booking'),
                                $cancellation_hours
                            ); ?>
                        </p>
                        <p><?php _e('Pour toute question, veuillez nous contacter directement.', 'ikomiris-booking'); ?></p>
                        <a href="<?php echo home_url(); ?>" class="ibs-btn ibs-btn-secondary">
                            <?php _e('Retour à l\'accueil', 'ikomiris-booking'); ?>
                        </a>
                    </div>

                <?php elseif ($booking_datetime < $now): ?>
                    <!-- Réservation passée -->
                    <div class="ibs-cancellation-past">
                        <p class="ibs-error-message">
                            <?php _e('Impossible d\'annuler une réservation passée.', 'ikomiris-booking'); ?>
                        </p>
                        <a href="<?php echo home_url(); ?>" class="ibs-btn ibs-btn-secondary">
                            <?php _e('Retour à l\'accueil', 'ikomiris-booking'); ?>
                        </a>
                    </div>

                <?php else: ?>
                    <!-- Formulaire de confirmation d'annulation -->
                    <div class="ibs-cancellation-info">
                        <?php if ($hours_remaining < 48): ?>
                            <p class="ibs-warning-message">
                                ⏰ <?php printf(
                                    __('Il vous reste environ %d heures pour annuler cette réservation.', 'ikomiris-booking'),
                                    ceil($hours_remaining)
                                ); ?>
                            </p>
                        <?php endif; ?>
                        <p><?php _e('Après annulation, vous recevrez un email de confirmation et le créneau sera de nouveau disponible pour d\'autres clients.', 'ikomiris-booking'); ?></p>
                    </div>

                    <div id="ibs-cancellation-message"></div>

                    <div class="ibs-cancellation-actions">
                        <button type="button" id="ibs-confirm-cancellation" class="ibs-btn ibs-btn-danger">
                            <?php _e('Oui, annuler ma réservation', 'ikomiris-booking'); ?>
                        </button>
                        <a href="<?php echo home_url(); ?>" class="ibs-btn ibs-btn-secondary">
                            <?php _e('Non, conserver ma réservation', 'ikomiris-booking'); ?>
                        </a>
                    </div>

                    <div id="ibs-cancellation-loading" style="display: none;">
                        <div class="ibs-spinner"></div>
                        <p><?php _e('Annulation en cours...', 'ikomiris-booking'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#ibs-confirm-cancellation').on('click', function(e) {
        e.preventDefault();

        if (!confirm('<?php echo esc_js(__('Êtes-vous absolument certain de vouloir annuler cette réservation ?', 'ikomiris-booking')); ?>')) {
            return;
        }

        // Masquer le bouton et afficher le loading
        $('.ibs-cancellation-actions').hide();
        $('#ibs-cancellation-loading').show();
        $('#ibs-cancellation-message').html('');

        // Envoyer la requête AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'ibs_cancel_booking',
                token: '<?php echo esc_js($token); ?>',
                nonce: '<?php echo $cancel_nonce; ?>'
            },
            success: function(response) {
                $('#ibs-cancellation-loading').hide();

                if (response.success) {
                    $('#ibs-cancellation-message').html(
                        '<div class="ibs-success-message">' +
                        '<div class="ibs-success-icon">✅</div>' +
                        '<h2><?php echo esc_js(__('Réservation annulée', 'ikomiris-booking')); ?></h2>' +
                        '<p>' + response.data.message + '</p>' +
                        '<p><?php echo esc_js(__('Vous allez recevoir un email de confirmation d\'annulation.', 'ikomiris-booking')); ?></p>' +
                        '<a href="<?php echo home_url(); ?>" class="ibs-btn ibs-btn-primary"><?php echo esc_js(__('Retour à l\'accueil', 'ikomiris-booking')); ?></a>' +
                        '</div>'
                    );
                } else {
                    $('#ibs-cancellation-message').html(
                        '<div class="ibs-error-message">' +
                        '<p>❌ ' + response.data.message + '</p>' +
                        '</div>'
                    );
                    $('.ibs-cancellation-actions').show();
                }
            },
            error: function(xhr, status, error) {
                $('#ibs-cancellation-loading').hide();
                $('#ibs-cancellation-message').html(
                    '<div class="ibs-error-message">' +
                    '<p>❌ <?php echo esc_js(__('Une erreur est survenue. Veuillez réessayer.', 'ikomiris-booking')); ?></p>' +
                    '</div>'
                );
                $('.ibs-cancellation-actions').show();
            }
        });
    });
});
</script>

<style>
.ibs-cancellation-page {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}

.ibs-cancellation-container {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 40px;
}

.ibs-cancellation-form h1,
.ibs-cancellation-error h1,
.ibs-cancellation-already h1 {
    margin-top: 20px;
    margin-bottom: 15px;
    color: #333;
}

.ibs-error-icon,
.ibs-info-icon,
.ibs-warning-icon,
.ibs-success-icon {
    font-size: 64px;
    text-align: center;
    margin-bottom: 20px;
}

.ibs-booking-summary {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 20px;
    margin: 25px 0;
}

.ibs-booking-summary h3 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #495057;
}

.ibs-details-table {
    width: 100%;
    border-collapse: collapse;
}

.ibs-details-table tr {
    border-bottom: 1px solid #e9ecef;
}

.ibs-details-table tr:last-child {
    border-bottom: none;
}

.ibs-details-table td {
    padding: 12px 8px;
    vertical-align: top;
}

.ibs-details-table td:first-child {
    width: 40%;
    color: #6c757d;
}

.ibs-cancellation-info {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 6px;
    padding: 15px;
    margin: 20px 0;
}

.ibs-warning-message {
    color: #856404;
    font-weight: bold;
    margin-bottom: 10px;
}

.ibs-error-message {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 15px;
    border-radius: 6px;
    margin: 20px 0;
}

.ibs-success-message {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 30px;
    border-radius: 6px;
    margin: 20px 0;
    text-align: center;
}

.ibs-cancellation-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 30px;
}

.ibs-btn {
    display: inline-block;
    padding: 12px 30px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}

.ibs-btn-danger {
    background: #dc3545;
    color: #fff;
}

.ibs-btn-danger:hover {
    background: #c82333;
}

.ibs-btn-primary {
    background: #007bff;
    color: #fff;
}

.ibs-btn-primary:hover {
    background: #0056b3;
}

.ibs-btn-secondary {
    background: #6c757d;
    color: #fff;
}

.ibs-btn-secondary:hover {
    background: #545b62;
}

#ibs-cancellation-loading {
    text-align: center;
    padding: 30px;
}

.ibs-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .ibs-cancellation-container {
        padding: 20px;
    }

    .ibs-cancellation-actions {
        flex-direction: column;
    }

    .ibs-btn {
        width: 100%;
    }

    .ibs-details-table td:first-child {
        width: 35%;
    }
}
</style>
