<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Structure de la table qcm_questions ===\n";
    $sql = "DESCRIBE qcm_questions";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($structure as $column) {
        echo $column['Field'] . " - " . $column['Type'] . "\n";
    }
    
    echo "\n=== Vérification des colonnes ===\n";
    $columns = array_column($structure, 'Field');
    echo "Colonnes trouvées: " . implode(', ', $columns) . "\n";
    
    if (in_array('category', $columns)) {
        echo "✓ Colonne 'category' existe\n";
    } else {
        echo "❌ Colonne 'category' manquante\n";
    }
    
    if (in_array('question_type', $columns)) {
        echo "✓ Colonne 'question_type' existe\n";
    } else {
        echo "❌ Colonne 'question_type' manquante\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
