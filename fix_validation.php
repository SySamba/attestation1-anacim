<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Correction du problème de validation ===\n\n";
    
    // Vérifier les questions et leurs choix
    $stmt = $pdo->prepare("SELECT q.*, GROUP_CONCAT(c.choice_text ORDER BY c.id SEPARATOR ' | ') as choices, 
                          GROUP_CONCAT(CASE WHEN c.is_correct = 1 THEN CONCAT(CHAR(97 + (c.id - (SELECT MIN(id) FROM qcm_choices WHERE question_id = q.id))), ': ', c.choice_text) END SEPARATOR ' | ') as correct_choices
                          FROM qcm_questions q 
                          LEFT JOIN qcm_choices c ON q.id = c.question_id 
                          GROUP BY q.id");
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($questions as $q) {
        echo "Question {$q['id']}: {$q['question_text']}\n";
        echo "Type: " . ($q['question_type'] ?? 'single') . "\n";
        echo "Choix: {$q['choices']}\n";
        echo "Bonnes réponses: {$q['correct_choices']}\n";
        
        // Vérifier la logique de validation pour cette question
        $stmt_choices = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt_choices->execute([$q['id']]);
        $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Validation test:\n";
        foreach ($choices as $index => $choice) {
            $letter = chr(97 + $index);
            if ($choice['is_correct'] == 1) {
                echo "  Réponse '$letter' devrait être CORRECTE\n";
            }
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
