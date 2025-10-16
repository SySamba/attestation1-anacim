<?php
session_start();
require_once 'config.php';

// Debug script to check THI sessions and identify why multiple attempts are possible

echo "<h2>Debug THI Multiple Attempts Issue</h2>";

// Get all candidates with category 1 (THI candidates)
$stmt = $pdo->prepare("SELECT id, prenom, nom, categorie FROM candidates WHERE categorie = '1' AND status = 'accepted' LIMIT 5");
$stmt->execute();
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Candidats Catégorie 1 (THI):</h3>";

foreach ($candidates as $candidate) {
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
    echo "<h4>Candidat: {$candidate['prenom']} {$candidate['nom']} (ID: {$candidate['id']})</h4>";
    
    // Check all sessions for this candidate
    $stmt = $pdo->prepare("
        SELECT id, phase, epreuve, status, started_at, completed_at, score 
        FROM qcm_sessions 
        WHERE candidate_id = ? 
        ORDER BY started_at DESC
    ");
    $stmt->execute([$candidate['id']]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sessions)) {
        echo "<p>Aucune session trouvée</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID Session</th><th>Phase</th><th>Épreuve</th><th>Status</th><th>Commencé</th><th>Terminé</th><th>Score</th></tr>";
        
        foreach ($sessions as $session) {
            $color = '';
            if ($session['status'] === 'completed') $color = 'background-color: #d4edda;';
            if ($session['status'] === 'in_progress') $color = 'background-color: #fff3cd;';
            
            echo "<tr style='$color'>";
            echo "<td>{$session['id']}</td>";
            echo "<td>{$session['phase']}</td>";
            echo "<td>{$session['epreuve']}</td>";
            echo "<td>{$session['status']}</td>";
            echo "<td>{$session['started_at']}</td>";
            echo "<td>{$session['completed_at']}</td>";
            echo "<td>{$session['score']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check specific THI sessions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM qcm_sessions 
            WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'
        ");
        $stmt->execute([$candidate['id']]);
        $thi_completed = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($thi_completed['count'] > 0) {
            echo "<p style='color: red;'><strong>⚠️ Ce candidat a {$thi_completed['count']} session(s) THI complétée(s)</strong></p>";
            
            // Test the restriction logic
            $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'completed' ORDER BY started_at DESC LIMIT 1");
            $stmt->execute([$candidate['id'], 'phase1', 'THI']);
            $completed_session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($completed_session) {
                echo "<p style='color: green;'>✓ Restriction devrait fonctionner - session complétée détectée</p>";
            } else {
                echo "<p style='color: red;'>✗ Problème - restriction ne détecte pas la session complétée</p>";
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ Aucune session THI complétée</p>";
        }
    }
    
    echo "</div>";
}

echo "<hr>";

// Check if there are any issues with the session status
echo "<h3>Analyse des Sessions THI:</h3>";

$stmt = $pdo->prepare("
    SELECT 
        qs.id, qs.candidate_id, qs.status, qs.started_at, qs.completed_at, qs.score,
        c.prenom, c.nom
    FROM qcm_sessions qs
    JOIN candidates c ON qs.candidate_id = c.id
    WHERE qs.phase = 'phase1' AND qs.epreuve = 'THI'
    ORDER BY qs.started_at DESC
    LIMIT 10
");
$stmt->execute();
$thi_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($thi_sessions)) {
    echo "<p>Aucune session THI trouvée dans la base de données</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Session ID</th><th>Candidat</th><th>Status</th><th>Commencé</th><th>Terminé</th><th>Score</th></tr>";
    
    foreach ($thi_sessions as $session) {
        $color = '';
        if ($session['status'] === 'completed') $color = 'background-color: #d4edda;';
        if ($session['status'] === 'in_progress') $color = 'background-color: #fff3cd;';
        
        echo "<tr style='$color'>";
        echo "<td>{$session['id']}</td>";
        echo "<td>{$session['prenom']} {$session['nom']} (ID: {$session['candidate_id']})</td>";
        echo "<td>{$session['status']}</td>";
        echo "<td>{$session['started_at']}</td>";
        echo "<td>{$session['completed_at']}</td>";
        echo "<td>{$session['score']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// Test the exact query used in candidate_qcm.php
echo "<h3>Test de la Requête de Restriction:</h3>";

if (!empty($candidates)) {
    $test_candidate_id = $candidates[0]['id'];
    echo "<p>Test avec candidat ID: $test_candidate_id</p>";
    
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'completed' ORDER BY started_at DESC LIMIT 1");
    $stmt->execute([$test_candidate_id, 'phase1', 'THI']);
    $completed_session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($completed_session) {
        echo "<p style='color: green;'>✓ Requête trouve une session complétée</p>";
        echo "<pre>" . print_r($completed_session, true) . "</pre>";
    } else {
        echo "<p style='color: orange;'>ℹ️ Aucune session complétée trouvée pour ce candidat</p>";
    }
}

?>
