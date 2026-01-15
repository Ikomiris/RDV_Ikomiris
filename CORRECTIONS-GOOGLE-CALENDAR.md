# 🔧 Corrections Apportées - Google Calendar

## 📋 Résumé des Problèmes Identifiés

### Problème 1 : Les événements Google ne sont pas pris en compte
**Cause** : Problème de gestion du timezone lors de la récupération des événements

### Problème 2 : Les RDV WordPress n'apparaissent pas dans Google Calendar
**Cause** : La synchronisation bidirectionnelle n'était pas implémentée (était documentée comme "bonus" mais pas codée)

---

## ✅ Corrections Apportées

### 1. Correction du Timezone dans la Récupération d'Événements

**Fichier** : `includes/Integrations/GoogleCalendar.php`

**Avant** :
```php
$time_min = $date . 'T00:00:00Z';  // Z = UTC, pas de conversion du timezone local
$time_max = $date . 'T23:59:59Z';
```

**Après** :
```php
// Récupération du timezone WordPress
$timezone = wp_timezone_string();
$time_min = $date . 'T00:00:00';
$time_max = $date . 'T23:59:59';

// Conversion en objets DateTime avec timezone
$dt_min = new \DateTime($time_min, new \DateTimeZone($timezone));
$dt_max = new \DateTime($time_max, new \DateTimeZone($timezone));

// Format ISO 8601 avec timezone (ex: 2025-01-15T00:00:00+01:00)
$time_min_formatted = $dt_min->format('c');
$time_max_formatted = $dt_max->format('c');
```

**Impact** : Les événements Google sont maintenant récupérés dans le bon timezone (celui de WordPress)

---

### 2. Amélioration de la Conversion des Événements

**Fichier** : `includes/Integrations/GoogleCalendar.php`

**Avant** :
```php
$start_timestamp = strtotime($start);  // Peut mal gérer le timezone
$end_timestamp = strtotime($end);
```

**Après** :
```php
// Utilisation de DateTime pour une meilleure gestion
$dt_start = new \DateTime($start);
$dt_end = new \DateTime($end);

// Conversion au timezone WordPress
$dt_start->setTimezone(new \DateTimeZone($timezone));
$dt_end->setTimezone(new \DateTimeZone($timezone));

// Format H:i:s pour compatibilité avec BookingAPI
$booking_time = $dt_start->format('H:i:s');
```

**Impact** : Les heures des événements Google sont correctement converties au timezone local

---

### 3. Ajout de Logs Détaillés

**Fichier** : `includes/Integrations/GoogleCalendar.php`

**Ajouts** :
- Log du calendar_id et de la date demandée
- Log de chaque événement récupéré avec son heure et durée
- Log des événements ignorés (toute la journée, dates différentes)
- Log du nombre d'événements convertis avec succès

**Exemple de logs** :
```
IBS Google Calendar: Récupération événements - Calendar: exemple@gmail.com, Date: 2026-01-15 (Europe/Paris)
IBS Google Calendar: Conversion de 3 événement(s) pour la date 2026-01-15
IBS Google Calendar: Événement ajouté - Rendez-vous client à 10:00:00 (60 min)
IBS Google Calendar: Événement ajouté - Pause déjeuner à 12:00:00 (60 min)
IBS Google Calendar: Événement ignoré (toute la journée) - Férié
IBS Google Calendar: 2 événement(s) converti(s) avec succès
```

**Impact** : Facilite grandement le débogage et l'identification des problèmes

---

### 4. Implémentation de la Synchronisation Bidirectionnelle

**Fichier** : `includes/API/BookingAPI.php`

**Nouvelle méthode** : `sync_to_google_calendar()`

**Fonctionnalités** :
- Récupération automatique du `google_calendar_id` du magasin
- Récupération du nom du service pour le titre de l'événement
- Construction d'un événement Google avec toutes les infos client
- Création de l'événement dans Google Calendar
- Sauvegarde du `google_event_id` dans la base de données WordPress

**Code ajouté dans `create_booking()`** :
```php
$booking_id = $wpdb->insert_id;

// NOUVEAU : Synchroniser avec Google Calendar
$this->sync_to_google_calendar(
    $booking_id, $store_id, $service_id, 
    $date, $time, $firstname, $lastname, 
    $email, $phone, $message, $service->duration
);

// Envoyer les emails de confirmation
$this->send_confirmation_emails($booking_id);
```

**Format de l'événement créé** :
- **Titre** : `Nom du Service - Prénom Nom`
- **Description** : 
  ```
  Réservation Ikomiris Booking System
  
  Client : Prénom Nom
  Email : email@exemple.com
  Téléphone : 0123456789
  Magasin : Nom du Magasin
  
  Message :
  Message du client
  ```
- **Dates** : Converties au timezone WordPress

**Impact** : Les réservations WordPress apparaissent maintenant automatiquement dans Google Calendar

---

### 5. Outil de Diagnostic Complet

**Fichier créé** : `diagnostic-google-calendar.php`

