<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== VÉRIFICATION COMPLÈTE DES QUESTIONS QCM ===\n\n";
    
    // 1. Analyser toutes les questions et leurs bonnes réponses
    $stmt = $pdo->prepare("SELECT * FROM qcm_questions ORDER BY id");
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total questions: " . count($questions) . "\n\n";
    
    foreach ($questions as $q) {
        echo "--- Question {$q['id']} ---\n";
        echo "Type: " . ($q['question_type'] ?? 'single') . "\n";
        echo "Texte: " . substr($q['question_text'], 0, 80) . "...\n";
        
        // Récupérer les choix
        $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
        $stmt->execute([$q['id']]);
        $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Choix (ordre ID):\n";
        $correct_count = 0;
        $correct_letters = [];
        
        foreach ($choices as $index => $choice) {
            $letter = chr(97 + $index);
            $is_correct = $choice['is_correct'] == 1;
            if ($is_correct) {
                $correct_count++;
                $correct_letters[] = $letter;
            }
            echo "  $letter) ID:{$choice['id']} - {$choice['choice_text']} " . ($is_correct ? '✓' : '') . "\n";
        }
        
        echo "Bonnes réponses: [" . implode(',', $correct_letters) . "] ($correct_count réponses)\n";
        
        // Vérifier la cohérence
        $question_type = $q['question_type'] ?? 'single';
        if ($question_type === 'multiple' && $correct_count < 2) {
            echo "⚠️  PROBLÈME: Question multiple avec seulement $correct_count bonne réponse\n";
        } elseif ($question_type !== 'multiple' && $correct_count > 1) {
            echo "⚠️  PROBLÈME: Question simple avec $correct_count bonnes réponses\n";
        }
        
        echo "\n";
    }
    
    // 2. Analyser les sessions récentes
    echo "=== ANALYSE DES SESSIONS RÉCENTES ===\n\n";
    
    $stmt = $pdo->prepare("SELECT s.*, c.prenom, c.nom FROM qcm_sessions s 
                          JOIN candidates c ON s.candidate_id = c.id 
                          WHERE s.status = 'completed' 
                          ORDER BY s.id DESC LIMIT 5");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sessions as $session) {
        echo "Session {$session['id']} - {$session['prenom']} {$session['nom']}:\n";
        echo "Score: {$session['score']}% ({$session['correct_answers']}/{$session['total_questions']})\n";
        
        // Analyser les réponses de cette session
        $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                              JOIN qcm_questions q ON qa.question_id = q.id 
                              WHERE qa.session_id = ?");
        $stmt->execute([$session['id']]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $single_correct = 0;
        $single_total = 0;
        $multiple_correct = 0;
        $multiple_total = 0;
        
        foreach ($answers as $answer) {
            $type = $answer['question_type'] ?? 'single';
            if ($type === 'multiple') {
                $multiple_total++;
                if ($answer['is_correct']) $multiple_correct++;
            } else {
                $single_total++;
                if ($answer['is_correct']) $single_correct++;
            }
        }
        
        echo "  Questions simples: $single_correct/$single_total\n";
        echo "  Questions multiples: $multiple_correct/$multiple_total\n";
        
        if ($multiple_total > 0 && $multiple_correct == 0) {
            echo "  🚨 PROBLÈME: Aucune question multiple correcte!\n";
        }
        
        echo "\n";
    }
    
    // 3. Test spécifique pour Modou Sy (dernier candidat)
    echo "=== ANALYSE DERNIER CANDIDAT ===\n\n";
    
    $stmt = $pdo->prepare("SELECT * FROM candidates ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $last_candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_candidate) {
        echo "Dernier candidat: {$last_candidate['prenom']} {$last_candidate['nom']} (ID: {$last_candidate['id']})\n";
        
        $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$last_candidate['id']]);
        $last_session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($last_session) {
            echo "Session: {$last_session['id']}\n";
            echo "Score: {$last_session['score']}%\n";
            
            // Détail des réponses
            $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                                  JOIN qcm_questions q ON qa.question_id = q.id 
                                  WHERE qa.session_id = ?");
            $stmt->execute([$last_session['id']]);
            $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Détail des réponses:\n";
            foreach ($answers as $answer) {
                echo "  Q{$answer['question_id']} ({$answer['question_type']}): '{$answer['selected_answer']}' - ";
                echo ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
            }
        } else {
            echo "Aucune session trouvée\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
