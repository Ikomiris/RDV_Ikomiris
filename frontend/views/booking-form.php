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

            // Permettre aujourd'hui - le filtrage des créneaux par délai minimum se fait côté backend
            $min_date = date('Y-m-d');
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
            <div class="ibs-card-section card-section">
                <div class="ibs-card-header">
                    <span class="ibs-section-icon">👤</span>
                    <h3><?php _e('Vos coordonnées', 'ikomiris-booking'); ?></h3>
                </div>
                <div class="ibs-grid-2">
                    <div class="ibs-form-group">
                        <label for="ibs-firstname"><?php _e('Prénom', 'ikomiris-booking'); ?> <span class="ibs-required">*</span></label>
                        <input type="text" id="ibs-firstname" name="prenom" required disabled>
                    </div>
                    <div class="ibs-form-group">
                        <label for="ibs-lastname"><?php _e('Nom', 'ikomiris-booking'); ?> <span class="ibs-required">*</span></label>
                        <input type="text" id="ibs-lastname" name="nom" required disabled>
                    </div>
                    <div class="ibs-form-group">
                        <label for="ibs-email"><?php _e('Email', 'ikomiris-booking'); ?> <span class="ibs-required">*</span></label>
                        <input type="email" id="ibs-email" name="email" required disabled>
                    </div>
                    <div class="ibs-form-group">
                        <label for="ibs-phone"><?php _e('Téléphone', 'ikomiris-booking'); ?> <span class="ibs-required">*</span></label>
                        <input type="tel" id="ibs-phone" name="telephone" required disabled>
                    </div>
                </div>
            </div>

            <div class="ibs-card-section card-section">
                <label class="ibs-gift-card-toggle" for="ibs-has-gift-card">
                    <input type="checkbox" id="ibs-has-gift-card" name="hasGiftCard" disabled>
                    <?php _e("🎁 J'ai une carte cadeau", 'ikomiris-booking'); ?>
                </label>
                <div class="ibs-form-group ibs-gift-card-code-wrapper" id="ibs-gift-card-code-wrapper" style="display: none;">
                    <label for="ibs-gift-card-code"><?php _e('Code de la carte cadeau', 'ikomiris-booking'); ?></label>
                    <input type="text" id="ibs-gift-card-code" name="giftCardCode" class="ibs-gift-card-code-input" placeholder="<?php _e('Entrez votre code', 'ikomiris-booking'); ?>" autocomplete="off" disabled>
                </div>
            </div>

            <div class="ibs-card-section card-section">
                <div class="ibs-card-header">
                    <span class="ibs-section-icon">👨‍👩‍👧</span>
                    <h3><?php _e("Confirmation d'âge", 'ikomiris-booking'); ?></h3>
                </div>
                <div class="ibs-age-box">
                    <label class="ibs-checkbox-inline" for="ibs-age-confirm">
                        <input type="checkbox" id="ibs-age-confirm" name="ageConfirm" required disabled>
                        <?php _e('Je confirme que toutes les personnes photographiées ont au moins 6 ans', 'ikomiris-booking'); ?> <span class="ibs-required">*</span>
                    </label>
                </div>
            </div>

            <div class="ibs-card-section card-section">
                <div class="ibs-card-header">
                    <span class="ibs-section-icon">💬</span>
                    <h3><?php _e('Message complémentaire', 'ikomiris-booking'); ?></h3>
                </div>
                <div class="ibs-form-group">
                    <label for="ibs-message"><?php _e('Message (optionnel)', 'ikomiris-booking'); ?></label>
                    <textarea id="ibs-message" name="message" rows="4" placeholder="<?php _e('Des précisions à nous transmettre ?', 'ikomiris-booking'); ?>" disabled></textarea>
                </div>
            </div>

            <div class="ibs-terms-box terms-checkbox">
                <input type="checkbox" id="ibs-terms" name="terms" required disabled>
                <label for="ibs-terms">
                    <?php _e("J'accepte les conditions générales d'utilisation", 'ikomiris-booking'); ?> <span class="ibs-required">*</span>
                </label>
            </div>

            <button type="submit" class="ibs-submit-btn btn-submit" disabled>
                <?php _e('Confirmer Ma Réservation', 'ikomiris-booking'); ?>
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
