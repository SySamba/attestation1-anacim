<?php
session_start();
require_once 'config.php';

echo "<h1>🔍 DEBUG CANDIDAT ACTUEL</h1>";

if (!isset($_SESSION['candidate_logged_in']) || !isset($_SESSION['candidate_id'])) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>❌ Aucun candidat connecté</h3>";
    echo "<p>Session candidate_logged_in: " . (isset($_SESSION['candidate_logged_in']) ? 'SET' : 'NOT SET') . "</p>";
    echo "<p>Session candidate_id: " . (isset($_SESSION['candidate_id']) ? $_SESSION['candidate_id'] : 'NOT SET') . "</p>";
    echo "</div>";
    
    // Force set a test candidate for debugging
    echo "<h2>🔧 Test avec candidat forcé</h2>";
    echo "<form method='POST'>";
    echo "<input type='number' name='force_candidate_id' placeholder='ID candidat' value='5'>";
    echo "<button type='submit'>Forcer Debug</button>";
    echo "</form>";
    
    if ($_POST && isset($_POST['force_candidate_id'])) {
        $_SESSION['candidate_logged_in'] = true;
        $_SESSION['candidate_id'] = $_POST['force_candidate_id'];
        echo "<p style='color: green;'>✅ Session forcée pour candidat ID: {$_POST['force_candidate_id']}</p>";
    } else {
        exit();
    }
}

$candidate_id = $_SESSION['candidate_id'];

// 1. Info candidat
echo "<h2>1️⃣ Candidat ID: $candidate_id</h2>";
$candidate_stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$candidate_stmt->execute([$candidate_id]);
$candidate = $candidate_stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidate) {
    echo "<p style='color: red;'>❌ Candidat introuvable</p>";
    exit();
}

echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>Nom</th><td>{$candidate['nom']} {$candidate['prenom']}</td></tr>";
echo "<tr><th>Catégorie</th><td>{$candidate['categorie']}</td></tr>";
echo "<tr><th>Status</th><td>{$candidate['status']}</td></tr>";
echo "</table>";

// 2. Sessions QCM
echo "<h2>2️⃣ Sessions QCM</h2>";
$sessions_sql = "SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC";
$sessions_stmt = $pdo->prepare($sessions_sql);
$sessions_stmt->execute([$candidate_id]);
$all_sessions = $sessions_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($all_sessions)) {
    echo "<p style='color: red;'>❌ AUCUNE SESSION QCM</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'><th>Phase</th><th>Épreuve</th><th>Score</th><th>Status</th><th>Date</th></tr>";
    foreach ($all_sessions as $session) {
        $color = $session['status'] === 'completed' ? '#d4edda' : '#f8d7da';
        echo "<tr style='background: $color;'>";
        echo "<td>{$session['phase']}</td>";
        echo "<td>{$session['epreuve']}</td>";
        echo "<td><strong>{$session['score']}%</strong></td>";
        echo "<td>{$session['status']}</td>";
        echo "<td>" . (isset($session['created_at']) ? $session['created_at'] : $session['id']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. Reproduire EXACTEMENT la logique de candidate_dashboard.php
echo "<h2>3️⃣ Test Logique Dashboard</h2>";

// Get phase results (only completed)
$phase_results = [];
foreach ($all_sessions as $session) {
    if ($session['status'] === 'completed') {
        $phase_results[] = $session;
    }
}

echo "<p><strong>Phase results (completed only):</strong> " . count($phase_results) . " sessions</p>";

// Initialize variables exactly like in dashboard
$thi_score = 0;
$thi_status = 'not_taken';
$thb_score = 0;
$thb_status = 'not_taken';

// Get scores from phase results
foreach ($phase_results as $result) {
    if ($result['phase'] === 'phase1') {
        if ($result['epreuve'] === 'THI') {
            $thi_score = $result['score'];
            $thi_status = $result['score'] >= 80 ? 'passed' : 'failed';
        } elseif ($result['epreuve'] === 'THB') {
            $thb_score = $result['score'];
            $thb_status = $result['score'] >= 80 ? 'passed' : 'failed';
        }
    }
}

echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>Variable</th><th>Valeur</th></tr>";
echo "<tr><td>thi_score</td><td><strong>$thi_score</strong></td></tr>";
echo "<tr><td>thi_status</td><td>$thi_status</td></tr>";
echo "<tr><td>thb_score</td><td><strong>$thb_score</strong></td></tr>";
echo "<tr><td>thb_status</td><td>$thb_status</td></tr>";
echo "</table>";

// Test access logic
if ($candidate['categorie'] == '1') {
    $can_access_imagerie = ($thi_score >= 80);
    $required_test = 'THI';
    $required_score = $thi_score;
} else {
    $can_access_imagerie = ($thb_score >= 80);
    $required_test = 'THB';
    $required_score = $thb_score;
}

echo "<div style='background: " . ($can_access_imagerie ? '#d4edda' : '#f8d7da') . "; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>" . ($can_access_imagerie ? '✅ ACCÈS AUTORISÉ' : '❌ ACCÈS BLOQUÉ') . "</h3>";
echo "<p><strong>Catégorie:</strong> {$candidate['categorie']}</p>";
echo "<p><strong>Test requis:</strong> $required_test</p>";
echo "<p><strong>Score obtenu:</strong> $required_score%</p>";
echo "<p><strong>Condition:</strong> Score ≥ 80%</p>";
echo "<p><strong>Résultat:</strong> " . ($can_access_imagerie ? 'PEUT ACCÉDER' : 'NE PEUT PAS ACCÉDER') . "</p>";
echo "</div>";

if (!$can_access_imagerie) {
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
    echo "<h3>🔍 Analyse du Problème</h3>";
    if ($required_score == 0) {
        echo "<p>❌ <strong>PROBLÈME:</strong> Aucun score trouvé pour $required_test</p>";
        echo "<p>Le candidat n'a pas encore passé ou terminé le test $required_test</p>";
    } else {
        echo "<p>❌ <strong>PROBLÈME:</strong> Score insuffisant ($required_score% < 80%)</p>";
    }
    echo "</div>";
}

echo "<div style='background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>🎯 Actions à Effectuer</h3>";
if ($required_score == 0) {
    echo "<p>1. Le candidat doit passer le test $required_test</p>";
    echo "<p>2. Le test doit être complété (status = 'completed')</p>";
} else if ($required_score < 80) {
    echo "<p>1. Le candidat a un score de $required_score% en $required_test</p>";
    echo "<p>2. Il faut un score ≥ 80% pour accéder à Pratique Imagerie</p>";
    echo "<p>3. Le candidat doit repasser le test ou accepter qu'il n'a pas le niveau requis</p>";
}
echo "</div>";
?>
