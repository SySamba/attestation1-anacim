<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== TEST BUG VALIDATION QCM ===\n\n";
    
    // Simuler exactement ce qui se passe dans save_answer
    echo "1. Test avec question simple:\n";
    
    $question_id = 1;
    $user_answer = 'a'; // Réponse utilisateur
    
    // Récupérer les choix comme dans l'API
    $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
    $stmt->execute([$question_id]);
    $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Choix pour question $question_id:\n";
    foreach ($choices as $i => $choice) {
        echo "ID {$choice['id']}: {$choice['choice_text']} - Correct: " . ($choice['is_correct'] ? 'OUI' : 'NON') . "\n";
    }
    
    // Reproduire la logique de qcm_api.php EXACTEMENT
    $sorted_choices = $choices;
    usort($sorted_choices, function($a, $b) { return $a['id'] - $b['id']; });
    
    echo "\nChoix après tri par ID:\n";
    $correct_answer = null;
    foreach ($sorted_choices as $index => $choice) {
        $letter = chr(97 + $index);
        echo "Index $index -> Lettre $letter: ID {$choice['id']} - {$choice['choice_text']} " . ($choice['is_correct'] ? '✓' : '') . "\n";
        if ($choice['is_correct'] == 1) {
            $correct_answer = $letter;
        }
    }
    
    echo "\nBonne réponse calculée: '$correct_answer'\n";
    echo "Réponse utilisateur: '$user_answer'\n";
    $is_correct = ($user_answer === $correct_answer);
    echo "Validation: " . ($is_correct ? 'CORRECT' : 'INCORRECT') . "\n\n";
    
    // Test avec TOUTES les questions
    echo "2. Test avec toutes les questions:\n\n";
    
    $stmt = $pdo->prepare("SELECT * FROM qcm_questions");
    $stmt->execute();
    $all_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_questions as $q) {
        echo "--- Question {$q['id']} ---\n";
        
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$q['id']]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Reproduire la logique
        $sorted_choices = $choices;
        usort($sorted_choices, function($a, $b) { return $a['id'] - $b['id']; });
        
        $correct_letters = [];
        $question_type = $q['question_type'] ?? 'single';
        
        if ($question_type === 'multiple') {
            foreach ($sorted_choices as $index => $choice) {
                if ($choice['is_correct'] == 1) {
                    $correct_letters[] = chr(97 + $index);
                }
            }
            echo "Type: MULTIPLE - Bonnes réponses: [" . implode(',', $correct_letters) . "]\n";
        } else {
            foreach ($sorted_choices as $index => $choice) {
                if ($choice['is_correct'] == 1) {
                    $correct_letters[] = chr(97 + $index);
                    break;
                }
            }
            echo "Type: SINGLE - Bonne réponse: " . ($correct_letters[0] ?? 'AUCUNE') . "\n";
        }
        
        // Afficher les choix
        foreach ($sorted_choices as $index => $choice) {
            $letter = chr(97 + $index);
            echo "  $letter) {$choice['choice_text']} " . ($choice['is_correct'] ? '✓' : '') . "\n";
        }
        echo "\n";
    }
    
    // Vérifier les sessions récentes
    echo "3. Analyse sessions récentes:\n\n";
    
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE status = 'completed' ORDER BY id DESC LIMIT 3");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sessions as $session) {
        echo "Session {$session['id']} - Candidat {$session['candidate_id']}:\n";
        echo "Score affiché: {$session['score']}%\n";
        
        $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                              JOIN qcm_questions q ON qa.question_id = q.id 
                              WHERE qa.session_id = ?");
        $stmt->execute([$session['id']]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $manual_correct = 0;
        foreach ($answers as $answer) {
            // Recalculer manuellement
            $stmt_c = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
            $stmt_c->execute([$answer['question_id']]);
            $choices = $stmt_c->fetchAll(PDO::FETCH_ASSOC);
            
            $sorted_choices = $choices;
            usort($sorted_choices, function($a, $b) { return $a['id'] - $b['id']; });
            
            $really_correct = false;
            $question_type = $answer['question_type'] ?? 'single';
            
            if ($question_type === 'multiple') {
                $correct_letters = [];
                foreach ($sorted_choices as $index => $choice) {
                    if ($choice['is_correct'] == 1) {
                        $correct_letters[] = chr(97 + $index);
                    }
                }
                
                $user_answers = [];
                if (!empty($answer['selected_answer'])) {
                    $user_answers = explode(',', $answer['selected_answer']);
                    $user_answers = array_map('trim', $user_answers);
                    $user_answers = array_filter($user_answers);
                }
                
                sort($user_answers);
                sort($correct_letters);
                $really_correct = ($user_answers === $correct_letters);
            } else {
                $correct_letter = null;
                foreach ($sorted_choices as $index => $choice) {
                    if ($choice['is_correct'] == 1) {
                        $correct_letter = chr(97 + $index);
                        break;
                    }
                }
                $really_correct = ($answer['selected_answer'] === $correct_letter);
            }
            
            if ($really_correct) $manual_correct++;
            
            echo "  Q{$answer['question_id']}: '{$answer['selected_answer']}' - Base: " . 
                 ($answer['is_correct'] ? 'OK' : 'KO') . " | Réel: " . ($really_correct ? 'OK' : 'KO') . "\n";
        }
        
        $manual_score = ($manual_correct / count($answers)) * 100;
        echo "Score recalculé: $manual_score% ($manual_correct/" . count($answers) . ")\n\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
