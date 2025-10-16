<?php
require_once 'config.php';

echo "<h2>Debug Test Completion Status</h2>";

$candidate_id = 1; // ID de test
$phase = 'phase1';
$epreuve = 'THI';

try {
    // 1. Vérifier les sessions QCM pour ce candidat
    echo "<h3>1. Sessions QCM pour candidat $candidate_id</h3>";
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY started_at DESC");
    $stmt->execute([$candidate_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sessions)) {
        echo "❌ Aucune session trouvée<br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Phase</th><th>Epreuve</th><th>Status</th><th>Score</th><th>Started</th><th>Completed</th></tr>";
        foreach ($sessions as $session) {
            echo "<tr>";
            echo "<td>{$session['id']}</td>";
            echo "<td>{$session['phase']}</td>";
            echo "<td>{$session['epreuve']}</td>";
            echo "<td><strong>{$session['status']}</strong></td>";
            echo "<td>" . ($session['score'] ? number_format($session['score'], 1) . '%' : 'N/A') . "</td>";
            echo "<td>{$session['started_at']}</td>";
            echo "<td>" . ($session['completed_at'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    }
    
    // 2. Test de la logique de détection de test complété
    echo "<h3>2. Test logique détection test complété</h3>";
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'completed' ORDER BY started_at DESC LIMIT 1");
    $stmt->execute([$candidate_id, $phase, $epreuve]);
    $completed_session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($completed_session) {
        echo "✅ Test complété détecté pour $phase/$epreuve<br>";
        echo "Session ID: {$completed_session['id']}<br>";
        echo "Score: " . number_format($completed_session['score'], 1) . "%<br>";
        echo "Complété le: {$completed_session['completed_at']}<br>";
        echo "<strong>➡️ L'utilisateur ne devrait PAS pouvoir refaire ce test</strong><br>";
    } else {
        echo "❌ Aucun test complété trouvé pour $phase/$epreuve<br>";
        echo "<strong>➡️ L'utilisateur peut faire le test</strong><br>";
    }
    
    // 3. Vérifier candidate_phase_results
    echo "<h3>3. Résultats dans candidate_phase_results</h3>";
    $stmt = $pdo->prepare("SELECT * FROM candidate_phase_results WHERE candidate_id = ? ORDER BY completed_at DESC");
    $stmt->execute([$candidate_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "❌ Aucun résultat dans candidate_phase_results<br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Phase</th><th>Epreuve</th><th>Score</th><th>Status</th><th>Session ID</th><th>Completed</th></tr>";
        foreach ($results as $result) {
            echo "<tr>";
            echo "<td>{$result['id']}</td>";
            echo "<td>{$result['phase']}</td>";
            echo "<td>{$result['epreuve']}</td>";
            echo "<td>" . number_format($result['score'], 1) . "%</td>";
            echo "<td><strong>{$result['status']}</strong></td>";
            echo "<td>" . ($result['session_id'] ?? 'N/A') . "</td>";
            echo "<td>" . ($result['completed_at'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    }
    
    // 4. Forcer la création d'un test complété pour tester
    echo "<h3>4. Actions de test</h3>";
    echo "<button onclick='createCompletedTest()'>Créer un test complété de test</button> ";
    echo "<button onclick='clearTests()'>Nettoyer tous les tests</button><br><br>";
    echo "<div id='test-result'></div>";
    
    // 5. Simuler l'accès à candidate_qcm.php
    echo "<h3>5. Simulation accès candidate_qcm.php</h3>";
    if ($completed_session) {
        echo "🚫 <strong style='color: red;'>ACCÈS REFUSÉ</strong> - Test déjà complété<br>";
        echo "Message: \"Vous avez déjà passé le test $epreuve. Une seule tentative est autorisée par épreuve.\"<br>";
    } else {
        echo "✅ <strong style='color: green;'>ACCÈS AUTORISÉ</strong> - Peut faire le test<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
?>

<script>
function createCompletedTest() {
    fetch('debug_test_completion.php?action=create_test', {
        method: 'POST'
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('test-result').innerHTML = data;
        setTimeout(() => location.reload(), 1000);
    });
}

function clearTests() {
    if (confirm('Supprimer tous les tests pour ce candidat ?')) {
        fetch('debug_test_completion.php?action=clear_tests', {
            method: 'POST'
        })
        .then(response => response.text())
        .then(data => {
            document.getElementById('test-result').innerHTML = data;
            setTimeout(() => location.reload(), 1000);
        });
    }
}
</script>

<?php
// Actions AJAX
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'create_test') {
        try {
            // Créer une session complétée
            $pdo->exec("INSERT INTO qcm_sessions (candidate_id, phase, epreuve, total_questions, status, score, correct_answers, started_at, completed_at) 
                       VALUES (1, 'phase1', 'THI', 10, 'completed', 85.0, 8, NOW(), NOW())
                       ON DUPLICATE KEY UPDATE status = 'completed', score = 85.0, completed_at = NOW()");
            
            $session_id = $pdo->lastInsertId();
            if ($session_id == 0) {
                // Update case
                $stmt = $pdo->prepare("SELECT id FROM qcm_sessions WHERE candidate_id = 1 AND phase = 'phase1' AND epreuve = 'THI' ORDER BY id DESC LIMIT 1");
                $stmt->execute();
                $session_id = $stmt->fetchColumn();
            }
            
            // Créer le résultat
            $pdo->exec("INSERT INTO candidate_phase_results (candidate_id, phase, epreuve, score, status, session_id, completed_at) 
                       VALUES (1, 'phase1', 'THI', 85.0, 'passed', $session_id, NOW())
                       ON DUPLICATE KEY UPDATE score = 85.0, status = 'passed', session_id = $session_id, completed_at = NOW()");
            
            echo "✅ Test complété créé avec succès (Score: 85%)";
        } catch (Exception $e) {
            echo "❌ Erreur: " . $e->getMessage();
        }
        exit;
    }
    
    if ($_GET['action'] === 'clear_tests') {
        try {
            $pdo->exec("DELETE FROM candidate_phase_results WHERE candidate_id = 1");
            $pdo->exec("DELETE FROM qcm_sessions WHERE candidate_id = 1");
            echo "✅ Tous les tests supprimés";
        } catch (Exception $e) {
            echo "❌ Erreur: " . $e->getMessage();
        }
        exit;
    }
}
?>
