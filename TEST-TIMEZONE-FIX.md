# Test de la correction du problème de timezone

## 🔧 Ce qui a été corrigé

Le système envoie maintenant les événements à Google Calendar en **UTC** (temps universel) avec le format RFC3339 standard, ce qui élimine toute ambiguïté sur le fuseau horaire.

### Changements apportés :

1. **includes/API/BookingAPI.php** (lignes 446-458)
   - Les dates locales sont converties en UTC avant l'envoi
   - Format utilisé : `2026-02-04T09:30:00Z` (avec Z pour UTC)
   - Ajout de logs pour tracer la conversion

2. **includes/Integrations/GoogleCalendar.php** (lignes 579-594)
   - Suppression du champ `timeZone` qui créait de la confusion
   - Google Calendar interprète maintenant directement l'UTC

## 📝 Exemple de conversion

Si vous créez une réservation pour :
- **Date locale** : 2026-02-04 à 10:30 (heure de Paris)
- **Timezone WordPress** : Europe/Paris (UTC+1 en hiver)
- **Envoyé à Google** : 2026-02-04T09:30:00Z (UTC)
- **Affiché dans Google Calendar** : 10:30 (dans le timezone de votre calendrier)

## 🧪 Comment tester

### Étape 1 : Vérifier le timezone WordPress

1. Allez dans **WordPress Admin → Réglages → Général**
2. Cherchez la section **Fuseau horaire**
3. **IMPORTANT** : Sélectionnez une ville, PAS un offset manuel
   - ✅ Correct : "Paris" ou "Europe/Paris"
   - ❌ Incorrect : "UTC+1" ou "UTC+2"

### Étape 2 : Créer une réservation de test

1. Allez sur le formulaire de réservation
2. Créez une réservation pour aujourd'hui ou demain
3. Choisissez un créneau précis (ex: 14:00)
4. Validez la réservation

### Étape 3 : Vérifier dans Google Calendar

1. Ouvrez votre Google Calendar lié au magasin
2. Trouvez l'événement qui vient d'être créé
3. **Vérifiez l'heure** : elle doit correspondre exactement à l'heure de réservation
   - Si vous avez réservé à 14:00, l'événement doit être à 14:00
   - Il ne doit plus y avoir de décalage d'1 heure

### Étape 4 : Consulter les logs (optionnel)

Si le problème persiste, consultez les logs WordPress :

```bash
# Emplacement typique des logs
wp-content/debug.log
# ou
wp-content/ibs-booking-debug.log
```

Cherchez les lignes qui commencent par :
- `IBS: Réservation locale`
- `IBS: DateTime local`
- `IBS: DateTime UTC`
- `IBS Google Calendar: Création événement`

**Exemple de logs corrects :**
```
[2026-02-04 13:25:10] IBS: Réservation locale - Date: 2026-02-04 14:00:00, Timezone: Europe/Paris
[2026-02-04 13:25:10] IBS: DateTime local - Start: 2026-02-04 14:00:00 CET (+01:00)
[2026-02-04 13:25:10] IBS: DateTime UTC - Start: 2026-02-04 13:00:00 UTC (+00:00)
[2026-02-04 13:25:10] IBS Google Calendar: Création événement - Start: 2026-02-04T13:00:00Z, End: 2026-02-04T13:30:00Z
```

Dans cet exemple :
- Réservation à 14:00 heure locale (Paris)
- Convertie en 13:00 UTC
- Google Calendar affichera 14:00 dans votre calendrier Paris

## ⚠️ Points de vigilance

### 1. Timezone WordPress non configuré
Si votre timezone WordPress est vide ou sur UTC alors que vous êtes en France :
- Les réservations seront créées en UTC
- Solution : Configurez le timezone dans Réglages → Général

### 2. Offset manuel au lieu d'un timezone nommé
Si vous utilisez "UTC+1" au lieu de "Europe/Paris" :
- Cela peut causer des problèmes avec l'heure d'été/hiver
- Solution : Utilisez toujours un timezone nommé (ville)

### 3. Anciennes réservations
Les réservations créées AVANT cette correction peuvent toujours avoir le décalage.
Les nouvelles réservations devraient être correctes.

## 🔍 Diagnostics supplémentaires

### Vérifier le timezone WordPress en PHP

Créez un fichier `test-tz.php` à la racine WordPress :

```php
<?php
require_once 'wp-load.php';

echo "Timezone WordPress : " . wp_timezone_string() . "\n";
echo "Option timezone_string : " . get_option('timezone_string') . "\n";
echo "GMT Offset : " . get_option('gmt_offset') . "\n";

$now = new DateTime('now', wp_timezone());
echo "Heure actuelle locale : " . $now->format('Y-m-d H:i:s T (P)') . "\n";

$now->setTimezone(new DateTimeZone('UTC'));
echo "Heure actuelle UTC : " . $now->format('Y-m-d H:i:s T (P)') . "\n";
```

Exécutez-le via CLI :
```bash
php test-tz.php
```

### Tester la conversion manuellement

```php
<?php
require_once 'wp-load.php';

$date = '2026-02-04';
$time = '14:00:00';

$tz = wp_timezone();
$dt = new DateTime($date . ' ' . $time, $tz);
echo "Local : " . $dt->format('Y-m-d H:i:s T') . "\n";

$dt->setTimezone(new DateTimeZone('UTC'));
echo "UTC : " . $dt->format('Y-m-d\TH:i:s\Z') . "\n";
```

## ✅ Checklist de validation

- [ ] Le timezone WordPress est configuré sur une ville (Europe/Paris)
- [ ] Une nouvelle réservation a été créée
- [ ] L'événement apparaît dans Google Calendar à l'heure correcte
- [ ] Il n'y a plus de décalage d'1 heure
- [ ] Les logs confirment la conversion UTC

## 📞 Si le problème persiste

Si après ces vérifications, le problème persiste :

1. Vérifiez les logs WordPress (debug.log)
2. Vérifiez le timezone de votre calendrier Google
   - Allez dans Google Calendar → Paramètres → Fuseau horaire
   - Assurez-vous qu'il est sur le bon fuseau
3. Partagez les logs pour analyse approfondie

## 🗑️ Nettoyage après tests

N'oubliez pas de supprimer les fichiers de test :
- `check-timezone.php`
- `test-tz.php` (si créé)
- `TEST-TIMEZONE-FIX.md` (ce fichier)
