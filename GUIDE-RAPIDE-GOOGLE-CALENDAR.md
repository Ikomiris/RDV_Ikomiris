# 🚀 Guide rapide : Résoudre le problème Google Calendar

## Votre situation actuelle

✅ La réservation #31 a été créée dans WordPress
❌ Elle n'apparaît pas dans Google Calendar
❌ Erreur 401 : "Request had invalid authentication credentials"

**Cause** : Le token d'authentification Google a expiré ou est invalide.

## 🔧 Solution en 3 étapes

### Étape 1 : Vérifier le diagnostic

1. Connectez-vous à l'admin WordPress
2. Allez dans **Réservations → Test Google Calendar**
3. Cliquez sur **"Tester la connexion Google"**
4. Lisez le diagnostic affiché

### Étape 2 : Générer un nouveau Refresh Token

Si le test confirme que le token est invalide :

1. **Ouvrez [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/)**

2. **Configurez vos credentials** (⚙️ en haut à droite) :
   ```
   ☑ Use your own OAuth credentials
   OAuth Client ID: [votre Client ID]
   OAuth Client secret: [votre Client Secret]
   ```

3. **Sélectionnez les scopes** (Step 1 - Select & authorize APIs) :
   - Cherchez "Google Calendar API v3"
   - **Cochez ces 2 scopes minimum** :
     ```
     ✅ https://www.googleapis.com/auth/calendar
     ✅ https://www.googleapis.com/auth/calendar.events
     ```
   - Si vous voulez éviter les erreurs futures, ajoutez aussi :
     ```
     ✅ https://www.googleapis.com/auth/calendar.readonly
     ✅ https://www.googleapis.com/auth/calendar.calendars.readonly
     ```

4. **Cliquez sur "Authorize APIs"**
   - Connectez-vous avec le compte Gmail qui possède les calendriers
   - **Important** : Utilisez le même compte que celui configuré dans les calendriers de vos magasins
   - Acceptez toutes les permissions

5. **Cliquez sur "Exchange authorization code for tokens"** (Step 2)

6. **Copiez le Refresh token** affiché dans la réponse JSON

### Étape 3 : Mettre à jour WordPress

1. Dans WordPress, allez dans **Réservations → Paramètres**
2. Section **"Google Agenda"**
3. Collez le nouveau Refresh Token dans le champ correspondant
4. Cliquez sur **"Enregistrer les paramètres"**

### Étape 4 : Vérifier que ça fonctionne

1. Retournez dans **Réservations → Test Google Calendar**
2. Cliquez sur **"Tester la connexion Google"**
3. Vous devriez voir :
   ```
   ✅ Access token obtenu avec succès
   ✅ API Google Calendar accessible
   ✅ X calendrier(s) trouvé(s)
   ```

4. Faites une **réservation test** pour demain
5. Vérifiez qu'elle apparaît bien dans Google Calendar

## ⚠️ Points importants

### Vérifier le timezone WordPress

1. Allez dans **Réglages → Général**
2. Dans "Fuseau horaire", assurez-vous d'avoir sélectionné :
   - 🇫🇷 **Europe/Paris** (pas UTC+1 ou UTC+2)
   - 🇧🇪 **Europe/Brussels**
   - 🇨🇭 **Europe/Zurich**
3. **Ne jamais utiliser d'offset fixe** comme "UTC+2"

### Vérifier les Calendar ID

Pour chaque magasin :

1. Allez dans **Réservations → Magasins**
2. Éditez chaque magasin
3. Vérifiez que le champ **"Google Calendar ID"** contient :
   - Un ID qui ressemble à : `c_xxxxxxxxxxxxx@group.calendar.google.com`
   - Ou l'email du calendrier : `votre-email@gmail.com` (pour le calendrier principal)

### Obtenir un Calendar ID

1. Allez sur [Google Calendar](https://calendar.google.com/)
2. Dans la liste de gauche, cliquez sur **⋮** à côté du calendrier voulu
3. Cliquez sur **"Paramètres et partage"**
4. Descendez jusqu'à **"Intégrer l'agenda"**
5. Copiez l'**"ID de l'agenda"**

## 🐛 En cas de problème

### Le test échoue toujours

- Vérifiez que vous avez bien copié le **Refresh Token** (pas l'Access Token)
- Vérifiez que le Client ID et Client Secret sont corrects
- Essayez de générer un nouveau token avec **tous les scopes** cochés

### Les réservations n'apparaissent toujours pas

- Vérifiez que "Google Calendar" est **activé** dans les Paramètres
- Vérifiez que le Calendar ID est correct pour chaque magasin
- Consultez les logs dans `wp-content/ibs-booking-debug.log`

### Message "Insufficient Permission"

Vous devez générer un nouveau token avec **plus de scopes** :
```
✅ https://www.googleapis.com/auth/calendar (obligatoire)
✅ https://www.googleapis.com/auth/calendar.events (obligatoire)
✅ https://www.googleapis.com/auth/calendar.readonly (recommandé)
✅ https://www.googleapis.com/auth/calendar.calendars.readonly (recommandé)
```

## 📚 Documents complémentaires

- [FIX-TIMEZONE-DECALAGE-1H.md](FIX-TIMEZONE-DECALAGE-1H.md) - Explications détaillées du problème de timezone
- Page de test : `Réservations → Test Google Calendar` - Outil de diagnostic intégré

## ✅ Checklist rapide

- [ ] Page de test Google Calendar accessible
- [ ] Test de connexion effectué
- [ ] Nouveau Refresh Token généré avec les bons scopes
- [ ] Token collé dans les paramètres WordPress
- [ ] Timezone WordPress configuré sur Europe/Paris (pas d'offset)
- [ ] Calendar ID vérifié pour chaque magasin
- [ ] Test de connexion réussi
- [ ] Réservation test créée et visible dans Google Calendar

---

**Note** : Une fois que tout fonctionne, les nouvelles réservations seront automatiquement synchronisées avec Google Calendar. Les anciennes réservations (comme la #31) ne seront pas automatiquement ajoutées - vous pouvez les ajouter manuellement dans Google Calendar si nécessaire.
