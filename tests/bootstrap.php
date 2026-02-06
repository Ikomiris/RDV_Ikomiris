<?php
/**
 * PHPUnit bootstrap file pour Ikomiris Booking System
 */

// Chemin vers le répertoire de tests WordPress
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Si le répertoire de tests WordPress n'existe pas, afficher un message d'aide
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Impossible de trouver le répertoire de tests WordPress.\n";
    echo "Veuillez définir la variable d'environnement WP_TESTS_DIR ou installer wordpress-tests-lib.\n";
    echo "\nPour installer :\n";
    echo "bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
    exit(1);
}

// Charger les fonctions de test WordPress
require_once $_tests_dir . '/includes/functions.php';

/**
 * Fonction pour charger le plugin avant les tests
 */
function _manually_load_plugin() {
    require dirname(__DIR__) . '/ikomiris-booking-system.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Démarrer l'environnement de test WordPress
require $_tests_dir . '/includes/bootstrap.php';
