<?php
session_start();
require_once 'config.php';

echo "<h2>🔍 Debug Candidat Actuel - THI Multiple Attempts</h2>";

// Check current session
$candidate_id = $_SESSION['candidate_id'] ?? null;
if (!$candidate_id) {
    echo "<p style='color: red;'>❌ Aucun candidat en session</p>";
    echo "<h3>Candidats C1 disponibles pour test:</h3>";
    $stmt = $pdo->prepare("SELECT id, prenom, nom FROM candidates WHERE categorie = '1' AND status = 'accepted'");
    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($candidates as $cand) {
        echo "<p><a href='?simulate={$cand['id']}'>{$cand['prenom']} {$cand['nom']} (ID: {$cand['id']})</a></p>";
    }
    exit;
}

// Simulate candidate if requested
if (isset($_GET['simulate'])) {
    $candidate_id = $_GET['simulate'];
    $_SESSION['candidate_id'] = $candidate_id;
    echo "<p style='color: blue;'>🔄 Session simulée pour candidat ID: $candidate_id</p>";
}

echo "<h3>Candidat en Session: ID $candidate_id</h3>";

// Get candidate info
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidate) {
    echo "<p style='color: red;'>❌ Candidat non trouvé</p>";
    exit;
}

echo "<p><strong>Nom:</strong> {$candidate['prenom']} {$candidate['nom']}</p>";
echo "<p><strong>Catégorie:</strong> {$candidate['categorie']}</p>";
echo "<p><strong>Status:</strong> {$candidate['status']}</p>";

// Check all THI sessions
echo "<h3>📊 Toutes les Sessions THI</h3>";
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' ORDER BY started_at DESC");
$stmt->execute([$candidate_id]);
$all_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($all_sessions)) {
    echo "<p style='color: green;'>✅ Aucune session THI - Accès normal autorisé</p>";
} else {
    echo "<p><strong>Nombre de sessions THI:</strong> " . count($all_sessions) . "</p>";
    
    if (count($all_sessions) > 1) {
        echo "<p style='color: red; font-size: 18px;'>🚨 PROBLÈME: Plus d'une session THI détectée!</p>";
    }
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Status</th><th>Score</th><th>Commencé</th><th>Terminé</th><th>Questions</th><th>Réponses Correctes</th></tr>";
    
    foreach ($all_sessions as $session) {
        $color = '';
        if ($session['status'] === 'completed') $color = 'background-color: #f8d7da;';
        if ($session['status'] === 'in_progress') $color = 'background-color: #fff3cd;';
        
        echo "<tr style='$color'>";
        echo "<td>{$session['id']}</td>";
        echo "<td><strong>{$session['status']}</strong></td>";
        echo "<td>{$session['score']}</td>";
        echo "<td>{$session['started_at']}</td>";
        echo "<td>{$session['completed_at']}</td>";
        echo "<td>{$session['total_questions']}</td>";
        echo "<td>{$session['correct_answers']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test exact logic from candidate_qcm_thi.php
echo "<h3>🧪 Test Logique candidate_qcm_thi.php</h3>";

$phase = 'phase1';
$epreuve = 'THI';

// Same query as candidate_qcm_thi.php
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'completed' ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$candidate_id, $phase, $epreuve]);
$completed_session = $stmt->fetch(PDO::FETCH_ASSOC);

if ($completed_session) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 2px solid #dc3545; border-radius: 5px;'>";
    echo "<h4>❌ ACCÈS DEVRAIT ÊTRE BLOQUÉ</h4>";
    echo "<p><strong>Session complétée détectée:</strong></p>";
    echo "<ul>";
    echo "<li>Session ID: {$completed_session['id']}</li>";
    echo "<li>Score: {$completed_session['score']}%</li>";
    echo "<li>Terminé: {$completed_session['completed_at']}</li>";
    echo "</ul>";
    echo "<p><strong>Message d'erreur:</strong> \"Vous avez déjà passé le test THI. Une seule tentative est autorisée par épreuve.\"</p>";
    echo "<p><strong>Redirection:</strong> candidate_dashboard.php</p>";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; padding: 15px; border: 2px solid #28a745; border-radius: 5px;'>";
    echo "<h4>✅ ACCÈS AUTORISÉ</h4>";
    echo "<p>Aucune session complétée détectée - Le candidat peut commencer le test</p>";
    echo "</div>";
}

// Test dashboard logic
echo "<h3>🎯 Test Logique Dashboard</h3>";

// Same logic as candidate_dashboard.php
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC");
$stmt->execute([$candidate_id]);
$all_sessions_dash = $stmt->fetchAll(PDO::FETCH_ASSOC);

