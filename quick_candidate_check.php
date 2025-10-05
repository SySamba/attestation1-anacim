<?php
session_start();
require_once 'config.php';

echo "<h1>🔍 VÉRIFICATION RAPIDE CANDIDAT CONNECTÉ</h1>";

if (!isset($_SESSION['candidate_logged_in']) || !isset($_SESSION['candidate_id'])) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>❌ Aucun candidat connecté</h3>";
    echo "<p>Connectez-vous d'abord comme candidat pour utiliser ce debug</p>";
    echo "</div>";
    exit();
}

$candidate_id = $_SESSION['candidate_id'];

echo "<h2>📋 Candidat Connecté: ID $candidate_id</h2>";

// Infos candidat
$candidate_stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$candidate_stmt->execute([$candidate_id]);
$candidate = $candidate_stmt->fetch(PDO::FETCH_ASSOC);

echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>Nom:</strong> {$candidate['nom']} {$candidate['prenom']}<br>";
echo "<strong>Catégorie:</strong> {$candidate['categorie']}<br>";
echo "<strong>Status:</strong> {$candidate['status']}";
echo "</div>";

// Sessions QCM
echo "<h2>🎯 Sessions QCM Phase 1</h2>";
$sessions_sql = "SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND status = 'completed' ORDER BY created_at DESC";
$sessions_stmt = $pdo->prepare($sessions_sql);
$sessions_stmt->execute([$candidate_id]);
$sessions = $sessions_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sessions)) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h3>❌ AUCUNE SESSION COMPLÉTÉE</h3>";
    echo "<p>Le candidat n'a pas encore terminé de test Phase 1</p>";
    echo "</div>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'><th>Épreuve</th><th>Score</th><th>Status</th><th>Date</th></tr>";
    
    $thi_score = 0;
    $thb_score = 0;
    
    foreach ($sessions as $session) {
        $color = $session['score'] >= 80 ? '#d4edda' : '#f8d7da';
        echo "<tr style='background: $color;'>";
        echo "<td><strong>{$session['epreuve']}</strong></td>";
        echo "<td><strong>{$session['score']}%</strong></td>";
        echo "<td>{$session['status']}</td>";
        echo "<td>{$session['created_at']}</td>";
        echo "</tr>";
        
        if ($session['epreuve'] === 'THI') $thi_score = $session['score'];
        if ($session['epreuve'] === 'THB') $thb_score = $session['score'];
    }
    echo "</table>";
}

// Test logique d'accès
echo "<h2>🔓 Test Logique d'Accès Pratique Imagerie</h2>";

$required_test = ($candidate['categorie'] == '1') ? 'THI' : 'THB';
$required_score = ($candidate['categorie'] == '1') ? $thi_score : $thb_score;

echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>Test requis:</strong> $required_test<br>";
echo "<strong>Score obtenu:</strong> $required_score%<br>";
echo "<strong>Minimum requis:</strong> 80%<br>";
echo "</div>";

$can_access = $required_score >= 80;

echo "<div style='background: " . ($can_access ? '#d4edda' : '#f8d7da') . "; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>" . ($can_access ? '✅ ACCÈS AUTORISÉ' : '❌ ACCÈS BLOQUÉ') . "</h3>";
if ($can_access) {
    echo "<p><strong>Le candidat DEVRAIT avoir accès à Pratique Imagerie</strong></p>";
    echo "<p>Si l'accès est toujours bloqué, il y a un bug dans candidate_dashboard.php</p>";
} else {
    echo "<p><strong>Score insuffisant: $required_score% < 80%</strong></p>";
    echo "<p>Le candidat doit d'abord réussir $required_test avec au moins 80%</p>";
}
echo "</div>";

// Code de debug pour candidate_dashboard.php
if ($can_access) {
    echo "<h2>🔧 Code Debug pour candidate_dashboard.php</h2>";
    echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px;'>";
    echo "<pre>";
    echo "// Ajoutez ce code temporaire dans candidate_dashboard.php avant la logique d'accès:\n";
    echo "echo \"DEBUG: Candidat catégorie: {$candidate['categorie']}\";\n";
    echo "echo \"DEBUG: Score $required_test: $required_score%\";\n";
    echo "echo \"DEBUG: Can access: \" . (\$can_access_imagerie ? 'OUI' : 'NON');\n";
    echo "</pre>";
    echo "</div>";
}
?>
