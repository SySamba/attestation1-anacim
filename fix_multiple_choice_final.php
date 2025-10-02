<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CORRECTION FINALE QUESTIONS MULTIPLES ===\n\n";
    
    // 1. Recalculer TOUTES les réponses multiples
    $stmt = $pdo->prepare("SELECT qa.*, q.question_type, s.candidate_id 
                          FROM qcm_answers qa 
                          JOIN qcm_questions q ON qa.question_id = q.id 
                          JOIN qcm_sessions s ON qa.session_id = s.id
                          WHERE q.question_type = 'multiple'");
    $stmt->execute();
    $multiple_answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Réponses multiples à recalculer: " . count($multiple_answers) . "\n\n";
    
    $corrections = 0;
    
    foreach ($multiple_answers as $answer) {
        echo "Candidat {$answer['candidate_id']} - Session {$answer['session_id']} - Q{$answer['question_id']}:\n";
        echo "Réponse: '{$answer['selected_answer']}'\n";
        echo "Statut actuel: " . ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
        
        // Récupérer les choix corrects
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$answer['question_id']]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer les bonnes réponses
        $correct_letters = [];
        foreach ($choices as $index => $choice) {
            if ($choice['is_correct'] == 1) {
                $correct_letters[] = chr(97 + $index);
            }
        }
        
        // Parser la réponse utilisateur
        $user_answers = [];
        if (!empty($answer['selected_answer'])) {
            if (strpos($answer['selected_answer'], ',') !== false) {
                $user_answers = explode(',', $answer['selected_answer']);
            } else {
                $user_answers = [$answer['selected_answer']];
            }
            
            // Nettoyer les espaces
            $user_answers = array_map('trim', $user_answers);
            $user_answers = array_filter($user_answers); // Enlever les vides
        }
        
        // Trier pour comparaison
        sort($user_answers);
        sort($correct_letters);
        
        $should_be_correct = ($user_answers === $correct_letters);
        
        echo "Attendu: [" . implode(',', $correct_letters) . "]\n";
        echo "Donné: [" . implode(',', $user_answers) . "]\n";
        echo "Devrait être: " . ($should_be_correct ? 'CORRECT' : 'INCORRECT') . "\n";
        
        // Corriger si nécessaire
        if ($should_be_correct != $answer['is_correct']) {
            $stmt = $pdo->prepare("UPDATE qcm_answers SET is_correct = ? WHERE id = ?");
            $stmt->execute([$should_be_correct ? 1 : 0, $answer['id']]);
            echo "✓ CORRIGÉ: " . ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . 
                 " -> " . ($should_be_correct ? 'CORRECT' : 'INCORRECT') . "\n";
            $corrections++;
        } else {
            echo "Pas de correction nécessaire\n";
        }
        echo "\n";
    }
    
    echo "Corrections effectuées: $corrections\n\n";
    
    // 2. Recalculer les scores des sessions affectées
    $stmt = $pdo->prepare("SELECT DISTINCT s.* FROM qcm_sessions s 
                          JOIN qcm_answers qa ON s.id = qa.session_id 
                          JOIN qcm_questions q ON qa.question_id = q.id 
                          WHERE q.question_type = 'multiple' AND s.status = 'completed'");
    $stmt->execute();
    $affected_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Sessions à recalculer: " . count($affected_sessions) . "\n\n";
    
    foreach ($affected_sessions as $session) {
        echo "Session {$session['id']} - Candidat {$session['candidate_id']}:\n";
        echo "Score actuel: {$session['score']}%\n";
        
        // Compter les bonnes réponses
        $stmt = $pdo->prepare("SELECT COUNT(*) as correct FROM qcm_answers WHERE session_id = ? AND is_correct = 1");
        $stmt->execute([$session['id']]);
        $correct_count = $stmt->fetch(PDO::FETCH_ASSOC)['correct'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM qcm_answers WHERE session_id = ?");
        $stmt->execute([$session['id']]);
        $total_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $new_score = ($correct_count / $total_count) * 100;
        
        echo "Nouveau score: $new_score% ($correct_count/$total_count)\n";
        
        // Mettre à jour
        $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ? WHERE id = ?");
        $stmt->execute([$new_score, $correct_count, $session['id']]);
        
        if (abs($new_score - $session['score']) > 0.1) {
            echo "✓ SCORE CORRIGÉ: {$session['score']}% -> $new_score%\n";
        }
        echo "\n";
    }
    
    echo "=== CORRECTION TERMINÉE ===\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
