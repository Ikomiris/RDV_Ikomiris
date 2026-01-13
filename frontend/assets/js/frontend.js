(function($) {
    'use strict';
    
    const IBS = {
        currentStep: 1,
        selectedStore: null,
        selectedService: null,
        selectedDate: null,
        selectedTime: null,
        
        init: function() {
            this.loadStores();
            this.bindEvents();
        },
        
        bindEvents: function() {
            const self = this;
            
            // Navigation retour
            $(document).on('click', '.ibs-back-btn', function() {
                const backTo = parseInt($(this).data('back-to'));
                self.goToStep(backTo);
            });
            
            // Sélection du magasin
            $(document).on('click', '.ibs-store-card', function() {
                self.selectedStore = $(this).data('store-id');
                self.loadServices(self.selectedStore);
                self.goToStep(2);
            });
            
            // Sélection du service
            $(document).on('click', '.ibs-service-card', function() {
                self.selectedService = $(this).data('service-id');
                self.goToStep(3);
            });
            
            // Sélection de la date
            $('#ibs-date-picker').on('change', function() {
                self.selectedDate = $(this).val();
                if (self.selectedDate) {
                    self.loadAvailableSlots();
                    self.goToStep(4);
                }
            });
            
            // Sélection du créneau
            $(document).on('click', '.ibs-slot-btn', function() {
                self.selectedTime = $(this).data('time');
                self.displayBookingSummary();
                self.goToStep(5);
            });
            
            // Soumission du formulaire
            $('#ibs-customer-form').on('submit', function(e) {
                e.preventDefault();
                self.submitBooking();
            });
        },
        
        goToStep: function(step) {
            $('.ibs-step').addClass('ibs-step-hidden');
            $('#ibs-step-' + this.getStepName(step)).removeClass('ibs-step-hidden');
            this.currentStep = step;
            
            // Scroll to top
            $('html, body').animate({
                scrollTop: $('#ibs-booking-form').offset().top - 50
            }, 300);
        },
        
        getStepName: function(step) {
            const steps = {
                1: 'store',
                2: 'service',
                3: 'date',
                4: 'time',
                5: 'form',
                6: 'confirmation'
            };
            return steps[step];
        },
        
        loadStores: function() {
            const self = this;
            
            $.ajax({
                url: ibsFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ibs_get_stores',
                    nonce: ibsFrontend.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderStores(response.data);
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function() {
                    self.showError(ibsFrontend.strings.error);
                }
            });
        },
        
        renderStores: function(stores) {
            const $container = $('#ibs-stores-list');
            $container.empty();
            
            if (stores.length === 0) {
                $container.html('<p class="ibs-no-slots">Aucun magasin disponible</p>');
                return;
            }
            
            stores.forEach(function(store) {
                const imageHtml = store.image_url 
                    ? `<img src="${store.image_url}" alt="${store.name}">`
                    : '';
                
                const html = `
                    <div class="ibs-store-card" data-store-id="${store.id}">
                        ${imageHtml}
                        <div class="ibs-store-name">${store.name}</div>
                        ${store.address ? `<div class="ibs-store-address">${store.address}</div>` : ''}
                        ${store.phone ? `<div class="ibs-store-contact">📞 ${store.phone}</div>` : ''}
                        ${store.email ? `<div class="ibs-store-contact">✉️ ${store.email}</div>` : ''}
                    </div>
                `;
                $container.append(html);
            });
        },
        
        loadServices: function(storeId) {
            const self = this;
            $('#ibs-services-list').html('<div class="ibs-loading">' + ibsFrontend.strings.loading + '</div>');
            
            $.ajax({
                url: ibsFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ibs_get_services',
                    nonce: ibsFrontend.nonce,
                    store_id: storeId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderServices(response.data);
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function() {
                    self.showError(ibsFrontend.strings.error);
                }
            });
        },
        
        renderServices: function(services) {
            const $container = $('#ibs-services-list');
            $container.empty();
            
            if (services.length === 0) {
                $container.html('<p class="ibs-no-slots">Aucun service disponible</p>');
                return;
            }
            
            services.forEach(function(service) {
                const imageHtml = service.image_url 
                    ? `<img src="${service.image_url}" alt="${service.name}">`
                    : '';
                
                const priceHtml = service.price 
                    ? `<span class="ibs-service-price">${parseFloat(service.price).toFixed(2)}€</span>`
                    : '';
                
                const html = `
                    <div class="ibs-service-card" data-service-id="${service.id}">
                        ${imageHtml}
                        <div class="ibs-service-name">${service.name}</div>
                        ${service.description ? `<div class="ibs-service-description">${service.description}</div>` : ''}
                        <div class="ibs-service-info">
                            <span class="ibs-service-duration">⏱️ ${service.duration} min</span>
                            ${priceHtml}
                        </div>
                    </div>
                `;
                $container.append(html);
            });
        },
        
        loadAvailableSlots: function() {
            const self = this;
            $('#ibs-slots-list').html('<div class="ibs-loading">' + ibsFrontend.strings.loading + '</div>');
            
            // Afficher la date sélectionnée
            const dateObj = new Date(this.selectedDate);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const formattedDate = dateObj.toLocaleDateString('fr-FR', options);
            $('#ibs-selected-date-display').text(formattedDate);
            
            $.ajax({
                url: ibsFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ibs_get_available_slots',
                    nonce: ibsFrontend.nonce,
                    store_id: this.selectedStore,
                    service_id: this.selectedService,
                    date: this.selectedDate
                },
                success: function(response) {
                    if (response.success) {
                        self.renderSlots(response.data);
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function() {
                    self.showError(ibsFrontend.strings.error);
                }
            });
        },
        
        renderSlots: function(slots) {
            const $container = $('#ibs-slots-list');
            $container.empty();
            
            if (slots.length === 0) {
                $container.html('<p class="ibs-no-slots">' + ibsFrontend.strings.noSlots + '</p>');
                return;
            }
            
            slots.forEach(function(slot) {
                const html = `
                    <button class="ibs-slot-btn" data-time="${slot}">
                        ${slot}
                    </button>
                `;
                $container.append(html);
            });
        },
        
        displayBookingSummary: function() {
            // Récupérer les informations du magasin et du service
            const storeName = $(`.ibs-store-card[data-store-id="${this.selectedStore}"] .ibs-store-name`).text();
            const serviceName = $(`.ibs-service-card[data-service-id="${this.selectedService}"] .ibs-service-name`).text();
            const serviceDuration = $(`.ibs-service-card[data-service-id="${this.selectedService}"] .ibs-service-duration`).text();
            
            const dateObj = new Date(this.selectedDate);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const formattedDate = dateObj.toLocaleDateString('fr-FR', options);
            
            const html = `
                <h3>Récapitulatif de votre réservation</h3>
                <div class="ibs-summary-item">
                    <span class="ibs-summary-label">Magasin :</span>
                    <span class="ibs-summary-value">${storeName}</span>
                </div>
                <div class="ibs-summary-item">
                    <span class="ibs-summary-label">Service :</span>
                    <span class="ibs-summary-value">${serviceName}</span>
                </div>
                <div class="ibs-summary-item">
                    <span class="ibs-summary-label">Durée :</span>
                    <span class="ibs-summary-value">${serviceDuration}</span>
                </div>
                <div class="ibs-summary-item">
                    <span class="ibs-summary-label">Date :</span>
                    <span class="ibs-summary-value">${formattedDate}</span>
                </div>
                <div class="ibs-summary-item">
                    <span class="ibs-summary-label">Heure :</span>
                    <span class="ibs-summary-value">${this.selectedTime}</span>
                </div>
            `;
            
            $('#ibs-booking-summary').html(html);
        },
        
        submitBooking: function() {
            const self = this;
            const $form = $('#ibs-customer-form');
            const $submitBtn = $form.find('.ibs-submit-btn');
            
            // Validation
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }
            
            // Désactiver le bouton
            $submitBtn.prop('disabled', true).text('Envoi en cours...');
            
            const formData = {
                action: 'ibs_create_booking',
                nonce: ibsFrontend.nonce,
                store_id: this.selectedStore,
                service_id: this.selectedService,
                date: this.selectedDate,
                time: this.selectedTime,
                firstname: $('#ibs-firstname').val(),
                lastname: $('#ibs-lastname').val(),
                email: $('#ibs-email').val(),
                phone: $('#ibs-phone').val(),
                message: $('#ibs-message').val()
            };
            
            $.ajax({
                url: ibsFrontend.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $('#ibs-confirmation-text').text(response.data.message);
                        self.goToStep(6);
                    } else {
                        self.showError(response.data.message);
                        $submitBtn.prop('disabled', false).text('Confirmer ma réservation');
                    }
                },
                error: function() {
                    self.showError(ibsFrontend.strings.error);
                    $submitBtn.prop('disabled', false).text('Confirmer ma réservation');
                }
            });
        },
        
        showError: function(message) {
            alert(message);
        }
    };
    
    // Initialiser au chargement de la page
    $(document).ready(function() {
        if ($('#ibs-booking-form').length) {
            IBS.init();
        }
    });
    
})(jQuery);
