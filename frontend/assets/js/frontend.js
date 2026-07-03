(function($) {
    'use strict';

    const IBS = {
        selectedStore: null,
        selectedService: null,
        selectedDate: null,
        selectedTime: null,
        flatpickrInstance: null,
        availabilityMap: {},
        loadedAvailabilityMonths: {},

        init: function() {
            const self = this;
            this.bindEvents();
            this.initDatePicker();
            // Refresh nonce first (may be stale due to page caching), then load stores
            this.refreshNonce(function() {
                self.loadStores();
            });
        },

        refreshNonce: function(callback) {
            $.ajax({
                url: ibsFrontend.ajaxUrl,
                type: 'POST',
                data: { action: 'ibs_refresh_nonce' },
                success: function(response) {
                    if (response.success && response.data && response.data.nonce) {
                        ibsFrontend.nonce = response.data.nonce;
                    }
                    if (callback) callback();
                },
                error: function() {
                    if (callback) callback();
                }
            });
        },

        initDatePicker: function() {
            const self = this;
            const dateInput = document.getElementById('ibs-date-picker');

            if (!dateInput || typeof flatpickr === 'undefined') return;

            if (this.flatpickrInstance) {
                this.flatpickrInstance.destroy();
                this.flatpickrInstance = null;
            }

            // Nouveau contexte magasin/service : la disponibilité doit être rechargée
            this.availabilityMap = {};
            this.loadedAvailabilityMonths = {};

            const today = new Date();
            const maxDate = new Date(today);
            maxDate.setDate(today.getDate() + (ibsFrontend.settings.maxBookingDelay || 90));

            const button = document.getElementById('ibs-date-btn');

            this.flatpickrInstance = flatpickr(dateInput, {
                locale: 'fr',
                dateFormat: 'Y-m-d',
                minDate: today,
                maxDate: maxDate,
                disableMobile: false,
                clickOpens: false,
                allowInput: false,
                static: false,
                appendTo: document.body,
                inline: false,
                positionElement: button || undefined,

                disable: [
                    function(date) {
                        // Ne bloque que les jours dont on sait déjà qu'ils sont indisponibles ;
                        // les jours pas encore chargés restent cliquables (chargement asynchrone)
                        return self.availabilityMap[self.formatDateYMD(date)] === false;
                    }
                ],

                onDayCreate: function(dObj, dStr, instance, dayElem) {
                    const dateStr = self.formatDateYMD(dayElem.dateObj);
                    const status = self.availabilityMap[dateStr];
                    dayElem.classList.remove('ibs-day-available', 'ibs-day-unavailable');
                    if (status === true) {
                        dayElem.classList.add('ibs-day-available');
                    } else if (status === false) {
                        dayElem.classList.add('ibs-day-unavailable');
                    }
                },

                onMonthChange: function(selectedDates, dateStr, instance) {
                    const monthStr = instance.currentYear + '-' + String(instance.currentMonth + 1).padStart(2, '0');
                    self.loadMonthlyAvailability(monthStr);
                },

                onChange: function(selectedDates, dateStr, instance) {
                    if (!dateStr) return;
                    self.selectedDate = dateStr;
                    instance._shouldBeClosed = true;
                    const cal = instance.calendarContainer;
                    if (cal) {
                        cal.style.setProperty('display', 'none', 'important');
                        cal.style.setProperty('visibility', 'hidden', 'important');
                        cal.style.setProperty('opacity', '0', 'important');
                    }
                    try { instance.close(); } catch (e) {}
                    self.handleDateChange(dateStr);
                },

                onReady: function(selectedDates, dateStr, instance) {
                    // Surcharger le positionnement interne de Flatpickr
                    if (instance._positionCalendar) {
                        instance._positionCalendar = function() {
                            self.positionCalendar(instance);
                        };
                    }
                    // Fermer le calendrier au scroll
                    let scrollTimeout;
                    window.addEventListener('scroll', function() {
                        clearTimeout(scrollTimeout);
                        scrollTimeout = setTimeout(function() {
                            if (instance.isOpen) instance.close();
                        }, 50);
                    }, { passive: true });
                },

                onOpen: function(selectedDates, dateStr, instance) {
                    if (instance._shouldBeClosed) {
                        instance.close();
                        return;
                    }
                    // Un seul rAF suffit — le DOM est prêt au prochain frame
                    requestAnimationFrame(function() {
                        if (!instance._shouldBeClosed) {
                            self.positionCalendar(instance);
                        }
                    });
                },
            });

            const currentMonth = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');
            this.loadMonthlyAvailability(currentMonth);
        },

        formatDateYMD: function(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        },

        loadMonthlyAvailability: function(month) {
            if (!this.selectedStore || !this.selectedService) return;

            const key = this.selectedStore + '_' + this.selectedService + '_' + month;
            if (this.loadedAvailabilityMonths[key]) return;
            this.loadedAvailabilityMonths[key] = true;

            const self = this;
            $.ajax({
                url: ibsFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ibs_get_monthly_availability',
                    nonce: ibsFrontend.nonce,
                    store_id: this.selectedStore,
                    service_id: this.selectedService,
                    month: month,
                },
                success: function(response) {
                    if (response.success) {
                        Object.assign(self.availabilityMap, response.data);
                        if (self.flatpickrInstance) {
                            self.flatpickrInstance.redraw();
                        }
                    }
                },
                error: function() {
                    // Permet un nouvel essai si la requête a échoué
                    delete self.loadedAvailabilityMonths[key];
                },
            });
        },

        bindEvents: function() {
            const self = this;

            // Sélection du magasin
            $(document).on('click', '.ibs-store-card', function() {
                // Désélectionner tous les magasins
                $('.ibs-store-card').removeClass('ibs-card-selected');
                $(this).addClass('ibs-card-selected');

                self.selectedStore = $(this).data('store-id');
                self.loadServices(self.selectedStore);
                self.enableSection('service');
            });

            // Sélection du service
            $(document).on('click', '.ibs-service-card', function() {
                // Désélectionner tous les services
                $('.ibs-service-card').removeClass('ibs-card-selected');
                $(this).addClass('ibs-card-selected');

                self.selectedService = $(this).data('service-id');
                self.enableSection('date');

                // Scroll vers la section date
                self.scrollToSection('date');
            });

            // Ouverture du date picker via le bouton
            $(document).on('click', '#ibs-date-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if ($(this).hasClass('ibs-date-btn-disabled')) return false;
                if (typeof flatpickr === 'undefined') return false;

                if (!self.flatpickrInstance) {
                    self.initDatePicker();
                }

                if (self.flatpickrInstance) {
                    self.flatpickrInstance._shouldBeClosed = false;
                    self.flatpickrInstance.open();
                }

                return false;
            });

            // Changer de date
            $(document).on('click', '#ibs-change-date', function(e) {
                e.preventDefault();
                if (self.flatpickrInstance) {
                    // Réinitialiser le flag de fermeture avant d'ouvrir
                    self.flatpickrInstance._shouldBeClosed = false;
                    self.flatpickrInstance.open();
                }
            });

            // Sélection du créneau
            $(document).on('click', '.ibs-slot-btn', function() {
                // Désélectionner tous les créneaux
                $('.ibs-slot-btn').removeClass('ibs-slot-selected');
                $(this).addClass('ibs-slot-selected');

                self.selectedTime = $(this).data('time');
                self.displayBookingSummary();
                self.enableSection('form');

                // Scroll vers le formulaire
                self.scrollToSection('form');
            });

            // Soumission du formulaire
            $('#ibs-customer-form').on('submit', function(e) {
                e.preventDefault();
                self.submitBooking();
            });

            // Carte cadeau: afficher/masquer le code
            $(document).on('change', '#ibs-has-gift-card', function() {
                self.updateGiftCardState();
            });
        },

        positionCalendar: function(instance) {
            if (!instance.isOpen || instance._shouldBeClosed) return;

            const calendar = instance.calendarContainer;
            const button = document.getElementById('ibs-date-btn');
            if (!calendar || !button) return;

            const rect = button.getBoundingClientRect();
            const calWidth = 307;
            const left = Math.max(10, rect.left + rect.width / 2 - calWidth / 2);

            calendar.classList.remove('arrowTop', 'arrowBottom', 'arrowLeft', 'arrowRight');
            calendar.style.cssText = [
                'position:fixed!important',
                'top:' + (rect.bottom + 10) + 'px!important',
                'left:' + left + 'px!important',
                'z-index:99999!important',
                'display:block!important',
                'visibility:visible!important',
                'opacity:1!important',
                'pointer-events:auto!important',
                'transform:none!important',
                'margin:0!important',
            ].join(';');
        },

        handleDateChange: function(dateStr) {
            // Fermer le calendrier si il est ouvert
            if (this.flatpickrInstance) {
                // Marquer le calendrier comme devant être fermé
                this.flatpickrInstance._shouldBeClosed = true;
                
                const calendar = this.flatpickrInstance.calendarContainer;
                if (calendar) {
                    // Masquer immédiatement le calendrier avec !important
                    calendar.style.setProperty('display', 'none', 'important');
                    calendar.style.setProperty('visibility', 'hidden', 'important');
                    calendar.style.setProperty('opacity', '0', 'important');
                    calendar.style.setProperty('pointer-events', 'none', 'important');
                }
                try {
                    if (this.flatpickrInstance.isOpen) {
                        this.flatpickrInstance.close();
                    }
                } catch (e) {}
            }
            
            // Afficher la date sélectionnée
            const dateObj = new Date(dateStr + 'T00:00:00');
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const formattedDate = dateObj.toLocaleDateString('fr-FR', options);

            $('#ibs-selected-date-info').html(`
                <div class="ibs-selected-date-display">
                    📅 ${formattedDate}
                    <button type="button" class="ibs-change-date-btn" id="ibs-change-date">Modifier</button>
                </div>
            `).show();
            
            this.loadAvailableSlots();
            this.enableSection('time');

            // Scroll vers la section horaires
            const self = this;
            setTimeout(function() {
                self.scrollToSection('time');
            }, 100);
        },

        enableSection: function(section) {
            const $section = $('#ibs-section-' + section);
            $section.removeClass('ibs-section-disabled');

            if (section === 'date') {
                $('#ibs-date-picker').prop('disabled', false);
                $('#ibs-date-btn').removeClass('ibs-date-btn-disabled');
                this.initDatePicker();
            } else if (section === 'form') {
                $('#ibs-customer-form input, #ibs-customer-form textarea, #ibs-customer-form button').prop('disabled', false);
                this.updateGiftCardState();
            }
        },

        updateGiftCardState: function() {
            const $checkbox = $('#ibs-has-gift-card');
            const $codeWrapper = $('#ibs-gift-card-code-wrapper');
            const $codeInput = $('#ibs-gift-card-code');

            if (!$checkbox.length || !$codeWrapper.length || !$codeInput.length) {
                return;
            }

            if ($checkbox.is(':checked')) {
                $codeWrapper.show();
                $codeInput.prop('required', true).prop('disabled', false);
            } else {
                $codeWrapper.hide();
                $codeInput.prop('required', false).val('').prop('disabled', true);
            }
        },

        scrollToSection: function(section) {
            const $section = $('#ibs-section-' + section);
            if ($section.length) {
                $('html, body').animate({
                    scrollTop: $section.offset().top - 20
                }, 500);
            }
        },

        loadStores: function() {
            const self = this;
            $.ajax({
                url: ibsFrontend.ajaxUrl,
                type: 'POST',
                data: { action: 'ibs_get_stores', nonce: ibsFrontend.nonce },
                success: function(response) {
                    if (response.success) {
                        self.renderStores(response.data);
                    } else {
                        self.showError(response.data && response.data.message ? response.data.message : ibsFrontend.strings.error);
                    }
                },
                error: function() { self.showError(ibsFrontend.strings.error); }
            });
        },

        renderStores: function(stores) {
            const $container = $('#ibs-stores-list');
            $container.empty();

            if (stores.length === 0) {
                $container.html('<p class="ibs-no-slots">Aucun magasin disponible</p>');
                $('#ibs-section-store').show();
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

            if (stores.length === 1) {
                const onlyStore = stores[0];
                this.selectedStore = onlyStore.id;
                $container.find('.ibs-store-card').addClass('ibs-card-selected');
                $('#ibs-section-store').hide();
                this.loadServices(this.selectedStore);
                this.enableSection('service');
            } else {
                $('#ibs-section-store').show();
            }
        },

        loadServices: function(storeId) {
            const self = this;
            $('#ibs-services-list').html('<div class="ibs-loading">' + ibsFrontend.strings.loading + '</div>');
            $.ajax({
                url: ibsFrontend.ajaxUrl,
                type: 'POST',
                data: { action: 'ibs_get_services', nonce: ibsFrontend.nonce, store_id: storeId },
                success: function(response) {
                    if (response.success) {
                        self.renderServices(response.data);
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function() { self.showError(ibsFrontend.strings.error); }
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
            if (this._loadingSlotsAjax) {
                this._loadingSlotsAjax.abort();
            }
            const self = this;
            $('#ibs-slots-list').html('<div class="ibs-loading">' + ibsFrontend.strings.loading + '</div>');
            this._loadingSlotsAjax = $.ajax({
                url: ibsFrontend.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ibs_get_available_slots',
                    nonce: ibsFrontend.nonce,
                    store_id: this.selectedStore,
                    service_id: this.selectedService,
                    date: this.selectedDate,
                },
                success: function(response) {
                    self._loadingSlotsAjax = null;
                    if (response.success) {
                        self.renderSlots(response.data);
                    } else {
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr) {
                    if (xhr.statusText !== 'abort') self.showError(ibsFrontend.strings.error);
                },
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
                    <button type="button" class="ibs-slot-btn" data-time="${slot}">
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

            const dateObj = new Date(this.selectedDate + 'T00:00:00');
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

            $('#ibs-booking-summary').html(html).show();
        },

        submitBooking: function() {
            const self = this;
            const $form = $('#ibs-customer-form');
            const $submitBtn = $form.find('.ibs-submit-btn');

            // Validation
            if (!$('#ibs-age-confirm').is(':checked')) {
                alert('Veuillez confirmer que toutes les personnes photographiées ont au moins 6 ans.');
                return;
            }

            if (!$('#ibs-terms').is(':checked')) {
                alert('Veuillez accepter les conditions générales d\'utilisation.');
                return;
            }

            if ($('#ibs-has-gift-card').is(':checked') && !$('#ibs-gift-card-code').val().trim()) {
                alert('Veuillez saisir le code de votre carte cadeau.');
                return;
            }

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
                message: $('#ibs-message').val(),
                gift_card_code: $('#ibs-has-gift-card').is(':checked') ? $('#ibs-gift-card-code').val().trim() : '',
                has_gift_card: $('#ibs-has-gift-card').is(':checked') ? '1' : '0',
                age_confirm: $('#ibs-age-confirm').is(':checked') ? '1' : '0',
                terms: $('#ibs-terms').is(':checked') ? '1' : '0'
            };

            $.ajax({
                url: ibsFrontend.ajaxUrl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Masquer toutes les sections sauf la confirmation
                        $('.ibs-section').not('#ibs-section-confirmation').hide();
                        $('#ibs-section-confirmation').show();
                        $('#ibs-confirmation-text').text(response.data.message);

                        // Scroll vers le haut
                        $('html, body').animate({ scrollTop: $('#ibs-booking-form').offset().top - 20 }, 500);
                    } else {
                        self.showError(response.data.message);
                        $submitBtn.prop('disabled', false).text('Confirmer Ma Réservation');
                    }
                },
                error: function() {
                    self.showError(ibsFrontend.strings.error);
                    $submitBtn.prop('disabled', false).text('Confirmer Ma Réservation');
                }
            });
        },

        showError: function(message) {
            alert(message);
        }
    };

    $(document).ready(function() {
        if (!$('#ibs-booking-form').length) return;

        if (typeof flatpickr !== 'undefined') {
            IBS.init();
        } else {
            // Flatpickr chargé en defer — réessayer après le premier rendu
            setTimeout(function() {
                IBS.init();
            }, 200);
        }
    });

})(jQuery);
