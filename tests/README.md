# Tests PHPUnit - Ikomiris Booking System

Ce répertoire contient les tests automatisés pour le plugin Ikomiris Booking System.

## Installation de l'environnement de test

### Prérequis

- PHP 7.4 ou supérieur
- Composer
- MySQL ou MariaDB
- Git
- SVN (pour télécharger les tests WordPress)

### Étapes d'installation

1. **Installer les dépendances PHP via Composer**

```bash
composer install
```

2. **Installer l'environnement de test WordPress**

```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

Paramètres :
- `wordpress_test` : Nom de la base de données de test (sera créée)
- `root` : Utilisateur MySQL
- `''` : Mot de passe MySQL (vide dans l'exemple)
- `localhost` : Hôte MySQL
- `latest` : Version de WordPress (ou spécifiez une version comme `6.4`)

**Note Windows** : Utilisez Git Bash ou WSL pour exécuter ce script.

3. **Configurer la variable d'environnement (optionnel)**

Si le script ne trouve pas automatiquement le répertoire de tests :

```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
```

## Exécution des tests

### Lancer tous les tests

```bash
composer test
```

Ou directement avec PHPUnit :

```bash
./vendor/bin/phpunit
```

### Lancer un fichier de test spécifique

```bash
./vendor/bin/phpunit tests/test-rate-limiter.php
```

### Générer un rapport de couverture de code

```bash
composer test-coverage
```

Le rapport HTML sera généré dans `tests/coverage/index.html`.

## Structure des tests

```
tests/
├── bootstrap.php                   # Bootstrap PHPUnit
├── test-rate-limiter.php          # Tests du rate limiting
├── test-booking-validation.php    # Tests des validations de réservation
└── README.md                       # Ce fichier
```

## Tests disponibles

### test-rate-limiter.php

Tests de la classe `RateLimiter` :
- ✅ Première tentative autorisée
- ✅ Limite atteinte après max_attempts
- ✅ Réinitialisation des tentatives
- ✅ Blocage d'identifiant
- ✅ Obtention de l'IP client
- ✅ Génération d'empreinte client
- ✅ Messages de rate limit formatés
- ✅ Check rate limit pour réservations

### test-booking-validation.php

Tests des validations de réservation :
- ✅ Validation de l'email
- ✅ Validation du délai minimum de réservation
- ✅ Validation du délai maximum de réservation
- ✅ Création d'une réservation valide
- ✅ Génération de token d'annulation sécurisé
- ✅ Sanitisation des champs
- ✅ Vérification de l'existence du magasin et du service
- ✅ Validation du format de date

## Écrire de nouveaux tests

### Exemple de test simple

```php
<?php
class Test_My_Feature extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        // Initialisation avant chaque test
    }

    public function tearDown(): void {
        // Nettoyage après chaque test
        parent::tearDown();
    }

    public function test_my_function() {
        $result = my_function();
        $this->assertEquals('expected', $result);
    }
}
```

### Bonnes pratiques

1. **Isolation** : Chaque test doit être indépendant
2. **Cleanup** : Utilisez `tearDown()` pour nettoyer les données
3. **Nommage** : Préfixez les méthodes par `test_`
4. **Assertions** : Utilisez des assertions explicites
   - `assertEquals()` : Égalité stricte
   - `assertTrue()` / `assertFalse()` : Booléens
   - `assertNotNull()` : Vérifie qu'une valeur n'est pas null
   - `assertArrayHasKey()` : Vérifie une clé dans un tableau

## Déboguer les tests

### Activer le mode verbose

```bash
./vendor/bin/phpunit --verbose
```

### Voir les erreurs détaillées

```bash
./vendor/bin/phpunit --debug
```

### Arrêter à la première erreur

```bash
./vendor/bin/phpunit --stop-on-failure
```

## Intégration continue (CI)

Pour intégrer les tests dans GitHub Actions, GitLab CI ou autre :

```yaml
# Exemple GitHub Actions
- name: Install dependencies
  run: composer install

- name: Setup WordPress tests
  run: bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest

- name: Run tests
  run: composer test
```

## Dépannage

### Erreur "Cannot find WordPress tests"

Vérifiez que le script d'installation a bien été exécuté et que `WP_TESTS_DIR` pointe vers le bon répertoire.

### Erreur de connexion MySQL

Vérifiez les identifiants MySQL dans le fichier généré :
- `/tmp/wordpress-tests-lib/wp-tests-config.php`

### Tests lents

Les tests WordPress peuvent être lents. Pour accélérer :
1. Utilisez une base de données en mémoire (SQLite)
2. Limitez les tests exécutés avec `--filter`
3. Désactivez Xdebug si installé

## Ressources

- [Documentation PHPUnit](https://phpunit.de/documentation.html)
- [WordPress Plugin Unit Tests](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)
- [WP_UnitTestCase Reference](https://developer.wordpress.org/reference/classes/wp_unittestcase/)

## Support

Pour toute question sur les tests, consultez la documentation ou ouvrez une issue sur le dépôt GitHub.
