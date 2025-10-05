<?php
session_start();
require_once 'config.php';

echo "<h2>🔄 Force Refresh Autorisations</h2>";

// First, let's see the current state
echo "<h3>📊 État Actuel</h3>";
$check_sql = "SELECT c.nom, c.prenom, qs.score, ta.authorized, ta.authorized_at
              FROM candidates c 
              LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
              LEFT JOIN test_authorizations ta ON c.id = ta.candidate_id AND ta.test_type = 'PRATIQUE_IMAGERIE'
              WHERE c.status = 'accepted' AND qs.score >= 80
              ORDER BY c.nom";

$check_stmt = $pdo->prepare($check_sql);
$check_stmt->execute();
$before_results = $check_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Candidat</th><th>Score</th><th>Autorisé AVANT</th></tr>";
foreach ($before_results as $result) {
    echo "<tr>";
    echo "<td>" . $result['nom'] . " " . $result['prenom'] . "</td>";
    echo "<td>" . $result['score'] . "%</td>";
    echo "<td>" . ($result['authorized'] ? '✅ OUI' : '❌ NON') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Force complete refresh
echo "<h3>🔧 Correction Forcée</h3>";

// Delete all existing PRATIQUE_IMAGERIE authorizations
$delete_sql = "DELETE FROM test_authorizations WHERE test_type = 'PRATIQUE_IMAGERIE'";
$pdo->exec($delete_sql);
echo "<p>✅ Suppression des anciennes autorisations</p>";

// Re-create all authorizations for eligible candidates
$create_sql = "INSERT IGNORE INTO test_authorizations (candidate_id, test_type, authorized, authorized_by, authorized_at) 
               SELECT c.id, 'PRATIQUE_IMAGERIE', 1, 1, NOW()
               FROM candidates c 
               LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
               WHERE c.status = 'accepted' AND qs.score >= 80";

$create_stmt = $pdo->prepare($create_sql);
$create_stmt->execute();
echo "<p>✅ Recréation des autorisations pour candidats ≥80%</p>";

// Check results after
echo "<h3>📊 État Après Correction</h3>";
$after_stmt = $pdo->prepare($check_sql);
$after_stmt->execute();
$after_results = $after_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Candidat</th><th>Score</th><th>Autorisé APRÈS</th><th>Date</th></tr>";
foreach ($after_results as $result) {
    echo "<tr style='background: " . ($result['authorized'] ? '#d4edda' : '#f8d7da') . ";'>";
    echo "<td>" . $result['nom'] . " " . $result['prenom'] . "</td>";
    echo "<td>" . $result['score'] . "%</td>";
    echo "<td>" . ($result['authorized'] ? '✅ OUI' : '❌ NON') . "</td>";
    echo "<td>" . $result['authorized_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>✅ CORRECTION TERMINÉE</h3>";
echo "<p><strong>Toutes les autorisations ont été supprimées et recréées</strong></p>";
echo "<p>Maintenant:</p>";
echo "<ol>";
echo "<li>Allez sur <a href='admin_test_authorizations.php'>admin_test_authorizations.php</a></li>";
echo "<li>BA Coutaille devrait maintenant afficher 'AUTORISÉ - Peut passer l'examen'</li>";
echo "<li>Tous les candidats ≥80% sont autorisés</li>";
echo "</ol>";
echo "</div>";
?>
