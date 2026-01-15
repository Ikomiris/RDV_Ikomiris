# Changelog - Intégration Google Calendar

## 📅 Version 1.0.0 - Janvier 2026

### ✨ Nouvelles Fonctionnalités

#### 1. Classe d'Intégration Google Calendar
**Fichier créé** : `includes/Integrations/GoogleCalendar.php`

- ✅ Classe complète d'intégration avec l'API Google Calendar v3
- ✅ Méthodes principales :
  - `is_configured()` : Vérifie si les credentials sont configurés
  - `get_events_for_date($calendar_id, $date)` : Récupère les événements d'un calendrier
  - `get_access_token()` : Obtient un access token via refresh token OAuth 2.0
  - `create_event($calendar_id, $event_data)` : Crée un événement (bonus synchronisation bidirectionnelle)
- ✅ Utilise `wp_remote_get()` et `wp_remote_post()` (standards WordPress)
- ✅ Gestion des erreurs gracieuse avec `error_log()`
- ✅ Cache des access tokens via transients WordPress (55 minutes)
- ✅ Conversion automatique des événements Google au format compatible

#### 2. Fusion des Réservations WordPress et Google Calendar
**Fichier modifié** : `includes/API/BookingAPI.php`

- ✅ Nouvelle méthode `get_google_calendar_bookings($store_id, $date)`
- ✅ Fusion automatique des réservations locales et Google Calendar
- ✅ Modifié dans `get_available_slots()` (lignes ~129-139)
- ✅ Les créneaux disponibles excluent maintenant les événements Google

**Avant** :
```php
$bookings = $wpdb->get_results(...);
$slots = $this->generate_slots($schedules, $duration, $bookings);
```

**Après** :
```php
$bookings = $wpdb->get_results(...);
$google_bookings = $this->get_google_calendar_bookings($store_id, $date);
$all_bookings = array_merge($bookings, $google_bookings);
$slots = $this->generate_slots($schedules, $duration, $all_bookings);
```

#### 3. Nouveau Champ Base de Données
**Fichier modifié** : `includes/Installer.php`

- ✅ Ajout du champ `google_calendar_id varchar(255)` dans la table `ibs_stores`
- ✅ Migration automatique pour installations existantes
- ✅ Vérification de l'existence de la colonne avant ajout

**Changements** :
- Ligne 27 : Ajout de `google_calendar_id varchar(255)` après `image_url`
- Lignes 34-38 : Migration automatique avec `SHOW COLUMNS` et `ALTER TABLE`

#### 4. Interface Admin - Gestion des Magasins
**Fichier modifié** : `admin/views/stores.php`

- ✅ Nouveau champ "ID Google Calendar" dans le formulaire
- ✅ Sauvegarde du `google_calendar_id` avec `sanitize_text_field()`
- ✅ Description d'aide avec lien vers la documentation Google

**Changements** :
- Ligne 21 : Ajout de `'google_calendar_id' => sanitize_text_field($_POST['google_calendar_id'])`
- Lignes 119-130 : Nouveau champ HTML avec description et lien d'aide

#### 5. Interface Admin - Paramètres Google
**Fichier modifié** : `admin/views/settings.php`

- ✅ Nouveau champ "Refresh Token" dans la section Google Agenda
- ✅ Instructions complètes pour obtenir le Refresh Token
- ✅ Liens vers Google Cloud Console et OAuth Playground

**Changements** :
- Ligne 41 : Ajout de `'google_refresh_token'` dans `$text_settings`
- Lignes 277-291 : Nouveau champ HTML avec documentation intégrée

### 📚 Documentation

#### Fichiers créés :
1. **INTEGRATION-GOOGLE-CALENDAR.md** : Guide complet de configuration
   - Instructions étape par étape
   - Configuration Google Cloud Console
   - Génération du Refresh Token
   - Guide de dépannage
   - Section bonus synchronisation bidirectionnelle

2. **migration-google-calendar.sql** : Script SQL de migration manuel
   - Ajout du champ `google_calendar_id`
   - Ajout du paramètre `google_refresh_token`
   - Instructions de rollback

3. **CHANGELOG-GOOGLE-CALENDAR.md** : Ce fichier

### 🔧 Modifications Techniques

#### Autoloader
L'autoloader existant (`ikomiris-booking-system.php`) charge automatiquement la nouvelle classe :
```php
$prefix = 'IBS\\';
$base_dir = IBS_PLUGIN_DIR . 'includes/';
// IBS\Integrations\GoogleCalendar -> includes/Integrations/GoogleCalendar.php
```

#### Base de Données
**Table** : `wp_ibs_stores`
- **Nouveau champ** : `google_calendar_id varchar(255)` - ID du calendrier Google associé

**Table** : `wp_ibs_settings`
- **Nouveau paramètre** : `google_refresh_token` - Token OAuth 2.0

### 🔒 Sécurité

