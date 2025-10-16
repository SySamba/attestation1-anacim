<?php
session_start();
require_once 'config.php';

echo "<h2>Debug Page Reload Issue</h2>";

$candidate_id = $_SESSION['candidate_id'] ?? 1;
$phase = 'phase1';
$epreuve = 'THI';

try {
    echo "<h3>1. Sessions pour candidat $candidate_id</h3>";
    
    // Vérifier toutes les sessions
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY started_at DESC");
    $stmt->execute([$candidate_id]);
    $all_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Phase</th><th>Epreuve</th><th>Status</th><th>Score</th><th>Started</th><th>Completed</th></tr>";
    foreach ($all_sessions as $session) {
        $bg_color = '';
        if ($session['status'] === 'completed') $bg_color = 'background: #d4edda;';
        if ($session['status'] === 'in_progress') $bg_color = 'background: #fff3cd;';
        
        echo "<tr style='$bg_color'>";
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
    
    echo "<h3>2. Test logique candidate_qcm.php</h3>";
    
    // Test 1: Vérifier session complétée
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'completed' ORDER BY started_at DESC LIMIT 1");
    $stmt->execute([$candidate_id, $phase, $epreuve]);
    $completed_session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($completed_session) {
        echo "✅ <strong>Session complétée trouvée</strong><br>";
        echo "- ID: {$completed_session['id']}<br>";
        echo "- Score: " . number_format($completed_session['score'], 1) . "%<br>";
        echo "- Complété: {$completed_session['completed_at']}<br>";
        echo "➡️ <strong style='color: green;'>DEVRAIT AFFICHER LES RÉSULTATS</strong><br><br>";
    } else {
        echo "❌ <strong>Aucune session complétée trouvée</strong><br>";
        echo "➡️ <strong style='color: red;'>PERMET DE FAIRE LE TEST</strong><br><br>";
    }
    
    // Test 2: Vérifier session en cours
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'in_progress' ORDER BY started_at DESC LIMIT 1");
    $stmt->execute([$candidate_id, $phase, $epreuve]);
    $existing_session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_session) {
        echo "⚠️ <strong>Session en cours trouvée</strong><br>";
        echo "- ID: {$existing_session['id']}<br>";
        echo "- Démarrée: {$existing_session['started_at']}<br>";
        echo "➡️ <strong style='color: orange;'>DEVRAIT REPRENDRE LE TEST</strong><br><br>";
    } else {
        echo "✅ <strong>Aucune session en cours</strong><br><br>";
    }
    
    echo "<h3>3. Actions de debug</h3>";
    echo "<button onclick='markAsCompleted()'>Marquer dernière session comme complétée</button> ";
    echo "<button onclick='clearInProgress()'>Supprimer sessions en cours</button> ";
    echo "<button onclick='testReload()'>Tester rechargement page</button><br><br>";
    echo "<div id='debug-result'></div>";
    
    echo "<h3>4. Simulation du code candidate_qcm.php</h3>";
    echo "<pre>";
    echo "// Code actuel:\n";
    echo "if (\$completed_session) {\n";
    echo "    // Afficher les résultats\n";
    echo "    // Status: " . ($completed_session ? 'TROUVÉ' : 'NON TROUVÉ') . "\n";
    echo "} elseif (\$existing_session && \$existing_session['status'] === 'in_progress') {\n";
    echo "    // Reprendre le test\n";
    echo "    // Status: " . ($existing_session ? 'TROUVÉ' : 'NON TROUVÉ') . "\n";
    echo "} else {\n";
    echo "    // Nouveau test\n";
    echo "}\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
?>

<script>
function markAsCompleted() {
    fetch('debug_page_reload_issue.php?action=mark_completed', {
        method: 'POST'
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('debug-result').innerHTML = data;
        setTimeout(() => location.reload(), 1000);
    });
}

function clearInProgress() {
    fetch('debug_page_reload_issue.php?action=clear_progress', {
        method: 'POST'
    })
    .then(response => response.text())
    .then(data => {
        document.getElementById('debug-result').innerHTML = data;
        setTimeout(() => location.reload(), 1000);
    });
}

function testReload() {
    window.open('candidate_qcm.php?phase=phase1&epreuve=THI', '_blank');
}
</script>

<?php
// Actions AJAX
if (isset($_GET['action'])) {
    $candidate_id = $_SESSION['candidate_id'] ?? 1;
    
    if ($_GET['action'] === 'mark_completed') {
        try {
            // Marquer la dernière session comme complétée
            $pdo->exec("UPDATE qcm_sessions 
                       SET status = 'completed', completed_at = NOW(), score = 85.0, correct_answers = 8 
                       WHERE candidate_id = $candidate_id AND phase = 'phase1' AND epreuve = 'THI' 
                       ORDER BY started_at DESC LIMIT 1");
            echo "✅ Session marquée comme complétée";
        } catch (Exception $e) {
            echo "❌ Erreur: " . $e->getMessage();
        }
        exit;
    }
    
    if ($_GET['action'] === 'clear_progress') {
        try {
            $pdo->exec("UPDATE qcm_sessions 
                       SET status = 'expired' 
                       WHERE candidate_id = $candidate_id AND status = 'in_progress'");
            echo "✅ Sessions en cours supprimées";
        } catch (Exception $e) {
            echo "❌ Erreur: " . $e->getMessage();
        }
        exit;
    }
}
?>
