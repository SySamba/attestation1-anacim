<?php
require_once 'config.php';

echo "<h2>Debug QCM Submit Issue</h2>";

try {
    // 1. Vérifier les sessions QCM en cours
    echo "<h3>1. Sessions QCM en cours</h3>";
    $stmt = $pdo->query("SELECT * FROM qcm_sessions WHERE status = 'in_progress' ORDER BY started_at DESC LIMIT 5");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sessions)) {
        echo "❌ Aucune session en cours trouvée<br>";
        
        // Créer une session de test
        echo "Création d'une session de test...<br>";
        $pdo->exec("INSERT INTO qcm_sessions (candidate_id, phase, epreuve, total_questions, status) VALUES (1, 'phase1', 'THI', 10, 'in_progress')");
        $test_session_id = $pdo->lastInsertId();
        echo "✅ Session de test créée avec ID: $test_session_id<br>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Candidate ID</th><th>Phase</th><th>Epreuve</th><th>Status</th><th>Started</th></tr>";
        foreach ($sessions as $session) {
            echo "<tr>";
            echo "<td>{$session['id']}</td>";
            echo "<td>{$session['candidate_id']}</td>";
            echo "<td>{$session['phase']}</td>";
            echo "<td>{$session['epreuve']}</td>";
            echo "<td>{$session['status']}</td>";
            echo "<td>{$session['started_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        $test_session_id = $sessions[0]['id'];
    }
    
    // 2. Tester l'API submit_test
    echo "<h3>2. Test API submit_test</h3>";
    
    // Simuler la requête POST
    $test_data = [
        'action' => 'submit_test',
        'session_id' => $test_session_id
    ];
    
    echo "Test avec session_id: $test_session_id<br>";
    
    // Simuler le traitement de qcm_api.php
    try {
        // Vérifier que la session existe
        $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id = ? AND status = 'in_progress'");
        $stmt->execute([$test_session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            echo "❌ Session non trouvée ou non active<br>";
        } else {
            echo "✅ Session trouvée: ID {$session['id']}<br>";
            
            // Calculer le score (simulé)
            $stmt = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(is_correct), 0) as correct FROM qcm_answers WHERE session_id = ?");
            $stmt->execute([$test_session_id]);
            $results = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $total_questions = $session['total_questions'];
            $correct_count = $results['correct'] ?? 0;
            $score = $total_questions > 0 ? ($correct_count / $total_questions) * 100 : 0;
            
            echo "Réponses correctes: $correct_count / $total_questions<br>";
            echo "Score calculé: " . number_format($score, 1) . "%<br>";
            
            // Test de mise à jour de la session
            $stmt = $pdo->prepare("UPDATE qcm_sessions SET completed_at = NOW(), score = ?, correct_answers = ?, status = 'completed' WHERE id = ?");
            $result = $stmt->execute([$score, $correct_count, $test_session_id]);
            
            if ($result) {
                echo "✅ Session mise à jour avec succès<br>";
            } else {
                echo "❌ Erreur mise à jour session<br>";
            }
            
            // Test candidate_phase_results
            echo "<h4>Test candidate_phase_results</h4>";
            $stmt = $pdo->prepare("INSERT INTO candidate_phase_results (candidate_id, phase, epreuve, score, status, session_id, completed_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, NOW()) 
                                  ON DUPLICATE KEY UPDATE score = ?, status = ?, session_id = ?, completed_at = NOW()");
            $result_status = $score >= 80 ? 'passed' : 'failed';
            
            try {
                $stmt->execute([
                    $session['candidate_id'], 
                    $session['phase'], 
                    $session['epreuve'], 
                    $score, 
                    $result_status, 
                    $test_session_id,
                    $score, 
                    $result_status, 
                    $test_session_id
                ]);
                echo "✅ candidate_phase_results mis à jour<br>";
            } catch (PDOException $e) {
                echo "❌ Erreur candidate_phase_results: " . $e->getMessage() . "<br>";
            }
        }
        
    } catch (PDOException $e) {
        echo "❌ Erreur test API: " . $e->getMessage() . "<br>";
    }
    
    // 3. Vérifier la structure des tables
    echo "<h3>3. Vérification structure tables</h3>";
    
    // Vérifier qcm_sessions
    $stmt = $pdo->query("DESCRIBE qcm_sessions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Colonnes qcm_sessions: " . implode(', ', $columns) . "<br>";
    
    // Vérifier candidate_phase_results
    $stmt = $pdo->query("DESCRIBE candidate_phase_results");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Colonnes candidate_phase_results: " . implode(', ', $columns) . "<br>";
    
    // 4. Test direct de l'API
    echo "<h3>4. Test API Direct</h3>";
    echo "<button onclick='testSubmitAPI()'>Tester Submit API</button>";
    echo "<div id='api-result'></div>";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
?>

<script>
function testSubmitAPI() {
    const sessionId = <?php echo $test_session_id ?? 1; ?>;
    
    fetch('qcm_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'submit_test',
            session_id: sessionId
        })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('api-result').innerHTML = 
            '<h4>Résultat API:</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
    })
    .catch(error => {
        document.getElementById('api-result').innerHTML = 
            '<h4>Erreur API:</h4><pre>' + error.toString() + '</pre>';
    });
}
</script>
