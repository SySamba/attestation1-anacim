<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DEBUG PROBLÈME QUESTIONS MULTIPLES ===\n\n";
    
    // 1. Vérifier les sessions récentes
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions ORDER BY id DESC LIMIT 3");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sessions as $session) {
        echo "Session {$session['id']} - Candidat {$session['candidate_id']}:\n";
        echo "Score: {$session['score']}% ({$session['correct_answers']}/{$session['total_questions']})\n";
        
        // Analyser chaque réponse
        $stmt = $pdo->prepare("SELECT qa.*, q.question_type, q.question_text 
                              FROM qcm_answers qa 
                              JOIN qcm_questions q ON qa.question_id = q.id 
                              WHERE qa.session_id = ?");
        $stmt->execute([$session['id']]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $single_correct = 0;
        $multiple_correct = 0;
        $single_total = 0;
        $multiple_total = 0;
        
        foreach ($answers as $answer) {
            $type = $answer['question_type'] ?? 'single';
            
            if ($type === 'multiple') {
                $multiple_total++;
                if ($answer['is_correct']) $multiple_correct++;
                echo "  Q{$answer['question_id']} (MULTIPLE): '{$answer['selected_answer']}' - " . 
                     ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
            } else {
                $single_total++;
                if ($answer['is_correct']) $single_correct++;
                echo "  Q{$answer['question_id']} (SINGLE): '{$answer['selected_answer']}' - " . 
                     ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
            }
        }
        
        echo "Résumé: Single ($single_correct/$single_total), Multiple ($multiple_correct/$multiple_total)\n\n";
    }
    
    // 2. Test spécifique d'une question multiple
    echo "=== TEST QUESTION MULTIPLE SPÉCIFIQUE ===\n";
    
    $stmt = $pdo->prepare("SELECT * FROM qcm_questions WHERE question_type = 'multiple' LIMIT 1");
    $stmt->execute();
    $test_q = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_q) {
        echo "Question {$test_q['id']}: " . substr($test_q['question_text'], 0, 60) . "...\n";
        
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$test_q['id']]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Choix:\n";
        $correct_letters = [];
        foreach ($choices as $index => $choice) {
            $letter = chr(97 + $index);
            echo "  $letter) {$choice['choice_text']} " . ($choice['is_correct'] ? '✓' : '') . "\n";
            if ($choice['is_correct']) {
                $correct_letters[] = $letter;
            }
        }
        
        echo "Bonnes réponses: [" . implode(',', $correct_letters) . "]\n\n";
        
        // Simuler la validation avec différents inputs
        $test_inputs = [
            $correct_letters,
            ['a'],
            ['a', 'b'],
            ['a', 'b', 'c'],
        ];
        
        foreach ($test_inputs as $i => $input) {
            $sorted_input = $input;
            $sorted_correct = $correct_letters;
            sort($sorted_input);
            sort($sorted_correct);
            
            $result = ($sorted_input === $sorted_correct);
            echo "Test " . ($i+1) . " - Input: [" . implode(',', $input) . "] => " . ($result ? 'CORRECT' : 'INCORRECT') . "\n";
        }
    }
    
    // 3. Vérifier le problème dans l'envoi des réponses multiples
    echo "\n=== VÉRIFICATION ENVOI RÉPONSES MULTIPLES ===\n";
    
    // Simuler ce qui arrive du JavaScript
    $simulated_answer = ['a', 'b', 'd']; // Array depuis JavaScript
    $question_type = 'multiple';
    
    echo "Réponse simulée depuis JS: ";
    var_dump($simulated_answer);
    echo "Type: " . gettype($simulated_answer) . "\n";
    echo "Is array: " . (is_array($simulated_answer) ? 'OUI' : 'NON') . "\n";
    
    // Test de la logique de validation
    if (is_array($simulated_answer)) {
        sort($simulated_answer);
        echo "Après tri: [" . implode(',', $simulated_answer) . "]\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
