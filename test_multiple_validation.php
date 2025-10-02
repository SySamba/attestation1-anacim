<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== TEST VALIDATION CHOIX MULTIPLES ===\n\n";
    
    // Simuler exactement ce qui se passe dans save_answer
    $question_id = 3; // Question à choix multiples
    $answer = ['a', 'b', 'd']; // Réponse utilisateur
    $question_type = 'multiple';
    
    echo "Test avec question $question_id\n";
    echo "Réponse utilisateur: [" . implode(',', $answer) . "]\n";
    echo "Type: $question_type\n\n";
    
    // Récupérer les choix (comme dans l'API)
    $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
    $stmt->execute([$question_id]);
    $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Choix trouvés:\n";
    foreach ($choices as $choice) {
        echo "ID {$choice['id']}: {$choice['choice_text']} - Correct: " . ($choice['is_correct'] ? 'OUI' : 'NON') . "\n";
    }
    echo "\n";
    
    // Logique actuelle de l'API
    $correct_choices = [];
    $sorted_choices = $choices;
    usort($sorted_choices, function($a, $b) { return $a['id'] - $b['id']; });
    
    echo "Choix triés par ID:\n";
    foreach ($sorted_choices as $index => $choice) {
        $letter = chr(97 + $index);
        echo "Index $index -> Lettre $letter: {$choice['choice_text']} - Correct: " . ($choice['is_correct'] ? 'OUI' : 'NON') . "\n";
        
        if ($choice['is_correct'] == 1) {
            $correct_choices[] = $letter;
        }
    }
    
    echo "\nBonnes réponses calculées: [" . implode(',', $correct_choices) . "]\n";
    
    // Test de validation
    sort($answer);
    sort($correct_choices);
    $is_correct = ($answer === $correct_choices);
    
    echo "Réponse triée: [" . implode(',', $answer) . "]\n";
    echo "Correctes triées: [" . implode(',', $correct_choices) . "]\n";
    echo "Résultat validation: " . ($is_correct ? 'CORRECT' : 'INCORRECT') . "\n\n";
    
    // Vérifier ce qui est vraiment stocké en base
    echo "=== VÉRIFICATION BASE DE DONNÉES ===\n";
    
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = 3 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo "Dernière session candidat 3: {$session['id']}\n";
        
        $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                              JOIN qcm_questions q ON qa.question_id = q.id 
                              WHERE qa.session_id = ? AND q.question_type = 'multiple'");
        $stmt->execute([$session['id']]);
        $multiple_answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Réponses multiples dans cette session:\n";
        foreach ($multiple_answers as $ans) {
            echo "Q{$ans['question_id']}: '{$ans['selected_answer']}' - " . ($ans['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
        }
    }
    
    // Test avec une vraie question multiple de la base
    echo "\n=== TEST AVEC VRAIE QUESTION MULTIPLE ===\n";
    
    $stmt = $pdo->prepare("SELECT * FROM qcm_questions WHERE question_type = 'multiple' LIMIT 1");
    $stmt->execute();
    $real_question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($real_question) {
        echo "Question {$real_question['id']}: " . substr($real_question['question_text'], 0, 60) . "...\n";
        
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$real_question['id']]);
        $real_choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $real_correct = [];
        foreach ($real_choices as $index => $choice) {
            $letter = chr(97 + $index);
            echo "$letter) {$choice['choice_text']} " . ($choice['is_correct'] ? '✓' : '') . "\n";
            if ($choice['is_correct']) {
                $real_correct[] = $letter;
            }
        }
        
        echo "Bonnes réponses: [" . implode(',', $real_correct) . "]\n";
        
        // Simuler différentes réponses utilisateur
        $test_responses = [
            $real_correct, // Toutes bonnes
            array_slice($real_correct, 0, 1), // Une seule bonne
            array_merge($real_correct, ['c']), // Bonnes + une mauvaise
        ];
        
        foreach ($test_responses as $i => $test_resp) {
            sort($test_resp);
            $sorted_correct = $real_correct;
            sort($sorted_correct);
            $result = ($test_resp === $sorted_correct);
            echo "Test " . ($i+1) . " [" . implode(',', $test_resp) . "]: " . ($result ? 'CORRECT' : 'INCORRECT') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
