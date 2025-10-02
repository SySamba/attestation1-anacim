<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DEBUG PROBLÈME SYSTÉMIQUE QCM ===\n\n";
    
    // 1. Analyser TOUTES les sessions récentes
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE status = 'completed' ORDER BY id DESC LIMIT 10");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Sessions récentes analysées: " . count($sessions) . "\n\n";
    
    foreach ($sessions as $session) {
        echo "--- Session {$session['id']} - Candidat {$session['candidate_id']} ---\n";
        echo "Score: {$session['score']}% ({$session['correct_answers']}/{$session['total_questions']})\n";
        
        // Analyser chaque réponse de cette session
        $stmt = $pdo->prepare("SELECT qa.*, q.question_type, q.question_text 
                              FROM qcm_answers qa 
                              JOIN qcm_questions q ON qa.question_id = q.id 
                              WHERE qa.session_id = ?");
        $stmt->execute([$session['id']]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $should_be_correct = 0;
        
        foreach ($answers as $answer) {
            echo "Q{$answer['question_id']} ({$answer['question_type']}): ";
            echo "'{$answer['selected_answer']}' - ";
            echo ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT');
            
            // Vérifier manuellement si c'est vraiment correct
            $stmt_choices = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
            $stmt_choices->execute([$answer['question_id']]);
            $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
            
            $really_correct = false;
            $question_type = $answer['question_type'] ?? 'single';
            
            if ($question_type === 'multiple') {
                $correct_letters = [];
                foreach ($choices as $index => $choice) {
                    if ($choice['is_correct'] == 1) {
                        $correct_letters[] = chr(97 + $index);
                    }
                }
                
                $user_answers = [];
                if (!empty($answer['selected_answer'])) {
                    if (strpos($answer['selected_answer'], ',') !== false) {
                        $user_answers = explode(',', $answer['selected_answer']);
                    } else {
                        $user_answers = [$answer['selected_answer']];
                    }
                    $user_answers = array_map('trim', $user_answers);
                    $user_answers = array_filter($user_answers);
                }
                
                sort($user_answers);
                sort($correct_letters);
                $really_correct = ($user_answers === $correct_letters);
                
                echo " | Attendu: [" . implode(',', $correct_letters) . "] Donné: [" . implode(',', $user_answers) . "]";
            } else {
                $correct_letter = null;
                foreach ($choices as $index => $choice) {
                    if ($choice['is_correct'] == 1) {
                        $correct_letter = chr(97 + $index);
                        break;
                    }
                }
                $really_correct = ($answer['selected_answer'] === $correct_letter);
                echo " | Attendu: $correct_letter";
            }
            
            if ($really_correct) {
                $should_be_correct++;
                if (!$answer['is_correct']) {
                    echo " ❌ ERREUR DE VALIDATION!";
                }
            } else {
                if ($answer['is_correct']) {
                    echo " ❌ FAUX POSITIF!";
                }
            }
            
            echo "\n";
        }
        
        $should_be_score = ($should_be_correct / count($answers)) * 100;
        echo "Score réel calculé: $should_be_score% ($should_be_correct/" . count($answers) . ")\n";
        
        if (abs($should_be_score - $session['score']) > 0.1) {
            echo "🚨 PROBLÈME: Score en base ({$session['score']}%) != Score réel ($should_be_score%)\n";
        }
        echo "\n";
    }
    
    // 2. Analyser les questions et leurs choix
    echo "=== ANALYSE DES QUESTIONS ===\n\n";
    
    $stmt = $pdo->prepare("SELECT * FROM qcm_questions LIMIT 5");
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($questions as $q) {
        echo "Question {$q['id']} ({$q['question_type']}):\n";
        echo substr($q['question_text'], 0, 80) . "...\n";
        
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$q['id']]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($choices as $index => $choice) {
            $letter = chr(97 + $index);
            echo "  $letter) {$choice['choice_text']} " . ($choice['is_correct'] ? '✓' : '') . "\n";
        }
        echo "\n";
    }
    
    // 3. Test de la logique de validation actuelle
    echo "=== TEST LOGIQUE VALIDATION ===\n\n";
    
    // Simuler save_answer avec une question simple
    $test_question_id = 1;
    $test_answer = 'a';
    
    $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
    $stmt->execute([$test_question_id]);
    $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Test question $test_question_id avec réponse '$test_answer':\n";
    
    // Reproduire la logique de qcm_api.php
    $sorted_choices = $choices;
    usort($sorted_choices, function($a, $b) { return $a['id'] - $b['id']; });
    
    $correct_answer = null;
    foreach ($sorted_choices as $index => $choice) {
        $letter = chr(97 + $index);
        echo "Index $index -> Lettre $letter: {$choice['choice_text']} " . ($choice['is_correct'] ? '✓' : '') . "\n";
        if ($choice['is_correct'] == 1) {
            $correct_answer = $letter;
        }
    }
    
    echo "Bonne réponse calculée: '$correct_answer'\n";
    echo "Réponse testée: '$test_answer'\n";
    echo "Validation: " . ($test_answer === $correct_answer ? 'CORRECT' : 'INCORRECT') . "\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
