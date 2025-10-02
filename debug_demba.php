<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== DEBUG CANDIDAT DEMBA DIOP (ID: 3) ===\n\n";
    
    // 1. Trouver le candidat
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = 3");
    $stmt->execute();
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($candidate) {
        echo "Candidat trouvé: {$candidate['prenom']} {$candidate['nom']}\n\n";
    } else {
        echo "Candidat ID 3 non trouvé\n";
        exit;
    }
    
    // 2. Trouver sa session QCM
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = 3 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo "Aucune session QCM trouvée pour ce candidat\n";
        exit;
    }
    
    echo "Session ID: {$session['id']}\n";
    echo "Score affiché: {$session['score']}%\n";
    echo "Réponses correctes: {$session['correct_answers']}/{$session['total_questions']}\n";
    echo "Statut: {$session['status']}\n\n";
    
    // 3. Analyser chaque réponse
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
        echo "Texte: " . substr($answer['question_text'], 0, 80) . "...\n";
        echo "Type: " . ($answer['question_type'] ?? 'single') . "\n";
        echo "Réponse donnée: '{$answer['selected_answer']}'\n";
        echo "Marquée comme: " . ($answer['is_correct'] ? 'CORRECTE ✓' : 'INCORRECTE ✗') . "\n";
        
        // Récupérer les choix pour cette question
        $stmt_choices = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt_choices->execute([$answer['question_id']]);
        $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Choix disponibles:\n";
        $correct_letters = [];
        
        // Trier par ID pour avoir l'ordre correct
        usort($choices, function($a, $b) { return $a['id'] - $b['id']; });
        
        foreach ($choices as $index => $choice) {
            $letter = chr(97 + $index); // a, b, c, d
            $is_correct = $choice['is_correct'] == 1;
            if ($is_correct) {
                $correct_letters[] = $letter;
            }
            echo "  $letter) {$choice['choice_text']} " . ($is_correct ? '✓ CORRECT' : '') . "\n";
        }
        
        echo "Bonnes réponses attendues: " . implode(', ', $correct_letters) . "\n";
        
        // Analyser pourquoi c'est incorrect
        if (!$answer['is_correct']) {
            $user_answers = explode(',', $answer['selected_answer']);
            echo "PROBLÈME: Réponse '{$answer['selected_answer']}' ne correspond pas aux bonnes réponses [" . implode(',', $correct_letters) . "]\n";
            
            // Test de validation manuelle
            if ($answer['question_type'] === 'multiple') {
                sort($user_answers);
                sort($correct_letters);
                $should_be_correct = ($user_answers === $correct_letters);
            } else {
                $should_be_correct = ($answer['selected_answer'] === $correct_letters[0]);
            }
            
            echo "Validation manuelle: " . ($should_be_correct ? 'DEVRAIT ÊTRE CORRECT' : 'VRAIMENT INCORRECT') . "\n";
        }
        
        echo "\n";
    }
    
    // 4. Recalculer le score manuellement
    $correct_count = 0;
    foreach ($answers as $answer) {
        if ($answer['is_correct']) {
            $correct_count++;
        }
    }
    
    $manual_score = ($correct_count / count($answers)) * 100;
    echo "=== RÉCAPITULATIF ===\n";
    echo "Score en base: {$session['score']}%\n";
    echo "Score recalculé: $manual_score%\n";
    echo "Réponses correctes: $correct_count/" . count($answers) . "\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
