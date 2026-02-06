# Fix : Messages d'erreur WordPress 6.9.0

## Problème
Message "Deprecated" affiché sur le frontend concernant `WP_Dependencies->add_data()`.

## Cause
WordPress 6.9.0 a déprécié les commentaires conditionnels IE (Internet Explorer).
Ce message provient de WordPress Core ou d'un plugin/thème tiers, pas du plugin Ikomiris.

## Solution 1 : Masquer les warnings (Production)

Dans votre fichier `wp-config.php`, AVANT la ligne `/* That's all, stop editing! */` :

```php
// Désactiver l'affichage des erreurs en production
define('WP_DEBUG', false);
define('WP_DEBUG_DISPLAY', false);
@ini_set('display_errors', 0);
```

## Solution 2 : Logger les erreurs sans les afficher (Recommandé pour développement)

```php
// Mode debug activé mais n'affiche pas les erreurs
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false); // Ne pas afficher sur le site
define('WP_DEBUG_LOG', true);      // Logger dans wp-content/debug.log
@ini_set('display_errors', 0);
```

## Solution 3 : Identifier la source exacte

Pour savoir d'où vient l'erreur, activez temporairement :

```php
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);
define('WP_DEBUG_LOG', true);
```

Puis consultez le fichier `wp-content/debug.log` pour voir le stack trace complet.

## Note importante

Cette erreur est un **WARNING**, pas une erreur fatale. Elle n'empêche pas le site de fonctionner.
C'est simplement WordPress qui signale l'utilisation d'une fonction obsolète.

## Configuration recommandée pour production

```php
// === Configuration pour PRODUCTION ===
define('WP_DEBUG', false);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', false);
define('SCRIPT_DEBUG', false);
@ini_set('display_errors', 0);

// === Configuration pour DÉVELOPPEMENT ===
// define('WP_DEBUG', true);
// define('WP_DEBUG_DISPLAY', false);
// define('WP_DEBUG_LOG', true);
// define('SCRIPT_DEBUG', true);
```