**Fonctionnalités** :
- ✅ Vérification de la configuration (settings)
- ✅ Liste des magasins avec leur Calendar ID
- ✅ Test de connexion Google (obtention d'access token)
- ✅ Test de récupération d'événements par magasin et date
- ✅ Affichage des logs récents
- ✅ Recommandations personnalisées

**Accès** :
```
https://votre-site.com/wp-content/plugins/ikomiris-booking-system/diagnostic-google-calendar.php
```

**Impact** : Permet de diagnostiquer rapidement tous les problèmes de configuration

---

### 6. Guide de Dépannage Détaillé

**Fichier créé** : `GUIDE-DEPANNAGE-GOOGLE-CALENDAR.md`

**Contenu** :
- Étapes de vérification systématiques
- Causes fréquentes des problèmes
- Solutions détaillées pour chaque cas
- Tests rapides PHP à copier-coller
- Checklist complète de configuration

---

## 🎯 Résultats Attendus

Après ces corrections :

### ✅ Récupération des Événements Google
1. Les événements Google Calendar sont récupérés dans le bon timezone
2. Les créneaux occupés par des événements Google sont exclus de la disponibilité
3. Les logs détaillent chaque étape pour faciliter le débogage

### ✅ Création d'Événements dans Google Calendar
1. Chaque réservation WordPress crée automatiquement un événement Google
2. L'événement contient toutes les informations client
3. Le `google_event_id` est sauvegardé dans la base de données
4. Les logs confirment la synchronisation

---

## 🔍 Diagnostic Rapide

### Si les événements Google ne sont toujours pas pris en compte :

1. **Accédez à l'outil de diagnostic** :
   ```
   https://votre-site.com/wp-content/plugins/ikomiris-booking-system/diagnostic-google-calendar.php
   ```

2. **Vérifiez la configuration** :
   - Google Calendar activé : ✅
   - Client ID : ✅
   - Client Secret : ✅
   - Refresh Token : ✅
   - Calendar ID du magasin : ✅

3. **Testez la récupération** :
   - Sélectionnez un magasin
   - Choisissez une date avec des événements
   - Cliquez sur "Tester la Récupération"
   - Vérifiez que les événements s'affichent

4. **Consultez les logs** :
   ```
   wp-content/debug.log
   ```
   Recherchez : `IBS Google Calendar:`

### Si les RDV WordPress n'apparaissent pas dans Google :

1. **Vérifiez le scope OAuth** :
   - Doit être : `https://www.googleapis.com/auth/calendar.events`
   - **PAS** `.readonly` mais bien `.events`

2. **Régénérez le Refresh Token** si nécessaire :
   - Allez sur https://developers.google.com/oauthplayground/
   - Utilisez le scope `.events`
   - Copiez le nouveau Refresh Token dans les paramètres

3. **Vérifiez le Calendar ID du magasin** :
   - Allez dans `Magasins > Modifier`
   - Le champ "ID Google Calendar" doit être rempli
   - Format : `exemple@group.calendar.google.com`

4. **Créez une réservation test** :
   - Consultez `wp-content/debug.log`
   - Recherchez : `IBS: Réservation #XX synchronisée`

---

## 📦 Fichiers Modifiés/Créés

### Modifiés (3)
- `includes/Integrations/GoogleCalendar.php` - Corrections timezone + logs
- `includes/API/BookingAPI.php` - Ajout synchronisation bidirectionnelle

### Créés (3)
- `diagnostic-google-calendar.php` - Outil de diagnostic complet
- `GUIDE-DEPANNAGE-GOOGLE-CALENDAR.md` - Guide de dépannage
- `CORRECTIONS-GOOGLE-CALENDAR.md` - Ce fichier

---

## 🚀 Prochaines Actions

1. **Testez avec l'outil de diagnostic** :
   ```
   https://votre-site.com/wp-content/plugins/ikomiris-booking-system/diagnostic-google-calendar.php
   ```

2. **Activez les logs WordPress** dans `wp-config.php` :
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

3. **Vérifiez votre configuration** :
   - Paramètres > Google Agenda : Tous les champs remplis
   - Magasins : Calendar ID renseigné pour chaque magasin

4. **Testez la récupération** :
   - Créez un événement dans Google Calendar
   - Affichez le formulaire de réservation
   - Vérifiez que le créneau est bien exclu

5. **Testez la synchronisation** :
   - Créez une réservation WordPress
   - Vérifiez qu'elle apparaît dans Google Calendar
   - Consultez les logs pour confirmation

---

## 📞 Support

Si les problèmes persistent :
- Consultez `GUIDE-DEPANNAGE-GOOGLE-CALENDAR.md`
- Utilisez l'outil de diagnostic
- Vérifiez les logs détaillés dans `wp-content/debug.log`
- Vérifiez que le scope OAuth est `.events` (pas `.readonly`)

---

**Version** : 1.0.1  
**Date** : Janvier 2026  
**Priorité** : 🔴 CRITIQUE - Corrige des bugs majeurs

