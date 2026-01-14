<?php
/**
 * Script de diagnostic pour les paramètres du plugin
 * À exécuter directement dans le navigateur : http://votresite.com/wp-content/plugins/ikomiris-booking-system/diagnostic-settings.php
 */

// Charger WordPress
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Accès refusé');
}

global $wpdb;
$table = $wpdb->prefix . 'ibs_settings';

echo '<h1>🔍 Diagnostic des paramètres du plugin</h1>';
echo '<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>';

// 1. Vérifier que la table existe
echo '<h2>1. Vérification de la table</h2>';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
if ($table_exists) {
    echo '<p class="success">✓ La table ' . $table . ' existe</p>';
} else {
    echo '<p class="error">✗ La table ' . $table . ' n\'existe pas !</p>';
    echo '<p>Solution : Désactivez puis réactivez le plugin pour créer les tables.</p>';
    exit;
}

// 2. Vérifier la structure de la table
echo '<h2>2. Structure de la table</h2>';
$columns = $wpdb->get_results("DESCRIBE $table");
echo '<table>';
echo '<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr>';
foreach ($columns as $column) {
    echo '<tr>';
    echo '<td>' . esc_html($column->Field) . '</td>';
    echo '<td>' . esc_html($column->Type) . '</td>';
    echo '<td>' . esc_html($column->Null) . '</td>';
    echo '<td>' . esc_html($column->Key) . '</td>';
    echo '<td>' . esc_html($column->Default) . '</td>';
    echo '</tr>';
}
echo '</table>';

// 3. Compter les paramètres
echo '<h2>3. Nombre de paramètres enregistrés</h2>';
$count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
echo '<p>Nombre total de paramètres : <strong>' . $count . '</strong></p>';

// 4. Lister tous les paramètres
echo '<h2>4. Liste des paramètres actuels</h2>';
$settings = $wpdb->get_results("SELECT * FROM $table ORDER BY setting_key");
if (!empty($settings)) {
    echo '<table>';
    echo '<tr><th>ID</th><th>Clé</th><th>Valeur</th></tr>';
    foreach ($settings as $setting) {
        echo '<tr>';
        echo '<td>' . esc_html($setting->id) . '</td>';
        echo '<td>' . esc_html($setting->setting_key) . '</td>';
        echo '<td>' . esc_html(substr($setting->setting_value, 0, 100)) . (strlen($setting->setting_value) > 100 ? '...' : '') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p class="warning">⚠ Aucun paramètre trouvé dans la table</p>';
    echo '<p>Essayez de sauvegarder les paramètres depuis la page d\'administration.</p>';
}

// 5. Tester une insertion
echo '<h2>5. Test d\'insertion/mise à jour</h2>';
$test_key = 'test_diagnostic_' . time();
$test_value = 'Valeur de test ' . date('Y-m-d H:i:s');

$insert_result = $wpdb->insert(
    $table,
    [
        'setting_key' => $test_key,
        'setting_value' => $test_value
    ],
    ['%s', '%s']
);

if ($insert_result === false) {
    echo '<p class="error">✗ Erreur lors de l\'insertion : ' . esc_html($wpdb->last_error) . '</p>';
} else {
    echo '<p class="success">✓ Insertion réussie (ID: ' . $wpdb->insert_id . ')</p>';
    
    // Test de mise à jour
    $update_result = $wpdb->update(
        $table,
        ['setting_value' => $test_value . ' (modifié)'],
        ['setting_key' => $test_key],
        ['%s'],
        ['%s']
    );
    
    if ($update_result === false) {
        echo '<p class="error">✗ Erreur lors de la mise à jour : ' . esc_html($wpdb->last_error) . '</p>';
    } else {
        echo '<p class="success">✓ Mise à jour réussie (lignes affectées: ' . $update_result . ')</p>';
    }
    
    // Nettoyer
    $wpdb->delete($table, ['setting_key' => $test_key], ['%s']);
    echo '<p>→ Paramètre de test supprimé</p>';
}

// 6. Vérifier les permissions de la base de données
echo '<h2>6. Informations de la base de données</h2>';
echo '<pre>';
echo 'Préfixe des tables : ' . $wpdb->prefix . "\n";
echo 'Nom de la base : ' . DB_NAME . "\n";
echo 'Utilisateur : ' . DB_USER . "\n";
echo 'Charset : ' . $wpdb->charset . "\n";
echo 'Collate : ' . $wpdb->collate . "\n";
echo '</pre>';

// 7. Afficher les dernières erreurs WordPress
echo '<h2>7. Informations additionnelles</h2>';
if ($wpdb->last_error) {
    echo '<p class="error">Dernière erreur MySQL : ' . esc_html($wpdb->last_error) . '</p>';
} else {
    echo '<p class="success">Aucune erreur MySQL détectée</p>';
}

echo '<hr>';
echo '<p><a href="' . admin_url('admin.php?page=ikomiris-booking-settings') . '">← Retour aux paramètres</a></p>';

