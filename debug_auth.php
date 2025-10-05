<?php
session_start();
require_once 'config.php';

// Debug script to check authorization status
echo "<h3>Debug Autorisation Pratique Imagerie</h3>";

// Check all candidates with 80%+ scores
$sql = "SELECT c.id, c.nom, c.prenom, c.categorie, 
        qs.score as phase1_score,
        ta.authorized as imagerie_authorized,
        ta.authorized_at
        FROM candidates c 
        LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
        LEFT JOIN test_authorizations ta ON c.id = ta.candidate_id AND ta.test_type = 'PRATIQUE_IMAGERIE'
        WHERE c.status = 'accepted' AND qs.score >= 80
        ORDER BY c.nom";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($results) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Nom</th><th>Score</th><th>Autorisé</th><th>Date Auth</th><th>Action</th></tr>";
    
    $corrections = 0;
    foreach ($results as $result) {
        echo "<tr>";
        echo "<td>" . $result['nom'] . " " . $result['prenom'] . "</td>";
        echo "<td>" . $result['phase1_score'] . "%</td>";
        echo "<td>" . ($result['imagerie_authorized'] ? 'OUI' : 'NON') . "</td>";
        echo "<td>" . ($result['authorized_at'] ?: 'Aucune') . "</td>";
        
        // Force update if needed
        if ($result['phase1_score'] >= 80 && !$result['imagerie_authorized']) {
            $update_sql = "INSERT INTO test_authorizations (candidate_id, test_type, authorized, authorized_by, authorized_at) 
                           VALUES (?, 'PRATIQUE_IMAGERIE', 1, 1, NOW()) 
                           ON DUPLICATE KEY UPDATE authorized = 1, authorized_by = 1, authorized_at = NOW()";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$result['id']]);
            
            echo "<td style='color: green;'><strong>CORRIGÉ</strong></td>";
            $corrections++;
        } else {
            echo "<td>OK</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    if ($corrections > 0) {
        echo "<br><div style='background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
        echo "<strong>✅ $corrections autorisation(s) corrigée(s)</strong><br>";
        echo "Rechargez admin_test_authorizations.php pour voir les changements.";
        echo "</div>";
    }
} else {
    echo "Aucun candidat avec ≥80% trouvé";
}
?>
