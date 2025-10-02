<?php
// Debug du problème d'affichage - cache vs base de données
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DEBUG PROBLÈME AFFICHAGE MODOU SY ===\n\n";
    
    // Simuler exactement ce que fait candidate_qcm.php
    $candidate_id = 10; // Modou Sy
    
    echo "1. REQUÊTE COMME DANS candidate_qcm.php:\n";
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND status IN ('in_progress', 'completed') ORDER BY started_at DESC LIMIT 1");
    $stmt->execute([$candidate_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo "Session trouvée: {$session['id']}\n";
        echo "Score affiché: {$session['score']}%\n";
        echo "Correctes: {$session['correct_answers']}\n";
        echo "Total: {$session['total_questions']}\n";
        echo "Statut: {$session['status']}\n";
        echo "Complété: {$session['completed_at']}\n\n";
        
        // Vérifier si c'est un problème de cache de requête
        echo "2. REQUÊTE DIRECTE PAR ID:\n";
        $stmt2 = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id = ?");
        $stmt2->execute([$session['id']]);
        $session2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        echo "Score par ID: {$session2['score']}%\n";
        echo "Statut par ID: {$session2['status']}\n\n";
        
        // Vérifier les réponses
        echo "3. VÉRIFICATION RÉPONSES:\n";
        $stmt3 = $pdo->prepare("SELECT COUNT(*) as correct FROM qcm_answers WHERE session_id = ? AND is_correct = 1");
        $stmt3->execute([$session['id']]);
        $correct_count = $stmt3->fetch()['correct'];
        
        $stmt4 = $pdo->prepare("SELECT COUNT(*) as total FROM qcm_answers WHERE session_id = ?");
        $stmt4->execute([$session['id']]);
        $total_count = $stmt4->fetch()['total'];
        
        echo "Réponses correctes: $correct_count\n";
        echo "Total réponses: $total_count\n";
        echo "Score calculé: " . (($correct_count / $total_count) * 100) . "%\n\n";
        
        // FORCER une mise à jour complète
        echo "4. FORCE UPDATE:\n";
        $new_score = ($correct_count / $total_count) * 100;
        $new_status = ($new_score >= 80) ? 'passed' : 'failed';
        
        $stmt5 = $pdo->prepare("UPDATE qcm_sessions SET 
                               score = ?, 
                               correct_answers = ?, 
                               status = ?, 
                               completed_at = NOW() 
                               WHERE id = ?");
        $stmt5->execute([$new_score, $correct_count, $new_status, $session['id']]);
        
        echo "Mise à jour forcée: Score = $new_score%, Statut = $new_status\n";
        
        // Vérifier immédiatement après
        echo "5. VÉRIFICATION POST-UPDATE:\n";
        $stmt6 = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id = ?");
        $stmt6->execute([$session['id']]);
        $updated = $stmt6->fetch(PDO::FETCH_ASSOC);
        
        echo "Score après update: {$updated['score']}%\n";
        echo "Statut après update: {$updated['status']}\n";
        
        if ($updated['score'] == $new_score) {
            echo "\n✅ MISE À JOUR RÉUSSIE EN BASE\n";
            echo "Le problème peut venir du cache navigateur.\n";
            echo "Essayez Ctrl+F5 ou mode incognito.\n";
        } else {
            echo "\n❌ PROBLÈME DE MISE À JOUR EN BASE\n";
        }
    } else {
        echo "Aucune session trouvée\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
