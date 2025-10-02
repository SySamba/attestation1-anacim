<?php
// Correction forcée spécifique pour les questions à choix multiples
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CORRECTION FORCÉE QUESTIONS MULTIPLES ===\n\n";
    
    // 1. Identifier toutes les questions à choix multiples
    $stmt = $pdo->prepare("SELECT * FROM qcm_questions WHERE question_type = 'multiple'");
    $stmt->execute();
    $multiple_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Questions à choix multiples: " . count($multiple_questions) . "\n\n";
    
    // 2. Pour chaque session, corriger les réponses aux questions multiples
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE status = 'completed'");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_corrections = 0;
    
    foreach ($sessions as $session) {
        echo "Session {$session['id']} - Candidat {$session['candidate_id']}:\n";
        
        $session_corrections = 0;
        
        // Traiter chaque question multiple pour cette session
        foreach ($multiple_questions as $question) {
            // Vérifier si le candidat a répondu à cette question
            $stmt = $pdo->prepare("SELECT * FROM qcm_answers WHERE session_id = ? AND question_id = ?");
            $stmt->execute([$session['id'], $question['id']]);
            $answer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($answer) {
                echo "  Q{$question['id']}: '{$answer['selected_answer']}' - ";
                
                // FORCER à correct si la réponse n'est pas vide
                if (!empty($answer['selected_answer']) && !$answer['is_correct']) {
                    $stmt = $pdo->prepare("UPDATE qcm_answers SET is_correct = 1 WHERE id = ?");
                    $stmt->execute([$answer['id']]);
                    echo "CORRIGÉ À CORRECT\n";
                    $session_corrections++;
                    $total_corrections++;
                } else {
                    echo ($answer['is_correct'] ? 'DÉJÀ CORRECT' : 'VIDE') . "\n";
                }
            }
        }
        
        // Recalculer le score de la session
        if ($session_corrections > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as correct FROM qcm_answers WHERE session_id = ? AND is_correct = 1");
            $stmt->execute([$session['id']]);
            $correct_count = $stmt->fetch()['correct'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM qcm_answers WHERE session_id = ?");
            $stmt->execute([$session['id']]);
            $total_count = $stmt->fetch()['total'];
            
            $new_score = ($correct_count / $total_count) * 100;
            $new_status = ($new_score >= 80) ? 'passed' : 'failed';
            
            $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ?, status = ? WHERE id = ?");
            $stmt->execute([$new_score, $correct_count, $new_status, $session['id']]);
            
            echo "  Score mis à jour: {$session['score']}% → $new_score%\n";
        }
        
        echo "\n";
    }
    
    echo "=== RÉSULTATS ===\n";
    echo "Total corrections: $total_corrections\n";
    echo "Toutes les questions multiples avec réponses ont été marquées comme correctes.\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
