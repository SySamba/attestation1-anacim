<?php
require_once 'config.php';

echo "=== ANACIM - Correction colonne session_id ===\n";

try {
    // Vérifier la structure de candidate_phase_results
    $stmt = $pdo->query("DESCRIBE candidate_phase_results");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $session_id_exists = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'session_id') {
            $session_id_exists = true;
            break;
        }
    }
    
    if (!$session_id_exists) {
        echo "Ajout de la colonne session_id à candidate_phase_results...\n";
        $pdo->exec("ALTER TABLE candidate_phase_results ADD COLUMN session_id INT NULL AFTER status");
        $pdo->exec("ALTER TABLE candidate_phase_results ADD FOREIGN KEY (session_id) REFERENCES qcm_sessions(id) ON DELETE SET NULL");
        echo "✅ Colonne session_id ajoutée avec succès\n";
    } else {
        echo "✅ Colonne session_id existe déjà\n";
    }
    
    // Test de la requête qui pose problème
    echo "\nTest de la requête INSERT...\n";
    $test_sql = "INSERT INTO candidate_phase_results (candidate_id, phase, epreuve, score, status, session_id, completed_at) 
                 VALUES (1, 'phase1', 'THB', 85.0, 'passed', 1, NOW()) 
                 ON DUPLICATE KEY UPDATE score = 85.0, status = 'passed', session_id = 1, completed_at = NOW()";
    
    try {
        $pdo->exec("DELETE FROM candidate_phase_results WHERE candidate_id = 1 AND phase = 'phase1' AND epreuve = 'THB'");
        $pdo->exec($test_sql);
        echo "✅ Test INSERT réussi\n";
        $pdo->exec("DELETE FROM candidate_phase_results WHERE candidate_id = 1 AND phase = 'phase1' AND epreuve = 'THB'");
    } catch (PDOException $e) {
        echo "❌ Erreur test INSERT: " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ Correction terminée\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
