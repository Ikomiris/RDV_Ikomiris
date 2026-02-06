# 🔄 Système d'annulation de réservations

## ✅ Ce qui a été implémenté

### 1. **Base de données**
- ✅ Colonne `status` dans `ibs_bookings` (pending, confirmed, cancelled, completed)
- ✅ Colonne `cancel_token` dans `ibs_bookings` (token unique de 64 caractères)
- ✅ Colonne `cancelled_at` dans `ibs_bookings` (date/heure d'annulation)
- ✅ Colonne `google_event_id` dans `ibs_bookings` (ID de l'événement Google Calendar)
- ✅ Colonne `cancellation_hours` dans `ibs_stores` (délai d'annulation en heures, défaut: 24h)

### 2. **Emails**
- ✅ Email de confirmation avec lien d'annulation (déjà existant, mis à jour)
- ✅ Email de confirmation d'annulation au client
- ✅ Email de notification d'annulation à l'admin

### 3. **Page d'annulation frontend**
- ✅ Page dédiée accessible via `/reservation-annulation/?token=xxx`
- ✅ Affichage des détails de la réservation
- ✅ Vérification automatique :
  - Réservation déjà annulée
  - Réservation passée
  - Délai d'annulation dépassé
- ✅ Interface utilisateur intuitive avec confirmation
- ✅ Gestion AJAX pour l'annulation
- ✅ Messages de succès/erreur clairs

### 4. **Logique backend**
- ✅ Classe `CancellationHandler` pour gérer les annulations
- ✅ Vérification du token d'annulation
- ✅ Vérification des délais (réservation passée, délai d'annulation)
- ✅ Mise à jour du statut en base de données
- ✅ Suppression automatique de l'événement Google Calendar
- ✅ Envoi des emails de notification

### 5. **Intégration Google Calendar**
- ✅ Méthode `delete_event()` ajoutée à `GoogleCalendar`
- ✅ Suppression automatique des événements annulés

## 🔨 Ce qui reste à faire

### 1. **Interface admin** (en cours)
- ⏳ Afficher la colonne "Statut" dans la liste des réservations
- ⏳ Afficher la date d'annulation pour les réservations annulées
- ⏳ Filtrer les réservations par statut (Toutes, Confirmées, Annulées, Passées)
- ⏳ Badge visuel pour les statuts (vert = confirmée, rouge = annulée, gris = passée)

### 2. **Configuration des magasins**
- ⏳ Ajouter le champ "Délai d'annulation (heures)" dans le formulaire d'édition des magasins
- ⏳ Valeur par défaut : 24 heures
- ⏳ Permettre de personnaliser par magasin

## 🧪 Comment tester le système

### Étape 1 : Réactiver le plugin
```bash
# Le plugin doit être désactivé puis réactivé pour que les migrations de base de données s'exécutent
```

1. Allez dans **Extensions → Extensions installées**
2. Désactivez "Ikomiris Booking System"
3. Réactivez-le immédiatement

Cela va :
- Créer les colonnes manquantes dans la base de données
- Créer la page `/reservation-annulation/`

### Étape 2 : Configurer le délai d'annulation par défaut
Par défaut, le délai est de 24 heures. Pour le moment, vous pouvez le modifier directement en base de données si besoin :

```sql
-- Exemple : définir 48 heures de délai pour tous les magasins
UPDATE wp_ibs_stores SET cancellation_hours = 48;
```

(Une interface sera ajoutée dans l'admin très prochainement)

### Étape 3 : Créer une réservation test

1. Allez sur votre formulaire de réservation
2. Créez une nouvelle réservation pour demain (pas aujourd'hui, pour respecter le délai d'annulation)
3. Renseignez votre vraie adresse email

### Étape 4 : Tester l'annulation

1. **Consultez l'email de confirmation** reçu
2. **Cliquez sur le bouton rouge "Annuler ma réservation"**
3. Vous serez redirigé vers la page d'annulation
4. **Vérifiez les informations affichées** :
   - Détails de la réservation
   - Temps restant avant le délai limite
   - Boutons d'action
5. **Cliquez sur "Oui, annuler ma réservation"**
6. **Confirmez** dans la popup
7. **Vérifiez** :
   - Message de succès affiché
   - Email de confirmation d'annulation reçu
   - Email de notification envoyé à l'admin
   - Événement supprimé de Google Calendar

### Étape 5 : Tester les cas limites

**Test 1 : Tentative d'annulation avec le même lien**
- Cliquez à nouveau sur le lien d'annulation
- ✅ Le système doit afficher "Réservation déjà annulée"

**Test 2 : Délai d'annulation dépassé**
- Créez une réservation pour dans 20 heures (si délai = 24h)
- Attendez quelques heures (ou modifiez manuellement en BDD : `UPDATE wp_ibs_bookings SET booking_date = DATE_ADD(NOW(), INTERVAL 10 HOUR)`)
- ❌ Le système doit refuser l'annulation : "Le délai d'annulation est dépassé"

**Test 3 : Réservation passée**
- Créez une réservation
- Modifiez la date en base de données pour qu'elle soit dans le passé
- ❌ Le système doit refuser : "Impossible d'annuler une réservation passée"

## 📋 Structure des fichiers

### Nouveaux fichiers créés
```
includes/
├── Frontend/
│   └── CancellationHandler.php (✅ Gestion des annulations)
├── Email/
│   └── EmailHandler.php (✅ Méthodes d'annulation ajoutées)
└── Integrations/
    └── GoogleCalendar.php (✅ Méthode delete_event ajoutée)

frontend/
└── views/
    └── cancellation-page.php (✅ Template de la page d'annulation)
```

### Fichiers modifiés
```
includes/
├── Installer.php (✅ Migrations BDD + création page d'annulation)
└── API/
    └── BookingAPI.php (✅ Déjà fonctionnel pour générer token et event_id)

ikomiris-booking-system.php (✅ Initialisation CancellationHandler)
```

## 🔐 Sécurité

- ✅ Tokens d'annulation uniques de 64 caractères (générés avec `random_bytes`)
- ✅ Vérification des nonces pour les requêtes AJAX
- ✅ Vérification de la validité du token
- ✅ Vérification des délais (réservation passée, délai d'annulation)
- ✅ Logging de toutes les annulations
- ✅ Emails de notification automatiques

## 📨 Emails envoyés

### 1. Email client - Confirmation de réservation
- Envoyé lors de la création de la réservation
- Contient le bouton "Annuler ma réservation" avec le lien unique

### 2. Email client - Confirmation d'annulation
- Envoyé après annulation réussie
- Confirme l'annulation avec les détails de la réservation
- Affiche la date/heure d'annulation

### 3. Email admin - Notification d'annulation
- Envoyé à l'admin du magasin et à l'admin WordPress
- Informe de l'annulation
- Contient les coordonnées du client et les détails de la réservation

## 🔄 Processus d'annulation

```
Client clique sur le lien dans l'email
    ↓
Affichage de la page /reservation-annulation/?token=xxx
    ↓
Vérifications :
    - Token valide ? ✓
    - Réservation existe ? ✓
    - Déjà annulée ? ✗
    - Réservation passée ? ✗
    - Délai respecté ? ✓
    ↓
Affichage du formulaire de confirmation
    ↓
Client clique sur "Oui, annuler"
    ↓
Requête AJAX vers wp-ajax
    ↓
Backend :
    1. Vérification du nonce ✓
    2. Re-vérification des conditions ✓
    3. Mise à jour status = 'cancelled' ✓
    4. Enregistrement de cancelled_at ✓
    5. Suppression événement Google Calendar ✓
    6. Envoi email client ✓
    7. Envoi email admin ✓
    ↓
Affichage du message de succès
```

## 🎯 Prochaines étapes

1. **Terminer l'interface admin** (aujourd'hui)
   - Afficher les statuts
   - Filtrer par statut
   - Afficher les dates d'annulation

2. **Ajouter le champ dans l'édition des magasins** (aujourd'hui)
   - Champ "Délai d'annulation (heures)"
   - Validation (minimum 1h, maximum 168h/7 jours)

3. **Tests complets**
   - Tester tous les scénarios
   - Vérifier les emails
   - Vérifier Google Calendar

## 💡 Améliorations futures possibles

- [ ] Statistiques d'annulation dans le dashboard admin
- [ ] Raison d'annulation (optionnel) demandée au client
- [ ] Notification par SMS pour les annulations de dernière minute
- [ ] Blacklist automatique en cas d'annulations répétées
- [ ] Politique d'annulation personnalisable par service
- [ ] Frais d'annulation pour annulations tardives
- [ ] Remboursement automatique si paiement en ligne

---

**Note** : Le système est prêt à être testé ! Il ne manque plus que l'interface admin pour afficher les statuts et le champ de configuration dans les magasins.
