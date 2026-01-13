# 🚀 GUIDE D'INSTALLATION - Ikomiris Booking System

## 📋 Prérequis

- WordPress 5.8 ou supérieur
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Accès FTP ou cPanel

---

## 📦 Installation

### Méthode 1 : Upload via WordPress Admin (Recommandée)

1. **Zipper le dossier du plugin**
   ```bash
   cd /chemin/vers/ikomiris-booking-system
   zip -r ikomiris-booking-system.zip ikomiris-booking-system
   ```

2. **Dans WordPress Admin**
   - Allez dans **Extensions → Ajouter**
   - Cliquez sur **Téléverser une extension**
   - Sélectionnez le fichier `ikomiris-booking-system.zip`
   - Cliquez sur **Installer maintenant**
   - Activez le plugin

### Méthode 2 : Upload via FTP

1. **Téléchargez le dossier complet** `ikomiris-booking-system`

2. **Uploadez via FTP**
   - Connectez-vous à votre serveur FTP
   - Naviguez vers `/wp-content/plugins/`
   - Uploadez le dossier `ikomiris-booking-system`

3. **Activez le plugin**
   - Dans WordPress Admin : **Extensions → Extensions installées**
   - Trouvez "Ikomiris Booking System"
   - Cliquez sur **Activer**

---

## ✅ Vérification de l'installation

Après activation, vous devriez voir :

✅ Un nouveau menu **"Réservations"** dans la sidebar WordPress
✅ Une page **"Réservation"** créée automatiquement
✅ 7 nouvelles tables dans votre base de données (préfixe `wp_ibs_`)

---

## ⚙️ Configuration initiale

### 1. Créer votre premier magasin

1. Allez dans **Réservations → Magasins**
2. Cliquez sur **"Ajouter un magasin"**
3. Remplissez les informations :
   - Nom du magasin *
   - Adresse complète
   - Téléphone
   - Email
   - Description
   - **Image** (cliquez sur "Choisir une image")
   - Cochez "Magasin actif"
4. Cliquez sur **"Créer le magasin"**

### 2. Créer vos services

1. Allez dans **Réservations → Services**
2. Cliquez sur **"Ajouter un service"**
3. Remplissez les informations :
   - Nom du service *
   - Description
   - Durée (en minutes) * → Ex: 30, 60, 90
   - Prix (optionnel)
   - **Image du service** * → Très important pour l'affichage
   - Ordre d'affichage (0 = premier)
   - **Cochez le(s) magasin(s)** où ce service est disponible *
   - Cochez "Service actif"
4. Cliquez sur **"Créer le service"**

**Répétez** pour créer plusieurs services (recommandé : 3-5 services minimum)

### 3. Définir les horaires (À venir)

*Cette fonctionnalité sera disponible dans une prochaine version.*

Pour l'instant, les horaires par défaut sont configurés automatiquement.

### 4. Configurer les paramètres

1. Allez dans **Réservations → Paramètres**

2. **Paramètres de réservation**
   - Délai minimum : 2 heures (par défaut)
   - Délai maximum : 90 jours (par défaut)
   - Intervalle des créneaux : 10 minutes (recommandé)

3. **Apparence**
   - Choisissez votre couleur principale
   - Activez/désactivez l'affichage des prix

4. **Textes personnalisés**
   - Personnalisez le texte de confirmation
   - Ajoutez vos conditions générales

5. **Notifications Email**
   - Configurez les emails de confirmation
   - Activez les rappels automatiques
   - Définissez l'email admin

6. Cliquez sur **"Enregistrer les paramètres"**

---

## 📄 Intégrer le formulaire sur une page

### Option 1 : Page automatique

Une page "Réservation" a été créée automatiquement lors de l'activation.

**URL** : `https://ikomiris.com/reservation/`

### Option 2 : Shortcode personnalisé

Ajoutez le shortcode sur n'importe quelle page :

```
[ikomiris_booking]
```

**Exemple d'utilisation :**
1. Créez ou éditez une page
2. Ajoutez un bloc "Shortcode" (ou en mode texte)
3. Insérez `[ikomiris_booking]`
4. Publiez la page

---

## 🎨 Personnalisation avancée

### Modifier les couleurs via CSS

Ajoutez dans **Apparence → Personnaliser → CSS Additionnel** :

```css
:root {
    --ibs-primary: #0073aa;
    --ibs-primary-hover: #005177;
    --ibs-secondary: #f0f0f0;
}

/* Exemple : Modifier la couleur des boutons */
.ibs-submit-btn {
    background: #ff6b6b !important;
}

.ibs-submit-btn:hover {
    background: #ee5a52 !important;
}
```

### Cacher certains éléments

```css
/* Cacher les prix */
.ibs-service-price {
    display: none !important;
}

/* Cacher les images des magasins */
.ibs-store-card img {
    display: none !important;
}
```

---

## 🔧 Dépannage

### Le plugin ne s'affiche pas

**Vérifiez :**
1. Le plugin est bien activé
2. Le shortcode est correctement écrit : `[ikomiris_booking]`
3. Pas de conflits JavaScript (ouvrez la console du navigateur F12)

### Les créneaux ne s'affichent pas

**Vérifiez :**
1. Les horaires sont définis pour le magasin
2. Le service a une durée valide
3. La date sélectionnée est dans le futur

### Les emails ne sont pas envoyés

**Vérifiez :**
1. La configuration SMTP de WordPress
2. Les paramètres dans **Réservations → Paramètres → Notifications**
3. Le dossier spam du client

**Solution recommandée :** Installez un plugin SMTP comme "WP Mail SMTP"

### Problème d'upload d'images

**Vérifiez :**
1. Les permissions des dossiers uploads
2. La limite de taille d'upload PHP (php.ini)
3. Utilisez des images < 2MB

---

## 📊 Tables de la base de données

Le plugin crée ces tables (avec le préfixe WordPress, ex: `wp_`) :

```
wp_ibs_stores          → Magasins
wp_ibs_services        → Services
wp_ibs_store_services  → Liaison magasins-services
wp_ibs_schedules       → Horaires
wp_ibs_exceptions      → Dates exceptionnelles
wp_ibs_bookings        → Réservations
wp_ibs_settings        → Paramètres
```

---

## 🔒 Permissions requises

Le plugin nécessite :
- **Gestion des options** : Pour accéder aux paramètres
- **Upload de fichiers** : Pour les images
- **Accès base de données** : Pour stocker les données

Réservé aux **Administrateurs** uniquement.

---

## 🆘 Support

**Email :** support@ikomiris.com
**Documentation :** Voir README.md
**Données d'exemple :** Voir EXEMPLES-DONNEES.md

---

## ✅ Checklist de mise en production

Avant de mettre en ligne :

- [ ] Créer minimum 2 magasins
- [ ] Créer minimum 3 services avec images
- [ ] Tester une réservation complète
- [ ] Vérifier la réception des emails
- [ ] Tester sur mobile
- [ ] Personnaliser les couleurs
- [ ] Ajouter vos conditions générales
- [ ] Définir l'email admin
- [ ] Tester l'annulation de réservation
- [ ] Vérifier que les créneaux s'affichent correctement

---

🎉 **Votre système de réservation est prêt !**

Besoin d'aide ? Contactez support@ikomiris.com
