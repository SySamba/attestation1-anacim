<?php
// Vérifier les données de session pour Modou Sy
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== VÉRIFICATION SESSION MODOU SY ===\n\n";
    
    // Vérifier les données en base
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = 10 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo "DONNÉES EN BASE:\n";
        echo "Session ID: {$session['id']}\n";
        echo "Score: {$session['score']}%\n";
        echo "Réponses correctes: {$session['correct_answers']}\n";
        echo "Total questions: {$session['total_questions']}\n";
        echo "Statut: {$session['status']}\n";
        echo "Complété le: {$session['completed_at']}\n\n";
        
        // Vérifier les réponses individuelles
        $stmt = $pdo->prepare("SELECT * FROM qcm_answers WHERE session_id = ?");
        $stmt->execute([$session['id']]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "RÉPONSES INDIVIDUELLES:\n";
        $correct_count = 0;
        foreach ($answers as $answer) {
            echo "Q{$answer['question_id']}: '{$answer['selected_answer']}' - " . 
                 ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
            if ($answer['is_correct']) $correct_count++;
        }
        
        echo "\nCOMPTAGE RÉEL: $correct_count/" . count($answers) . "\n";
        $real_score = ($correct_count / count($answers)) * 100;
        echo "SCORE RÉEL: $real_score%\n";
        
        if ($real_score != $session['score']) {
            echo "\n🚨 INCOHÉRENCE DÉTECTÉE!\n";
            echo "Score en session: {$session['score']}%\n";
            echo "Score calculé: $real_score%\n";
            
            // Corriger l'incohérence
            $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ? WHERE id = ?");
            $stmt->execute([$real_score, $correct_count, $session['id']]);
            echo "✅ INCOHÉRENCE CORRIGÉE!\n";
        }
    } else {
        echo "Aucune session trouvée pour candidat 10\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
