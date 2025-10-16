<?php
session_start();
require_once 'config.php';

echo "<h2>Debug THI Access - Test en Temps Réel</h2>";

// Simuler l'accès comme un candidat C1
if (isset($_GET['test_candidate'])) {
    $candidate_id = $_GET['test_candidate'];
    $_SESSION['candidate_id'] = $candidate_id;
    echo "<p style='color: blue;'>Session simulée pour candidat ID: $candidate_id</p>";
}

$candidate_id = $_SESSION['candidate_id'] ?? null;
if (!$candidate_id) {
    echo "<p style='color: red;'>Aucun candidat en session</p>";
    echo "<h3>Candidats C1 disponibles:</h3>";
    $stmt = $pdo->prepare("SELECT id, prenom, nom FROM candidates WHERE categorie = '1' AND status = 'accepted'");
    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($candidates as $cand) {
        echo "<p><a href='?test_candidate={$cand['id']}'>{$cand['prenom']} {$cand['nom']} (ID: {$cand['id']})</a></p>";
    }
    exit;
}

echo "<h3>Test d'Accès THI - Candidat ID: $candidate_id</h3>";

// Récupérer info candidat
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ? AND status = 'accepted'");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidate) {
    echo "<p style='color: red;'>❌ Candidat non trouvé ou non autorisé</p>";
    exit;
}

echo "<p><strong>Candidat:</strong> {$candidate['prenom']} {$candidate['nom']} (Catégorie: {$candidate['categorie']})</p>";

// Test 1: Vérification catégorie
if ($candidate['categorie'] != '1') {
    echo "<p style='color: red;'>❌ BLOQUÉ - Seuls les candidats C1 peuvent accéder au THI</p>";
    exit;
} else {
    echo "<p style='color: green;'>✅ Catégorie C1 - Accès autorisé</p>";
}

// Test 2: Vérification session complétée (MÊME LOGIQUE QUE candidate_qcm_thi.php)
$phase = 'phase1';
$epreuve = 'THI';

$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'completed' ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$candidate_id, $phase, $epreuve]);
$completed_session = $stmt->fetch(PDO::FETCH_ASSOC);

if ($completed_session) {
    echo "<p style='color: red;'>❌ BLOQUÉ - Session complétée détectée</p>";
    echo "<p>Session ID: {$completed_session['id']}</p>";
    echo "<p>Score: {$completed_session['score']}%</p>";
    echo "<p>Terminé: {$completed_session['completed_at']}</p>";
    echo "<p><strong>REDIRECTION vers dashboard avec message d'erreur</strong></p>";
} else {
    echo "<p style='color: green;'>✅ Aucune session complétée - Accès autorisé</p>";
}

// Test 3: Vérification session en cours
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'in_progress' ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$candidate_id, $phase, $epreuve]);
$existing_session = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_session) {
    echo "<p style='color: orange;'>⚠️ Session en cours détectée - Reprise possible</p>";
    echo "<p>Session ID: {$existing_session['id']}</p>";
    echo "<p>Commencé: {$existing_session['started_at']}</p>";
} else {
    echo "<p style='color: green;'>✅ Aucune session en cours - Nouvelle session possible</p>";
}

// Test 4: Simulation du comportement final
echo "<h3>Résultat Final:</h3>";
if ($completed_session) {
    echo "<div style='background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb;'>";
    echo "<strong>❌ ACCÈS REFUSÉ</strong><br>";
    echo "Le candidat devrait être redirigé vers le dashboard avec le message:<br>";
    echo "<em>\"Vous avez déjà passé le test THI. Une seule tentative est autorisée par épreuve.\"</em>";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; padding: 10px; border: 1px solid #c3e6cb;'>";
    echo "<strong>✅ ACCÈS AUTORISÉ</strong><br>";
    if ($existing_session) {
        echo "Reprise de la session en cours ID: {$existing_session['id']}";
    } else {
        echo "Création d'une nouvelle session THI";
    }
    echo "</div>";
}

// Test 5: Vérifier toutes les sessions THI du candidat
echo "<h3>Historique Complet des Sessions THI:</h3>";
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? ORDER BY started_at DESC");
$stmt->execute([$candidate_id, $phase, $epreuve]);
$all_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($all_sessions)) {
    echo "<p>Aucune session THI trouvée</p>";
} else {
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

echo "<hr>";
echo "<p><a href='candidate_qcm_thi.php'>🔗 Tester l'accès réel à candidate_qcm_thi.php</a></p>";
echo "<p><a href='candidate_dashboard.php'>🔗 Retour au dashboard</a></p>";
echo "<p><a href='?'>🔄 Changer de candidat</a></p>";
?>
