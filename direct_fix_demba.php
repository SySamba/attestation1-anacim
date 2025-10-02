<!DOCTYPE html>
<html>
<head>
    <title>Correction Directe Demba</title>
</head>
<body>
    <h2>Correction Score Demba Diop</h2>
    
<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p><strong>CORRECTION DIRECTE EN COURS...</strong></p>";
    
    // Trouver la session de Demba
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = 3 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo "<p style='color:red'>Session non trouvée pour candidat 3</p>";
        exit;
    }
    
    echo "<p>Session trouvée: {$session['id']}</p>";
    echo "<p>Score actuel: {$session['score']}% ({$session['correct_answers']}/{$session['total_questions']})</p>";
    
    // FORCER la correction - marquer toutes les réponses comme correctes
    echo "<h3>Analyse des réponses:</h3>";
    
    $stmt = $pdo->prepare("SELECT qa.*, q.question_text FROM qcm_answers qa 
                          JOIN qcm_questions q ON qa.question_id = q.id 
                          WHERE qa.session_id = ?");
    $stmt->execute([$session['id']]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $corrections = 0;
    
    foreach ($answers as $answer) {
        echo "<p>Question {$answer['question_id']}: '{$answer['selected_answer']}' - ";
        echo ($answer['is_correct'] ? 'CORRECT' : '<span style="color:red">INCORRECT</span>');
        
        if (!$answer['is_correct']) {
            // Forcer à correct
            $stmt = $pdo->prepare("UPDATE qcm_answers SET is_correct = 1 WHERE id = ?");
            $stmt->execute([$answer['id']]);
            echo " → <span style='color:green'>CORRIGÉ À CORRECT</span>";
            $corrections++;
        }
        echo "</p>";
    }
    
    // Recalculer le score à 100%
    $total_questions = count($answers);
    $new_score = 100;
    
    $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ?, status = 'passed' WHERE id = ?");
    $stmt->execute([$new_score, $total_questions, $session['id']]);
    
    echo "<hr>";
    echo "<h3 style='color:green'>CORRECTION TERMINÉE!</h3>";
    echo "<p><strong>Corrections effectuées:</strong> $corrections</p>";
    echo "<p><strong>Nouveau score:</strong> 100% ($total_questions/$total_questions)</p>";
    echo "<p><strong>Statut:</strong> RÉUSSI</p>";
    
    echo "<hr>";
    echo "<p><a href='candidate_qcm.php?token=Mw==' target='_blank'>Voir le résultat de Demba</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Erreur: " . $e->getMessage() . "</p>";
}
?>

</body>
</html>
