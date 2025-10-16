<?php
session_start();
require_once 'config.php';

echo "<h2>🔧 Fix Maty's Epreuve Column - URGENT</h2>";

// Find Maty
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE prenom LIKE '%maty%' AND nom LIKE '%ndiaye%'");
$stmt->execute();
$maty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$maty) {
    echo "<p style='color: red;'>Maty non trouvée</p>";
    exit;
}

echo "<p><strong>Candidat:</strong> {$maty['prenom']} {$maty['nom']} (ID: {$maty['id']})</p>";

// Check current state
echo "<h3>1. État Actuel des Sessions</h3>";
$stmt = $pdo->prepare("SELECT id, phase, epreuve, status, score, started_at FROM qcm_sessions WHERE candidate_id = ? ORDER BY started_at DESC LIMIT 10");
$stmt->execute([$maty['id']]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Phase</th><th>Épreuve</th><th>Status</th><th>Score</th><th>Date</th></tr>";
foreach ($sessions as $session) {
    $epreuve_display = empty($session['epreuve']) ? '<span style="color: red;">VIDE</span>' : $session['epreuve'];
    echo "<tr>";
    echo "<td>{$session['id']}</td>";
    echo "<td>{$session['phase']}</td>";
    echo "<td>$epreuve_display</td>";
    echo "<td>{$session['status']}</td>";
    echo "<td>{$session['score']}</td>";
    echo "<td>{$session['started_at']}</td>";
    echo "</tr>";
}
echo "</table>";

// Fix: Update all Maty's sessions to THI (since she's category 1)
echo "<h3>2. Correction - Mise à Jour vers THI</h3>";

$update_sql = "UPDATE qcm_sessions SET epreuve = 'THI' WHERE candidate_id = ? AND phase = 'phase1' AND (epreuve = '' OR epreuve IS NULL)";
$update_stmt = $pdo->prepare($update_sql);
$result = $update_stmt->execute([$maty['id']]);

if ($result) {
    $affected_rows = $update_stmt->rowCount();
    echo "<p style='color: green;'>✅ Mise à jour réussie: $affected_rows sessions mises à jour</p>";
} else {
    echo "<p style='color: red;'>❌ Erreur lors de la mise à jour</p>";
}

// Verify fix
echo "<h3>3. Vérification Après Correction</h3>";
$stmt = $pdo->prepare("SELECT id, phase, epreuve, status, score, started_at FROM qcm_sessions WHERE candidate_id = ? ORDER BY started_at DESC LIMIT 10");
$stmt->execute([$maty['id']]);
$sessions_after = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Phase</th><th>Épreuve</th><th>Status</th><th>Score</th><th>Date</th></tr>";
foreach ($sessions_after as $session) {
    $color = ($session['epreuve'] === 'THI') ? 'background-color: #d4edda;' : 'background-color: #f8d7da;';
    echo "<tr style='$color'>";
    echo "<td>{$session['id']}</td>";
    echo "<td>{$session['phase']}</td>";
    echo "<td><strong>{$session['epreuve']}</strong></td>";
    echo "<td>{$session['status']}</td>";
    echo "<td>{$session['score']}</td>";
    echo "<td>{$session['started_at']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test dashboard logic now
echo "<h3>4. Test Logique Dashboard Après Correction</h3>";

// Same logic as dashboard
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC");
$stmt->execute([$maty['id']]);
$all_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$phase_results = array_values(array_filter($all_sessions, function($session) {
    return $session['status'] === 'completed';
}));

$thi_result = null;
foreach ($phase_results as $result) {
    if ($result['phase'] === 'phase1' && $result['epreuve'] === 'THI') {
        $thi_result = $result;
        break;
    }
}

if ($thi_result) {
    echo "<div style='background: #d4edda; padding: 15px; border: 2px solid #28a745; border-radius: 5px;'>";
    echo "<h4>✅ THI Result Trouvé!</h4>";
    echo "<p><strong>Score:</strong> {$thi_result['score']}%</p>";
    $status = $thi_result['score'] >= 80 ? 'RÉUSSI' : 'TERMINÉ';
    echo "<p><strong>Status:</strong> $status</p>";
    echo "<p>Le dashboard devrait maintenant afficher ce résultat</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border: 2px solid #dc3545; border-radius: 5px;'>";
    echo "<h4>❌ Toujours Pas de THI Result</h4>";
    echo "<p>Il y a encore un problème</p>";
    echo "</div>";
}

// Count attempts
$attempts_stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'");
$attempts_stmt->execute([$maty['id']]);
$thi_attempts = $attempts_stmt->fetch(PDO::FETCH_ASSOC)['attempts'];

echo "<p><strong>Tentatives THI après correction:</strong> $thi_attempts</p>";

if ($thi_attempts > 0) {
    echo "<div style='background: #d4edda; padding: 20px; border: 3px solid #28a745; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3>🎉 PROBLÈME RÉSOLU!</h3>";
    echo "<p><strong>Maty a maintenant $thi_attempts session(s) THI détectée(s)</strong></p>";
    echo "<p>Le dashboard devrait maintenant afficher son score au lieu de DISPONIBLE</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='candidate_dashboard.php'>🔗 Tester le dashboard maintenant</a></p>";
?>
