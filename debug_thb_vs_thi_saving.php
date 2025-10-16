<?php
session_start();
require_once 'config.php';

echo "<h2>Debug THB vs THI - Comparaison Sauvegarde</h2>";

$candidate_id = $_SESSION['candidate_id'] ?? 1;

try {
    echo "<h3>1. Sessions THB vs THI pour candidat $candidate_id</h3>";
    
    // Sessions THB
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND epreuve = 'THB' ORDER BY started_at DESC");
    $stmt->execute([$candidate_id]);
    $thb_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sessions THI
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND epreuve = 'THI' ORDER BY started_at DESC");
    $stmt->execute([$candidate_id]);
    $thi_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div style='display: flex; gap: 20px;'>";
    
    // THB Sessions
    echo "<div style='flex: 1;'>";
    echo "<h4>Sessions THB (" . count($thb_sessions) . ")</h4>";
    if (empty($thb_sessions)) {
        echo "<p>❌ Aucune session THB</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f8f9fa;'><th>ID</th><th>Status</th><th>Score</th><th>Correct</th><th>Total</th><th>Terminé</th></tr>";
        foreach ($thb_sessions as $session) {
            $bg = $session['status'] === 'completed' ? 'background: #d4edda;' : '';
            echo "<tr style='$bg'>";
            echo "<td>{$session['id']}</td>";
            echo "<td><strong>{$session['status']}</strong></td>";
            echo "<td>" . ($session['score'] ? number_format($session['score'], 1) . '%' : 'N/A') . "</td>";
            echo "<td>" . ($session['correct_answers'] ?? 'N/A') . "</td>";
            echo "<td>{$session['total_questions']}</td>";
            echo "<td>" . ($session['completed_at'] ? date('d/m H:i', strtotime($session['completed_at'])) : 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // THI Sessions
    echo "<div style='flex: 1;'>";
    echo "<h4>Sessions THI (" . count($thi_sessions) . ")</h4>";
    if (empty($thi_sessions)) {
        echo "<p>❌ Aucune session THI</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f8f9fa;'><th>ID</th><th>Status</th><th>Score</th><th>Correct</th><th>Total</th><th>Terminé</th></tr>";
        foreach ($thi_sessions as $session) {
            $bg = $session['status'] === 'completed' ? 'background: #d4edda;' : '';
            echo "<tr style='$bg'>";
            echo "<td>{$session['id']}</td>";
            echo "<td><strong>{$session['status']}</strong></td>";
            echo "<td>" . ($session['score'] ? number_format($session['score'], 1) . '%' : 'N/A') . "</td>";
            echo "<td>" . ($session['correct_answers'] ?? 'N/A') . "</td>";
            echo "<td>{$session['total_questions']}</td>";
            echo "<td>" . ($session['completed_at'] ? date('d/m H:i', strtotime($session['completed_at'])) : 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    echo "</div>";
    
    echo "<h3>2. Résultats dans candidate_phase_results</h3>";
    
    // Résultats THB
    $stmt = $pdo->prepare("SELECT * FROM candidate_phase_results WHERE candidate_id = ? AND epreuve = 'THB' ORDER BY completed_at DESC");
    $stmt->execute([$candidate_id]);
    $thb_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Résultats THI
    $stmt = $pdo->prepare("SELECT * FROM candidate_phase_results WHERE candidate_id = ? AND epreuve = 'THI' ORDER BY completed_at DESC");
    $stmt->execute([$candidate_id]);
    $thi_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div style='display: flex; gap: 20px;'>";
    
    // THB Results
    echo "<div style='flex: 1;'>";
    echo "<h4>Résultats THB (" . count($thb_results) . ")</h4>";
    if (empty($thb_results)) {
        echo "<p>❌ Aucun résultat THB dans candidate_phase_results</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f8f9fa;'><th>ID</th><th>Score</th><th>Status</th><th>Session ID</th><th>Terminé</th></tr>";
        foreach ($thb_results as $result) {
            $bg = $result['status'] === 'passed' ? 'background: #d4edda;' : 'background: #f8d7da;';
            echo "<tr style='$bg'>";
            echo "<td>{$result['id']}</td>";
            echo "<td>" . number_format($result['score'], 1) . "%</td>";
            echo "<td><strong>{$result['status']}</strong></td>";
            echo "<td>" . ($result['session_id'] ?? 'N/A') . "</td>";
            echo "<td>" . ($result['completed_at'] ? date('d/m H:i', strtotime($result['completed_at'])) : 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // THI Results
    echo "<div style='flex: 1;'>";
    echo "<h4>Résultats THI (" . count($thi_results) . ")</h4>";
    if (empty($thi_results)) {
        echo "<p>❌ Aucun résultat THI dans candidate_phase_results</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f8f9fa;'><th>ID</th><th>Score</th><th>Status</th><th>Session ID</th><th>Terminé</th></tr>";
        foreach ($thi_results as $result) {
            $bg = $result['status'] === 'passed' ? 'background: #d4edda;' : 'background: #f8d7da;';
            echo "<tr style='$bg'>";
            echo "<td>{$result['id']}</td>";
            echo "<td>" . number_format($result['score'], 1) . "%</td>";
            echo "<td><strong>{$result['status']}</strong></td>";
            echo "<td>" . ($result['session_id'] ?? 'N/A') . "</td>";
            echo "<td>" . ($result['completed_at'] ? date('d/m H:i', strtotime($result['completed_at'])) : 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    echo "</div>";
    
    echo "<h3>3. Analyse des différences</h3>";
    
    $thb_completed = array_filter($thb_sessions, function($s) { return $s['status'] === 'completed'; });
    $thi_completed = array_filter($thi_sessions, function($s) { return $s['status'] === 'completed'; });
    
    echo "<div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Comparaison:</h4>";
    echo "<ul>";
    echo "<li><strong>THB:</strong> " . count($thb_completed) . " sessions complétées → " . count($thb_results) . " résultats sauvés</li>";
    echo "<li><strong>THI:</strong> " . count($thi_completed) . " sessions complétées → " . count($thi_results) . " résultats sauvés</li>";
    echo "</ul>";
    
    if (count($thb_completed) > 0 && count($thb_results) > 0) {
        echo "<p style='color: green;'>✅ <strong>THB fonctionne correctement</strong> - sessions complétées sont sauvées dans candidate_phase_results</p>";
    }
    
    if (count($thi_completed) > 0 && count($thi_results) === 0) {
        echo "<p style='color: red;'>❌ <strong>THI a un problème</strong> - sessions complétées mais pas de résultats sauvés</p>";
    }
    echo "</div>";
    
    echo "<h3>4. Test de sauvegarde manuelle</h3>";
    echo "<button onclick='testThbSave()' style='background: #28a745; color: white; border: none; padding: 8px 16px; margin: 5px;'>Tester sauvegarde THB</button>";
    echo "<button onclick='testThiSave()' style='background: #007bff; color: white; border: none; padding: 8px 16px; margin: 5px;'>Tester sauvegarde THI</button>";
    echo "<button onclick='clearAllResults()' style='background: #dc3545; color: white; border: none; padding: 8px 16px; margin: 5px;'>Nettoyer résultats</button>";
    
    echo "<div id='test-result' style='margin-top: 20px; padding: 10px; border: 1px solid #ddd; background: #f8f9fa;'></div>";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
?>

<script>
function testThbSave() {
    fetch('debug_thb_vs_thi_saving.php?action=test_thb_save', {
        method: 'POST'
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('test-result').innerHTML = data;
        setTimeout(() => location.reload(), 2000);
    });
}

function testThiSave() {
    fetch('debug_thb_vs_thi_saving.php?action=test_thi_save', {
        method: 'POST'
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('test-result').innerHTML = data;
        setTimeout(() => location.reload(), 2000);
    });
}

function clearAllResults() {
    if (confirm('Supprimer tous les résultats de test ?')) {
        fetch('debug_thb_vs_thi_saving.php?action=clear_all', {
            method: 'POST'
        })
        .then(response => response.text())
        .then(data => {
            document.getElementById('test-result').innerHTML = data;
            setTimeout(() => location.reload(), 2000);
        });
    }
}
</script>

<?php
// Actions AJAX
if (isset($_GET['action'])) {
    $candidate_id = $_SESSION['candidate_id'] ?? 1;
    
    try {
        switch ($_GET['action']) {
            case 'test_thb_save':
                // Créer session THB complétée
                $stmt = $pdo->prepare("INSERT INTO qcm_sessions (candidate_id, phase, epreuve, status, score, correct_answers, total_questions, started_at, completed_at) VALUES (?, 'phase1', 'THB', 'completed', 85.0, 17, 20, NOW() - INTERVAL 1 HOUR, NOW())");
                $stmt->execute([$candidate_id]);
                $session_id = $pdo->lastInsertId();
                
                // Sauver dans candidate_phase_results comme le fait qcm_api.php
                $stmt = $pdo->prepare("INSERT INTO candidate_phase_results (candidate_id, phase, epreuve, score, status, session_id, completed_at) VALUES (?, 'phase1', 'THB', 85.0, 'passed', ?, NOW()) ON DUPLICATE KEY UPDATE score = 85.0, status = 'passed', session_id = ?, completed_at = NOW()");
                $stmt->execute([$candidate_id, $session_id, $session_id]);
                
                echo "✅ Test THB sauvé avec succès (Session ID: $session_id)";
                break;
                
            case 'test_thi_save':
                // Créer session THI complétée
                $stmt = $pdo->prepare("INSERT INTO qcm_sessions (candidate_id, phase, epreuve, status, score, correct_answers, total_questions, started_at, completed_at) VALUES (?, 'phase1', 'THI', 'completed', 90.0, 18, 20, NOW() - INTERVAL 1 HOUR, NOW())");
                $stmt->execute([$candidate_id]);
                $session_id = $pdo->lastInsertId();
                
                // Sauver dans candidate_phase_results comme le fait qcm_api.php
                $stmt = $pdo->prepare("INSERT INTO candidate_phase_results (candidate_id, phase, epreuve, score, status, session_id, completed_at) VALUES (?, 'phase1', 'THI', 90.0, 'passed', ?, NOW()) ON DUPLICATE KEY UPDATE score = 90.0, status = 'passed', session_id = ?, completed_at = NOW()");
                $stmt->execute([$candidate_id, $session_id, $session_id]);
                
                echo "✅ Test THI sauvé avec succès (Session ID: $session_id)";
                break;
                
            case 'clear_all':
                $pdo->exec("DELETE FROM candidate_phase_results WHERE candidate_id = $candidate_id");
                $pdo->exec("DELETE FROM qcm_sessions WHERE candidate_id = $candidate_id");
                echo "✅ Tous les résultats supprimés";
                break;
        }
    } catch (Exception $e) {
        echo "❌ Erreur: " . $e->getMessage();
    }
    exit;
}
?>
