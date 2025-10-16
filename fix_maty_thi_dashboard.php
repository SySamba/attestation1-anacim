<?php
session_start();
require_once 'config.php';

// Script pour corriger le problème de Maty Ndiaye et autres candidats THI

echo "<h2>Correction Dashboard THI - Détection Sessions Complétées</h2>";

// Find all category 1 candidates with completed THI sessions
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.prenom, c.nom, c.categorie,
           qs.id as session_id, qs.score, qs.status, qs.completed_at
    FROM candidates c
    JOIN qcm_sessions qs ON c.id = qs.candidate_id
    WHERE c.categorie = '1' 
    AND qs.phase = 'phase1' 
    AND qs.epreuve = 'THI' 
    AND qs.status = 'completed'
    ORDER BY c.nom, qs.completed_at DESC
");
$stmt->execute();
$thi_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Candidats C1 avec THI Complété:</h3>";

if (empty($thi_candidates)) {
    echo "<p>Aucun candidat C1 avec THI complété trouvé</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Candidat</th><th>Session ID</th><th>Score</th><th>Status</th><th>Complété</th><th>Dashboard Status</th></tr>";
    
    foreach ($thi_candidates as $candidate) {
        // Test dashboard logic for each candidate
        $stmt_dashboard = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND status = 'completed' ORDER BY id DESC");
        $stmt_dashboard->execute([$candidate['id']]);
        $phase_results = $stmt_dashboard->fetchAll(PDO::FETCH_ASSOC);
        
        $thi_result = null;
        foreach ($phase_results as $result) {
            if ($result['phase'] === 'phase1' && $result['epreuve'] === 'THI') {
                $thi_result = $result;
                break;
            }
        }
        
        $thi_status = $thi_result ? ($thi_result['score'] >= 80 ? 'passed' : 'failed') : 'pending';
        
        // Count attempts
        $attempts_stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'");
        $attempts_stmt->execute([$candidate['id']]);
        $thi_attempts = $attempts_stmt->fetch(PDO::FETCH_ASSOC)['attempts'];
        
        $dashboard_status = "ERREUR";
        if ($thi_status === 'passed') {
            $dashboard_status = "✅ RÉUSSI (OK)";
        } elseif ($thi_status === 'failed') {
            $dashboard_status = "❌ TERMINÉ (OK)";
        } else {
            if ($thi_attempts > 0) {
                $dashboard_status = "⚠️ DÉJÀ PASSÉ (OK)";
            } else {
                $dashboard_status = "🎯 DISPONIBLE (PROBLÈME!)";
            }
        }
        
        $color = ($dashboard_status === "🎯 DISPONIBLE (PROBLÈME!)") ? 'background-color: #f8d7da;' : 'background-color: #d4edda;';
        
        echo "<tr style='$color'>";
        echo "<td>{$candidate['prenom']} {$candidate['nom']} (ID: {$candidate['id']})</td>";
        echo "<td>{$candidate['session_id']}</td>";
        echo "<td>{$candidate['score']}%</td>";
        echo "<td>{$candidate['status']}</td>";
        echo "<td>{$candidate['completed_at']}</td>";
        echo "<td><strong>$dashboard_status</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test specific case of Maty
echo "<h3>Test Spécifique - Maty Ndiaye:</h3>";
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE prenom LIKE '%maty%' AND nom LIKE '%ndiaye%'");
$stmt->execute();
$maty = $stmt->fetch(PDO::FETCH_ASSOC);

if ($maty) {
    echo "<p>Candidat trouvé: {$maty['prenom']} {$maty['nom']} (ID: {$maty['id']})</p>";
    
    // Check sessions
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' ORDER BY started_at DESC");
    $stmt->execute([$maty['id']]);
    $maty_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Sessions THI trouvées: " . count($maty_sessions) . "</p>";
    
    foreach ($maty_sessions as $session) {
        echo "<p>Session {$session['id']}: Status = {$session['status']}, Score = {$session['score']}%, Terminé = {$session['completed_at']}</p>";
    }
    
    // Test dashboard logic
    $stmt_dashboard = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND status = 'completed' ORDER BY id DESC");
    $stmt_dashboard->execute([$maty['id']]);
    $phase_results = $stmt_dashboard->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>Phase results: " . count($phase_results) . "</p>";
    
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
        echo "<p style='color: red;'>❌ PROBLÈME: Aucun THI result trouvé dans phase_results</p>";
        
        // Debug: show all phase_results
        echo "<h4>Tous les phase_results:</h4>";
        foreach ($phase_results as $result) {
            echo "<p>Phase: {$result['phase']}, Epreuve: {$result['epreuve']}, Score: {$result['score']}%</p>";
        }
    }
} else {
    echo "<p style='color: red;'>Maty Ndiaye non trouvée</p>";
}

?>

<h3>Solution Proposée:</h3>
<p>Le problème semble être dans la logique du dashboard. Il faut s'assurer que:</p>
<ol>
<li>La requête <code>qcm_sessions</code> récupère bien toutes les sessions complétées</li>
<li>La boucle <code>foreach</code> trouve bien les résultats THI</li>
<li>Le compteur d'tentatives fonctionne correctement</li>
</ol>

<p><strong>Action recommandée:</strong> Vérifier et corriger la logique dans candidate_dashboard.php</p>
