(function($) {
    'use strict';

    const IBS = {
        selectedStore: null,
        selectedService: null,
        selectedDate: null,
        selectedTime: null,
        flatpickrInstance: null,

        init: function() {
            this.loadStores();
            this.bindEvents();
            this.initDatePicker();
        },

        initDatePicker: function() {
            const self = this;
            const dateInput = document.getElementById('ibs-date-picker');
            
            if (!dateInput) {
                console.log('IBS: Date input not found');
                return;
            }

            // Vérifier que Flatpickr est chargé
            if (typeof flatpickr === 'undefined') {
                console.error('IBS: Flatpickr n\'est pas chargé');
                return;
            }

            // Détruire l'instance existante si elle existe
            if (this.flatpickrInstance) {
                this.flatpickrInstance.destroy();
                this.flatpickrInstance = null;
            }

            // Calculer les dates min et max
            const minBookingDelay = ibsFrontend.settings.minBookingDelay || 2;
            const maxBookingDelay = ibsFrontend.settings.maxBookingDelay || 90;
            
            const today = new Date();
            const minDate = new Date(today);
            minDate.setDate(today.getDate() + Math.ceil(minBookingDelay / 24));
            
            const maxDate = new Date(today);
            maxDate.setDate(today.getDate() + maxBookingDelay);

            try {
                // Obtenir le bouton pour le positionnement
                const button = document.getElementById('ibs-date-btn');
                
                // Configuration Flatpickr
                const flatpickrConfig = {
                    locale: 'fr',
                    dateFormat: 'Y-m-d',
                    minDate: minDate,
                    maxDate: maxDate,
                    disableMobile: false,
                    clickOpens: false, // On contrôle l'ouverture manuellement
                    allowInput: false,
                    static: false, // Positionnement dynamique mais on le contrôle manuellement
                    appendTo: document.body, // Attacher au body
                    inline: false, // Pas inline, on veut un popup
                    onChange: function(selectedDates, dateStr, instance) {
                        if (dateStr) {
                            self.selectedDate = dateStr;
                            console.log('IBS: Date sélectionnée:', dateStr);
                            
                            // Marquer que le calendrier doit être fermé
                            instance._shouldBeClosed = true;
                            
                            // Fermer le calendrier immédiatement après la sélection
                            const calendar = instance.calendarContainer;
                            if (calendar) {
                                // Masquer immédiatement le calendrier
                                calendar.style.setProperty('display', 'none', 'important');
                                calendar.style.setProperty('visibility', 'hidden', 'important');
                                calendar.style.setProperty('opacity', '0', 'important');
                                calendar.style.setProperty('pointer-events', 'none', 'important');
                            }
                            
                            // Fermer via l'API Flatpickr
                            try {
                                instance.close();
                            } catch (e) {
                                console.error('IBS: Erreur lors de la fermeture du calendrier', e);
                            }
                            
                            // Appeler handleDateChange pour afficher la date et charger les créneaux
                            self.handleDateChange(dateStr);
                        }
                    },
                    onReady: function(selectedDates, dateStr, instance) {
                        // Remplacer complètement la fonction de positionnement de Flatpickr
                        const calendar = instance.calendarContainer;
                        if (calendar) {
                            calendar.style.position = 'fixed';
                            
                            // Remplacer la fonction de positionnement de Flatpickr
                            if (instance._positionCalendar) {
                                instance._positionCalendar = function() {
                                    // Utiliser notre propre fonction de positionnement au lieu de celle de Flatpickr
                                    self.positionCalendar(instance);
                                };
                            }
                            
                            // Ajouter un listener sur le scroll pour fermer le calendrier s'il est encore ouvert
                            let scrollTimeout;
                            const closeOnScroll = function() {
                                if (instance.isOpen) {
                                    instance.close();
                                    const cal = instance.calendarContainer;
                                    if (cal) {
                                        cal.style.setProperty('display', 'none', 'important');
                                        cal.style.setProperty('visibility', 'hidden', 'important');
                                        cal.style.setProperty('opacity', '0', 'important');
                                    }
                                }
                            };
                            
                            window.addEventListener('scroll', function() {
                                clearTimeout(scrollTimeout);
                                scrollTimeout = setTimeout(closeOnScroll, 50);
                            }, { passive: true });
                            
                            // Observer les changements de style pour corriger immédiatement
                            // Mais seulement si le calendrier est ouvert
                            const observer = new MutationObserver(function(mutations) {
                                // Ne pas repositionner si le calendrier est censé être fermé
                                if (!instance.isOpen || instance._shouldBeClosed) {
                                    return;
                                }
                                
                                mutations.forEach(function(mutation) {
                                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                                        // Vérifier si la position a changé incorrectement
                                        const currentRect = calendar.getBoundingClientRect();
                                        const button = document.getElementById('ibs-date-btn');
                                        if (button && instance.isOpen && !instance._shouldBeClosed) {
                                            const buttonRect = button.getBoundingClientRect();
                                            const expectedTop = buttonRect.bottom + 10;
                                            if (Math.abs(currentRect.top - expectedTop) > 50) {
                                                // Repositionner si nécessaire
                                                self.positionCalendar(instance);
                                            }
                                        }
                                    }
                                });
                            });
                            
                            observer.observe(calendar, {
                                attributes: true,
                                attributeFilter: ['style', 'class']
                            });
                            
                            // Stocker l'observer pour pouvoir le nettoyer plus tard si nécessaire
                            instance._positionObserver = observer;
                        }
                    },
                    onOpen: function(selectedDates, dateStr, instance) {
                        console.log('IBS: Date picker ouvert - onOpen callback');
                        // Ne pas positionner si le calendrier doit être fermé
                        if (instance._shouldBeClosed) {
                            const calendar = instance.calendarContainer;
                            if (calendar) {
                                calendar.style.setProperty('display', 'none', 'important');
                                calendar.style.setProperty('visibility', 'hidden', 'important');
                                calendar.style.setProperty('opacity', '0', 'important');
                                calendar.style.setProperty('pointer-events', 'none', 'important');
                            }
                            instance.close();
                            return;
                        }
                        
                        // Attendre que le calendrier soit complètement rendu
                        requestAnimationFrame(function() {
                            if (!instance._shouldBeClosed) {
                                self.positionCalendar(instance);
                                requestAnimationFrame(function() {
                                    if (!instance._shouldBeClosed) {
                                        self.positionCalendar(instance);
                                        setTimeout(function() {
                                            if (!instance._shouldBeClosed) {
                                                self.positionCalendar(instance);
                                            }
                                        }, 10);
                                        setTimeout(function() {
                                            if (!instance._shouldBeClosed) {
                                                self.positionCalendar(instance);
                                            }
                                        }, 50);
                                        setTimeout(function() {
                                            if (!instance._shouldBeClosed) {
                                                self.positionCalendar(instance);
                                            }
                                        }, 100);
                                    }
                                });
                            }
                        });
                    }
                };
                
                // Ajouter positionElement si le bouton existe
                if (button) {
                    flatpickrConfig.positionElement = button;
                }
                
                // Initialiser Flatpickr
                this.flatpickrInstance = flatpickr(dateInput, flatpickrConfig);
                console.log('IBS: Flatpickr initialisé avec succès');
            } catch (error) {
                console.error('IBS: Erreur lors de l\'initialisation de Flatpickr', error);
            }
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
                console.log('IBS: Bouton date cliqué');
                console.log('IBS: Bouton disabled?', $(this).hasClass('ibs-date-btn-disabled'));
                console.log('IBS: Flatpickr disponible?', typeof flatpickr !== 'undefined');
                console.log('IBS: Flatpickr instance?', self.flatpickrInstance);
                
                if ($(this).hasClass('ibs-date-btn-disabled')) {
                    console.log('IBS: Bouton désactivé, action ignorée');
                    return false;
                }
                
                // Vérifier que Flatpickr est chargé
                if (typeof flatpickr === 'undefined') {
                    console.error('IBS: Flatpickr n\'est pas chargé. Vérifiez que les scripts sont bien enregistrés.');
                    alert('Le sélecteur de date n\'est pas disponible. Veuillez recharger la page.');
                    return false;
                }
                
                // Si pas d'instance, en créer une
                if (!self.flatpickrInstance) {
                    console.log('IBS: Création d\'une nouvelle instance Flatpickr...');
                    self.initDatePicker();
                }
                
                // Ouvrir le date picker
                if (self.flatpickrInstance) {
                    console.log('IBS: Ouverture du date picker');
                    try {
                        // Obtenir la position du bouton pour positionner le calendrier
                        const button = document.getElementById('ibs-date-btn');
                        if (button) {
                            const rect = button.getBoundingClientRect();
                            console.log('IBS: Position du bouton', rect);
                        }
                        
                        // Réinitialiser le flag de fermeture avant d'ouvrir
                        self.flatpickrInstance._shouldBeClosed = false;
                        
                        // Ouvrir le date picker
                        self.flatpickrInstance.open();
                        
                        // Le positionnement sera géré par le callback onOpen et le MutationObserver
                        
                        // Vérifier que le calendrier est bien affiché après ouverture
                        setTimeout(function() {
                            const calendar = self.flatpickrInstance.calendarContainer;
                            if (calendar) {
                                console.log('IBS: Calendrier trouvé dans le DOM');
                                
                                // Obtenir la position réelle du calendrier
                                const calendarRect = calendar.getBoundingClientRect();
                                console.log('IBS: Position réelle du calendrier', calendarRect);
                                
                                // Obtenir la position du bouton
                                const button = document.getElementById('ibs-date-btn');
                                if (button) {
                                    const buttonRect = button.getBoundingClientRect();
                                    
                                // Si le calendrier est hors écran ou mal positionné, le repositionner
                                if (calendarRect.top < 0 || calendarRect.left < 0 || 
                                    calendarRect.top > window.innerHeight || 
                                    calendarRect.left > window.innerWidth ||
                                    calendarRect.top > buttonRect.bottom + 100) { // Si trop bas
                                    console.log('IBS: Calendrier hors écran, repositionnement...');
                                    self.positionCalendar(self.flatpickrInstance);
                                }
                                }
                                
                                console.log('IBS: Style final du calendrier', {
                                    display: window.getComputedStyle(calendar).display,
                                    visibility: window.getComputedStyle(calendar).visibility,
                                    opacity: window.getComputedStyle(calendar).opacity,
                                    zIndex: window.getComputedStyle(calendar).zIndex,
                                    position: window.getComputedStyle(calendar).position,
                                    top: window.getComputedStyle(calendar).top,
                                    left: window.getComputedStyle(calendar).left,
                                    width: window.getComputedStyle(calendar).width,
                                    height: window.getComputedStyle(calendar).height
                                });
                                
                                // Forcer l'affichage si nécessaire
                                calendar.style.zIndex = '99999';
                                calendar.style.display = 'block';
                                calendar.style.visibility = 'visible';
                                calendar.style.opacity = '1';
                            } else {
                                console.error('IBS: Calendrier non trouvé dans le DOM');
                            }
                        }, 100);
                    } catch (error) {
                        console.error('IBS: Erreur lors de l\'ouverture du date picker', error);
                        alert('Erreur lors de l\'ouverture du sélecteur de date.');
                    }
                } else {
                    console.error('IBS: Impossible de créer l\'instance Flatpickr');
                    alert('Impossible d\'initialiser le sélecteur de date.');
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
            // Ne pas repositionner si le calendrier est censé être fermé
            if (!instance.isOpen || instance._shouldBeClosed) {
                const calendar = instance.calendarContainer;
                if (calendar) {
                    calendar.style.setProperty('display', 'none', 'important');
                    calendar.style.setProperty('visibility', 'hidden', 'important');
                    calendar.style.setProperty('opacity', '0', 'important');
                    calendar.style.setProperty('pointer-events', 'none', 'important');
                }
                return;
            }
            
            console.log('IBS: positionCalendar appelée');
            const calendar = instance.calendarContainer;
            if (!calendar) {
                console.warn('IBS: Calendrier non trouvé');
                return;
            }
            
            const button = document.getElementById('ibs-date-btn');
            if (!button) {
                console.error('IBS: Bouton non trouvé pour le positionnement');
                return;
            }
            
            console.log('IBS: positionCalendar - calendrier et bouton trouvés');
            
            // Obtenir les coordonnées du bouton relatives à la fenêtre visible
            const rect = button.getBoundingClientRect();
            const calendarWidth = 307; // Largeur standard de Flatpickr
            const leftPosition = rect.left + (rect.width / 2) - (calendarWidth / 2);
            // Utiliser directement rect.bottom qui est déjà relatif à la fenêtre visible
            const topPosition = rect.bottom + 10;
            
            console.log('IBS: Calcul position calendrier', {
                buttonTop: rect.top,
                buttonBottom: rect.bottom,
                topPosition: topPosition,
                windowHeight: window.innerHeight,
                scrollY: window.scrollY,
                pageYOffset: window.pageYOffset,
                documentScrollTop: document.documentElement.scrollTop
            });
            
            // Vérifier la position actuelle du calendrier avant repositionnement
            const beforeRect = calendar.getBoundingClientRect();
            const beforeStyle = window.getComputedStyle(calendar);
            console.log('IBS: Position avant repositionnement', {
                top: beforeRect.top,
                left: beforeRect.left,
                computedTop: beforeStyle.top,
                computedLeft: beforeStyle.left,
                computedPosition: beforeStyle.position,
                computedTransform: beforeStyle.transform
            });
            
            // Supprimer toutes les transformations et classes de positionnement de Flatpickr
            calendar.classList.remove('arrowTop', 'arrowBottom', 'arrowLeft', 'arrowRight');
            
            // Utiliser cssText pour forcer tous les styles d'un coup et éviter les conflits
            const stylesToApply = `
                position: fixed !important;
                top: ${topPosition}px !important;
                left: ${Math.max(10, leftPosition)}px !important;
                z-index: 99999 !important;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                pointer-events: auto !important;
                transform: none !important;
                margin: 0 !important;
                width: auto !important;
                height: auto !important;
            `;
            
            // Supprimer d'abord tous les styles existants
            calendar.removeAttribute('style');
            // Appliquer nos styles
            calendar.style.cssText = stylesToApply.trim();
            
            // Forcer un reflow pour s'assurer que les styles sont appliqués
            void calendar.offsetHeight;
            
            // Vérifier les styles appliqués
            const afterStyle = window.getComputedStyle(calendar);
            console.log('IBS: Styles appliqués', {
                computedTop: afterStyle.top,
                computedLeft: afterStyle.left,
                computedPosition: afterStyle.position
            });
            
            // Vérifier que le positionnement a bien été appliqué
            const actualRect = calendar.getBoundingClientRect();
            const expectedTop = rect.bottom + 10;
            const actualTop = actualRect.top;
            
            console.log('IBS: Calendrier repositionné', {
                topSet: topPosition + 'px',
                topExpected: expectedTop,
                topActual: actualTop,
                difference: Math.abs(actualTop - expectedTop),
                buttonTop: rect.top,
                buttonBottom: rect.bottom,
                buttonRect: rect,
                calendarRect: actualRect
            });
            
            // Si le positionnement n'a pas fonctionné, essayer une approche plus agressive
            if (Math.abs(actualTop - expectedTop) > 50) {
                console.warn('IBS: Positionnement échoué, tentative alternative agressive...');
                // Supprimer tous les styles inline qui pourraient interférer
                const allStyles = calendar.getAttribute('style');
                calendar.removeAttribute('style');
                
                // Réappliquer uniquement nos styles
                calendar.style.cssText = `
                    position: fixed !important;
                    top: ${topPosition}px !important;
                    left: ${Math.max(10, leftPosition)}px !important;
                    z-index: 99999 !important;
                    display: block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                    pointer-events: auto !important;
                    transform: none !important;
                    margin: 0 !important;
                `;
            }
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
                } catch (e) {
                    console.error('IBS: Erreur lors de la fermeture du calendrier dans handleDateChange', e);
                }
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

            // Activer les éléments interactifs selon la section
            if (section === 'date') {
                $('#ibs-date-picker').prop('disabled', false);
                $('#ibs-date-btn').removeClass('ibs-date-btn-disabled');
                // Réinitialiser Flatpickr quand la section est activée
                console.log('IBS: Section date activée, réinitialisation de Flatpickr');
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

    // Initialiser au chargement de la page
    $(document).ready(function() {
        if ($('#ibs-booking-form').length) {
            console.log('IBS: Formulaire de réservation détecté');
            
            // Fonction pour initialiser avec vérification de Flatpickr
            function initIBS() {
                if (typeof flatpickr !== 'undefined') {
                    console.log('IBS: Flatpickr disponible, initialisation...');
                    IBS.init();
                } else {
                    console.warn('IBS: Flatpickr non disponible, nouvelle tentative dans 200ms...');
                    // Si Flatpickr n'est pas encore chargé, attendre un peu
                    setTimeout(function() {
                        if (typeof flatpickr !== 'undefined') {
                            console.log('IBS: Flatpickr maintenant disponible, initialisation...');
                            IBS.init();
                        } else {
                            console.error('IBS: Flatpickr n\'est toujours pas chargé après attente');
                            // Initialiser quand même sans le date picker
                            IBS.loadStores();
                            IBS.bindEvents();
                        }
                    }, 200);
                }
            }
            
            initIBS();
        }
    });

})(jQuery);
