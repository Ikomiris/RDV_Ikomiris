<?php
/**
 * Vérification de wp-config.php
 * Placez ce fichier à la racine de votre installation WordPress
 * Accédez-y via : https://votre-site.com/check-wp-config.php
 * SUPPRIMEZ-LE APRÈS UTILISATION pour des raisons de sécurité !
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vérification wp-config.php</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
        .masked { color: #999; }
    </style>
</head>
<body>
    <h1>Vérification de wp-config.php</h1>
    
    <?php
    $wp_config_path = __DIR__ . '/wp-config.php';
    
    if (!file_exists($wp_config_path)) {
        die('<p class="error">✗ Fichier wp-config.php non trouvé</p>');
    }
    
    echo '<h2>1. Lecture du fichier wp-config.php</h2>';
    $content = file_get_contents($wp_config_path);
    $lines = explode("\n", $content);
    
    echo '<p>Nombre de lignes : ' . count($lines) . '</p>';
    
    // Chercher les définitions de constantes
    echo '<h2>2. Recherche des constantes de base de données</h2>';
    $found_constants = [];
    foreach ($lines as $num => $line) {
        $line_num = $num + 1;
        if (preg_match('/define\s*\(\s*[\'"]DB_(NAME|USER|PASSWORD|HOST|CHARSET|COLLATE)[\'"]/i', $line)) {
            // Masquer les valeurs sensibles
            $masked_line = preg_replace(
                '/define\s*\(\s*[\'"](DB_\w+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i',
                'define(\'$1\', \'***MASKED***\')',
                $line
            );
            $found_constants[] = [
                'line' => $line_num,
                'original' => trim($line),
                'masked' => trim($masked_line)
            ];
        }
    }
    
    if (empty($found_constants)) {
        echo '<p class="error">✗ Aucune constante DB_* trouvée dans wp-config.php</p>';
        echo '<p class="warning">⚠ Le fichier wp-config.php ne contient pas les définitions de base de données standard.</p>';
    } else {
        echo '<p class="success">✓ Constantes trouvées :</p>';
        echo '<ul>';
        foreach ($found_constants as $const) {
            echo '<li>Ligne ' . $const['line'] . ' : <code>' . htmlspecialchars($const['masked'], ENT_QUOTES, 'UTF-8') . '</code></li>';
        }
        echo '</ul>';
    }
    
    // Vérifier s'il y a des includes ou requires
    echo '<h2>3. Recherche d\'includes/requires</h2>';
    $includes = [];
    foreach ($lines as $num => $line) {
        $line_num = $num + 1;
        if (preg_match('/(require|include|require_once|include_once)\s*\([^)]+\)/i', $line, $matches)) {
            $includes[] = [
                'line' => $line_num,
                'code' => trim($line)
            ];
        }
    }
    
    if (!empty($includes)) {
        echo '<p class="warning">⚠ Fichiers inclus/requis trouvés :</p>';
        echo '<ul>';
        foreach ($includes as $inc) {
            echo '<li>Ligne ' . $inc['line'] . ' : <code>' . htmlspecialchars($inc['code'], ENT_QUOTES, 'UTF-8') . '</code></li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="success">✓ Aucun include/require trouvé</p>';
    }
    
    // Vérifier la structure générale
    echo '<h2>4. Structure du fichier (premières 100 lignes)</h2>';
    $first_100 = array_slice($lines, 0, 100);
    $display_content = implode("\n", $first_100);
    
    // Masquer les mots de passe et valeurs sensibles
    $display_content = preg_replace(
        '/define\s*\(\s*[\'"](DB_\w+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i',
        'define(\'$1\', \'***MASKED***\')',
        $display_content
    );
    $display_content = preg_replace(
        '/define\s*\(\s*[\'"]AUTH_KEY[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i',
        'define(\'AUTH_KEY\', \'***MASKED***\')',
        $display_content
    );
    $display_content = preg_replace(
        '/define\s*\(\s*[\'"]SECURE_AUTH_KEY[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i',
        'define(\'SECURE_AUTH_KEY\', \'***MASKED***\')',
        $display_content
    );
    
    echo '<pre>' . htmlspecialchars($display_content, ENT_QUOTES, 'UTF-8') . '</pre>';
    
    // Test de chargement
    echo '<h2>5. Test de chargement de wp-config.php</h2>';
    try {
        // Sauvegarder l'état actuel
        $before_db_name = defined('DB_NAME');
        $before_db_user = defined('DB_USER');
        $before_db_host = defined('DB_HOST');
        
        // Charger wp-config.php
        require_once($wp_config_path);
        
        // Vérifier après chargement
        $after_db_name = defined('DB_NAME');
        $after_db_user = defined('DB_USER');
        $after_db_host = defined('DB_HOST');
        
        echo '<p>Avant chargement :</p>';
        echo '<ul>';
        echo '<li>DB_NAME défini : ' . ($before_db_name ? '<span class="success">Oui</span>' : '<span class="error">Non</span>') . '</li>';
        echo '<li>DB_USER défini : ' . ($before_db_user ? '<span class="success">Oui</span>' : '<span class="error">Non</span>') . '</li>';
        echo '<li>DB_HOST défini : ' . ($before_db_host ? '<span class="success">Oui</span>' : '<span class="error">Non</span>') . '</li>';
        echo '</ul>';
        
        echo '<p>Après chargement :</p>';
        echo '<ul>';
        echo '<li>DB_NAME défini : ' . ($after_db_name ? '<span class="success">Oui</span>' : '<span class="error">Non</span>') . '</li>';
        echo '<li>DB_USER défini : ' . ($after_db_user ? '<span class="success">Oui</span>' : '<span class="error">Non</span>') . '</li>';
        echo '<li>DB_HOST défini : ' . ($after_db_host ? '<span class="success">Oui</span>' : '<span class="error">Non</span>') . '</li>';
        echo '</ul>';
        
        if ($after_db_name && $after_db_user && $after_db_host) {
            echo '<p class="success">✓ Les constantes sont maintenant définies après le chargement</p>';
            echo '<p>DB_NAME = ' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p>DB_USER = ' . htmlspecialchars(DB_USER, ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p>DB_HOST = ' . htmlspecialchars(DB_HOST, ENT_QUOTES, 'UTF-8') . '</p>';
        } else {
            echo '<p class="error">✗ Les constantes ne sont toujours pas définies après le chargement</p>';
            echo '<p class="warning">⚠ Cela peut indiquer :</p>';
            echo '<ul>';
            echo '<li>Les définitions sont dans un fichier inclus</li>';
            echo '<li>Il y a une erreur qui empêche l\'exécution des définitions</li>';
            echo '<li>Le fichier wp-config.php est structuré différemment</li>';
            echo '</ul>';
        }
        
    } catch (Throwable $e) {
        echo '<p class="error">✗ Erreur lors du chargement :</p>';
        echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n\n" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    ?>
    
    <hr>
    <p><strong>⚠ IMPORTANT : Supprimez ce fichier après utilisation pour des raisons de sécurité !</strong></p>
</body>
</html>

