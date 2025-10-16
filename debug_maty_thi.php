<?php
session_start();
require_once 'config.php';

echo "<h2>Debug Maty Ndiaye THI Status</h2>";

// Find Maty Ndiaye
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE prenom LIKE '%maty%' AND nom LIKE '%ndiaye%'");
$stmt->execute();
$maty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$maty) {
    echo "<p style='color: red;'>Candidat Maty Ndiaye non trouvé</p>";
    exit;
}

echo "<h3>Informations Candidat:</h3>";
echo "<p>ID: {$maty['id']}</p>";
echo "<p>Nom: {$maty['nom']}</p>";
echo "<p>Prénom: {$maty['prenom']}</p>";
echo "<p>Catégorie: {$maty['categorie']}</p>";
echo "<p>Status: {$maty['status']}</p>";

// Check all THI sessions for Maty
echo "<h3>Sessions THI de Maty:</h3>";
$stmt = $pdo->prepare("
    SELECT * FROM qcm_sessions 
    WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' 
    ORDER BY started_at DESC
");
$stmt->execute([$maty['id']]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sessions)) {
    echo "<p style='color: blue;'>Aucune session THI trouvée</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Status</th><th>Score</th><th>Commencé</th><th>Terminé</th><th>Questions</th><th>Réponses Correctes</th></tr>";
    
    foreach ($sessions as $session) {
        $color = '';
        if ($session['status'] === 'completed') $color = 'background-color: #d4edda;';
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

// Test the exact logic from candidate_dashboard.php
echo "<h3>Test Logique Dashboard:</h3>";

// Get candidate's QCM results (same as dashboard)
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND status = 'completed' ORDER BY id DESC");
$stmt->execute([$maty['id']]);
$phase_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Phase results trouvés: " . count($phase_results) . "</p>";

// Same logic as dashboard
$thi_result = null;
foreach ($phase_results as $result) {
    if ($result['phase'] === 'phase1' && $result['epreuve'] === 'THI') {
        $thi_result = $result;
        break;
    }
}

echo "<h4>THI Result:</h4>";
if ($thi_result) {
    echo "<pre>" . print_r($thi_result, true) . "</pre>";
    $thi_status = $thi_result['score'] >= 80 ? 'passed' : 'failed';
    echo "<p>THI Status calculé: <strong>$thi_status</strong></p>";
} else {
    echo "<p style='color: orange;'>Aucun résultat THI trouvé dans phase_results</p>";
}

// Count attempts for THI (same as dashboard)
$attempts_stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'");
$attempts_stmt->execute([$maty['id']]);
$thi_attempts = $attempts_stmt->fetch(PDO::FETCH_ASSOC)['attempts'];

echo "<p>Tentatives THI: <strong>$thi_attempts</strong></p>";

// Dashboard logic simulation
echo "<h3>Simulation Logique Dashboard:</h3>";
$thi_status = $thi_result ? ($thi_result['score'] >= 80 ? 'passed' : 'failed') : 'pending';

echo "<p>Status final: <strong>$thi_status</strong></p>";

if ($thi_status === 'passed') {
    echo "<p style='color: green;'>✅ RÉUSSI - Bouton désactivé</p>";
} elseif ($thi_status === 'failed') {
    echo "<p style='color: red;'>❌ TERMINÉ - Bouton désactivé</p>";
} else {
    if ($thi_attempts > 0) {
        echo "<p style='color: orange;'>⚠️ DÉJÀ PASSÉ - Bouton désactivé</p>";
    } else {
        echo "<p style='color: blue;'>🎯 DISPONIBLE - Bouton actif (PROBLÈME!)</p>";
    }
}

// Test the restriction logic from candidate_qcm_thi.php
echo "<h3>Test Restriction candidate_qcm_thi.php:</h3>";
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'completed' ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$maty['id'], 'phase1', 'THI']);
$completed_session = $stmt->fetch(PDO::FETCH_ASSOC);

if ($completed_session) {
    echo "<p style='color: green;'>✅ Restriction devrait fonctionner - Session complétée détectée</p>";
    echo "<p>Session ID: {$completed_session['id']}</p>";
    echo "<p>Score: {$completed_session['score']}%</p>";
    echo "<p>Terminé: {$completed_session['completed_at']}</p>";
} else {
    echo "<p style='color: red;'>❌ PROBLÈME - Aucune session complétée détectée par la restriction</p>";
}

?>
