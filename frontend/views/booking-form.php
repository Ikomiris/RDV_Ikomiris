<div class="ibs-booking-container" id="ibs-booking-form">
    
    <!-- Étape 1: Sélection du magasin -->
    <div class="ibs-step" id="ibs-step-store" data-step="1">
        <h2 class="ibs-step-title"><?php _e('Choisissez votre magasin', 'ikomiris-booking'); ?></h2>
        <div class="ibs-stores-grid" id="ibs-stores-list">
            <div class="ibs-loading"><?php _e('Chargement...', 'ikomiris-booking'); ?></div>
        </div>
    </div>
    
    <!-- Étape 2: Sélection du service -->
    <div class="ibs-step ibs-step-hidden" id="ibs-step-service" data-step="2">
        <h2 class="ibs-step-title"><?php _e('Choisissez votre service', 'ikomiris-booking'); ?></h2>
        <button class="ibs-back-btn" data-back-to="1">← <?php _e('Retour', 'ikomiris-booking'); ?></button>
        <div class="ibs-services-grid" id="ibs-services-list">
            <div class="ibs-loading"><?php _e('Chargement...', 'ikomiris-booking'); ?></div>
        </div>
    </div>
    
    <!-- Étape 3: Sélection de la date -->
    <div class="ibs-step ibs-step-hidden" id="ibs-step-date" data-step="3">
        <h2 class="ibs-step-title"><?php _e('Choisissez une date', 'ikomiris-booking'); ?></h2>
        <button class="ibs-back-btn" data-back-to="2">← <?php _e('Retour', 'ikomiris-booking'); ?></button>
        <div class="ibs-calendar-container">
            <input type="date" id="ibs-date-picker" class="ibs-date-picker" min="<?php echo date('Y-m-d'); ?>">
        </div>
    </div>
    
    <!-- Étape 4: Sélection de l'heure -->
    <div class="ibs-step ibs-step-hidden" id="ibs-step-time" data-step="4">
        <h2 class="ibs-step-title"><?php _e('Choisissez un créneau horaire', 'ikomiris-booking'); ?></h2>
        <button class="ibs-back-btn" data-back-to="3">← <?php _e('Retour', 'ikomiris-booking'); ?></button>
        <div class="ibs-selected-date" id="ibs-selected-date-display"></div>
        <div class="ibs-slots-grid" id="ibs-slots-list">
            <div class="ibs-loading"><?php _e('Chargement...', 'ikomiris-booking'); ?></div>
        </div>
    </div>
    
    <!-- Étape 5: Formulaire client -->
    <div class="ibs-step ibs-step-hidden" id="ibs-step-form" data-step="5">
        <h2 class="ibs-step-title"><?php _e('Vos coordonnées', 'ikomiris-booking'); ?></h2>
        <button class="ibs-back-btn" data-back-to="4">← <?php _e('Retour', 'ikomiris-booking'); ?></button>
        
        <div class="ibs-booking-summary" id="ibs-booking-summary"></div>
        
        <form id="ibs-customer-form" class="ibs-form">
            <div class="ibs-form-row">
                <div class="ibs-form-group">
                    <label for="ibs-firstname"><?php _e('Prénom', 'ikomiris-booking'); ?> *</label>
                    <input type="text" id="ibs-firstname" name="firstname" required>
                </div>
                <div class="ibs-form-group">
                    <label for="ibs-lastname"><?php _e('Nom', 'ikomiris-booking'); ?> *</label>
                    <input type="text" id="ibs-lastname" name="lastname" required>
                </div>
            </div>
            
            <div class="ibs-form-row">
                <div class="ibs-form-group">
                    <label for="ibs-email"><?php _e('Email', 'ikomiris-booking'); ?> *</label>
                    <input type="email" id="ibs-email" name="email" required>
                </div>
                <div class="ibs-form-group">
                    <label for="ibs-phone"><?php _e('Téléphone', 'ikomiris-booking'); ?> *</label>
                    <input type="tel" id="ibs-phone" name="phone" required>
                </div>
            </div>
            
            <div class="ibs-form-group">
                <label for="ibs-message"><?php _e('Message (optionnel)', 'ikomiris-booking'); ?></label>
                <textarea id="ibs-message" name="message" rows="4"></textarea>
            </div>
            
            <div class="ibs-form-group ibs-checkbox-group">
                <label>
                    <input type="checkbox" id="ibs-terms" name="terms" required>
                    <?php _e("J'accepte les conditions générales", 'ikomiris-booking'); ?> *
                </label>
            </div>
            
            <button type="submit" class="ibs-submit-btn">
                <?php _e('Confirmer ma réservation', 'ikomiris-booking'); ?>
            </button>
        </form>
    </div>
    
    <!-- Étape 6: Confirmation -->
    <div class="ibs-step ibs-step-hidden" id="ibs-step-confirmation" data-step="6">
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
