<?php
session_start();
require_once 'config.php';

echo "<h1>🔍 DIAGNOSTIC FINAL - AUTORISATIONS</h1>";

// 1. Check database structure
echo "<h2>1️⃣ Structure Table test_authorizations</h2>";
$structure = $pdo->query("DESCRIBE test_authorizations")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Key</th></tr>";
foreach ($structure as $col) {
    echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Key']}</td></tr>";
}
echo "</table>";

// 2. Check all data in test_authorizations
echo "<h2>2️⃣ Contenu Table test_authorizations</h2>";
$all_auth = $pdo->query("SELECT * FROM test_authorizations WHERE test_type = 'PRATIQUE_IMAGERIE'")->fetchAll(PDO::FETCH_ASSOC);
echo "<p><strong>Nombre d'enregistrements:</strong> " . count($all_auth) . "</p>";
echo "<table border='1'><tr><th>ID</th><th>Candidate ID</th><th>Authorized</th><th>Date</th></tr>";
foreach ($all_auth as $auth) {
    echo "<tr><td>{$auth['id']}</td><td>{$auth['candidate_id']}</td><td>" . ($auth['authorized'] ? 'OUI' : 'NON') . "</td><td>{$auth['authorized_at']}</td></tr>";
}
echo "</table>";

// 3. Check candidates with scores
echo "<h2>3️⃣ Candidats avec Scores ≥80%</h2>";
$candidates_80 = $pdo->query("SELECT c.id, c.nom, c.prenom, qs.score 
                              FROM candidates c 
                              LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
                              WHERE c.status = 'accepted' AND qs.score >= 80
                              ORDER BY c.nom")->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1'><tr><th>ID</th><th>Nom</th><th>Score</th><th>Autorisation DB</th></tr>";
foreach ($candidates_80 as $candidate) {
    $auth_check = $pdo->prepare("SELECT authorized FROM test_authorizations WHERE candidate_id = ? AND test_type = 'PRATIQUE_IMAGERIE'");
    $auth_check->execute([$candidate['id']]);
    $auth_result = $auth_check->fetch(PDO::FETCH_ASSOC);
    $authorized = $auth_result ? ($auth_result['authorized'] ? 'OUI' : 'NON') : 'AUCUN';
    
    echo "<tr><td>{$candidate['id']}</td><td>{$candidate['nom']} {$candidate['prenom']}</td><td>{$candidate['score']}%</td><td><strong>$authorized</strong></td></tr>";
}
echo "</table>";

// 4. Force fix ALL
echo "<h2>4️⃣ CORRECTION FORCÉE</h2>";
$fixed = 0;
foreach ($candidates_80 as $candidate) {
    $fix_sql = "INSERT INTO test_authorizations (candidate_id, test_type, authorized, authorized_by, authorized_at) 
                VALUES (?, 'PRATIQUE_IMAGERIE', 1, 1, NOW())
                ON DUPLICATE KEY UPDATE authorized = 1, authorized_by = 1, authorized_at = NOW()";
    $fix_stmt = $pdo->prepare($fix_sql);
    $fix_stmt->execute([$candidate['id']]);
    $fixed++;
}

echo "<div style='background: #d4edda; padding: 20px; border: 2px solid #28a745; border-radius: 10px;'>";
echo "<h3>✅ CORRECTION TERMINÉE</h3>";
echo "<p><strong>$fixed candidats corrigés</strong></p>";
echo "</div>";

// 5. Final verification
echo "<h2>5️⃣ VÉRIFICATION FINALE</h2>";
echo "<table border='1'><tr><th>Candidat</th><th>Score</th><th>Autorisé</th><th>Statut</th></tr>";
foreach ($candidates_80 as $candidate) {
    $final_check = $pdo->prepare("SELECT authorized FROM test_authorizations WHERE candidate_id = ? AND test_type = 'PRATIQUE_IMAGERIE'");
    $final_check->execute([$candidate['id']]);
    $final_result = $final_check->fetch(PDO::FETCH_ASSOC);
    $is_authorized = $final_result && $final_result['authorized'];
    $status = $is_authorized ? '✅ AUTORISÉ' : '❌ PROBLÈME';
    $color = $is_authorized ? '#d4edda' : '#f8d7da';
    
    echo "<tr style='background: $color;'>";
    echo "<td>{$candidate['nom']} {$candidate['prenom']}</td>";
    echo "<td>{$candidate['score']}%</td>";
    echo "<td>" . ($is_authorized ? 'OUI' : 'NON') . "</td>";
    echo "<td><strong>$status</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<div style='background: #cce5ff; padding: 20px; border: 2px solid #007bff; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>🎯 MAINTENANT</h3>";
echo "<p><strong>Allez sur <a href='admin_test_authorizations.php'>admin_test_authorizations.php</a></strong></p>";
echo "<p>TOUS les candidats ≥80% devraient maintenant afficher 'AUTORISÉ - Peut passer l'examen'</p>";
echo "</div>";
?>
