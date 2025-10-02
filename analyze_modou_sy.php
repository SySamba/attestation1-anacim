<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== ANALYSE CANDIDAT MODOU SY ===\n\n";
    
    // 1. Trouver le candidat Modou Sy
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE nom LIKE '%sy%' OR prenom LIKE '%modou%'");
    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Candidats trouvés:\n";
    foreach ($candidates as $candidate) {
        echo "ID {$candidate['id']}: {$candidate['prenom']} {$candidate['nom']}\n";
    }
    
    if (empty($candidates)) {
        echo "Aucun candidat 'Modou Sy' trouvé. Affichage des derniers candidats:\n";
        $stmt = $pdo->prepare("SELECT * FROM candidates ORDER BY id DESC LIMIT 5");
        $stmt->execute();
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($candidates as $candidate) {
            echo "ID {$candidate['id']}: {$candidate['prenom']} {$candidate['nom']}\n";
        }
    }
    
    // Prendre le dernier candidat ajouté
    $modou_candidate = end($candidates);
    $modou_id = $modou_candidate['id'];
    
    echo "\n=== ANALYSE CANDIDAT ID $modou_id: {$modou_candidate['prenom']} {$modou_candidate['nom']} ===\n\n";
    
    // 2. Trouver sa session QCM
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$modou_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo "Aucune session QCM trouvée pour ce candidat\n";
        exit;
    }
    
    echo "Session ID: {$session['id']}\n";
    echo "Score: {$session['score']}%\n";
    echo "Réponses correctes: {$session['correct_answers']}/{$session['total_questions']}\n";
    echo "Statut: {$session['status']}\n\n";
    
    // 3. Analyser chaque réponse en détail
    $stmt = $pdo->prepare("SELECT qa.*, q.question_text, q.question_type 
                          FROM qcm_answers qa 
                          JOIN qcm_questions q ON qa.question_id = q.id 
                          WHERE qa.session_id = ? 
                          ORDER BY qa.question_id");
    $stmt->execute([$session['id']]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== ANALYSE DÉTAILLÉE DES RÉPONSES ===\n\n";
    
    foreach ($answers as $answer) {
        echo "--- Question {$answer['question_id']} ---\n";
        echo "Type: " . ($answer['question_type'] ?? 'single') . "\n";
        echo "Texte: " . substr($answer['question_text'], 0, 100) . "...\n";
        echo "Réponse donnée: '{$answer['selected_answer']}'\n";
        echo "Marquée comme: " . ($answer['is_correct'] ? 'CORRECTE ✓' : 'INCORRECTE ✗') . "\n";
        
        // Récupérer les choix pour cette question
        $stmt_choices = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt_choices->execute([$answer['question_id']]);
        $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Choix disponibles:\n";
        $correct_letters = [];
        
        foreach ($choices as $index => $choice) {
            $letter = chr(97 + $index); // a, b, c, d
            $is_correct = $choice['is_correct'] == 1;
            if ($is_correct) {
                $correct_letters[] = $letter;
            }
            echo "  $letter) {$choice['choice_text']} " . ($is_correct ? '✓ CORRECT' : '') . "\n";
        }
        
        echo "Bonnes réponses attendues: [" . implode(', ', $correct_letters) . "]\n";
        
        // Validation manuelle
        $question_type = $answer['question_type'] ?? 'single';
        $should_be_correct = false;
        
        if ($question_type === 'multiple') {
            $user_answers = [];
            if (!empty($answer['selected_answer'])) {
                $user_answers = explode(',', $answer['selected_answer']);
                $user_answers = array_map('trim', $user_answers);
                $user_answers = array_filter($user_answers);
            }
            
            sort($user_answers);
            sort($correct_letters);
            $should_be_correct = ($user_answers === $correct_letters);
            
            echo "Réponse parsée: [" . implode(', ', $user_answers) . "]\n";
        } else {
            $should_be_correct = in_array($answer['selected_answer'], $correct_letters);
        }
        
        echo "Validation manuelle: " . ($should_be_correct ? 'DEVRAIT ÊTRE CORRECT ✓' : 'VRAIMENT INCORRECT ✗') . "\n";
        
        if ($should_be_correct != $answer['is_correct']) {
            echo "🚨 PROBLÈME DE VALIDATION DÉTECTÉ!\n";
        }
        
        echo "\n";
    }
    
    // 4. Résumé des problèmes
    $manual_correct = 0;
    foreach ($answers as $answer) {
        $stmt_choices = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt_choices->execute([$answer['question_id']]);
        $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
        
        $correct_letters = [];
        foreach ($choices as $index => $choice) {
            if ($choice['is_correct'] == 1) {
                $correct_letters[] = chr(97 + $index);
            }
        }
        
        $question_type = $answer['question_type'] ?? 'single';
        $should_be_correct = false;
        
        if ($question_type === 'multiple') {
            $user_answers = [];
            if (!empty($answer['selected_answer'])) {
                $user_answers = explode(',', $answer['selected_answer']);
                $user_answers = array_map('trim', $user_answers);
                $user_answers = array_filter($user_answers);
            }
            sort($user_answers);
            sort($correct_letters);
            $should_be_correct = ($user_answers === $correct_letters);
        } else {
            $should_be_correct = in_array($answer['selected_answer'], $correct_letters);
        }
        
        if ($should_be_correct) {
            $manual_correct++;
        }
    }
    
    $manual_score = ($manual_correct / count($answers)) * 100;
    
    echo "=== RÉSUMÉ ===\n";
    echo "Score en base: {$session['score']}%\n";
    echo "Score recalculé: $manual_score%\n";
    echo "Réponses correctes réelles: $manual_correct/" . count($answers) . "\n";
    
    if (abs($manual_score - $session['score']) > 0.1) {
        echo "🚨 DIFFÉRENCE DÉTECTÉE! Le score devrait être $manual_score% au lieu de {$session['score']}%\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
