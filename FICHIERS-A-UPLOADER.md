# Fichiers à uploader sur Hostinger - Phase 1

## ✅ NOUVEAUX DOSSIERS à créer sur le serveur

### includes/Email/ (NOUVEAU)
- ✅ EmailHandler.php

### includes/Security/ (NOUVEAU)
- ✅ RateLimiter.php

## ✅ FICHIERS MODIFIÉS à remplacer

### includes/API/
- ✅ BookingAPI.php (MODIFIÉ - emails, validations, rate-limiting)

### includes/Frontend/
- ✅ Assets.php (MODIFIÉ - ajout settings JavaScript)

### frontend/views/
- ✅ booking-form.php (MODIFIÉ - date picker avec min/max)

## ✅ DOCUMENTATION (optionnel mais recommandé)
- CORRECTIONS-PHASE-1.md

## ⚠️ DOSSIER À SUPPRIMER sur le serveur
- includes/GoogleCalendar/ (ancienne classe déplacée en local vers backup)

## ❌ NE PAS UPLOADER
- /vendor/
- /tests/
- /node_modules/
- /.git/
- /.vscode/
- /backup-2026-01-14/
- composer.json
- composer.lock
- phpunit.xml
- .gitignore
- bin/

---

## 🎯 ORDRE D'UPLOAD RECOMMANDÉ

1. **Créer les nouveaux dossiers** (Email, Security)
2. **Uploader les nouveaux fichiers** dans ces dossiers
3. **Remplacer les fichiers modifiés** (BookingAPI.php, etc.)
4. **Supprimer includes/GoogleCalendar/** sur le serveur
5. **Tester le site**

---

## ✅ CHECKLIST POST-DÉPLOIEMENT

- [ ] Les emails de confirmation sont envoyés
- [ ] Le rate-limiting fonctionne (tester 6 réservations rapides)
- [ ] Les dates invalides sont bloquées
- [ ] Aucune erreur dans les logs WordPress
- [ ] Le site fonctionne normalement
