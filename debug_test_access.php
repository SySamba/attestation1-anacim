<?php
session_start();
require_once 'config.php';

// Debug script to check test access restrictions and question availability

echo "<h2>Debug Test Access & Question Availability</h2>";

// Test 1: Check THI restriction for a candidate who already completed it
echo "<h3>Test 1: THI Multiple Attempts Check</h3>";

// Get a candidate with category 1 who might have completed THI
$stmt = $pdo->prepare("
    SELECT c.id, c.prenom, c.nom, c.categorie, 
           qs.phase, qs.epreuve, qs.status, qs.completed_at
    FROM candidates c 
    LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id 
    WHERE c.categorie = '1' AND qs.epreuve = 'THI' AND qs.status = 'completed'
    LIMIT 1
");
$stmt->execute();
$thi_candidate = $stmt->fetch(PDO::FETCH_ASSOC);

if ($thi_candidate) {
    echo "Candidat trouvé: {$thi_candidate['prenom']} {$thi_candidate['nom']} (ID: {$thi_candidate['id']})<br>";
    echo "THI complété le: {$thi_candidate['completed_at']}<br>";
    
    // Check if restriction works
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'completed'");
    $stmt->execute([$thi_candidate['id'], 'phase1', 'THI']);
    $completed_session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($completed_session) {
        echo "<span style='color: green;'>✓ Restriction fonctionne: Session complétée détectée</span><br>";
    } else {
        echo "<span style='color: red;'>✗ Problème: Aucune session complétée détectée</span><br>";
    }
} else {
    echo "Aucun candidat C1 avec THI complété trouvé<br>";
}

echo "<hr>";

// Test 2: Check THB question availability
echo "<h3>Test 2: THB Question Availability</h3>";

// Check questions for THB
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM qcm_questions WHERE phase = 'phase1' AND epreuve = 'THB'");
$stmt->execute();
$thb_count = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Questions THB disponibles: {$thb_count['count']}<br>";

if ($thb_count['count'] >= 3) {
    echo "<span style='color: green;'>✓ Suffisamment de questions THB disponibles</span><br>";
} else {
    echo "<span style='color: red;'>✗ Pas assez de questions THB (minimum 3 requis)</span><br>";
}

// Check questions for THI
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM qcm_questions WHERE phase = 'phase1' AND epreuve = 'THI' AND category = '1'");
$stmt->execute();
$thi_count = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Questions THI disponibles: {$thi_count['count']}<br>";

if ($thi_count['count'] >= 3) {
    echo "<span style='color: green;'>✓ Suffisamment de questions THI disponibles</span><br>";
} else {
    echo "<span style='color: red;'>✗ Pas assez de questions THI (minimum 3 requis)</span><br>";
}

echo "<hr>";

// Test 3: Check all questions by epreuve
echo "<h3>Test 3: Répartition des Questions par Épreuve</h3>";

$stmt = $pdo->prepare("
    SELECT epreuve, phase, category, COUNT(*) as count 
    FROM qcm_questions 
    GROUP BY epreuve, phase, category 
    ORDER BY epreuve, phase, category
");
$stmt->execute();
$questions_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Épreuve</th><th>Phase</th><th>Catégorie</th><th>Nombre de Questions</th></tr>";
foreach ($questions_breakdown as $row) {
    echo "<tr>";
    echo "<td>{$row['epreuve']}</td>";
    echo "<td>{$row['phase']}</td>";
    echo "<td>{$row['category']}</td>";
    echo "<td>{$row['count']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";

// Test 4: Simulate API call for THB
echo "<h3>Test 4: Simulation API THB</h3>";

try {
    $phase = 'phase1';
    $epreuve = 'THB';
    
    // Use the new query logic
    $stmt = $pdo->prepare("SELECT * FROM qcm_questions WHERE phase = ? AND epreuve = ? ORDER BY RAND() LIMIT 20");
    $stmt->execute([$phase, $epreuve]);
    $questions_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Questions trouvées pour THB: " . count($questions_raw) . "<br>";
    
    if (count($questions_raw) >= 3) {
        echo "<span style='color: green;'>✓ API THB devrait fonctionner maintenant</span><br>";
    } else {
        echo "<span style='color: red;'>✗ API THB aura encore des problèmes</span><br>";
    }
    
} catch (Exception $e) {
    echo "<span style='color: red;'>Erreur API: " . $e->getMessage() . "</span><br>";
}

?>
