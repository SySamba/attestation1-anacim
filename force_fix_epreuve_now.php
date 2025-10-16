<?php
session_start();
require_once 'config.php';

echo "<h2>🚨 FORCE FIX EPREUVE - Maty Ndiaye</h2>";

// Find Maty
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE prenom LIKE '%maty%' AND nom LIKE '%ndiaye%'");
$stmt->execute();
$maty = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<p><strong>Candidat:</strong> {$maty['prenom']} {$maty['nom']} (ID: {$maty['id']})</p>";

// Check database schema first
echo "<h3>1. Vérification Schema Table qcm_sessions</h3>";
$schema = $pdo->query("DESCRIBE qcm_sessions")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
foreach ($schema as $col) {
    $highlight = ($col['Field'] === 'epreuve') ? 'background-color: #fff3cd;' : '';
    echo "<tr style='$highlight'>";
    echo "<td><strong>{$col['Field']}</strong></td>";
    echo "<td>{$col['Type']}</td>";
    echo "<td>{$col['Null']}</td>";
    echo "<td>{$col['Default']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check current epreuve values
echo "<h3>2. Valeurs Actuelles Epreuve pour Maty</h3>";
$stmt = $pdo->prepare("SELECT id, epreuve, LENGTH(epreuve) as epreuve_length, status, score FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC LIMIT 5");
$stmt->execute([$maty['id']]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Epreuve</th><th>Length</th><th>Status</th><th>Score</th></tr>";
foreach ($sessions as $session) {
    echo "<tr>";
    echo "<td>{$session['id']}</td>";
    echo "<td>'" . htmlspecialchars($session['epreuve']) . "'</td>";
    echo "<td>{$session['epreuve_length']}</td>";
    echo "<td>{$session['status']}</td>";
    echo "<td>{$session['score']}</td>";
    echo "</tr>";
}
echo "</table>";

// Force update with different approaches
echo "<h3>3. Tentatives de Correction</h3>";

// Method 1: Direct update
echo "<h4>Méthode 1: UPDATE direct</h4>";
$update1 = "UPDATE qcm_sessions SET epreuve = 'THI' WHERE candidate_id = ? AND phase = 'phase1'";
$stmt1 = $pdo->prepare($update1);
$result1 = $stmt1->execute([$maty['id']]);
$affected1 = $stmt1->rowCount();
echo "<p>Résultat: $affected1 lignes affectées</p>";

// Check if it worked
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM qcm_sessions WHERE candidate_id = ? AND epreuve = 'THI'");
$stmt->execute([$maty['id']]);
$thi_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "<p>Sessions avec epreuve = 'THI': $thi_count</p>";

if ($thi_count > 0) {
    echo "<div style='background: #d4edda; padding: 15px; border: 2px solid #28a745; border-radius: 5px;'>";
    echo "<h4>✅ SUCCÈS!</h4>";
    echo "<p>$thi_count sessions maintenant marquées comme THI</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border: 2px solid #dc3545; border-radius: 5px;'>";
    echo "<h4>❌ ÉCHEC - Tentative alternative</h4>";
    echo "</div>";
    
    // Method 2: Update each session individually
    echo "<h4>Méthode 2: UPDATE individuel</h4>";
    $stmt = $pdo->prepare("SELECT id FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1'");
    $stmt->execute([$maty['id']]);
    $session_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $updated = 0;
    foreach ($session_ids as $session_id) {
        $update_individual = "UPDATE qcm_sessions SET epreuve = 'THI' WHERE id = ?";
        $stmt_ind = $pdo->prepare($update_individual);
        if ($stmt_ind->execute([$session_id])) {
            $updated++;
        }
    }
    echo "<p>Sessions mises à jour individuellement: $updated</p>";
}

// Final verification
echo "<h3>4. Vérification Finale</h3>";
$stmt = $pdo->prepare("SELECT id, epreuve, status, score FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC LIMIT 5");
$stmt->execute([$maty['id']]);
$final_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Epreuve</th><th>Status</th><th>Score</th></tr>";
foreach ($final_sessions as $session) {
    $color = ($session['epreuve'] === 'THI') ? 'background-color: #d4edda;' : 'background-color: #f8d7da;';
    echo "<tr style='$color'>";
    echo "<td>{$session['id']}</td>";
    echo "<td><strong>{$session['epreuve']}</strong></td>";
    echo "<td>{$session['status']}</td>";
    echo "<td>{$session['score']}</td>";
    echo "</tr>";
}
echo "</table>";

// Test dashboard logic
echo "<h3>5. Test Final Dashboard</h3>";
$stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'");
$stmt->execute([$maty['id']]);
$final_attempts = $stmt->fetch(PDO::FETCH_ASSOC)['attempts'];

echo "<p><strong>Tentatives THI finales:</strong> $final_attempts</p>";

if ($final_attempts > 0) {
    // Get best score
    $stmt = $pdo->prepare("SELECT MAX(score) as best_score FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'");
    $stmt->execute([$maty['id']]);
    $best_score = $stmt->fetch(PDO::FETCH_ASSOC)['best_score'];
    
    echo "<div style='background: #d4edda; padding: 20px; border: 3px solid #28a745; border-radius: 10px;'>";
    echo "<h3>🎉 PROBLÈME RÉSOLU!</h3>";
    echo "<p><strong>Maty a maintenant $final_attempts tentative(s) THI détectée(s)</strong></p>";
    echo "<p><strong>Meilleur score:</strong> $best_score%</p>";
    echo "<p>Le dashboard devrait maintenant afficher son résultat</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 20px; border: 3px solid #dc3545; border-radius: 10px;'>";
    echo "<h3>❌ PROBLÈME PERSISTE</h3>";
    echo "<p>La mise à jour n'a pas fonctionné - problème plus profond</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='candidate_dashboard.php'>🔗 Tester le dashboard maintenant</a></p>";
?>
