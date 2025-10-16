<?php
session_start();
require_once 'config.php';

echo "<h2>🔧 Debug Direct - Dashboard Maty</h2>";

// Find and set Maty's session
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE prenom LIKE '%maty%' AND nom LIKE '%ndiaye%'");
$stmt->execute();
$maty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$maty) {
    echo "<p style='color: red;'>Maty non trouvée</p>";
    exit;
}

$candidate_id = $maty['id'];
$_SESSION['candidate_id'] = $candidate_id;

echo "<p><strong>Debug pour:</strong> {$maty['prenom']} {$maty['nom']} (ID: {$candidate_id})</p>";

// EXACT same logic as candidate_dashboard.php
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC");
$stmt->execute([$candidate_id]);
$all_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>1. All Sessions Retrieved:</h3>";
echo "<p>Count: " . count($all_sessions) . "</p>";
foreach ($all_sessions as $session) {
    echo "<p>ID: {$session['id']}, Phase: {$session['phase']}, Epreuve: {$session['epreuve']}, Status: {$session['status']}, Score: {$session['score']}</p>";
}

// Filter completed sessions for phase results - Convert to indexed array
$phase_results = array_values(array_filter($all_sessions, function($session) {
    return $session['status'] === 'completed';
}));

echo "<h3>2. Phase Results Filtered:</h3>";
echo "<p>Count: " . count($phase_results) . "</p>";
foreach ($phase_results as $result) {
    echo "<p>Phase: {$result['phase']}, Epreuve: {$result['epreuve']}, Score: {$result['score']}</p>";
}

// THI logic
$thi_result = null;
foreach ($phase_results as $result) {
    if ($result['phase'] === 'phase1' && $result['epreuve'] === 'THI') {
        $thi_result = $result;
        break;
    }
}

echo "<h3>3. THI Result Search:</h3>";
if ($thi_result) {
    echo "<p style='color: green;'>✅ THI Result Found: Score = {$thi_result['score']}%</p>";
} else {
    echo "<p style='color: red;'>❌ THI Result NOT FOUND</p>";
}

$thi_status = $thi_result ? ($thi_result['score'] >= 80 ? 'passed' : 'failed') : 'pending';
$thi_score = $thi_result ? $thi_result['score'] : null;

// Count attempts for THI
$attempts_stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'");
$attempts_stmt->execute([$candidate_id]);
$thi_attempts = $attempts_stmt->fetch(PDO::FETCH_ASSOC)['attempts'];

echo "<h3>4. Attempts Count:</h3>";
echo "<p>THI Attempts: $thi_attempts</p>";

// If no thi_result but attempts exist, get the latest score
if (!$thi_result && $thi_attempts > 0) {
    $latest_stmt = $pdo->prepare("SELECT score FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed' ORDER BY completed_at DESC LIMIT 1");
    $latest_stmt->execute([$candidate_id]);
    $latest_session = $latest_stmt->fetch(PDO::FETCH_ASSOC);
    if ($latest_session) {
        $thi_score = $latest_session['score'];
        echo "<p style='color: orange;'>⚠️ Score retrieved from latest session: {$thi_score}%</p>";
    }
}

echo "<h3>5. Final Status Calculation:</h3>";
echo "<p><strong>THI Status:</strong> $thi_status</p>";
echo "<p><strong>THI Score:</strong> " . ($thi_score ? $thi_score . "%" : "null") . "</p>";

echo "<h3>6. Dashboard Display Logic:</h3>";
if ($thi_status === 'passed') {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid #c3e6cb;'>";
    echo "<h4>✅ RÉUSSI</h4>";
    echo "<p>Score: " . number_format($thi_score, 1) . "%</p>";
    echo "<p>Badge: ✅ RÉUSSI</p>";
    echo "<p>Button: Disabled (Complété)</p>";
    echo "</div>";
} elseif ($thi_status === 'failed') {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb;'>";
    echo "<h4>❌ TERMINÉ</h4>";
    echo "<p>Score: " . number_format($thi_score, 1) . "%</p>";
    echo "<p>Badge: ❌ TERMINÉ</p>";
    echo "<p>Button: Disabled (Complété)</p>";
    echo "</div>";
} else {
    if ($thi_attempts > 0) {
        echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7;'>";
        echo "<h4>⚠️ DÉJÀ PASSÉ</h4>";
        if ($thi_score !== null) {
            echo "<p>Score: " . number_format($thi_score, 1) . "%</p>";
        }
        echo "<p>Badge: ⚠️ DÉJÀ PASSÉ</p>";
        echo "<p>Button: Disabled (Complété)</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #cce5ff; padding: 10px; border: 1px solid #007bff;'>";
        echo "<h4>🎯 DISPONIBLE</h4>";
        echo "<p>Badge: 🎯 DISPONIBLE</p>";
        echo "<p>Button: Active (Commencer THI)</p>";
        echo "<p style='color: red;'><strong>C'EST CE QUI S'AFFICHE ACTUELLEMENT - PROBLÈME!</strong></p>";
        echo "</div>";
    }
}

// Solution
if ($thi_attempts > 0 && $thi_status === 'pending') {
    echo "<div style='background: #f8d7da; padding: 20px; border: 3px solid #dc3545; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3>🚨 PROBLÈME IDENTIFIÉ</h3>";
    echo "<p><strong>Maty a $thi_attempts tentative(s) THI mais thi_result est null</strong></p>";
    echo "<p>La logique de recherche dans phase_results ne trouve pas ses sessions THI</p>";
    echo "<p><strong>Solution:</strong> Vérifier pourquoi les sessions ne sont pas dans phase_results</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='candidate_dashboard.php'>🔗 Voir le dashboard réel</a></p>";
?>
