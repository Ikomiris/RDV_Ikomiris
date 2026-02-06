<div class="ibs-booking-container" id="ibs-booking-form">

    <!-- Section 1: Sélection du magasin -->
    <div class="ibs-section" id="ibs-section-store">
        <h2 class="ibs-section-title"><?php _e('Choisissez votre magasin', 'ikomiris-booking'); ?></h2>
        <div class="ibs-stores-grid" id="ibs-stores-list">
            <div class="ibs-loading"><?php _e('Chargement...', 'ikomiris-booking'); ?></div>
        </div>
    </div>

    <!-- Section 2: Sélection du service -->
    <div class="ibs-section ibs-section-disabled" id="ibs-section-service">
        <h2 class="ibs-section-title"><?php _e('Choisissez votre service', 'ikomiris-booking'); ?></h2>
        <div class="ibs-services-grid" id="ibs-services-list">
            <p class="ibs-section-placeholder"><?php _e('Sélectionnez d\'abord un magasin', 'ikomiris-booking'); ?></p>
        </div>
    </div>

    <!-- Section 3: Sélection de la date -->
    <div class="ibs-section ibs-section-disabled" id="ibs-section-date">
        <h2 class="ibs-section-title"><?php _e('Choisissez une date', 'ikomiris-booking'); ?></h2>
        <div class="ibs-date-container">
            <?php
            global $wpdb;
            $min_booking_delay = $wpdb->get_var("SELECT setting_value FROM {$wpdb->prefix}ibs_settings WHERE setting_key = 'min_booking_delay'");
            $max_booking_delay = $wpdb->get_var("SELECT setting_value FROM {$wpdb->prefix}ibs_settings WHERE setting_key = 'max_booking_delay'");

            $min_booking_delay = $min_booking_delay !== null ? intval($min_booking_delay) : 2;
            $max_booking_delay = $max_booking_delay !== null ? intval($max_booking_delay) : 90;

            // Calculer la date minimum (maintenant + délai minimum en heures)
            $min_date = date('Y-m-d', strtotime('+' . ceil($min_booking_delay / 24) . ' days'));
            // Calculer la date maximum (maintenant + délai maximum en jours)
            $max_date = date('Y-m-d', strtotime('+' . $max_booking_delay . ' days'));
            ?>
            <div class="ibs-date-picker-wrapper">
                <input type="text" id="ibs-date-picker" class="ibs-date-picker-input"
                       placeholder="<?php _e('Cliquez pour sélectionner une date', 'ikomiris-booking'); ?>"
                       readonly
                       disabled>
                <button type="button" class="ibs-date-btn ibs-date-btn-disabled" id="ibs-date-btn">
                    📅 <?php _e('Sélectionner une date', 'ikomiris-booking'); ?>
                </button>
            </div>
            <div class="ibs-selected-date-info" id="ibs-selected-date-info" style="display: none;"></div>
        </div>
    </div>

    <!-- Section 4: Sélection de l'heure -->
    <div class="ibs-section ibs-section-disabled" id="ibs-section-time">
        <h2 class="ibs-section-title"><?php _e('Choisissez un créneau horaire', 'ikomiris-booking'); ?></h2>
        <div class="ibs-slots-grid" id="ibs-slots-list">
            <p class="ibs-section-placeholder"><?php _e('Sélectionnez d\'abord une date', 'ikomiris-booking'); ?></p>
        </div>
    </div>

    <!-- Section 5: Formulaire client et récapitulatif -->
    <div class="ibs-section ibs-section-disabled" id="ibs-section-form">
        <h2 class="ibs-section-title"><?php _e('Vos coordonnées', 'ikomiris-booking'); ?></h2>

        <div class="ibs-booking-summary" id="ibs-booking-summary" style="display: none;"></div>

        <form id="ibs-customer-form" class="ibs-form">
            <div class="ibs-form-row">
                <div class="ibs-form-group">
                    <label for="ibs-firstname"><?php _e('Prénom', 'ikomiris-booking'); ?> *</label>
                    <input type="text" id="ibs-firstname" name="firstname" required disabled>
                </div>
                <div class="ibs-form-group">
                    <label for="ibs-lastname"><?php _e('Nom', 'ikomiris-booking'); ?> *</label>
                    <input type="text" id="ibs-lastname" name="lastname" required disabled>
                </div>
            </div>

            <div class="ibs-form-row">
                <div class="ibs-form-group">
                    <label for="ibs-email"><?php _e('Email', 'ikomiris-booking'); ?> *</label>
                    <input type="email" id="ibs-email" name="email" required disabled>
                </div>
                <div class="ibs-form-group">
                    <label for="ibs-phone"><?php _e('Téléphone', 'ikomiris-booking'); ?> *</label>
                    <input type="tel" id="ibs-phone" name="phone" required disabled>
                </div>
            </div>

            <div class="ibs-form-row ibs-gift-card-row">
                <div class="ibs-form-group">
                    <label class="ibs-gift-card-label" for="ibs-gift-card-enabled">
                        <input type="checkbox" id="ibs-gift-card-enabled" name="gift_card_enabled" disabled>
                        <?php _e("J'ai une carte cadeau", 'ikomiris-booking'); ?>
                    </label>
                </div>
                <div class="ibs-form-group">
                    <label for="ibs-gift-card-code"><?php _e('Code', 'ikomiris-booking'); ?></label>
                    <input type="text" id="ibs-gift-card-code" name="gift_card_code" class="ibs-gift-card-code-input" autocomplete="off" disabled>
                </div>
            </div>

            <div class="ibs-form-group">
                <label for="ibs-message"><?php _e('Message (optionnel)', 'ikomiris-booking'); ?></label>
                <textarea id="ibs-message" name="message" rows="4" disabled></textarea>
            </div>

            <div class="ibs-form-group ibs-checkbox-group">
                <label>
                    <input type="checkbox" id="ibs-terms" name="terms" required disabled>
                    <?php _e("J'accepte les conditions générales", 'ikomiris-booking'); ?> *
                </label>
            </div>

            <button type="submit" class="ibs-submit-btn" disabled>
                <?php _e('Confirmer ma réservation', 'ikomiris-booking'); ?>
            </button>
        </form>
    </div>

    <!-- Section 6: Confirmation (masquée par défaut) -->
    <div class="ibs-section" id="ibs-section-confirmation" style="display: none;">
        <div class="ibs-confirmation-message">
            <div class="ibs-success-icon">✓</div>
            <h2><?php _e('Réservation confirmée !', 'ikomiris-booking'); ?></h2>
            <p id="ibs-confirmation-text"></p>
            <button class="ibs-new-booking-btn" onclick="location.reload()">
                <?php _e('Prendre un nouveau rendez-vous', 'ikomiris-booking'); ?>
            </button>
        </div>
    </div>

</div>