$phase_results = array_filter($all_sessions_dash, function($session) {
    return $session['status'] === 'completed';
});

$thi_result = null;
foreach ($phase_results as $result) {
    if ($result['phase'] === 'phase1' && $result['epreuve'] === 'THI') {
        $thi_result = $result;
        break;
    }
}

$thi_status = $thi_result ? ($thi_result['score'] >= 80 ? 'passed' : 'failed') : 'pending';
$thi_score = $thi_result ? $thi_result['score'] : null;

// Count attempts
$attempts_stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'");
$attempts_stmt->execute([$candidate_id]);
$thi_attempts = $attempts_stmt->fetch(PDO::FETCH_ASSOC)['attempts'];

// If no thi_result but attempts exist, get the latest score
if (!$thi_result && $thi_attempts > 0) {
    $latest_stmt = $pdo->prepare("SELECT score FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed' ORDER BY completed_at DESC LIMIT 1");
    $latest_stmt->execute([$candidate_id]);
    $latest_session = $latest_stmt->fetch(PDO::FETCH_ASSOC);
    if ($latest_session) {
        $thi_score = $latest_session['score'];
    }
}

echo "<div style='background: #e7f3ff; padding: 15px; border: 2px solid #007bff; border-radius: 5px;'>";
echo "<h4>📊 Status Dashboard</h4>";
echo "<ul>";
echo "<li><strong>THI Status:</strong> $thi_status</li>";
echo "<li><strong>THI Score:</strong> " . ($thi_score ? $thi_score . "%" : "null") . "</li>";
echo "<li><strong>Tentatives:</strong> $thi_attempts</li>";
echo "</ul>";

if ($thi_status === 'passed') {
    echo "<p style='color: green;'><strong>✅ RÉUSSI</strong> - Bouton désactivé</p>";
} elseif ($thi_status === 'failed') {
    echo "<p style='color: red;'><strong>❌ TERMINÉ</strong> - Bouton désactivé</p>";
} else {
    if ($thi_attempts > 0) {
        echo "<p style='color: orange;'><strong>⚠️ DÉJÀ PASSÉ</strong> - Bouton désactivé</p>";
    } else {
        echo "<p style='color: blue;'><strong>🎯 DISPONIBLE</strong> - Bouton actif (PROBLÈME!)</p>";
    }
}
echo "</div>";

// Final diagnosis
echo "<h3>🏥 DIAGNOSTIC FINAL</h3>";

$has_completed = count($all_sessions) > 0 && in_array('completed', array_column($all_sessions, 'status'));
$dashboard_should_block = ($thi_status !== 'pending') || ($thi_attempts > 0);
$file_should_block = $completed_session !== false;

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Vérification</th><th>Résultat</th><th>Status</th></tr>";
echo "<tr>";
echo "<td>Sessions THI complétées</td>";
echo "<td>" . ($has_completed ? "OUI" : "NON") . "</td>";
echo "<td style='background: " . ($has_completed ? "#f8d7da" : "#d4edda") . ";'>" . ($has_completed ? "❌ Devrait bloquer" : "✅ OK") . "</td>";
echo "</tr>";
echo "<tr>";
echo "<td>Dashboard devrait bloquer</td>";
echo "<td>" . ($dashboard_should_block ? "OUI" : "NON") . "</td>";
echo "<td style='background: " . ($dashboard_should_block ? "#d4edda" : "#f8d7da") . ";'>" . ($dashboard_should_block ? "✅ Correct" : "❌ Problème") . "</td>";
echo "</tr>";
echo "<tr>";
echo "<td>candidate_qcm_thi.php devrait bloquer</td>";
echo "<td>" . ($file_should_block ? "OUI" : "NON") . "</td>";
echo "<td style='background: " . ($file_should_block ? "#d4edda" : "#f8d7da") . ";'>" . ($file_should_block ? "✅ Correct" : "❌ Problème") . "</td>";
echo "</tr>";
echo "</table>";

if ($has_completed && !$file_should_block) {
    echo "<div style='background: #f8d7da; padding: 20px; border: 3px solid #dc3545; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3>🚨 PROBLÈME DÉTECTÉ</h3>";
    echo "<p><strong>Le candidat a des sessions THI complétées mais candidate_qcm_thi.php ne les détecte pas!</strong></p>";
    echo "<p>Vérifiez que le fichier candidate_qcm_thi.php utilise bien la même logique de requête.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='candidate_qcm_thi.php'>🔗 Tester candidate_qcm_thi.php</a></p>";
echo "<p><a href='candidate_dashboard.php'>🔗 Voir dashboard</a></p>";
echo "<p><a href='?'>🔄 Changer de candidat</a></p>";
?>
