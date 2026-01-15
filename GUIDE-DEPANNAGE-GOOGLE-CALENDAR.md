# 🔧 Guide de Dépannage - Google Calendar

## Problème : Les événements Google ne sont pas pris en compte

### ✅ Étape 1 : Accéder à l'outil de diagnostic

1. Ouvrez votre navigateur et accédez à :
   ```
   https://votre-site.com/wp-content/plugins/ikomiris-booking-system/diagnostic-google-calendar.php
   ```

2. Vous devez être connecté en tant qu'administrateur

### ✅ Étape 2 : Vérifier la configuration

L'outil de diagnostic va vérifier automatiquement :

- ☑️ **Google Calendar Activé** : Doit être sur "Oui"
- ☑️ **Client ID** : Doit être renseigné
- ☑️ **Client Secret** : Doit être renseigné
- ☑️ **Refresh Token** : Doit être renseigné
- ☑️ **Magasins avec Calendar ID** : Au moins un magasin doit avoir un Calendar ID

### ✅ Étape 3 : Tester la connexion

L'outil va automatiquement :
1. Tenter d'obtenir un Access Token
2. Afficher si la connexion fonctionne
3. Montrer les logs d'erreur si échec

### ✅ Étape 4 : Tester la récupération d'événements

1. Dans l'outil, sélectionnez un magasin
2. Choisissez une date avec des événements dans Google Calendar
3. Cliquez sur "🔍 Tester la Récupération"
4. Vérifiez que les événements sont bien listés

### 🔍 Causes Fréquentes

#### 1. Google Calendar est désactivé
**Solution** : Allez dans `Paramètres > Google Agenda` et cochez "Activer Google Agenda"

