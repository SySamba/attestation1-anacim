<?php
session_start();
require_once 'config.php';

echo "<h1>🔧 TEST SIMPLE AUTORISATION</h1>";

// Test direct sur BA Coutaille
$test_id = 5; // ID de BA Coutaille (à ajuster si nécessaire)

echo "<h2>1️⃣ Test Direct sur Candidat ID $test_id</h2>";

// Vérifier si l'autorisation existe
$check_sql = "SELECT * FROM test_authorizations WHERE candidate_id = ? AND test_type = 'PRATIQUE_IMAGERIE'";
$check_stmt = $pdo->prepare($check_sql);
$check_stmt->execute([$test_id]);
$existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    echo "<p>✅ Autorisation existe: ID={$existing['id']}, Authorized={$existing['authorized']}</p>";
} else {
    echo "<p>❌ Aucune autorisation trouvée</p>";
    
    // Créer l'autorisation
    $create_sql = "INSERT INTO test_authorizations (candidate_id, test_type, authorized, authorized_by, authorized_at) VALUES (?, 'PRATIQUE_IMAGERIE', 1, 1, NOW())";
    $create_stmt = $pdo->prepare($create_sql);
    $create_stmt->execute([$test_id]);
    echo "<p>✅ Autorisation créée</p>";
}

// Test de la requête utilisée dans admin_test_authorizations.php
echo "<h2>2️⃣ Test Requête Admin</h2>";
$admin_sql = "SELECT authorized FROM test_authorizations WHERE candidate_id = ? AND test_type = 'PRATIQUE_IMAGERIE' LIMIT 1";
$admin_stmt = $pdo->prepare($admin_sql);
$admin_stmt->execute([$test_id]);
$admin_result = $admin_stmt->fetch(PDO::FETCH_ASSOC);

echo "<p>Résultat requête admin: ";
if ($admin_result) {
    echo "authorized = " . $admin_result['authorized'];
    $is_authorized = ($admin_result && $admin_result['authorized'] == 1) ? 1 : 0;
    echo " → Statut: " . ($is_authorized ? 'AUTORISÉ' : 'NON AUTORISÉ');
} else {
    echo "Aucun résultat";
}
echo "</p>";

// Forcer la mise à jour
echo "<h2>3️⃣ Force Update</h2>";
$update_sql = "UPDATE test_authorizations SET authorized = 1, authorized_at = NOW() WHERE candidate_id = ? AND test_type = 'PRATIQUE_IMAGERIE'";
$update_stmt = $pdo->prepare($update_sql);
$update_stmt->execute([$test_id]);
echo "<p>✅ Mise à jour forcée</p>";

// Re-test
$retest_stmt = $pdo->prepare($admin_sql);
$retest_stmt->execute([$test_id]);
$retest_result = $retest_stmt->fetch(PDO::FETCH_ASSOC);
echo "<p>Après mise à jour: authorized = " . ($retest_result ? $retest_result['authorized'] : 'NULL') . "</p>";

echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>🔄 Maintenant testez admin_test_authorizations.php</h3>";
echo "<p>Le candidat ID $test_id devrait maintenant être AUTORISÉ</p>";
echo "</div>";
?>
