<?php
// Script pour corriger le problème de validation de Demba
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CORRECTION PROBLÈME DEMBA DIOP ===\n\n";
    
    // 1. Identifier le problème dans la logique de validation
    echo "1. Test de la logique de validation actuelle:\n";
    
    // Simuler une question avec choix
    $test_question_id = 1;
    $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
    $stmt->execute([$test_question_id]);
    $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($choices) > 0) {
        echo "Question $test_question_id - Choix dans l'ordre des IDs:\n";
        
        // Trier par ID (comme dans le code corrigé)
        usort($choices, function($a, $b) { return $a['id'] - $b['id']; });
        
        foreach ($choices as $index => $choice) {
            $letter = chr(97 + $index);
            echo "  $letter) ID:{$choice['id']} - {$choice['choice_text']} " . ($choice['is_correct'] ? '✓' : '') . "\n";
        }
        
        // Trouver la bonne réponse selon notre logique
        $correct_answer = null;
        foreach ($choices as $index => $choice) {
            if ($choice['is_correct'] == 1) {
                $correct_answer = chr(97 + $index);
                echo "\nBonne réponse calculée: '$correct_answer'\n";
                break;
            }
        }
    }
    
    // 2. Vérifier si le problème vient des choix multiples mal gérés
    echo "\n2. Vérification des questions à choix multiples:\n";
    $stmt = $pdo->prepare("SELECT * FROM qcm_questions WHERE question_type = 'multiple'");
    $stmt->execute();
    $multiple_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Questions à choix multiples: " . count($multiple_questions) . "\n";
    
    foreach ($multiple_questions as $q) {
        echo "Question {$q['id']}: " . substr($q['question_text'], 0, 50) . "...\n";
        
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$q['id']]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $correct_choices = [];
        foreach ($choices as $index => $choice) {
            if ($choice['is_correct'] == 1) {
                $correct_choices[] = chr(97 + $index);
            }
        }
        
        echo "  Bonnes réponses: " . implode(', ', $correct_choices) . "\n";
    }
    
    // 3. Recalculer les scores pour toutes les sessions récentes
    echo "\n3. Recalcul des scores récents:\n";
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id >= (SELECT MAX(id) - 5 FROM qcm_sessions)");
    $stmt->execute();
    $recent_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recent_sessions as $session) {
        echo "\nSession {$session['id']} (Candidat {$session['candidate_id']}):\n";
        echo "Score actuel: {$session['score']}%\n";
        
        // Recalculer manuellement
        $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                              JOIN qcm_questions q ON qa.question_id = q.id 
                              WHERE qa.session_id = ?");
        $stmt->execute([$session['id']]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $correct_count = 0;
        foreach ($answers as $answer) {
            // Vérifier manuellement chaque réponse
            $stmt_choices = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
            $stmt_choices->execute([$answer['question_id']]);
            $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
            
            usort($choices, function($a, $b) { return $a['id'] - $b['id']; });
            
            $is_really_correct = false;
            
            if ($answer['question_type'] === 'multiple') {
                $correct_letters = [];
                foreach ($choices as $index => $choice) {
                    if ($choice['is_correct'] == 1) {
                        $correct_letters[] = chr(97 + $index);
                    }
                }
                
                $user_answers = explode(',', $answer['selected_answer']);
                sort($user_answers);
                sort($correct_letters);
                $is_really_correct = ($user_answers === $correct_letters);
            } else {
                $correct_letter = null;
                foreach ($choices as $index => $choice) {
                    if ($choice['is_correct'] == 1) {
                        $correct_letter = chr(97 + $index);
                        break;
                    }
                }
                $is_really_correct = ($answer['selected_answer'] === $correct_letter);
            }
            
            if ($is_really_correct) {
                $correct_count++;
            }
            
            echo "  Q{$answer['question_id']}: '{$answer['selected_answer']}' - " . 
                 ($answer['is_correct'] ? 'Marqué CORRECT' : 'Marqué INCORRECT') . 
                 " / Vraiment: " . ($is_really_correct ? 'CORRECT' : 'INCORRECT') . "\n";
        }
        
        $real_score = ($correct_count / count($answers)) * 100;
        echo "Score recalculé: $real_score% ($correct_count/" . count($answers) . ")\n";
        
        // Mettre à jour si différent
        if (abs($real_score - $session['score']) > 0.1) {
            echo "MISE À JOUR NÉCESSAIRE!\n";
            $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ? WHERE id = ?");
            $stmt->execute([$real_score, $correct_count, $session['id']]);
            echo "Score mis à jour de {$session['score']}% vers $real_score%\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
