<?php
require_once 'config/database.php';

echo "=== CORRECTION FORCÉE DES QUESTIONS À CHOIX MULTIPLES ===\n\n";

// 1. D'abord, modifier la structure de la table si ce n'est pas déjà fait
try {
    $pdo->exec("ALTER TABLE qcm_answers MODIFY COLUMN selected_answer VARCHAR(50) NOT NULL");
    echo "✓ Structure de table mise à jour\n";
} catch (Exception $e) {
    echo "Structure déjà correcte ou erreur: " . $e->getMessage() . "\n";
}

// 2. Récupérer toutes les sessions terminées
$stmt = $pdo->query("SELECT * FROM qcm_sessions WHERE status = 'completed'");
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Sessions à corriger: " . count($sessions) . "\n\n";

foreach ($sessions as $session) {
    echo "Session {$session['id']}:\n";
    
    // Récupérer toutes les réponses avec les types de questions
    $stmt = $pdo->prepare("
        SELECT qa.*, q.question_type 
        FROM qcm_answers qa 
        JOIN qcm_questions q ON qa.question_id = q.id 
        WHERE qa.session_id = ?
    ");
    $stmt->execute([$session['id']]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $correct_count = 0;
    $corrections_made = 0;
    
    foreach ($answers as $answer) {
        // Récupérer les choix corrects pour cette question
        $stmt_choices = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt_choices->execute([$answer['question_id']]);
        $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
        
        $correct_letters = [];
        foreach ($choices as $index => $choice) {
            if ($choice['is_correct'] == 1) {
                $correct_letters[] = chr(97 + $index); // a, b, c, d
            }
        }
        
        $is_correct = false;
        
        if ($answer['question_type'] === 'multiple') {
            // Questions à choix multiples
            $user_answers = array_filter(explode(',', $answer['selected_answer']));
            $user_answers = array_map('trim', $user_answers);
            sort($user_answers);
            sort($correct_letters);
            
            $is_correct = ($user_answers === $correct_letters);
            
            echo "  Q{$answer['question_id']} (multiple): '{$answer['selected_answer']}' vs [" . implode(',', $correct_letters) . "] = " . ($is_correct ? 'CORRECT' : 'INCORRECT') . "\n";
        } else {
            // Questions à choix unique
            $is_correct = in_array($answer['selected_answer'], $correct_letters);
            echo "  Q{$answer['question_id']} (single): '{$answer['selected_answer']}' vs [" . implode(',', $correct_letters) . "] = " . ($is_correct ? 'CORRECT' : 'INCORRECT') . "\n";
        }
        
        if ($is_correct) {
            $correct_count++;
        }
        
        // Mettre à jour si nécessaire
        if ($is_correct != $answer['is_correct']) {
            $stmt_update = $pdo->prepare("UPDATE qcm_answers SET is_correct = ? WHERE id = ?");
            $stmt_update->execute([$is_correct ? 1 : 0, $answer['id']]);
            $corrections_made++;
        }
    }
    
    // Calculer le nouveau score
    $total_questions = count($answers);
    $new_score = ($correct_count / $total_questions) * 100;
    
    // Mettre à jour la session
    $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ? WHERE id = ?");
    $stmt->execute([$new_score, $correct_count, $session['id']]);
    
    echo "  Score: {$session['score']}% -> $new_score% ($correct_count/$total_questions)\n";
    echo "  Corrections: $corrections_made réponses corrigées\n\n";
}

echo "=== CORRECTION TERMINÉE ===\n";
?>
