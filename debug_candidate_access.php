<?php
session_start();
require_once 'config.php';

echo "<h1>🔍 DEBUG ACCÈS CANDIDAT</h1>";

// Simuler une session candidat pour déboguer
if (!isset($_SESSION['candidate_logged_in'])) {
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>⚠️ Pas de session candidat</h3>";
    echo "<p>Entrez l'ID du candidat à déboguer :</p>";
    echo "<form method='POST'>";
    echo "<input type='number' name='candidate_id' placeholder='ID candidat' required>";
    echo "<button type='submit'>Déboguer</button>";
    echo "</form>";
    echo "</div>";
    
    if ($_POST && isset($_POST['candidate_id'])) {
        $debug_candidate_id = $_POST['candidate_id'];
        $_SESSION['candidate_logged_in'] = true;
        $_SESSION['candidate_id'] = $debug_candidate_id;
        echo "<p>✅ Session simulée pour candidat ID: $debug_candidate_id</p>";
    } else {
        exit();
    }
}

$candidate_id = $_SESSION['candidate_id'];

echo "<h2>1️⃣ Informations Candidat</h2>";
$candidate_stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$candidate_stmt->execute([$candidate_id]);
$candidate = $candidate_stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidate) {
    echo "<p>❌ Candidat introuvable</p>";
    exit();
}

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Champ</th><th>Valeur</th></tr>";
echo "<tr><td>ID</td><td>{$candidate['id']}</td></tr>";
echo "<tr><td>Nom</td><td>{$candidate['nom']} {$candidate['prenom']}</td></tr>";
echo "<tr><td>Catégorie</td><td>{$candidate['categorie']}</td></tr>";
echo "<tr><td>Status</td><td>{$candidate['status']}</td></tr>";
echo "</table>";

echo "<h2>2️⃣ Résultats QCM Sessions</h2>";
$sessions_stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY created_at DESC");
$sessions_stmt->execute([$candidate_id]);
$sessions = $sessions_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sessions)) {
    echo "<p>❌ Aucune session QCM trouvée</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'><th>Phase</th><th>Épreuve</th><th>Score</th><th>Status</th><th>Date</th></tr>";
    foreach ($sessions as $session) {
        $color = $session['status'] === 'completed' ? '#d4edda' : '#f8d7da';
        echo "<tr style='background: $color;'>";
        echo "<td>{$session['phase']}</td>";
        echo "<td>{$session['epreuve']}</td>";
        echo "<td>{$session['score']}%</td>";
        echo "<td>{$session['status']}</td>";
        echo "<td>{$session['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>3️⃣ Test Logique d'Accès</h2>";

// Reproduire la logique de candidate_dashboard.php
$phase_results = [];
foreach ($sessions as $session) {
    if ($session['status'] === 'completed') {
        $phase_results[] = $session;
    }
}

// THI/THB logic
$thi_status = 'not_taken';
$thi_score = 0;
$thb_status = 'not_taken';
$thb_score = 0;

foreach ($phase_results as $result) {
    if ($result['phase'] === 'phase1') {
        if ($result['epreuve'] === 'THI') {
            $thi_status = $result['score'] >= 80 ? 'passed' : 'failed';
            $thi_score = $result['score'];
        } elseif ($result['epreuve'] === 'THB') {
            $thb_status = $result['score'] >= 80 ? 'passed' : 'failed';
            $thb_score = $result['score'];
        }
    }
}

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr style='background: #f8f9fa;'><th>Variable</th><th>Valeur</th></tr>";
echo "<tr><td>thi_status</td><td>$thi_status</td></tr>";
echo "<tr><td>thi_score</td><td>$thi_score</td></tr>";
echo "<tr><td>thb_status</td><td>$thb_status</td></tr>";
echo "<tr><td>thb_score</td><td>$thb_score</td></tr>";
echo "</table>";

echo "<h2>4️⃣ Test Accès Pratique Imagerie</h2>";

// Test de la logique modifiée
if ($candidate['categorie'] == '1') {
    // C1 needs THI with 80%+
    $can_access_imagerie = ($thi_status === 'passed' && $thi_score >= 80);
    $required_test = 'THI';
    $required_score = $thi_score;
    $required_status = $thi_status;
} else {
    // Categories 2,3,4,5 need THB with 80%+
    $can_access_imagerie = ($thb_status === 'passed' && $thb_score >= 80);
    $required_test = 'THB';
    $required_score = $thb_score;
    $required_status = $thb_status;
}

echo "<div style='background: " . ($can_access_imagerie ? '#d4edda' : '#f8d7da') . "; padding: 20px; border-radius: 10px;'>";
echo "<h3>" . ($can_access_imagerie ? '✅ ACCÈS AUTORISÉ' : '❌ ACCÈS BLOQUÉ') . "</h3>";
echo "<p><strong>Test requis:</strong> $required_test</p>";
echo "<p><strong>Score obtenu:</strong> $required_score%</p>";
echo "<p><strong>Status:</strong> $required_status</p>";
echo "<p><strong>Condition:</strong> Score ≥ 80% ET Status = 'passed'</p>";
echo "<p><strong>Résultat:</strong> " . ($can_access_imagerie ? 'PEUT ACCÉDER' : 'NE PEUT PAS ACCÉDER') . "</p>";
echo "</div>";

if (!$can_access_imagerie) {
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🔧 Problème Identifié</h3>";
    if ($required_score < 80) {
        echo "<p>❌ Score insuffisant: $required_score% < 80%</p>";
    }
    if ($required_status !== 'passed') {
        echo "<p>❌ Status incorrect: '$required_status' ≠ 'passed'</p>";
    }
    echo "</div>";
}
?>
