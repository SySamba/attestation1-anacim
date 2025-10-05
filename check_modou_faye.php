<?php
session_start();
require_once 'config.php';

echo "<h2>🔍 Diagnostic Modou Faye</h2>";

// Check Modou Faye specifically
$sql = "SELECT c.id, c.nom, c.prenom, c.categorie, c.email,
        qs.score as phase1_score, qs.epreuve as phase1_test, qs.status as phase1_status,
        ta.authorized as imagerie_authorized, ta.authorized_at
        FROM candidates c 
        LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
        LEFT JOIN test_authorizations ta ON c.id = ta.candidate_id AND ta.test_type = 'PRATIQUE_IMAGERIE'
        WHERE c.prenom LIKE '%modou%' AND c.nom LIKE '%faye%'";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>📋 Informations Candidat</h3>";
    echo "<p><strong>Nom:</strong> " . $result['nom'] . " " . $result['prenom'] . "</p>";
    echo "<p><strong>Catégorie:</strong> " . $result['categorie'] . "</p>";
    echo "<p><strong>Email:</strong> " . $result['email'] . "</p>";
    echo "<p><strong>Score Phase 1:</strong> " . ($result['phase1_score'] ?: 'Aucun') . "%</p>";
    echo "<p><strong>Test Phase 1:</strong> " . ($result['phase1_test'] ?: 'Aucun') . "</p>";
    echo "<p><strong>Statut Phase 1:</strong> " . ($result['phase1_status'] ?: 'Non passé') . "</p>";
    echo "<p><strong>Autorisation Imagerie:</strong> " . ($result['imagerie_authorized'] ? 'OUI' : 'NON') . "</p>";
    echo "</div>";
    
    // Check if he has passed THB
    if (!$result['phase1_score'] || $result['phase1_score'] < 80) {
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>⚠️ PROBLÈME IDENTIFIÉ</h4>";
        echo "<p><strong>Modou Faye n'a pas encore passé ou réussi le test THB avec 80%+</strong></p>";
        echo "<p>Il doit d'abord:</p>";
        echo "<ol>";
        echo "<li>Passer le test THB (Théorie de Base)</li>";
        echo "<li>Obtenir un score ≥ 80%</li>";
        echo "<li>Ensuite il sera automatiquement autorisé pour la Pratique Imagerie</li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>✅ ÉLIGIBLE - Correction en cours...</h4>";
        
        // Force authorization
        $update_sql = "INSERT INTO test_authorizations (candidate_id, test_type, authorized, authorized_by, authorized_at) 
                       VALUES (?, 'PRATIQUE_IMAGERIE', 1, 1, NOW()) 
                       ON DUPLICATE KEY UPDATE authorized = 1, authorized_by = 1, authorized_at = NOW()";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$result['id']]);
        
        echo "<p>Autorisation forcée pour Modou Faye</p>";
        echo "</div>";
    }
} else {
    echo "<p style='color: red;'>Candidat Modou Faye non trouvé</p>";
}

echo "<hr>";
echo "<h3>🔄 Actions Recommandées</h3>";
echo "<div style='background: #cce5ff; padding: 15px; border: 1px solid #99ccff; border-radius: 5px;'>";
echo "<ol>";
echo "<li><strong>Pour Modou Faye:</strong> Il doit d'abord passer et réussir le test THB</li>";
echo "<li><strong>Pour BA Coutaille:</strong> Exécutez <a href='force_refresh_auth.php'>force_refresh_auth.php</a></li>";
echo "<li><strong>Vérification:</strong> Rechargez les pages admin après corrections</li>";
echo "</ol>";
echo "</div>";
?>
