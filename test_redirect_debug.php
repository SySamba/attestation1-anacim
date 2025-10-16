<?php
session_start();
require_once 'config.php';

echo "<h2>Test Redirect Debug</h2>";

// Simuler une soumission de test
if (isset($_POST['test_submit'])) {
    $candidate_id = $_SESSION['candidate_id'] ?? 1;
    $phase = 'phase1';
    $epreuve = 'THI';
    
    echo "<h3>Simulation soumission test</h3>";
    
    // Créer une session complétée
    $stmt = $pdo->prepare("INSERT INTO qcm_sessions (candidate_id, phase, epreuve, status, score, correct_answers, total_questions, started_at, completed_at) VALUES (?, ?, ?, 'completed', 85.0, 17, 20, NOW() - INTERVAL 1 HOUR, NOW())");
    $stmt->execute([$candidate_id, $phase, $epreuve]);
    $session_id = $pdo->lastInsertId();
    
    // Simuler la réponse JSON de qcm_api.php
    $response = [
        'success' => true,
        'score' => 85.0,
        'correct_answers' => 17,
        'total_questions' => 20,
        'passed' => true,
        'redirect_url' => 'candidate_qcm_results.php?phase=' . urlencode($phase) . '&epreuve=' . urlencode($epreuve)
    ];
    
    echo "<h4>Réponse JSON simulée:</h4>";
    echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
    
    echo "<h4>Test de redirection:</h4>";
    echo "<p>URL de redirection: <strong>{$response['redirect_url']}</strong></p>";
    echo "<button onclick='testRedirect()'>Tester la redirection</button>";
    
    echo "<script>
    function testRedirect() {
        console.log('Redirection vers: {$response['redirect_url']}');
        window.location.href = '{$response['redirect_url']}';
    }
    </script>";
    
    exit;
}

// Vérifier l'état actuel
$candidate_id = $_SESSION['candidate_id'] ?? 1;
echo "<h3>État actuel du candidat $candidate_id</h3>";

$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' ORDER BY started_at DESC LIMIT 3");
$stmt->execute([$candidate_id]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Status</th><th>Score</th><th>Démarré</th><th>Terminé</th></tr>";
foreach ($sessions as $session) {
    echo "<tr>";
    echo "<td>{$session['id']}</td>";
    echo "<td>{$session['status']}</td>";
    echo "<td>" . ($session['score'] ?? 'N/A') . "</td>";
    echo "<td>" . date('H:i:s', strtotime($session['started_at'])) . "</td>";
    echo "<td>" . ($session['completed_at'] ? date('H:i:s', strtotime($session['completed_at'])) : 'N/A') . "</td>";
    echo "</tr>";
}
echo "</table><br>";

echo "<h3>Tests</h3>";
echo "<form method='POST'>";
echo "<button type='submit' name='test_submit'>Simuler soumission de test</button>";
echo "</form>";

echo "<br><a href='candidate_qcm_results.php?phase=phase1&epreuve=THI' target='_blank'>Tester page résultats directement</a>";
echo "<br><a href='candidate_qcm.php?phase=phase1&epreuve=THI' target='_blank'>Aller à la page QCM</a>";
echo "<br><a href='candidate_dashboard.php' target='_blank'>Aller au dashboard</a>";
?>
