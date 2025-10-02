<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== RECALCUL DE TOUS LES SCORES ===\n\n";
    
    // Récupérer toutes les sessions complétées
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE status = 'completed'");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Sessions à recalculer: " . count($sessions) . "\n\n";
    
    foreach ($sessions as $session) {
        echo "Session {$session['id']} - Candidat {$session['candidate_id']}:\n";
        echo "Score actuel: {$session['score']}%\n";
        
        // Récupérer toutes les réponses
        $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                              JOIN qcm_questions q ON qa.question_id = q.id 
                              WHERE qa.session_id = ?");
        $stmt->execute([$session['id']]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $correct_count = 0;
        $total_questions = count($answers);
        
        foreach ($answers as $answer) {
            // Récupérer les choix pour cette question
            $stmt_choices = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
            $stmt_choices->execute([$answer['question_id']]);
            $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
            
            // Trier par ID pour avoir l'ordre correct
            usort($choices, function($a, $b) { return $a['id'] - $b['id']; });
            
            $is_correct = false;
            $question_type = $answer['question_type'] ?? 'single';
            
            if ($question_type === 'multiple') {
                // Questions à choix multiples
                $correct_letters = [];
                foreach ($choices as $index => $choice) {
                    if ($choice['is_correct'] == 1) {
                        $correct_letters[] = chr(97 + $index);
                    }
                }
                
                $user_answers = explode(',', $answer['selected_answer']);
                sort($user_answers);
                sort($correct_letters);
                $is_correct = ($user_answers === $correct_letters);
            } else {
                // Questions à choix unique
                $correct_letter = null;
                foreach ($choices as $index => $choice) {
                    if ($choice['is_correct'] == 1) {
                        $correct_letter = chr(97 + $index);
                        break;
                    }
                }
                $is_correct = ($answer['selected_answer'] === $correct_letter);
            }
            
            if ($is_correct) {
                $correct_count++;
            }
            
            // Mettre à jour la réponse si nécessaire
            if ($is_correct != $answer['is_correct']) {
                $stmt_update = $pdo->prepare("UPDATE qcm_answers SET is_correct = ? WHERE id = ?");
                $stmt_update->execute([$is_correct ? 1 : 0, $answer['id']]);
                echo "  Q{$answer['question_id']}: Corrigé " . ($is_correct ? 'INCORRECT->CORRECT' : 'CORRECT->INCORRECT') . "\n";
            }
        }
        
        // Calculer le nouveau score
        $new_score = ($correct_count / $total_questions) * 100;
        
        echo "Nouveau score: $new_score% ($correct_count/$total_questions)\n";
        
        // Mettre à jour la session
        $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ? WHERE id = ?");
        $stmt->execute([$new_score, $correct_count, $session['id']]);
        
        if (abs($new_score - $session['score']) > 0.1) {
            echo "✓ SCORE CORRIGÉ: {$session['score']}% -> $new_score%\n";
        } else {
            echo "Score inchangé\n";
        }
        echo "\n";
    }
    
    echo "=== RECALCUL TERMINÉ ===\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
