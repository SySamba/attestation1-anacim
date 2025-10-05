<?php
session_start();
require_once 'config.php';

echo "<h1>🧹 NETTOYAGE SYSTÈME D'AUTORISATION</h1>";

// 1. Supprimer toutes les autorisations PRATIQUE_IMAGERIE
echo "<h2>1️⃣ Suppression des autorisations Pratique Imagerie</h2>";
$delete_count = $pdo->exec("DELETE FROM test_authorizations WHERE test_type = 'PRATIQUE_IMAGERIE'");
echo "<p>✅ $delete_count autorisations supprimées</p>";

// 2. Vérifier qu'il ne reste que THI/THB
echo "<h2>2️⃣ Vérification des autorisations restantes</h2>";
$remaining_stmt = $pdo->query("SELECT test_type, COUNT(*) as count FROM test_authorizations GROUP BY test_type");
$remaining = $remaining_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($remaining)) {
    echo "<p>✅ Aucune autorisation restante</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Type de Test</th><th>Nombre</th></tr>";
    foreach ($remaining as $row) {
        echo "<tr><td>{$row['test_type']}</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
}

// 3. Test de l'accès direct pour quelques candidats
echo "<h2>3️⃣ Test accès direct basé sur le score</h2>";
$test_candidates_sql = "SELECT c.id, c.nom, c.prenom, c.categorie, qs.score 
                        FROM candidates c 
                        LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
                        WHERE c.status = 'accepted' 
                        ORDER BY qs.score DESC 
                        LIMIT 10";

$test_stmt = $pdo->prepare($test_candidates_sql);
$test_stmt->execute();
$test_candidates = $test_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f8f9fa;'><th>Nom</th><th>Catégorie</th><th>Score Phase 1</th><th>Accès Imagerie</th></tr>";

foreach ($test_candidates as $candidate) {
    $score = $candidate['score'] ?? 0;
    $can_access = $score >= 80;
    $status = $can_access ? '✅ AUTORISÉ' : '❌ BLOQUÉ';
    $color = $can_access ? '#d4edda' : '#f8d7da';
    
    echo "<tr style='background: $color;'>";
    echo "<td>{$candidate['nom']} {$candidate['prenom']}</td>";
    echo "<td>Catégorie {$candidate['categorie']}</td>";
    echo "<td>{$score}%</td>";
    echo "<td><strong>$status</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<div style='background: #d1ecf1; padding: 20px; border: 2px solid #bee5eb; border-radius: 10px; margin: 20px 0;'>";
echo "<h3>✅ SYSTÈME SIMPLIFIÉ</h3>";
echo "<p><strong>Maintenant :</strong></p>";
echo "<ul>";
echo "<li>✅ Plus besoin d'autoriser manuellement la Pratique Imagerie</li>";
echo "<li>✅ Accès automatique si score Phase 1 ≥ 80%</li>";
echo "<li>✅ Logique simplifiée dans candidate_dashboard.php</li>";
echo "<li>✅ Bouton 'Autorisations Tests' retiré du dashboard admin</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>🎯 TESTEZ MAINTENANT</h3>";
echo "<p>1. Connectez-vous comme candidat avec score ≥80%</p>";
echo "<p>2. La Pratique Imagerie doit être accessible directement</p>";
echo "<p>3. Plus besoin de passer par admin_test_authorizations.php</p>";
echo "</div>";
?>
