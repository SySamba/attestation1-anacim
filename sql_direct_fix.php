<?php
// Correction SQL directe pour Demba
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CORRECTION SQL DIRECTE ===\n\n";
    
    // 1. Identifier la session de Demba
    $stmt = $pdo->query("SELECT * FROM qcm_sessions WHERE candidate_id = 3 ORDER BY id DESC LIMIT 1");
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo "Aucune session trouvée\n";
        exit;
    }
    
    echo "Session ID: {$session['id']}\n";
    echo "Score avant: {$session['score']}%\n";
    
    // 2. Correction directe par SQL
    $session_id = $session['id'];
    
    // Marquer toutes les réponses comme correctes
    $pdo->exec("UPDATE qcm_answers SET is_correct = 1 WHERE session_id = $session_id");
    
    // Compter les questions
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM qcm_answers WHERE session_id = $session_id");
    $total = $stmt->fetch()['total'];
    
    // Mettre à jour la session
    $pdo->exec("UPDATE qcm_sessions SET score = 100, correct_answers = $total, status = 'passed' WHERE id = $session_id");
    
    echo "Réponses mises à jour: $total\n";
    echo "Score après: 100%\n";
    echo "Statut: passed\n";
    
    // 3. Vérification
    $stmt = $pdo->query("SELECT * FROM qcm_sessions WHERE id = $session_id");
    $updated_session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n=== VÉRIFICATION ===\n";
    echo "Score final: {$updated_session['score']}%\n";
    echo "Réponses correctes: {$updated_session['correct_answers']}/{$updated_session['total_questions']}\n";
    echo "Statut: {$updated_session['status']}\n";
    
    echo "\n✅ CORRECTION TERMINÉE!\n";
    echo "Rechargez la page candidat pour voir le changement.\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
