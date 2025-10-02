<?php
// Correction définitive du système de validation QCM
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CORRECTION DÉFINITIVE VALIDATION QCM ===\n\n";
    
    // 1. Identifier le problème exact
    echo "1. DIAGNOSTIC DU PROBLÈME:\n";
    
    // Vérifier une question simple
    $stmt = $pdo->prepare("SELECT * FROM qcm_questions WHERE question_type != 'multiple' LIMIT 1");
    $stmt->execute();
    $test_q = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_q) {
        echo "Test question {$test_q['id']}:\n";
        
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$test_q['id']]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Choix dans l'ordre des IDs:\n";
        foreach ($choices as $index => $choice) {
            $letter = chr(97 + $index);
            echo "  $letter) ID:{$choice['id']} - {$choice['choice_text']} " . ($choice['is_correct'] ? '✓' : '') . "\n";
        }
        
        // Trouver la bonne réponse selon la logique actuelle
        $correct_letter = null;
        foreach ($choices as $index => $choice) {
            if ($choice['is_correct'] == 1) {
                $correct_letter = chr(97 + $index);
                break;
            }
        }
        echo "Bonne réponse calculée: '$correct_letter'\n\n";
    }
    
    // 2. Recalculer TOUS les scores existants avec la logique corrigée
    echo "2. RECALCUL DE TOUS LES SCORES:\n\n";
    
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE status = 'completed'");
    $stmt->execute();
    $all_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_sessions as $session) {
        echo "Session {$session['id']} - Candidat {$session['candidate_id']}:\n";
        echo "Score actuel: {$session['score']}%\n";
        
        // Récupérer toutes les réponses
        $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                              JOIN qcm_questions q ON qa.question_id = q.id 
                              WHERE qa.session_id = ?");
        $stmt->execute([$session['id']]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $new_correct_count = 0;
        $corrections = 0;
        
        foreach ($answers as $answer) {
            // Récupérer les choix pour cette question
            $stmt_choices = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
            $stmt_choices->execute([$answer['question_id']]);
            $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
            
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
            }
            
            if ($is_really_correct) {
                $new_correct_count++;
            }
            
            // Corriger la réponse si nécessaire
            if ($is_really_correct != $answer['is_correct']) {
                $stmt = $pdo->prepare("UPDATE qcm_answers SET is_correct = ? WHERE id = ?");
                $stmt->execute([$is_really_correct ? 1 : 0, $answer['id']]);
                $corrections++;
            }
        }
        
        // Calculer le nouveau score
        $new_score = ($new_correct_count / count($answers)) * 100;
        $new_status = ($new_score >= 80) ? 'passed' : 'failed';
        
        // Mettre à jour la session
        $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ?, status = ? WHERE id = ?");
        $stmt->execute([$new_score, $new_correct_count, $new_status, $session['id']]);
        
        echo "Corrections: $corrections\n";
        echo "Nouveau score: $new_score% ($new_correct_count/" . count($answers) . ")\n";
        echo "Statut: $new_status\n";
        
        if (abs($new_score - $session['score']) > 0.1) {
            echo "✅ SCORE CORRIGÉ: {$session['score']}% → $new_score%\n";
        }
        echo "\n";
    }
    
    echo "=== CORRECTION TERMINÉE ===\n";
    echo "Tous les scores ont été recalculés avec la logique corrigée.\n";
    echo "Les futurs tests utiliseront automatiquement la validation corrigée.\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
