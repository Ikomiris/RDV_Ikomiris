<?php
/**
 * Plugin Name: Ikomiris Booking System
 * Plugin URI: https://ikomiris.com
 * Description: Système de réservation de rendez-vous multi-magasins avec gestion des services et intégration Google Agenda
 * Version: 1.0.0
 * Author: Ikomiris
 * Author URI: https://ikomiris.com
 * Text Domain: ikomiris-booking
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Définir les constantes du plugin
define('IBS_VERSION', '1.0.0');
define('IBS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IBS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IBS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'IBS\\';
    $base_dir = IBS_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Classe principale du plugin
final class Ikomiris_Booking_System {
    
    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function activate() {
        require_once IBS_PLUGIN_DIR . 'includes/Installer.php';
        IBS\Installer::activate();
    }

    public function deactivate() {
        require_once IBS_PLUGIN_DIR . 'includes/Installer.php';
        IBS\Installer::deactivate();
    }

    public function init() {
        // Charger les traductions
        load_plugin_textdomain('ikomiris-booking', false, dirname(IBS_PLUGIN_BASENAME) . '/languages');

        // Initialiser les composants
        new IBS\Admin\AdminMenu();
        new IBS\Admin\Assets();
        new IBS\Frontend\Shortcode();
        new IBS\Frontend\Assets();
        new IBS\API\BookingAPI();
    }
}

// Initialiser le plugin
function ikomiris_booking_system() {
    return Ikomiris_Booking_System::instance();
}

ikomiris_booking_system();
