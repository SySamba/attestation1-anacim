<?php
session_start();
require_once 'config.php';

echo "<h2>🔧 Quick Fix - Maty Ndiaye THI Issue</h2>";

// Find Maty specifically
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE prenom LIKE '%maty%' AND nom LIKE '%ndiaye%'");
$stmt->execute();
$maty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$maty) {
    echo "<p style='color: red;'>Maty Ndiaye non trouvée</p>";
    exit;
}

echo "<p><strong>Candidat trouvé:</strong> {$maty['prenom']} {$maty['nom']} (ID: {$maty['id']})</p>";

// Check ALL her THI sessions
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' ORDER BY started_at DESC");
$stmt->execute([$maty['id']]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Sessions THI de Maty:</h3>";
echo "<p><strong>Nombre total:</strong> " . count($sessions) . "</p>";

if (empty($sessions)) {
    echo "<p style='color: blue;'>Aucune session THI - Normal que ce soit DISPONIBLE</p>";
    exit;
}

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Status</th><th>Score</th><th>Commencé</th><th>Terminé</th></tr>";

$completed_count = 0;
foreach ($sessions as $session) {
    if ($session['status'] === 'completed') $completed_count++;
    
    $color = '';
    if ($session['status'] === 'completed') $color = 'background-color: #f8d7da;';
    if ($session['status'] === 'in_progress') $color = 'background-color: #fff3cd;';
    
    echo "<tr style='$color'>";
    echo "<td>{$session['id']}</td>";
    echo "<td><strong>{$session['status']}</strong></td>";
    echo "<td>{$session['score']}</td>";
    echo "<td>{$session['started_at']}</td>";
    echo "<td>{$session['completed_at']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><strong>Sessions complétées:</strong> $completed_count</p>";

if ($completed_count > 0) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 2px solid #dc3545; border-radius: 5px;'>";
    echo "<h3>🚨 PROBLÈME CONFIRMÉ</h3>";
    echo "<p>Maty a $completed_count session(s) THI complétée(s) mais voit encore 'DISPONIBLE'</p>";
    echo "</div>";
    
    // Test the exact dashboard logic
    echo "<h3>🧪 Test Logique Dashboard</h3>";
    
    // Simulate session for Maty
    $_SESSION['candidate_id'] = $maty['id'];
    
    // Same logic as candidate_dashboard.php
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC");
    $stmt->execute([$maty['id']]);
    $all_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $phase_results = array_filter($all_sessions, function($session) {
        return $session['status'] === 'completed';
    });
    
    echo "<p>Phase results trouvés: " . count($phase_results) . "</p>";
    
    $thi_result = null;
    foreach ($phase_results as $result) {
        if ($result['phase'] === 'phase1' && $result['epreuve'] === 'THI') {
            $thi_result = $result;
            break;
        }
    }
    
    if ($thi_result) {
        echo "<p style='color: green;'>✅ THI Result trouvé: Score = {$thi_result['score']}%</p>";
        $thi_status = $thi_result['score'] >= 80 ? 'passed' : 'failed';
        echo "<p>Status calculé: <strong>$thi_status</strong></p>";
    } else {
        echo "<p style='color: red;'>❌ THI Result NOT FOUND dans phase_results</p>";
        echo "<p>C'est ça le problème! La logique ne trouve pas le résultat THI.</p>";
        
        // Debug phase_results
        echo "<h4>Debug phase_results:</h4>";
        foreach ($phase_results as $result) {
            echo "<p>Phase: '{$result['phase']}', Epreuve: '{$result['epreuve']}', Score: {$result['score']}%</p>";
        }
    }
    
    // Count attempts
    $attempts_stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'");
    $attempts_stmt->execute([$maty['id']]);
    $thi_attempts = $attempts_stmt->fetch(PDO::FETCH_ASSOC)['attempts'];
    
    echo "<p>Tentatives comptées: <strong>$thi_attempts</strong></p>";
    
    // Final status calculation
    $thi_status = $thi_result ? ($thi_result['score'] >= 80 ? 'passed' : 'failed') : 'pending';
    $thi_score = $thi_result ? $thi_result['score'] : null;
    
    // If no thi_result but attempts exist, get the latest score
    if (!$thi_result && $thi_attempts > 0) {
        $latest_stmt = $pdo->prepare("SELECT score FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed' ORDER BY completed_at DESC LIMIT 1");
        $latest_stmt->execute([$maty['id']]);
        $latest_session = $latest_stmt->fetch(PDO::FETCH_ASSOC);
        if ($latest_session) {
            $thi_score = $latest_session['score'];
            echo "<p style='color: orange;'>⚠️ Score récupéré depuis latest_session: {$thi_score}%</p>";
        }
    }
    
    echo "<h4>Status Final Dashboard:</h4>";
    if ($thi_status === 'passed') {
        echo "<p style='color: green;'><strong>✅ RÉUSSI</strong> - Bouton devrait être désactivé</p>";
    } elseif ($thi_status === 'failed') {
        echo "<p style='color: red;'><strong>❌ TERMINÉ</strong> - Bouton devrait être désactivé</p>";
    } else {
        if ($thi_attempts > 0) {
            echo "<p style='color: orange;'><strong>⚠️ DÉJÀ PASSÉ</strong> - Bouton devrait être désactivé</p>";
        } else {
            echo "<p style='color: blue;'><strong>🎯 DISPONIBLE</strong> - Bouton actif (C'EST LE PROBLÈME!)</p>";
        }
    }
    
} else {
    echo "<div style='background: #d4edda; padding: 15px; border: 2px solid #28a745; border-radius: 5px;'>";
    echo "<h3>✅ NORMAL</h3>";
    echo "<p>Maty n'a aucune session complétée - Normal que ce soit DISPONIBLE</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='candidate_dashboard.php'>🔗 Voir le dashboard de Maty</a></p>";
echo "<p><a href='debug_current_candidate.php?simulate={$maty['id']}'>🔗 Debug complet</a></p>";
?>
