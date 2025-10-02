<?php
require_once 'config/database.php';

try {
    // Vérifier la structure de la table qcm_questions
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
    
    echo "\n=== Nombre de questions ===\n";
    $sql = "SELECT COUNT(*) as count FROM qcm_questions";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "Questions dans la base: $count\n";
    
    if ($count > 0) {
        echo "\n=== Exemple de questions ===\n";
        $sql = "SELECT id, question_text, question_type FROM qcm_questions LIMIT 3";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($questions as $q) {
            echo "ID: {$q['id']}, Type: " . ($q['question_type'] ?? 'NULL') . ", Texte: " . substr($q['question_text'], 0, 50) . "...\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
