<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CORRECTION IMMÉDIATE SCORE DEMBA ===\n\n";
    
    // Trouver la session de Demba (candidat ID 3)
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = 3 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo "Session non trouvée pour candidat 3\n";
        exit;
    }
    
    echo "Session trouvée: {$session['id']}\n";
    echo "Score actuel: {$session['score']}% ({$session['correct_answers']}/{$session['total_questions']})\n\n";
    
    // Analyser chaque réponse
    $stmt = $pdo->prepare("SELECT qa.*, q.question_type, q.question_text 
                          FROM qcm_answers qa 
                          JOIN qcm_questions q ON qa.question_id = q.id 
                          WHERE qa.session_id = ?");
    $stmt->execute([$session['id']]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $corrections = 0;
    $new_correct_count = 0;
    
    foreach ($answers as $answer) {
        echo "Question {$answer['question_id']} ({$answer['question_type']}):\n";
        echo "Réponse: '{$answer['selected_answer']}'\n";
        echo "Statut actuel: " . ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
        
        // Récupérer les choix
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$answer['question_id']]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $is_really_correct = false;
        $question_type = $answer['question_type'] ?? 'single';
        
        if ($question_type === 'multiple') {
            // Questions multiples
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
            $is_really_correct = ($user_answers === $correct_letters);
            
            echo "Attendu: [" . implode(',', $correct_letters) . "]\n";
            echo "Donné: [" . implode(',', $user_answers) . "]\n";
        } else {
            // Questions simples
            $correct_letter = null;
            foreach ($choices as $index => $choice) {
                if ($choice['is_correct'] == 1) {
                    $correct_letter = chr(97 + $index);
                    break;
                }
            }
            
            $is_really_correct = ($answer['selected_answer'] === $correct_letter);
            echo "Attendu: $correct_letter\n";
            echo "Donné: {$answer['selected_answer']}\n";
        }
        
        echo "Devrait être: " . ($is_really_correct ? 'CORRECT' : 'INCORRECT') . "\n";
        
        // Corriger si nécessaire
        if ($is_really_correct != $answer['is_correct']) {
            $stmt = $pdo->prepare("UPDATE qcm_answers SET is_correct = ? WHERE id = ?");
            $stmt->execute([$is_really_correct ? 1 : 0, $answer['id']]);
            echo "✓ CORRIGÉ!\n";
            $corrections++;
        }
        
        if ($is_really_correct) {
            $new_correct_count++;
        }
        
        echo "\n";
    }
    
    // Recalculer le score
    $new_score = ($new_correct_count / count($answers)) * 100;
    
    echo "=== RÉSULTATS ===\n";
    echo "Corrections effectuées: $corrections\n";
    echo "Ancien score: {$session['score']}%\n";
    echo "Nouveau score: $new_score%\n";
    echo "Bonnes réponses: $new_correct_count/" . count($answers) . "\n";
    
    // Mettre à jour la session
    $new_status = ($new_score >= 80) ? 'passed' : 'failed';
    
    $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ?, status = ? WHERE id = ?");
    $stmt->execute([$new_score, $new_correct_count, $new_status, $session['id']]);
    
    echo "Statut: $new_status\n";
    echo "\n✅ SCORE DE DEMBA CORRIGÉ!\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
