<?php
session_start();
require_once 'config.php';

echo "<h2>🔧 Correction Directe des Autorisations</h2>";

// Get all candidates with 80%+ scores
$candidates_sql = "SELECT c.id, c.nom, c.prenom, qs.score
                   FROM candidates c 
                   LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
                   WHERE c.status = 'accepted' AND qs.score >= 80
                   ORDER BY c.nom";

$stmt = $pdo->prepare($candidates_sql);
$stmt->execute();
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>📋 Candidats à Autoriser (" . count($candidates) . ")</h3>";

$success_count = 0;
foreach ($candidates as $candidate) {
    // Use INSERT ... ON DUPLICATE KEY UPDATE for each candidate individually
    $auth_sql = "INSERT INTO test_authorizations (candidate_id, test_type, authorized, authorized_by, authorized_at) 
                 VALUES (?, 'PRATIQUE_IMAGERIE', 1, 1, NOW())
                 ON DUPLICATE KEY UPDATE authorized = 1, authorized_by = 1, authorized_at = NOW()";
    
    $auth_stmt = $pdo->prepare($auth_sql);
    $auth_stmt->execute([$candidate['id']]);
    
    echo "<p>✅ " . $candidate['nom'] . " " . $candidate['prenom'] . " (" . $candidate['score'] . "%) - Autorisé</p>";
    $success_count++;
}

echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>✅ AUTORISATION TERMINÉE</h3>";
echo "<p><strong>$success_count candidats autorisés avec succès</strong></p>";
echo "</div>";

// Verify the results
echo "<h3>🔍 Vérification Finale</h3>";
$verify_sql = "SELECT c.nom, c.prenom, qs.score, ta.authorized, ta.authorized_at
               FROM candidates c 
               LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
               LEFT JOIN test_authorizations ta ON c.id = ta.candidate_id AND ta.test_type = 'PRATIQUE_IMAGERIE'
               WHERE c.status = 'accepted' AND qs.score >= 80
               ORDER BY c.nom";

$verify_stmt = $pdo->prepare($verify_sql);
$verify_stmt->execute();
$results = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
echo "<tr style='background: #f8f9fa;'><th>Candidat</th><th>Score</th><th>Autorisé</th><th>Date</th></tr>";

foreach ($results as $result) {
    $status = $result['authorized'] ? '✅ OUI' : '❌ NON';
    $color = $result['authorized'] ? '#d4edda' : '#f8d7da';
    
    echo "<tr style='background: $color;'>";
    echo "<td>" . $result['nom'] . " " . $result['prenom'] . "</td>";
    echo "<td>" . $result['score'] . "%</td>";
    echo "<td><strong>$status</strong></td>";
    echo "<td>" . ($result['authorized_at'] ?: 'Aucune') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<div style='background: #cce5ff; padding: 15px; border: 1px solid #99ccff; border-radius: 5px; margin: 20px 0;'>";
echo "<h4>🔄 Prochaines Étapes</h4>";
echo "<ol>";
echo "<li>Allez sur <a href='admin_test_authorizations.php'>admin_test_authorizations.php</a></li>";
echo "<li>Vérifiez que TOUS les candidats ≥80% affichent maintenant <strong>'AUTORISÉ - Peut passer l'examen'</strong></li>";
echo "<li>Les candidats peuvent maintenant accéder à candidate_imagerie.php</li>";
echo "</ol>";
echo "</div>";
?>
