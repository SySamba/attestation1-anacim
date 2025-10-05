<?php
session_start();
require_once 'config.php';

echo "<h1>🚨 CORRECTION FORCÉE DÉFINITIVE</h1>";

// 1. Supprimer TOUTES les autorisations PRATIQUE_IMAGERIE
echo "<h2>1️⃣ Nettoyage complet</h2>";
$delete_all = $pdo->exec("DELETE FROM test_authorizations WHERE test_type = 'PRATIQUE_IMAGERIE'");
echo "<p>✅ $delete_all autorisations supprimées</p>";

// 2. Recréer les autorisations une par une
echo "<h2>2️⃣ Recréation individuelle</h2>";
$candidates_80_sql = "SELECT c.id, c.nom, c.prenom, qs.score 
                      FROM candidates c 
                      LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
                      WHERE c.status = 'accepted' AND qs.score >= 80
                      ORDER BY c.nom";

$candidates_stmt = $pdo->prepare($candidates_80_sql);
$candidates_stmt->execute();
$candidates_80 = $candidates_stmt->fetchAll(PDO::FETCH_ASSOC);

$created = 0;
foreach ($candidates_80 as $candidate) {
    $insert_sql = "INSERT INTO test_authorizations (candidate_id, test_type, authorized, authorized_by, authorized_at) 
                   VALUES (?, 'PRATIQUE_IMAGERIE', 1, 1, NOW())";
    $insert_stmt = $pdo->prepare($insert_sql);
    $insert_stmt->execute([$candidate['id']]);
    echo "<p>✅ {$candidate['nom']} {$candidate['prenom']} (ID: {$candidate['id']}) - CRÉÉ</p>";
    $created++;
}

echo "<div style='background: #d4edda; padding: 20px; border: 2px solid #28a745; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>✅ CRÉATION TERMINÉE</h3>";
echo "<p><strong>$created autorisations créées</strong></p>";
echo "</div>";

// 3. Vérification finale avec affichage détaillé
echo "<h2>3️⃣ Vérification finale</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f8f9fa;'><th>ID</th><th>Nom</th><th>Score</th><th>Auth DB</th><th>Statut Final</th></tr>";

foreach ($candidates_80 as $candidate) {
    $verify_sql = "SELECT authorized FROM test_authorizations WHERE candidate_id = ? AND test_type = 'PRATIQUE_IMAGERIE'";
    $verify_stmt = $pdo->prepare($verify_sql);
    $verify_stmt->execute([$candidate['id']]);
    $verify_result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    $auth_value = $verify_result ? $verify_result['authorized'] : 'NULL';
    $is_authorized = ($verify_result && $verify_result['authorized'] == 1);
    $status = $is_authorized ? '✅ AUTORISÉ' : '❌ ERREUR';
    $color = $is_authorized ? '#d4edda' : '#f8d7da';
    
    echo "<tr style='background: $color;'>";
    echo "<td>{$candidate['id']}</td>";
    echo "<td>{$candidate['nom']} {$candidate['prenom']}</td>";
    echo "<td>{$candidate['score']}%</td>";
    echo "<td>$auth_value</td>";
    echo "<td><strong>$status</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<div style='background: #007bff; color: white; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>🎯 MAINTENANT TESTEZ</h3>";
echo "<p><strong>1. Allez sur <a href='admin_test_authorizations.php' style='color: yellow;'>admin_test_authorizations.php</a></strong></p>";
echo "<p><strong>2. TOUS les candidats ≥80% DOIVENT maintenant afficher 'AUTORISÉ - Peut passer l'examen'</strong></p>";
echo "<p><strong>3. Si ça ne marche TOUJOURS pas, il y a un bug dans la logique d'affichage PHP</strong></p>";
echo "</div>";
?>
