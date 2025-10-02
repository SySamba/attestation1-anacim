<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DEBUG QUESTIONS À CHOIX MULTIPLES ===\n\n";
    
    // 1. Identifier les questions à choix multiples
    $stmt = $pdo->prepare("SELECT * FROM qcm_questions WHERE question_type = 'multiple'");
    $stmt->execute();
    $multiple_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Questions à choix multiples trouvées: " . count($multiple_questions) . "\n\n";
    
    foreach ($multiple_questions as $q) {
        echo "--- Question {$q['id']} ---\n";
        echo "Texte: " . substr($q['question_text'], 0, 80) . "...\n";
        echo "Type: {$q['question_type']}\n";
        
        // Récupérer les choix
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$q['id']]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Choix disponibles:\n";
        $correct_choices = [];
        foreach ($choices as $index => $choice) {
            $letter = chr(97 + $index);
            if ($choice['is_correct'] == 1) {
                $correct_choices[] = $letter;
            }
            echo "  $letter) {$choice['choice_text']} " . ($choice['is_correct'] ? '✓' : '') . "\n";
        }
        
        echo "Bonnes réponses attendues: [" . implode(',', $correct_choices) . "]\n\n";
    }
    
    // 2. Vérifier les réponses données aux questions multiples
    echo "=== RÉPONSES AUX QUESTIONS MULTIPLES ===\n\n";
    
    $stmt = $pdo->prepare("SELECT qa.*, q.question_text, s.candidate_id 
                          FROM qcm_answers qa 
                          JOIN qcm_questions q ON qa.question_id = q.id 
                          JOIN qcm_sessions s ON qa.session_id = s.id
                          WHERE q.question_type = 'multiple'
                          ORDER BY qa.session_id, qa.question_id");
    $stmt->execute();
    $multiple_answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Réponses aux questions multiples: " . count($multiple_answers) . "\n\n";
    
    foreach ($multiple_answers as $answer) {
        echo "Session {$answer['session_id']} - Candidat {$answer['candidate_id']} - Q{$answer['question_id']}:\n";
        echo "Réponse donnée: '{$answer['selected_answer']}'\n";
        echo "Marquée comme: " . ($answer['is_correct'] ? 'CORRECTE' : 'INCORRECTE') . "\n";
        
        // Vérifier manuellement
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$answer['question_id']]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $correct_letters = [];
        foreach ($choices as $index => $choice) {
            if ($choice['is_correct'] == 1) {
                $correct_letters[] = chr(97 + $index);
            }
        }
        
        $user_answers = explode(',', $answer['selected_answer']);
        sort($user_answers);
        sort($correct_letters);
        
        $should_be_correct = ($user_answers === $correct_letters);
        
        echo "Attendu: [" . implode(',', $correct_letters) . "]\n";
        echo "Donné: [" . implode(',', $user_answers) . "]\n";
        echo "Devrait être: " . ($should_be_correct ? 'CORRECT' : 'INCORRECT') . "\n";
        
        if ($should_be_correct != $answer['is_correct']) {
            echo "⚠️  PROBLÈME DÉTECTÉ!\n";
        }
        echo "\n";
    }
    
    // 3. Test de la logique de validation multiple
    echo "=== TEST LOGIQUE VALIDATION MULTIPLE ===\n\n";
    
    // Simuler différents cas
    $test_cases = [
        ['user' => ['a', 'b'], 'correct' => ['a', 'b'], 'expected' => true],
        ['user' => ['b', 'a'], 'correct' => ['a', 'b'], 'expected' => true], // ordre différent
        ['user' => ['a'], 'correct' => ['a', 'b'], 'expected' => false], // manque une réponse
        ['user' => ['a', 'b', 'c'], 'correct' => ['a', 'b'], 'expected' => false], // trop de réponses
        ['user' => ['c'], 'correct' => ['a', 'b'], 'expected' => false], // mauvaise réponse
    ];
    
    foreach ($test_cases as $i => $test) {
        $user = $test['user'];
        $correct = $test['correct'];
        
        sort($user);
        sort($correct);
        $result = ($user === $correct);
        
        echo "Test " . ($i + 1) . ": ";
        echo "User[" . implode(',', $test['user']) . "] vs Correct[" . implode(',', $test['correct']) . "] ";
        echo "=> " . ($result ? 'CORRECT' : 'INCORRECT');
        echo " (attendu: " . ($test['expected'] ? 'CORRECT' : 'INCORRECT') . ")";
        
        if ($result === $test['expected']) {
            echo " ✓\n";
        } else {
            echo " ❌ ERREUR!\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
