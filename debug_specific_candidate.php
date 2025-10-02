<?php
require_once 'config/database.php';

// Analyser un candidat spécifique (demba diop qui devrait avoir 5/5)
$stmt = $pdo->prepare("SELECT c.prenom, c.nom, qs.id as session_id, qs.score, qs.correct_answers, qs.total_questions
                       FROM candidates c 
                       JOIN qcm_sessions qs ON c.id = qs.candidate_id 
                       WHERE c.nom LIKE '%diop%' AND c.prenom LIKE '%demba%'");
$stmt->execute();
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidate) {
    echo "Candidat Demba Diop non trouvé\n";
    exit;
}

echo "=== ANALYSE DÉTAILLÉE: {$candidate['prenom']} {$candidate['nom']} ===\n";
echo "Session ID: {$candidate['session_id']}\n";
echo "Score actuel: {$candidate['score']}%\n";
echo "Réponses correctes: {$candidate['correct_answers']}/{$candidate['total_questions']}\n\n";

// Récupérer toutes les réponses de ce candidat
$stmt = $pdo->prepare("SELECT qa.question_id, qa.selected_answer, qa.is_correct, q.question_text, q.question_type
                       FROM qcm_answers qa 
                       JOIN qcm_questions q ON qa.question_id = q.id 
                       WHERE qa.session_id = ?
                       ORDER BY qa.question_id");
$stmt->execute([$candidate['session_id']]);
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($answers as $answer) {
    echo "Question {$answer['question_id']} ({$answer['question_type']}):\n";
    echo "  Texte: " . substr($answer['question_text'], 0, 100) . "...\n";
    echo "  Réponse candidat: '{$answer['selected_answer']}'\n";
    echo "  Marqué comme: " . ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
    
    // Récupérer les bonnes réponses
    $stmt_choices = $pdo->prepare("SELECT choice_text, is_correct FROM qcm_choices WHERE question_id = ? ORDER BY id");
    $stmt_choices->execute([$answer['question_id']]);
    $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  Choix disponibles:\n";
    $correct_letters = [];
    foreach ($choices as $index => $choice) {
        $letter = chr(97 + $index);
        echo "    $letter) " . $choice['choice_text'] . ($choice['is_correct'] ? ' [CORRECT]' : '') . "\n";
        if ($choice['is_correct']) {
            $correct_letters[] = $letter;
        }
    }
    
    echo "  Bonnes réponses attendues: " . implode(',', $correct_letters) . "\n";
    
    // Vérification manuelle
    if ($answer['question_type'] === 'multiple') {
        $user_answers = explode(',', $answer['selected_answer']);
        sort($user_answers);
        sort($correct_letters);
        $should_be_correct = ($user_answers === $correct_letters);
        echo "  Devrait être: " . ($should_be_correct ? 'CORRECT' : 'INCORRECT') . "\n";
        if ($should_be_correct !== (bool)$answer['is_correct']) {
            echo "  ⚠️  ERREUR DE VALIDATION DÉTECTÉE!\n";
        }
    } else {
        $should_be_correct = ($answer['selected_answer'] === $correct_letters[0]);
        echo "  Devrait être: " . ($should_be_correct ? 'CORRECT' : 'INCORRECT') . "\n";
        if ($should_be_correct !== (bool)$answer['is_correct']) {
            echo "  ⚠️  ERREUR DE VALIDATION DÉTECTÉE!\n";
        }
    }
    echo "\n";
}
?>
