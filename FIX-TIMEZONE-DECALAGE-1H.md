# Correction du décalage d'1 heure avec Google Calendar

## Problème identifié

Le décalage d'1 heure entre les rendez-vous pris et ceux affichés dans Google Agenda est causé par l'utilisation d'un **offset fixe** (`+02:00`) au lieu d'un **fuseau horaire nommé** (comme `Europe/Paris`) dans WordPress.

### Pourquoi c'est un problème ?

Les offsets fixes comme `+02:00` ne tiennent **pas compte du changement d'heure été/hiver** :
- En **été** : `Europe/Paris` = UTC+2
- En **hiver** : `Europe/Paris` = UTC+1

Si WordPress est configuré avec `+02:00` fixe, en février (hiver), il y a un **décalage d'1 heure** car le système devrait être à UTC+1.

## Corrections appliquées

J'ai modifié le fichier [includes/API/BookingAPI.php](includes/API/BookingAPI.php) pour :

1. **Détecter automatiquement** si WordPress utilise un offset fixe
2. **Essayer de deviner** le timezone approprié (ex: `+02:00` → `Europe/Paris`)
3. **Utiliser le timezone du calendrier Google** en priorité (si accessible)
4. **Logger des avertissements** pour vous alerter du problème

## Solution recommandée : Configurer un timezone nommé dans WordPress

### Étapes à suivre :

1. **Allez dans l'administration WordPress**
   - Menu : `Réglages` → `Général`

2. **Trouvez le paramètre "Fuseau horaire"**
   - Si vous voyez quelque chose comme `UTC+2` ou un **offset numérique**, c'est le problème !

3. **Sélectionnez votre ville/pays**
   - Pour la France : `Europe/Paris`
   - Pour la Belgique : `Europe/Brussels`
   - Pour la Suisse : `Europe/Zurich`
   - Pour le Canada (Montréal) : `America/Toronto`
   - Pour le Canada (Vancouver) : `America/Vancouver`

4. **Enregistrez les modifications**

5. **Testez une nouvelle réservation**
   - Créez un nouveau rendez-vous
   - Vérifiez qu'il apparaît à la bonne heure dans Google Calendar

## Vérification des logs

Pour vérifier que la correction fonctionne, consultez les logs WordPress :
- Fichier : `wp-content/ibs-booking-debug.log`
- Cherchez les lignes commençant par `IBS:`

Vous devriez voir :
```
IBS: Utilisation du timezone WordPress - Europe/Paris
```
Ou si le système a deviné :
```
IBS: Utilisation du timezone deviné - Europe/Paris
```

Si vous voyez :
```
IBS: ATTENTION - WordPress utilise un offset fixe (+02:00)
```
Cela confirme que WordPress doit être reconfiguré.

## Problème d'authentification Google Calendar (Erreur 401/403)

### Erreur 401 - Invalid Credentials

Si vous voyez cette erreur :
```
"code": 401,
"message": "Request had invalid authentication credentials."
```

Cela signifie que le **Refresh Token est invalide ou expiré**. Les tokens OAuth peuvent expirer pour plusieurs raisons :
- Le token a été révoqué manuellement
- Les credentials (Client ID/Secret) ont changé
- Le compte Google a révoqué l'accès
- Le token n'a pas été utilisé pendant plus de 6 mois

### Erreur 403 - Insufficient Permissions

Si vous voyez cette erreur :
```
"code": 403,
"message": "Request had insufficient authentication scopes."
```

Cela signifie que l'application n'a pas les **permissions nécessaires** pour :
- Lire les détails du calendrier (`calendar.v3.Calendars.Get`)
- Consulter les disponibilités (`calendar.v3.Freebusy.Query`)
- Créer des événements (`calendar.v3.Events.Insert`)

### 🔧 Solution : Utiliser la page de test Google Calendar

J'ai créé une page de diagnostic et de test :

1. **Allez dans l'admin WordPress** : `Réservations` → `Test Google Calendar`

2. **Cliquez sur "Tester la connexion Google"**
   - La page va diagnostiquer le problème
   - Afficher les calendriers accessibles si la connexion fonctionne
   - Vous donner des instructions précises si elle échoue

3. **Suivez les instructions affichées** pour générer un nouveau Refresh Token

### Générer un nouveau Refresh Token

1. **Allez sur [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/)**

2. **Configurez vos credentials** (cliquez sur ⚙️ en haut à droite) :
   - Cochez "Use your own OAuth credentials"
   - Entrez votre Client ID et Client Secret

3. **Sélectionnez les scopes** (Step 1) :
   - Cherchez "Calendar API v3"
   - Sélectionnez **au minimum** :
     - ✅ `https://www.googleapis.com/auth/calendar`
     - ✅ `https://www.googleapis.com/auth/calendar.events`
   - **Recommandé** pour éviter les erreurs 403 :
     - ✅ `https://www.googleapis.com/auth/calendar.readonly`

4. **Autorisez** (cliquez sur "Authorize APIs")
   - Connectez-vous avec le compte Google qui possède les calendriers
   - Acceptez toutes les permissions demandées

5. **Échangez le code** (cliquez sur "Exchange authorization code for tokens")

6. **Copiez le Refresh Token** affiché

7. **Collez-le dans WordPress** :
   - `Réservations` → `Paramètres` → Section "Google Agenda"
   - Champ "Refresh Token"
   - Enregistrez

8. **Testez à nouveau** sur la page `Test Google Calendar`

## Test complet

Après avoir configuré le timezone WordPress :

1. **Créez un rendez-vous test** pour demain à 10h00
2. **Vérifiez dans Google Calendar** qu'il apparaît bien à 10h00
3. **Consultez les logs** pour voir quel timezone a été utilisé

## Support

Si le problème persiste après ces corrections :
- Vérifiez les logs dans `wp-content/ibs-booking-debug.log`
- Notez le timezone affiché dans les logs
- Vérifiez la configuration du timezone dans WordPress
- Assurez-vous que les permissions Google Calendar sont correctes
