<?php
session_start();
require_once 'config.php';

echo "<h2>🔧 Fix Database Schema - Add THI to Enum</h2>";

// Current schema
echo "<h3>1. Schema Actuel</h3>";
$schema = $pdo->query("SHOW COLUMNS FROM qcm_sessions WHERE Field = 'epreuve'")->fetch(PDO::FETCH_ASSOC);
echo "<p><strong>Type actuel:</strong> {$schema['Type']}</p>";

// The problem: enum doesn't include 'THI'
echo "<div style='background: #f8d7da; padding: 15px; border: 2px solid #dc3545; border-radius: 5px;'>";
echo "<h4>🚨 PROBLÈME IDENTIFIÉ</h4>";
echo "<p>Le champ 'epreuve' est un ENUM qui ne contient pas 'THI':</p>";
echo "<p><code>{$schema['Type']}</code></p>";
echo "<p>Il faut ajouter 'THI' à cet enum</p>";
echo "</div>";

// Fix the schema
echo "<h3>2. Correction du Schema</h3>";

try {
    $alter_sql = "ALTER TABLE qcm_sessions MODIFY COLUMN epreuve ENUM('THB','FBAG','PLP','FMAG','IMAGERIE','THI') DEFAULT 'THB'";
    $pdo->exec($alter_sql);
    echo "<p style='color: green;'>✅ Schema modifié avec succès - 'THI' ajouté à l'enum</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur lors de la modification: " . $e->getMessage() . "</p>";
    exit;
}

// Verify schema change
echo "<h3>3. Vérification Schema Modifié</h3>";
$new_schema = $pdo->query("SHOW COLUMNS FROM qcm_sessions WHERE Field = 'epreuve'")->fetch(PDO::FETCH_ASSOC);
echo "<p><strong>Nouveau type:</strong> {$new_schema['Type']}</p>";

if (strpos($new_schema['Type'], 'THI') !== false) {
    echo "<p style='color: green;'>✅ 'THI' maintenant présent dans l'enum</p>";
} else {
    echo "<p style='color: red;'>❌ 'THI' toujours absent</p>";
    exit;
}

// Now update Maty's sessions
echo "<h3>4. Mise à Jour Sessions Maty</h3>";

// Find Maty
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE prenom LIKE '%maty%' AND nom LIKE '%ndiaye%'");
$stmt->execute();
$maty = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<p><strong>Candidat:</strong> {$maty['prenom']} {$maty['nom']} (ID: {$maty['id']})</p>";

// Update all her sessions to THI
$update_sql = "UPDATE qcm_sessions SET epreuve = 'THI' WHERE candidate_id = ? AND phase = 'phase1'";
$update_stmt = $pdo->prepare($update_sql);
$result = $update_stmt->execute([$maty['id']]);
$affected = $update_stmt->rowCount();

echo "<p><strong>Sessions mises à jour:</strong> $affected</p>";

// Verify the update worked
echo "<h3>5. Vérification Finale</h3>";
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

// Count THI attempts
$stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'");
$stmt->execute([$maty['id']]);
$thi_attempts = $stmt->fetch(PDO::FETCH_ASSOC)['attempts'];

echo "<p><strong>Tentatives THI détectées:</strong> $thi_attempts</p>";

if ($thi_attempts > 0) {
    // Get best score
    $stmt = $pdo->prepare("SELECT MAX(score) as best_score FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'");
    $stmt->execute([$maty['id']]);
    $best_score = $stmt->fetch(PDO::FETCH_ASSOC)['best_score'];
    
    echo "<div style='background: #d4edda; padding: 20px; border: 3px solid #28a745; border-radius: 10px;'>";
    echo "<h3>🎉 PROBLÈME RÉSOLU!</h3>";
    echo "<p><strong>Schema corrigé et sessions mises à jour</strong></p>";
    echo "<p><strong>Tentatives THI:</strong> $thi_attempts</p>";
    echo "<p><strong>Meilleur score:</strong> $best_score%</p>";
    echo "<p>Le dashboard devrait maintenant fonctionner correctement</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 20px; border: 3px solid #dc3545; border-radius: 10px;'>";
    echo "<h3>❌ PROBLÈME PERSISTE</h3>";
    echo "<p>Même après correction du schema, aucune tentative THI détectée</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='candidate_dashboard.php'>🔗 Tester le dashboard maintenant</a></p>";
?>