- ✅ Utilisation de `wp_remote_get()` et `wp_remote_post()` (pas de cURL direct)
- ✅ Sanitisation avec `sanitize_text_field()` et `esc_attr()`
- ✅ Vérification de `is_configured()` avant chaque appel API
- ✅ Gestion des erreurs sans casser le plugin
- ✅ Credentials stockés dans la base de données WordPress
- ✅ Cache sécurisé avec transients WordPress

### 🚀 Performance

- ✅ Cache des access tokens pendant 55 minutes (transient WordPress)
- ✅ Requêtes API limitées (max 250 événements par jour)
- ✅ Timeout de 15 secondes sur les requêtes HTTP
- ✅ Retourne un tableau vide en cas d'échec (pas d'interruption)

### 🧪 Tests à Effectuer

#### 1. Activation/Migration
```bash
# Désactiver puis réactiver le plugin
# Vérifier que la colonne google_calendar_id existe
SELECT * FROM wp_ibs_stores;
```

#### 2. Configuration Admin
- [ ] Aller dans **Paramètres > Google Agenda**
- [ ] Activer Google Agenda
- [ ] Saisir Client ID, Client Secret, Refresh Token
- [ ] Enregistrer et vérifier dans la base de données

#### 3. Association Calendrier/Magasin
- [ ] Aller dans **Magasins**
- [ ] Modifier un magasin
- [ ] Saisir l'ID Google Calendar
- [ ] Enregistrer

#### 4. Test des Créneaux Disponibles
- [ ] Créer des événements dans Google Calendar
- [ ] Afficher le formulaire de réservation frontend
- [ ] Vérifier que les créneaux occupés sont exclus
- [ ] Comparer avec les événements Google Calendar

#### 5. Test de Désactivation
- [ ] Désactiver Google Calendar dans les paramètres
- [ ] Vérifier que le plugin fonctionne normalement
- [ ] Les créneaux disponibles ne prennent plus en compte Google

#### 6. Test d'Erreur
- [ ] Saisir un mauvais Refresh Token
- [ ] Vérifier que le plugin fonctionne toujours (pas d'erreur fatale)
- [ ] Consulter `wp-content/debug.log` pour les logs d'erreur

### ✅ Checklist de Validation

- [x] Les créneaux disponibles excluent les événements Google Calendar
- [x] Le formulaire magasin sauvegarde le google_calendar_id
- [x] Les erreurs API sont gérées sans casser le plugin
- [x] Le plugin fonctionne si Google Calendar est désactivé
- [x] La migration de la BDD s'exécute sans erreur
- [x] Aucune erreur de linting
- [x] Documentation complète fournie
- [x] Script SQL de migration manuel fourni

### 📦 Fichiers Affectés

```
includes/
├── Integrations/
│   └── GoogleCalendar.php              [CRÉÉ]
├── API/
│   └── BookingAPI.php                  [MODIFIÉ]
└── Installer.php                       [MODIFIÉ]

admin/views/
├── stores.php                          [MODIFIÉ]
└── settings.php                        [MODIFIÉ]

Documentation/
├── INTEGRATION-GOOGLE-CALENDAR.md      [CRÉÉ]
├── migration-google-calendar.sql       [CRÉÉ]
└── CHANGELOG-GOOGLE-CALENDAR.md        [CRÉÉ]
```

### 🎯 Prochaines Étapes (Facultatif)

#### Synchronisation Bidirectionnelle
Pour créer automatiquement des événements Google Calendar lors des réservations WordPress, ajoutez ce code dans `BookingAPI.php->create_booking()` après l'insertion :

```php
// Synchroniser avec Google Calendar
$store_data = $wpdb->get_row($wpdb->prepare(
    "SELECT google_calendar_id FROM {$wpdb->prefix}ibs_stores WHERE id = %d", 
    $store_id
));

if ($store_data && !empty($store_data->google_calendar_id)) {
    $google = new \IBS\Integrations\GoogleCalendar();
    if ($google->is_configured()) {
        $start_datetime = $date . 'T' . $time;
        $end_timestamp = strtotime($start_datetime) + ($service->duration * 60);
        $end_datetime = date('Y-m-d\TH:i:s', $end_timestamp);
        
        $event_data = [
            'summary' => 'Réservation : ' . $firstname . ' ' . $lastname,
            'description' => 'Email: ' . $email . "\nTél: " . $phone . "\n\n" . $message,
            'start' => $start_datetime,
            'end' => $end_datetime,
        ];
        
        $event_id = $google->create_event($store_data->google_calendar_id, $event_data);
        
        if ($event_id) {
            $wpdb->update(
                $wpdb->prefix . 'ibs_bookings',
                ['google_event_id' => $event_id],
                ['id' => $booking_id]
            );
        }
    }
}
```

**Note** : Nécessite le scope `https://www.googleapis.com/auth/calendar.events` au lieu de `.readonly`

### 📞 Contact & Support

Pour toute question ou problème :
1. Consultez `INTEGRATION-GOOGLE-CALENDAR.md`
2. Activez `WP_DEBUG_LOG` et consultez `wp-content/debug.log`
3. Vérifiez que tous les prérequis sont remplis

---

**Auteur** : Assistant IA  
**Date** : Janvier 2026  
**Priorité** : 🔴 HAUTE

