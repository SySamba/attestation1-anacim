<?php
session_start();
require_once 'config.php';

echo "<h2>🔧 Correction Forcée des Autorisations</h2>";

// Force update all candidates with 80%+ to be authorized
$update_sql = "INSERT INTO test_authorizations (candidate_id, test_type, authorized, authorized_by, authorized_at) 
               SELECT c.id, 'PRATIQUE_IMAGERIE', 1, 1, NOW()
               FROM candidates c 
               LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
               WHERE c.status = 'accepted' AND qs.score >= 80
               ON DUPLICATE KEY UPDATE authorized = 1, authorized_by = 1, authorized_at = NOW()";

$stmt = $pdo->prepare($update_sql);
$stmt->execute();

echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>✅ CORRECTION TERMINÉE</h3>";
echo "<p><strong>Toutes les autorisations ont été mises à jour pour les candidats avec ≥80%</strong></p>";
echo "<p>BA Coutaille et tous les autres candidats éligibles sont maintenant autorisés.</p>";
echo "</div>";

// Verify the fix
$verify_sql = "SELECT c.nom, c.prenom, qs.score, ta.authorized 
               FROM candidates c 
               LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
               LEFT JOIN test_authorizations ta ON c.id = ta.candidate_id AND ta.test_type = 'PRATIQUE_IMAGERIE'
               WHERE c.status = 'accepted' AND qs.score >= 80
               ORDER BY c.nom";

$verify_stmt = $pdo->prepare($verify_sql);
$verify_stmt->execute();
$results = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>📊 Vérification des Autorisations</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
echo "<tr style='background: #f8f9fa;'><th>Candidat</th><th>Score</th><th>Statut</th></tr>";

foreach ($results as $result) {
    $status = $result['authorized'] ? '✅ AUTORISÉ' : '❌ NON AUTORISÉ';
    $color = $result['authorized'] ? '#d4edda' : '#f8d7da';
    
    echo "<tr style='background: $color;'>";
    echo "<td>" . htmlspecialchars($result['nom'] . ' ' . $result['prenom']) . "</td>";
    echo "<td>" . $result['score'] . "%</td>";
    echo "<td><strong>$status</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<div style='background: #cce5ff; padding: 15px; border: 1px solid #99ccff; border-radius: 5px; margin: 20px 0;'>";
echo "<h4>🔄 Prochaines Étapes</h4>";
echo "<ol>";
echo "<li>Retournez à <a href='admin_test_authorizations.php'>admin_test_authorizations.php</a></li>";
echo "<li>Vérifiez que BA Coutaille affiche maintenant <strong>'AUTORISÉ - Peut passer l'examen'</strong></li>";
echo "<li>Les candidats peuvent maintenant accéder à la Pratique Imagerie</li>";
echo "</ol>";
echo "</div>";
?>
