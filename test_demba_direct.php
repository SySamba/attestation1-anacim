<?php
// Test direct sans include
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier les sessions du candidat 3
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = 3");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Sessions trouvées pour candidat 3: " . count($sessions) . "\n";
    
    foreach ($sessions as $session) {
        echo "Session {$session['id']}: Score {$session['score']}%, Correctes: {$session['correct_answers']}/{$session['total_questions']}\n";
        
        // Vérifier les réponses
        $stmt2 = $pdo->prepare("SELECT * FROM qcm_answers WHERE session_id = ?");
        $stmt2->execute([$session['id']]);
        $answers = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Réponses pour session {$session['id']}:\n";
        foreach ($answers as $answer) {
            echo "  Q{$answer['question_id']}: '{$answer['selected_answer']}' - " . ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
        }
        echo "\n";
    }
    
    // Vérifier les questions et leurs bonnes réponses
    echo "=== QUESTIONS ET BONNES RÉPONSES ===\n";
    $stmt = $pdo->prepare("SELECT q.id, q.question_text, c.choice_text, c.is_correct 
                          FROM qcm_questions q 
                          JOIN qcm_choices c ON q.id = c.question_id 
                          ORDER BY q.id, c.id");
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $current_q = null;
    $choice_index = 0;
    
    foreach ($data as $row) {
        if ($current_q !== $row['id']) {
            if ($current_q !== null) echo "\n";
            echo "Question {$row['id']}: " . substr($row['question_text'], 0, 50) . "...\n";
            $current_q = $row['id'];
            $choice_index = 0;
        }
        
        $letter = chr(97 + $choice_index);
        echo "  $letter) {$row['choice_text']} " . ($row['is_correct'] ? '✓' : '') . "\n";
        $choice_index++;
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
