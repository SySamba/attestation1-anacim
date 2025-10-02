<?php
require_once 'config/database.php';

try {
    // Modifier la structure de la table qcm_answers pour supporter les réponses multiples
    $sql = "ALTER TABLE qcm_answers MODIFY COLUMN selected_answer VARCHAR(20) NOT NULL";
    $pdo->exec($sql);
    
    echo "Table qcm_answers modifiée avec succès pour supporter les réponses multiples.\n";
    echo "Le champ selected_answer peut maintenant stocker des valeurs comme 'a,c' ou 'b,d'.\n";
    
} catch (PDOException $e) {
    echo "Erreur lors de la modification de la table: " . $e->getMessage() . "\n";
}
?>
