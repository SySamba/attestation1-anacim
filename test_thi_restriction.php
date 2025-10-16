<?php
// Test script to verify THI restriction logic
session_start();
require_once 'config.php';

echo "<h2>Test THI Restriction Logic</h2>";

// Simulate the exact logic from candidate_qcm.php
$candidate_id = 11; // Test with a specific candidate ID
$phase = 'phase1';
$epreuve = 'THI';

echo "<p>Testing with candidate_id: $candidate_id, phase: $phase, epreuve: $epreuve</p>";

// Check if candidate already has a completed session for this specific phase/epreuve
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'completed' ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$candidate_id, $phase, $epreuve]);
$completed_session = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Query Result:</h3>";
if ($completed_session) {
    echo "<div style='background-color: #f8d7da; padding: 10px; border: 1px solid #f5c6cb;'>";
    echo "<strong>COMPLETED SESSION FOUND - Should redirect!</strong><br>";
    echo "Session ID: " . $completed_session['id'] . "<br>";
    echo "Started: " . $completed_session['started_at'] . "<br>";
    echo "Completed: " . $completed_session['completed_at'] . "<br>";
    echo "Score: " . $completed_session['score'] . "<br>";
    echo "Status: " . $completed_session['status'] . "<br>";
    echo "</div>";
    
    echo "<h3>Restriction Test:</h3>";
    echo "<p style='color: red;'>The restriction logic should prevent access with message:</p>";
    echo "<p><em>\"Vous avez déjà passé le test THI. Une seule tentative est autorisée par épreuve.\"</em></p>";
    
} else {
    echo "<div style='background-color: #d1ecf1; padding: 10px; border: 1px solid #bee5eb;'>";
    echo "<strong>NO COMPLETED SESSION FOUND - Access allowed</strong>";
    echo "</div>";
}

// Check all sessions for this candidate
echo "<h3>All Sessions for Candidate $candidate_id:</h3>";
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY started_at DESC");
$stmt->execute([$candidate_id]);
$all_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($all_sessions)) {
    echo "<p>No sessions found for this candidate.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Phase</th><th>Épreuve</th><th>Status</th><th>Started</th><th>Completed</th><th>Score</th></tr>";
    
    foreach ($all_sessions as $session) {
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
}

// Test with different candidate IDs to find one with completed THI
echo "<h3>Search for Candidates with Completed THI:</h3>";
$stmt = $pdo->prepare("
    SELECT DISTINCT qs.candidate_id, c.prenom, c.nom, c.categorie, 
           COUNT(*) as thi_sessions,
           MAX(qs.completed_at) as last_completed
    FROM qcm_sessions qs
    JOIN candidates c ON qs.candidate_id = c.id
    WHERE qs.phase = 'phase1' AND qs.epreuve = 'THI' AND qs.status = 'completed'
    GROUP BY qs.candidate_id
    ORDER BY last_completed DESC
    LIMIT 5
");
$stmt->execute();
$thi_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($thi_candidates)) {
    echo "<p>No candidates found with completed THI sessions.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Candidate ID</th><th>Name</th><th>Category</th><th>THI Sessions</th><th>Last Completed</th></tr>";
    
    foreach ($thi_candidates as $candidate) {
        echo "<tr>";
        echo "<td>{$candidate['candidate_id']}</td>";
        echo "<td>{$candidate['prenom']} {$candidate['nom']}</td>";
        echo "<td>{$candidate['categorie']}</td>";
        echo "<td>{$candidate['thi_sessions']}</td>";
        echo "<td>{$candidate['last_completed']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>If any candidate has more than 1 THI session, the restriction is not working!</strong></p>";
}

?>
