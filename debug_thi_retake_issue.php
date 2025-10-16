<?php
session_start();
require_once 'config.php';

echo "<h2>Debug THI Retake Issue</h2>";

// Get current candidate from session
$candidate_id = $_SESSION['candidate_id'] ?? null;

if (!$candidate_id) {
    echo "❌ Aucun candidat connecté. <a href='candidate_login.php'>Se connecter</a>";
    exit;
}

// Get candidate info
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Candidat actuel: {$candidate['prenom']} {$candidate['nom']} (ID: $candidate_id)</h3>";

$phase = 'phase1';
$epreuve = 'THI';

try {
    echo "<h3>1. Analyse des sessions THI</h3>";
    
    // Toutes les sessions THI pour ce candidat
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? ORDER BY started_at DESC");
    $stmt->execute([$candidate_id, $phase, $epreuve]);
    $all_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'><th>ID</th><th>Status</th><th>Score</th><th>Questions</th><th>Bonnes</th><th>Démarré</th><th>Terminé</th><th>Actions</th></tr>";
    
    foreach ($all_sessions as $session) {
        $bg_color = '';
        if ($session['status'] === 'completed') $bg_color = 'background: #d4edda;';
        if ($session['status'] === 'in_progress') $bg_color = 'background: #fff3cd;';
        if ($session['status'] === 'expired') $bg_color = 'background: #f8d7da;';
        
        echo "<tr style='$bg_color'>";
        echo "<td>{$session['id']}</td>";
        echo "<td><strong>{$session['status']}</strong></td>";
        echo "<td>" . ($session['score'] ? number_format($session['score'], 1) . '%' : 'N/A') . "</td>";
        echo "<td>{$session['total_questions']}</td>";
        echo "<td>" . ($session['correct_answers'] ?? 'N/A') . "</td>";
        echo "<td>" . date('d/m H:i', strtotime($session['started_at'])) . "</td>";
        echo "<td>" . ($session['completed_at'] ? date('d/m H:i', strtotime($session['completed_at'])) : 'N/A') . "</td>";
        echo "<td>";
        if ($session['status'] === 'in_progress') {
            echo "<button onclick='expireSession({$session['id']})' style='background: #dc3545; color: white; border: none; padding: 2px 8px;'>Expirer</button>";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    echo "<h3>2. Test de la logique candidate_qcm.php</h3>";
    
    // Test logique exacte de candidate_qcm.php
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'completed' ORDER BY started_at DESC LIMIT 1");
    $stmt->execute([$candidate_id, $phase, $epreuve]);
    $completed_session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($completed_session) {
        echo "✅ <strong style='color: green;'>Session complétée trouvée - DEVRAIT BLOQUER L'ACCÈS</strong><br>";
        echo "- Session ID: {$completed_session['id']}<br>";
        echo "- Score: " . number_format($completed_session['score'], 1) . "%<br>";
        echo "- Terminé: {$completed_session['completed_at']}<br>";
        echo "➡️ <strong>Le candidat devrait être redirigé vers le dashboard avec un message d'erreur</strong><br><br>";
    } else {
        echo "❌ <strong style='color: red;'>Aucune session complétée - PERMET L'ACCÈS AU TEST</strong><br>";
        echo "➡️ <strong>Le candidat peut faire/refaire le test</strong><br><br>";
    }
    
    // Vérifier sessions en cours
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'in_progress' ORDER BY started_at DESC LIMIT 1");
    $stmt->execute([$candidate_id, $phase, $epreuve]);
    $existing_session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_session) {
        echo "⚠️ <strong style='color: orange;'>Session en cours trouvée</strong><br>";
        echo "- Session ID: {$existing_session['id']}<br>";
        echo "- Démarrée: {$existing_session['started_at']}<br>";
        echo "➡️ <strong>Devrait reprendre le test existant</strong><br><br>";
    }
    
    echo "<h3>3. Vérification candidate_phase_results</h3>";
    
    $stmt = $pdo->prepare("SELECT * FROM candidate_phase_results WHERE candidate_id = ? AND phase = ? AND epreuve = ?");
    $stmt->execute([$candidate_id, $phase, $epreuve]);
    $phase_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($phase_results) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f8f9fa;'><th>ID</th><th>Score</th><th>Status</th><th>Session ID</th><th>Terminé</th></tr>";
        foreach ($phase_results as $result) {
            echo "<tr>";
            echo "<td>{$result['id']}</td>";
            echo "<td>" . number_format($result['score'], 1) . "%</td>";
            echo "<td>{$result['status']}</td>";
            echo "<td>" . ($result['session_id'] ?? 'N/A') . "</td>";
            echo "<td>" . ($result['completed_at'] ? date('d/m H:i', strtotime($result['completed_at'])) : 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Aucun résultat dans candidate_phase_results";
    }
    
    echo "<h3>4. Actions de correction</h3>";
    echo "<button onclick='clearAllInProgress()' style='background: #dc3545; color: white; border: none; padding: 8px 16px; margin: 5px;'>Supprimer toutes sessions en cours</button>";
    echo "<button onclick='createCompletedTest()' style='background: #28a745; color: white; border: none; padding: 8px 16px; margin: 5px;'>Créer test complété (85%)</button>";
    echo "<button onclick='clearAllSessions()' style='background: #6c757d; color: white; border: none; padding: 8px 16px; margin: 5px;'>Supprimer toutes sessions</button>";
    echo "<button onclick='testAccess()' style='background: #007bff; color: white; border: none; padding: 8px 16px; margin: 5px;'>Tester accès QCM</button>";
    
    echo "<div id='result' style='margin-top: 20px; padding: 10px; border: 1px solid #ddd; background: #f8f9fa;'></div>";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
?>

<script>
function expireSession(sessionId) {
    fetch('debug_thi_retake_issue.php?action=expire_session&session_id=' + sessionId, {
        method: 'POST'
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('result').innerHTML = data;
        setTimeout(() => location.reload(), 1000);
    });
}

function clearAllInProgress() {
    if (confirm('Supprimer toutes les sessions en cours ?')) {
        fetch('debug_thi_retake_issue.php?action=clear_in_progress', {
            method: 'POST'
        })
        .then(response => response.text())
        .then(data => {
            document.getElementById('result').innerHTML = data;
            setTimeout(() => location.reload(), 1000);
        });
    }
}

function createCompletedTest() {
    if (confirm('Créer un test complété avec 85% ?')) {
        fetch('debug_thi_retake_issue.php?action=create_completed', {
            method: 'POST'
        })
        .then(response => response.text())
        .then(data => {
            document.getElementById('result').innerHTML = data;
            setTimeout(() => location.reload(), 1000);
        });
    }
}

function clearAllSessions() {
    if (confirm('ATTENTION: Supprimer TOUTES les sessions THI ? Cette action est irréversible.')) {
        fetch('debug_thi_retake_issue.php?action=clear_all', {
            method: 'POST'
        })
        .then(response => response.text())
        .then(data => {
            document.getElementById('result').innerHTML = data;
            setTimeout(() => location.reload(), 1000);
        });
    }
}

function testAccess() {
    window.open('candidate_qcm.php?phase=phase1&epreuve=THI', '_blank');
}
</script>

<?php
// Actions AJAX
if (isset($_GET['action'])) {
    $candidate_id = $_SESSION['candidate_id'];
    $phase = 'phase1';
    $epreuve = 'THI';
    
    try {
        switch ($_GET['action']) {
            case 'expire_session':
                $session_id = $_GET['session_id'];
                $pdo->exec("UPDATE qcm_sessions SET status = 'expired' WHERE id = $session_id");
                echo "✅ Session $session_id expirée";
                break;
                
            case 'clear_in_progress':
                $pdo->exec("UPDATE qcm_sessions SET status = 'expired' WHERE candidate_id = $candidate_id AND status = 'in_progress'");
                echo "✅ Toutes les sessions en cours ont été expirées";
                break;
                
            case 'create_completed':
                // Créer une session complétée
                $stmt = $pdo->prepare("INSERT INTO qcm_sessions (candidate_id, phase, epreuve, status, score, correct_answers, total_questions, started_at, completed_at) VALUES (?, ?, ?, 'completed', 85.0, 17, 20, NOW() - INTERVAL 1 HOUR, NOW())");
                $stmt->execute([$candidate_id, $phase, $epreuve]);
                $session_id = $pdo->lastInsertId();
                
                // Ajouter dans candidate_phase_results
                $stmt = $pdo->prepare("INSERT INTO candidate_phase_results (candidate_id, phase, epreuve, score, status, session_id, completed_at) VALUES (?, ?, ?, 85.0, 'passed', ?, NOW()) ON DUPLICATE KEY UPDATE score = 85.0, status = 'passed', session_id = ?, completed_at = NOW()");
                $stmt->execute([$candidate_id, $phase, $epreuve, $session_id, $session_id]);
                
                echo "✅ Test complété créé avec 85% (Session ID: $session_id)";
                break;
                
            case 'clear_all':
                $pdo->exec("DELETE FROM qcm_sessions WHERE candidate_id = $candidate_id AND phase = '$phase' AND epreuve = '$epreuve'");
                $pdo->exec("DELETE FROM candidate_phase_results WHERE candidate_id = $candidate_id AND phase = '$phase' AND epreuve = '$epreuve'");
                echo "✅ Toutes les sessions et résultats THI supprimés";
                break;
        }
    } catch (Exception $e) {
        echo "❌ Erreur: " . $e->getMessage();
    }
    exit;
}
?>
