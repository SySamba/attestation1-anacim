<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Debug QCM - Vérification des réponses ===\n\n";
    
    // Récupérer une question avec ses choix
    $stmt = $pdo->prepare("SELECT * FROM qcm_questions LIMIT 1");
    $stmt->execute();
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($question) {
        echo "Question ID: {$question['id']}\n";
        echo "Texte: {$question['question_text']}\n";
        echo "Type: " . ($question['question_type'] ?? 'NULL') . "\n\n";
        
        // Récupérer les choix
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$question['id']]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Choix disponibles:\n";
        foreach ($choices as $index => $choice) {
            $letter = chr(97 + $index); // a, b, c, d
            $correct = $choice['is_correct'] ? '✓ CORRECT' : '';
            echo "$letter) {$choice['choice_text']} $correct\n";
        }
        
        echo "\n=== Test de validation ===\n";
        
        // Simuler la logique de validation
        foreach ($choices as $index => $choice) {
            if ($choice['is_correct'] == 1) {
                $correct_answer = chr(97 + $index);
                echo "Bonne réponse attendue: $correct_answer\n";
                break;
            }
        }
        
        // Test avec différentes réponses
        $test_answers = ['a', 'b', 'c', 'd'];
        foreach ($test_answers as $test_answer) {
            $is_correct = false;
            foreach ($choices as $index => $choice) {
                if ($choice['is_correct'] == 1) {
                    $correct_answer = chr(97 + $index);
                    $is_correct = ($test_answer === $correct_answer);
                    break;
                }
            }
            echo "Réponse '$test_answer': " . ($is_correct ? 'CORRECT' : 'INCORRECT') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
