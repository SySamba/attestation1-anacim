<?php
session_start();
require_once 'config.php';

echo "<h2>🔍 Vérification Sessions Maty Ndiaye</h2>";

// Find Maty
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE prenom LIKE '%maty%' AND nom LIKE '%ndiaye%'");
$stmt->execute();
$maty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$maty) {
    echo "<p style='color: red;'>Maty Ndiaye non trouvée</p>";
    exit;
}

echo "<p><strong>Candidat:</strong> {$maty['prenom']} {$maty['nom']} (ID: {$maty['id']})</p>";

// Check all sessions
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY started_at DESC");
$stmt->execute([$maty['id']]);
$all_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Toutes les Sessions de Maty:</h3>";
if (empty($all_sessions)) {
    echo "<p style='color: red;'>❌ AUCUNE SESSION TROUVÉE</p>";
    echo "<p>C'est pourquoi le dashboard affiche DISPONIBLE</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Phase</th><th>Épreuve</th><th>Status</th><th>Score</th><th>Commencé</th><th>Terminé</th></tr>";
    
    $completed_thi = 0;
    foreach ($all_sessions as $session) {
        $color = '';
        if ($session['status'] === 'completed') $color = 'background-color: #d4edda;';
        if ($session['status'] === 'in_progress') $color = 'background-color: #fff3cd;';
        
        if ($session['phase'] === 'phase1' && $session['epreuve'] === 'THI' && $session['status'] === 'completed') {
            $completed_thi++;
            $color = 'background-color: #f8d7da;'; // Rouge pour THI complété
        }
        
        echo "<tr style='$color'>";
        echo "<td>{$session['id']}</td>";
        echo "<td>{$session['phase']}</td>";
        echo "<td>{$session['epreuve']}</td>";
        echo "<td><strong>{$session['status']}</strong></td>";
        echo "<td>{$session['score']}</td>";
        echo "<td>{$session['started_at']}</td>";
        echo "<td>{$session['completed_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Sessions THI complétées:</strong> $completed_thi</p>";
    
    if ($completed_thi > 0) {
        echo "<div style='background: #f8d7da; padding: 15px; border: 2px solid #dc3545; border-radius: 5px;'>";
        echo "<h3>🚨 PROBLÈME CONFIRMÉ</h3>";
        echo "<p>Maty a $completed_thi session(s) THI complétée(s) mais le dashboard affiche DISPONIBLE</p>";
        echo "<p>Le dashboard devrait afficher son score et le statut RÉUSSI/TERMINÉ</p>";
        echo "</div>";
    }
}

// Test exact dashboard logic
echo "<h3>🧪 Test Logique Dashboard Actuelle</h3>";

// Simulate Maty's session
$_SESSION['candidate_id'] = $maty['id'];

// Exact same logic as candidate_dashboard.php
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC");
$stmt->execute([$maty['id']]);
$all_sessions_dash = $stmt->fetchAll(PDO::FETCH_ASSOC);

$phase_results = array_filter($all_sessions_dash, function($session) {
    return $session['status'] === 'completed';
});

echo "<p>Sessions récupérées: " . count($all_sessions_dash) . "</p>";
echo "<p>Phase results filtrés: " . count($phase_results) . "</p>";

$thi_result = null;
foreach ($phase_results as $result) {
    if ($result['phase'] === 'phase1' && $result['epreuve'] === 'THI') {
        $thi_result = $result;
        break;
    }
}

if ($thi_result) {
    echo "<div style='background: #d4edda; padding: 15px; border: 2px solid #28a745; border-radius: 5px;'>";
    echo "<h4>✅ THI Result Trouvé</h4>";
    echo "<p>Score: {$thi_result['score']}%</p>";
    echo "<p>Status calculé: " . ($thi_result['score'] >= 80 ? 'RÉUSSI' : 'TERMINÉ') . "</p>";
    echo "<p>Le dashboard DEVRAIT afficher ce résultat</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border: 2px solid #dc3545; border-radius: 5px;'>";
    echo "<h4>❌ THI Result NON TROUVÉ</h4>";
    echo "<p>C'est le problème! La logique ne trouve pas le résultat THI</p>";
    echo "</div>";
    
    // Debug phase_results
    echo "<h4>Debug phase_results:</h4>";
    if (empty($phase_results)) {
        echo "<p style='color: red;'>Aucun phase_results - problème de filtrage</p>";
    } else {
        foreach ($phase_results as $result) {
            echo "<p>Phase: '{$result['phase']}', Epreuve: '{$result['epreuve']}', Score: {$result['score']}%</p>";
        }
    }
}

// Count attempts
$attempts_stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'");
$attempts_stmt->execute([$maty['id']]);
$thi_attempts = $attempts_stmt->fetch(PDO::FETCH_ASSOC)['attempts'];

echo "<p><strong>Tentatives THI comptées:</strong> $thi_attempts</p>";

// Final dashboard status
$thi_status = $thi_result ? ($thi_result['score'] >= 80 ? 'passed' : 'failed') : 'pending';
$thi_score = $thi_result ? $thi_result['score'] : null;

if (!$thi_result && $thi_attempts > 0) {
    $latest_stmt = $pdo->prepare("SELECT score FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed' ORDER BY completed_at DESC LIMIT 1");
    $latest_stmt->execute([$maty['id']]);
    $latest_session = $latest_stmt->fetch(PDO::FETCH_ASSOC);
    if ($latest_session) {
        $thi_score = $latest_session['score'];
    }
}

echo "<h3>📊 Status Final Dashboard</h3>";
echo "<p><strong>THI Status:</strong> $thi_status</p>";
echo "<p><strong>THI Score:</strong> " . ($thi_score ? $thi_score . "%" : "null") . "</p>";
echo "<p><strong>Tentatives:</strong> $thi_attempts</p>";

if ($thi_status === 'passed') {
    echo "<p style='color: green; font-size: 18px;'><strong>✅ RÉUSSI</strong> - Devrait afficher le score</p>";
} elseif ($thi_status === 'failed') {
    echo "<p style='color: red; font-size: 18px;'><strong>❌ TERMINÉ</strong> - Devrait afficher le score</p>";
} else {
    if ($thi_attempts > 0) {
        echo "<p style='color: orange; font-size: 18px;'><strong>⚠️ DÉJÀ PASSÉ</strong> - Devrait afficher le score</p>";
    } else {
        echo "<p style='color: blue; font-size: 18px;'><strong>🎯 DISPONIBLE</strong> - C'est ce qui s'affiche actuellement</p>";
    }
}

echo "<hr>";
echo "<p><a href='candidate_dashboard.php'>🔗 Voir le dashboard réel</a></p>";
?>
