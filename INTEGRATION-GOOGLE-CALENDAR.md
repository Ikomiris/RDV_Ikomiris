# Intégration Google Calendar - Guide de Configuration

Ce guide explique comment configurer l'intégration Google Calendar pour éviter les doubles réservations en synchronisant les événements de vos calendriers Google avec le plugin Ikomiris Booking System.

## 📋 Prérequis

- WordPress 5.8+ avec PHP 7.4+
- Plugin Ikomiris Booking System installé et activé
- Un compte Google avec accès à Google Cloud Console

## 🔧 Configuration Étape par Étape

### 1. Créer un Projet Google Cloud

1. Accédez à la [Google Cloud Console](https://console.cloud.google.com/)
2. Cliquez sur **"Créer un projet"**
3. Nommez votre projet (ex: "Ikomiris Booking System")
4. Notez l'ID du projet

### 2. Activer l'API Google Calendar

1. Dans le menu de gauche, allez à **"API et services" > "Bibliothèque"**
2. Recherchez **"Google Calendar API"**
3. Cliquez sur **"Activer"**

### 3. Créer les Identifiants OAuth 2.0

1. Allez dans **"API et services" > "Identifiants"**
2. Cliquez sur **"Créer des identifiants" > "ID client OAuth"**
3. Si demandé, configurez l'écran de consentement :
   - Type d'application : **Externe**
   - Nom de l'application : **Ikomiris Booking System**
   - Email de contact : votre email
   - Scopes : Ajoutez `https://www.googleapis.com/auth/calendar.readonly`
4. Type d'application : **Application Web**
5. Ajoutez l'URI de redirection autorisée : `https://developers.google.com/oauthplayground`
6. Cliquez sur **"Créer"**
7. **Notez le Client ID et le Client Secret** affichés

### 4. Générer le Refresh Token

1. Accédez à [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/)
2. Cliquez sur l'icône **⚙️** (Settings) en haut à droite
3. Cochez **"Use your own OAuth credentials"**
4. Entrez votre **Client ID** et **Client Secret**
5. Dans la liste de gauche, trouvez **"Calendar API v3"**
6. Sélectionnez le scope :
   - `https://www.googleapis.com/auth/calendar.readonly` (lecture seule - recommandé)
   - OU `https://www.googleapis.com/auth/calendar.events` (lecture/écriture pour synchronisation bidirectionnelle)
7. Cliquez sur **"Authorize APIs"**
8. Connectez-vous avec le compte Google qui possède les calendriers
9. Autorisez les permissions
10. Cliquez sur **"Exchange authorization code for tokens"**
11. **Notez le Refresh Token** affiché

### 5. Configurer le Plugin WordPress

1. Dans l'admin WordPress, allez à **Ikomiris Booking > Paramètres**
2. Section **"Google Agenda"** :
   - ✅ Cochez **"Activer Google Agenda"**
   - Collez le **Client ID**
   - Collez le **Client Secret**
   - Collez le **Refresh Token**
3. Cliquez sur **"Enregistrer les paramètres"**

### 6. Associer un Calendrier à un Magasin

1. Allez dans **Ikomiris Booking > Magasins**
2. Modifiez un magasin existant ou créez-en un nouveau
3. Dans le champ **"ID Google Calendar"**, entrez l'ID du calendrier :
   - Pour votre calendrier principal : votre adresse Gmail (ex: `moncompte@gmail.com`)
   - Pour un calendrier secondaire : trouvez l'ID dans les paramètres du calendrier (ex: `abc123@group.calendar.google.com`)
4. Enregistrez le magasin

#### Comment trouver l'ID d'un calendrier Google ?

1. Ouvrez [Google Calendar](https://calendar.google.com/)
2. Cliquez sur les **3 points** à côté du calendrier souhaité
3. Sélectionnez **"Paramètres et partage"**
4. Faites défiler jusqu'à **"Intégrer le calendrier"**
5. Copiez l'**ID du calendrier** (ex: `exemple@group.calendar.google.com`)

## 🔍 Fonctionnement

Une fois configuré, le plugin :

1. ✅ Récupère automatiquement les événements Google Calendar lors de l'affichage des créneaux disponibles
2. ✅ Exclut les heures déjà bloquées par des événements Google
3. ✅ Fusionne les réservations WordPress et Google pour éviter les chevauchements
4. ✅ Met en cache les access tokens pendant 55 minutes pour optimiser les performances
5. ✅ Continue de fonctionner même si Google Calendar est temporairement indisponible

## 🚀 Synchronisation Bidirectionnelle (Bonus)

Si vous avez utilisé le scope `calendar.events` (lecture/écriture), vous pouvez activer la synchronisation bidirectionnelle :

La méthode `GoogleCalendar->create_event()` permet de créer automatiquement un événement dans Google Calendar lors d'une réservation WordPress.

Pour l'activer, modifiez `BookingAPI.php` dans la méthode `create_booking()` après l'insertion :

```php
// Après : $booking_id = $wpdb->insert_id;

// Synchroniser avec Google Calendar
$store_data = $wpdb->get_row($wpdb->prepare("SELECT google_calendar_id FROM {$wpdb->prefix}ibs_stores WHERE id = %d", $store_id));
if ($store_data && !empty($store_data->google_calendar_id)) {
    $google = new \IBS\Integrations\GoogleCalendar();
    if ($google->is_configured()) {
        $start_datetime = $date . 'T' . $time;
        $end_timestamp = strtotime($start_datetime) + ($service->duration * 60);
        $end_datetime = date('Y-m-d\TH:i:s', $end_timestamp);
        
        $event_data = [
            'summary' => 'Réservation : ' . $firstname . ' ' . $lastname,
            'description' => 'Email: ' . $email . "\nTéléphone: " . $phone . "\n\n" . $message,
            'start' => $start_datetime,
            'end' => $end_datetime,
        ];
        
        $event_id = $google->create_event($store_data->google_calendar_id, $event_data);
        
        if ($event_id) {
            // Sauvegarder l'event_id dans la réservation
            $wpdb->update(
                $wpdb->prefix . 'ibs_bookings',
                ['google_event_id' => $event_id],
                ['id' => $booking_id]
            );
        }
    }
}
```

## 🔒 Sécurité

- ✅ Toutes les requêtes utilisent `wp_remote_get()` et `wp_remote_post()` (conformes aux standards WordPress)
- ✅ Les credentials sont stockés dans la base de données WordPress (table `ibs_settings`)
- ✅ Les access tokens sont mis en cache avec des transients WordPress (expiration automatique)
- ✅ Aucune donnée sensible n'est exposée côté client

## 🐛 Dépannage

### Les créneaux disponibles n'excluent pas les événements Google

1. Vérifiez que l'intégration est activée dans **Paramètres > Google Agenda**
2. Vérifiez que le **google_calendar_id** est renseigné pour le magasin
3. Vérifiez les logs WordPress : `wp-content/debug.log` (activez `WP_DEBUG_LOG`)
4. Testez le Refresh Token avec OAuth Playground

### Erreur "Invalid grant" ou "Token expired"

- Le Refresh Token a expiré ou est invalide
- Régénérez un nouveau Refresh Token via OAuth Playground
- Vérifiez que le Client ID et Client Secret correspondent au projet

### Erreur "Insufficient Permission" ou "Forbidden"

- Le scope OAuth n'est pas correct
- Utilisez `calendar.readonly` pour la lecture seule
- Ou `calendar.events` pour la synchronisation bidirectionnelle

### Les événements Google ne sont pas récupérés

1. Vérifiez que le compte Google connecté a accès au calendrier
2. Vérifiez que l'API Google Calendar est activée dans Cloud Console
3. Testez manuellement l'API avec un outil comme Postman

## 📚 Ressources

- [Google Calendar API v3 Documentation](https://developers.google.com/calendar/api/v3/reference)
- [OAuth 2.0 pour Applications Web](https://developers.google.com/identity/protocols/oauth2/web-server)
- [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/)
- [Trouver l'ID d'un calendrier](https://support.google.com/calendar/answer/37103)

## 📞 Support

Si vous rencontrez des problèmes, activez le mode debug WordPress :

```php
// Dans wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Les logs Google Calendar seront écrits dans `wp-content/debug.log` avec le préfixe `IBS Google Calendar:`.

---

**Version** : 1.0.0  
**Dernière mise à jour** : Janvier 2026

