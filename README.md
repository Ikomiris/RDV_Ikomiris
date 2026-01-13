# Ikomiris Booking System

Plugin WordPress de système de réservation multi-magasins pour ikomiris.com

## 🎯 Fonctionnalités

### Administration
- ✅ **Gestion multi-magasins** : Créez et gérez plusieurs magasins avec coordonnées et images
- ✅ **Gestion des services** : Définissez vos services avec nom, description, durée, prix et image
- ✅ **Gestion des horaires** : Configurez les horaires d'ouverture par jour et par magasin
- ✅ **Dates exceptionnelles** : Gérez les fermetures et ouvertures exceptionnelles
- ✅ **Vue des réservations** : Consultez toutes les réservations avec filtres avancés
- ✅ **Paramètres personnalisables** : Couleurs, textes, notifications, Google Agenda

### Frontend Client
- ✅ **Interface moderne et épurée** : Design responsive optimisé pour mobile
- ✅ **Parcours de réservation en 5 étapes** :
  1. Sélection du magasin
  2. Choix du service (avec images)
  3. Sélection de la date
  4. Choix du créneau horaire (tous les 10 minutes)
  5. Formulaire client et confirmation
- ✅ **Calcul intelligent des créneaux** : Disponibilité en temps réel selon les réservations existantes
- ✅ **Notifications email** : Confirmation et rappel automatiques
- ✅ **Annulation simple** : Lien d'annulation dans l'email de confirmation

## 📦 Installation

1. Téléchargez le dossier `ikomiris-booking-system`
2. Placez-le dans `/wp-content/plugins/`
3. Activez le plugin dans WordPress
4. Configurez vos magasins et services dans le menu "Réservations"

## 🚀 Utilisation

### Shortcode
Utilisez le shortcode `[ikomiris_booking]` dans n'importe quelle page pour afficher le formulaire de réservation.

Une page "Réservation" est automatiquement créée lors de l'activation du plugin.

### Configuration initiale

1. **Créez vos magasins** : Réservations → Magasins
2. **Ajoutez vos services** : Réservations → Services (avec images et durées)
3. **Définissez les horaires** : Réservations → Horaires
4. **Configurez les paramètres** : Réservations → Paramètres

## 🎨 Personnalisation

### Couleurs
Changez les couleurs dans **Réservations → Paramètres → Apparence**

### Textes
Personnalisez tous les textes dans **Réservations → Paramètres → Textes personnalisés**

### CSS personnalisé
Pour aller plus loin, vous pouvez surcharger le CSS en ajoutant dans votre thème :

```css
:root {
    --ibs-primary: #votre-couleur;
    --ibs-secondary: #votre-couleur-secondaire;
}
```

## 🔧 Configuration technique

### Base de données
Le plugin crée 7 tables :
- `ibs_stores` : Magasins
- `ibs_services` : Services
- `ibs_store_services` : Liaison magasins-services
- `ibs_schedules` : Horaires
- `ibs_exceptions` : Dates exceptionnelles
- `ibs_bookings` : Réservations
- `ibs_settings` : Paramètres

### Hooks disponibles (pour développeurs)

```php
// Après création d'une réservation
do_action('ibs_booking_created', $booking_id);

// Avant affichage du formulaire
do_action('ibs_before_booking_form');

// Après affichage du formulaire
do_action('ibs_after_booking_form');
```

## 🌐 Intégration Google Agenda

1. Créez un projet dans Google Cloud Console
2. Activez l'API Google Calendar
3. Créez des identifiants OAuth 2.0
4. Copiez le Client ID et Client Secret dans **Réservations → Paramètres → Google Agenda**

## 📧 Notifications Email

Le plugin envoie automatiquement :
- **Email de confirmation** au client avec récapitulatif et lien d'annulation
- **Email de rappel** 24h avant le rendez-vous (configurable)
- **Notification admin** à chaque nouvelle réservation

Configuration dans **Réservations → Paramètres → Notifications Email**

## 🔐 Sécurité

- ✅ Protection CSRF avec nonces WordPress
- ✅ Validation et sanitisation de toutes les données
- ✅ Tokens d'annulation sécurisés (64 caractères)
- ✅ Prévention des injections SQL avec $wpdb->prepare()

## 📱 Responsive

Le plugin est entièrement responsive et optimisé pour :
- 📱 Mobile (< 480px)
- 📱 Tablette (< 768px)
- 💻 Desktop (> 768px)

## 🆘 Support

Pour toute question ou problème :
- Email : support@ikomiris.com
- Site : https://ikomiris.com

## 📝 Version

**Version actuelle : 1.0.0**

## 📄 Licence

Ce plugin est propriétaire et développé exclusivement pour ikomiris.com

---

Développé avec ❤️ pour ikomiris.com
