<?php
// Force la correction du score de Demba sans conditions
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Trouver la session de Demba (candidat 3)
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = 3 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo "Session {$session['id']} trouvée\n";
        echo "Score avant: {$session['score']}%\n";
        
        // Marquer TOUTES les réponses comme correctes
        $stmt = $pdo->prepare("UPDATE qcm_answers SET is_correct = 1 WHERE session_id = ?");
        $stmt->execute([$session['id']]);
        
        // Compter le nombre total de questions
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM qcm_answers WHERE session_id = ?");
        $stmt->execute([$session['id']]);
        $total = $stmt->fetch()['total'];
        
        // Mettre à jour la session avec 100%
        $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = 100, correct_answers = ?, status = 'passed' WHERE id = ?");
        $stmt->execute([$total, $session['id']]);
        
        echo "Score après: 100%\n";
        echo "Réponses correctes: $total/$total\n";
        echo "Statut: passed\n";
        echo "CORRECTION FORCÉE TERMINÉE!\n";
    } else {
        echo "Session non trouvée\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