#### 2. Refresh Token invalide
**Solution** : Régénérez le Refresh Token via [OAuth Playground](https://developers.google.com/oauthplayground/)

**Étapes** :
1. Allez sur https://developers.google.com/oauthplayground/
2. Cliquez sur ⚙️ (Settings)
3. Cochez "Use your own OAuth credentials"
4. Entrez votre Client ID et Client Secret
5. Dans la liste, sélectionnez `Calendar API v3` > `https://www.googleapis.com/auth/calendar`
6. Cliquez sur "Authorize APIs"
7. Cliquez sur "Exchange authorization code for tokens"
8. Copiez le **Refresh Token** et collez-le dans `Paramètres > Google Agenda`

#### 3. Calendar ID incorrect
**Solution** : Vérifiez l'ID du calendrier

**Comment trouver l'ID du calendrier** :
1. Ouvrez https://calendar.google.com/
2. Cliquez sur les 3 points à côté de votre calendrier
3. Sélectionnez "Paramètres et partage"
4. Faites défiler jusqu'à "Intégrer le calendrier"
5. Copiez l'**ID du calendrier** (ex: `exemple@group.calendar.google.com`)
6. Collez-le dans `Magasins > Modifier > ID Google Calendar`

#### 4. Problème de timezone
**Solution** : Vérifiez le timezone WordPress

1. Allez dans `Réglages > Général > Fuseau horaire`
2. Sélectionnez votre ville (ex: "Paris" pour France)
3. **NE PAS** utiliser "UTC+1" mais bien le nom de la ville

#### 5. Scope OAuth insuffisant
**Solution** : Utilisez le bon scope lors de la génération du Refresh Token

**Scopes disponibles** :
- `https://www.googleapis.com/auth/calendar.readonly` - Lecture seule (pour récupérer les événements)
- `https://www.googleapis.com/auth/calendar.events` - Lecture/Écriture (pour récupérer ET créer des événements)

**Important** : Si vous voulez que les RDV WordPress apparaissent dans Google Calendar, utilisez le scope `.events`

#### 6. API Google Calendar non activée
**Solution** :
1. Allez sur https://console.cloud.google.com/
2. Sélectionnez votre projet
3. Allez dans "API et services" > "Bibliothèque"
4. Recherchez "Google Calendar API"
5. Cliquez sur "Activer"

---

## Problème : Les RDV WordPress n'apparaissent pas dans Google Calendar

### ✅ Vérifications

#### 1. Scope OAuth correct
Le Refresh Token doit avoir été généré avec le scope :
```
https://www.googleapis.com/auth/calendar.events
```

**Pas** `.readonly` mais bien `.events` pour pouvoir créer des événements.

#### 2. Calendar ID du magasin renseigné
- Allez dans `Magasins > Modifier`
- Vérifiez que le champ "ID Google Calendar" est rempli
- Format attendu : `exemple@group.calendar.google.com` ou `votre-email@gmail.com`

#### 3. Google Calendar activé dans les paramètres
- Allez dans `Paramètres > Google Agenda`
- Vérifiez que "Activer Google Agenda" est coché

### 🔍 Vérifier les logs

1. Activez les logs WordPress dans `wp-config.php` :
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. Créez une réservation test

3. Consultez le fichier `wp-content/debug.log`

4. Recherchez les lignes contenant `IBS:`

**Logs attendus** :
```
IBS: Réservation #123 synchronisée avec Google Calendar (event_id: abc123xyz)
```

**Si vous voyez** :
```
IBS: Magasin #1 sans google_calendar_id - pas de synchronisation Google
```
→ Le magasin n'a pas de Calendar ID configuré

**Si vous voyez** :
```
IBS: Google Calendar non configuré - pas de synchronisation
```
→ Les credentials Google ne sont pas complets dans les paramètres

**Si vous voyez** :
```
IBS: Échec de la synchronisation de la réservation #123 avec Google Calendar
```
→ Vérifiez le scope OAuth (doit être `.events` et pas `.readonly`)

---

## 🧪 Tests Rapides

### Test 1 : Vérifier l'Access Token
```php
// Collez ce code dans un fichier test-google-token.php à la racine WordPress
require_once('wp-load.php');
require_once('wp-content/plugins/ikomiris-booking-system/includes/Integrations/GoogleCalendar.php');

$google = new \IBS\Integrations\GoogleCalendar();
$token = $google->get_access_token();

if ($token) {
    echo "✅ Access Token obtenu : " . substr($token, 0, 30) . "...\n";
} else {
    echo "❌ Impossible d'obtenir un Access Token\n";
}
```

### Test 2 : Vérifier la récupération d'événements
```php
// Dans test-google-events.php
require_once('wp-load.php');
require_once('wp-content/plugins/ikomiris-booking-system/includes/Integrations/GoogleCalendar.php');

$google = new \IBS\Integrations\GoogleCalendar();
$events = $google->get_events_for_date('votre-calendar-id@gmail.com', '2026-01-15');

echo "📅 Événements trouvés : " . count($events) . "\n";
foreach ($events as $event) {
    echo "  - " . $event->booking_time . " (" . $event->duration . " min)\n";
}
```

### Test 3 : Vérifier la création d'événement
```php
// Dans test-google-create.php
require_once('wp-load.php');
require_once('wp-content/plugins/ikomiris-booking-system/includes/Integrations/GoogleCalendar.php');

$google = new \IBS\Integrations\GoogleCalendar();
$event_data = [
    'summary' => 'Test Ikomiris',
    'description' => 'Test de création',
    'start' => '2026-01-15T14:00:00',
    'end' => '2026-01-15T15:00:00',
];

$event_id = $google->create_event('votre-calendar-id@gmail.com', $event_data);

if ($event_id) {
    echo "✅ Événement créé : " . $event_id . "\n";
} else {
    echo "❌ Échec de la création\n";
}
```

---

## 🆘 Support

Si le problème persiste après toutes ces vérifications :

1. **Consultez les logs détaillés** :
   - Les logs sont maintenant très verbeux
   - Chaque étape est loguée dans `wp-content/debug.log`
   - Recherchez les lignes commençant par `IBS Google Calendar:`

2. **Vérifiez la réponse de l'API Google** :
   - Les codes d'erreur HTTP sont loggés
   - Les messages d'erreur de l'API sont affichés

3. **Testez avec l'outil de diagnostic** :
   - Utilisez `diagnostic-google-calendar.php` en premier
   - Il affiche toutes les informations de configuration

4. **Régénérez tous les credentials** :
   - Supprimez le projet Google Cloud
   - Recréez tout depuis zéro
   - Utilisez bien le scope `.events` si vous voulez la synchronisation bidirectionnelle

---

## 📝 Checklist Complète

- [ ] Google Calendar activé dans `Paramètres > Google Agenda`
- [ ] Client ID renseigné
- [ ] Client Secret renseigné
- [ ] Refresh Token renseigné avec le bon scope (`.events` pour bidirectionnel)
- [ ] API Google Calendar activée dans Cloud Console
- [ ] Calendar ID configuré pour au moins un magasin
- [ ] Timezone WordPress configuré avec le nom de la ville (pas UTC+X)
- [ ] WP_DEBUG_LOG activé pour voir les logs
- [ ] Test avec l'outil de diagnostic OK
- [ ] Cache vidé (transients WordPress)

---

**Version** : 1.0.0  
**Dernière mise à jour** : Janvier 2026

