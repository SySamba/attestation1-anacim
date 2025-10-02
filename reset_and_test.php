<?php
// Script pour diagnostiquer le problème de validation QCM
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DIAGNOSTIC QCM - PROBLÈME DE VALIDATION ===\n\n";
    
    // 1. Vérifier les questions et choix
    echo "1. Vérification des questions:\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_questions");
    $stmt->execute();
    $q_count = $stmt->fetchColumn();
    echo "Questions: $q_count\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_choices");
    $stmt->execute();
    $c_count = $stmt->fetchColumn();
    echo "Choix: $c_count\n\n";
    
    // 2. Analyser une question spécifique
    echo "2. Analyse d'une question:\n";
    $stmt = $pdo->prepare("SELECT * FROM qcm_questions LIMIT 1");
    $stmt->execute();
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($question) {
        echo "Question ID: {$question['id']}\n";
        echo "Texte: {$question['question_text']}\n";
        echo "Type: " . ($question['question_type'] ?? 'NULL') . "\n\n";
        
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$question['id']]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Choix:\n";
        foreach ($choices as $index => $choice) {
            $letter = chr(97 + $index);
            echo "$letter) {$choice['choice_text']} - Correct: " . ($choice['is_correct'] ? 'OUI' : 'NON') . "\n";
        }
    }
    
    // 3. Vérifier la dernière session
    echo "\n3. Dernière session de test:\n";
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo "Session ID: {$session['id']}\n";
        echo "Score: {$session['score']}%\n";
        echo "Correctes: {$session['correct_answers']}/{$session['total_questions']}\n\n";
        
        // Analyser les réponses
        $stmt = $pdo->prepare("SELECT * FROM qcm_answers WHERE session_id = ?");
        $stmt->execute([$session['id']]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Réponses données:\n";
        foreach ($answers as $answer) {
            echo "Q{$answer['question_id']}: {$answer['selected_answer']} - " . ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
