<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérifier le score actuel de Demba
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = 3 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo "Score actuel de Demba: {$session['score']}% ({$session['correct_answers']}/{$session['total_questions']})\n";
        echo "Statut: {$session['status']}\n\n";
        
        // Vérifier chaque réponse
        $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                              JOIN qcm_questions q ON qa.question_id = q.id 
                              WHERE qa.session_id = ?");
        $stmt->execute([$session['id']]);
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($answers as $answer) {
            echo "Q{$answer['question_id']} ({$answer['question_type']}): '{$answer['selected_answer']}' - " . 
                 ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
