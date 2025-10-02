<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Debug des réponses QCM ===\n\n";
    
    // Récupérer la dernière session
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo "Aucune session trouvée\n";
        exit;
    }
    
    echo "Session ID: {$session['id']}\n";
    echo "Score affiché: {$session['score']}%\n";
    echo "Réponses correctes comptées: {$session['correct_answers']}/{$session['total_questions']}\n\n";
    
    // Récupérer toutes les réponses de cette session
    $stmt = $pdo->prepare("SELECT qa.*, q.question_text, q.question_type 
                          FROM qcm_answers qa 
                          JOIN qcm_questions q ON qa.question_id = q.id 
                          WHERE qa.session_id = ?");
    $stmt->execute([$session['id']]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($answers as $answer) {
        echo "--- Question {$answer['question_id']} ---\n";
        echo "Texte: " . substr($answer['question_text'], 0, 60) . "...\n";
        echo "Type: " . ($answer['question_type'] ?? 'single') . "\n";
        echo "Réponse donnée: {$answer['selected_answer']}\n";
        echo "Marquée comme: " . ($answer['is_correct'] ? 'CORRECTE' : 'INCORRECTE') . "\n";
        
        // Vérifier les vraies bonnes réponses
        $stmt_choices = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt_choices->execute([$answer['question_id']]);
        $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Choix disponibles:\n";
        $correct_answers = [];
        foreach ($choices as $index => $choice) {
            $letter = chr(97 + $index);
            $is_correct = $choice['is_correct'] == 1;
            if ($is_correct) {
                $correct_answers[] = $letter;
            }
            echo "  $letter) {$choice['choice_text']} " . ($is_correct ? '✓' : '') . "\n";
        }
        
        echo "Vraies bonnes réponses: " . implode(', ', $correct_answers) . "\n";
        
        // Analyser pourquoi c'est marqué incorrect
        if (!$answer['is_correct']) {
            $user_answers = explode(',', $answer['selected_answer']);
            echo "Analyse: Réponse utilisateur (" . implode(',', $user_answers) . ") vs Correctes (" . implode(',', $correct_answers) . ")\n";
        }
        
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
