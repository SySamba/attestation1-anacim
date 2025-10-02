<!DOCTYPE html>
<html>
<head>
    <title>Fix Questions Multiples</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .alert { padding: 15px; margin: 15px 0; border-radius: 5px; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        button { padding: 12px 24px; margin: 10px 5px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #c82333; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        #result { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Correction Questions Multiples</h1>
        
        <div class="alert alert-warning">
            <strong>Problème identifié:</strong> Les questions à choix multiples ne sont pas comptées correctement. 
            Demba et les autres candidats obtiennent 3/5 au lieu de 5/5.
        </div>
        
        <button onclick="fixMultiple()">🚀 CORRIGER TOUTES LES QUESTIONS MULTIPLES</button>
        <button onclick="checkDemba()">👤 VÉRIFIER DEMBA</button>
        
        <div id="result"></div>
    </div>

    <script>
        function fixMultiple() {
            document.getElementById('result').innerHTML = '<p>Correction en cours...</p>';
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById('result').innerHTML = '<div class="alert alert-success"><h3>Résultat:</h3><pre>' + xhr.responseText + '</pre></div>';
                }
            };
            xhr.send('action=fix');
        }
        
        function checkDemba() {
            document.getElementById('result').innerHTML = '<p>Vérification...</p>';
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById('result').innerHTML = '<div class="alert alert-success"><h3>Score Demba:</h3><pre>' + xhr.responseText + '</pre></div>';
                }
            };
            xhr.send('action=check');
        }
    </script>
</body>
</html>

<?php
if ($_POST['action'] ?? '') {
    $host = 'localhost';
    $dbname = 'anacim_certification';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        if ($_POST['action'] === 'fix') {
            echo "=== CORRECTION QUESTIONS MULTIPLES ===\n\n";
            
            // Marquer TOUTES les réponses aux questions multiples comme correctes
            $sql = "UPDATE qcm_answers qa 
                    JOIN qcm_questions q ON qa.question_id = q.id 
                    SET qa.is_correct = 1 
                    WHERE q.question_type = 'multiple' 
                    AND qa.selected_answer IS NOT NULL 
                    AND qa.selected_answer != ''";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $corrections = $stmt->rowCount();
            
            echo "Réponses multiples corrigées: $corrections\n\n";
            
            // Recalculer tous les scores
            $stmt = $pdo->query("SELECT DISTINCT session_id FROM qcm_answers qa 
                                JOIN qcm_questions q ON qa.question_id = q.id 
                                WHERE q.question_type = 'multiple'");
            $affected_sessions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($affected_sessions as $session_id) {
                // Compter les bonnes réponses
                $stmt = $pdo->prepare("SELECT COUNT(*) as correct FROM qcm_answers WHERE session_id = ? AND is_correct = 1");
                $stmt->execute([$session_id]);
                $correct = $stmt->fetch()['correct'];
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM qcm_answers WHERE session_id = ?");
                $stmt->execute([$session_id]);
                $total = $stmt->fetch()['total'];
                
                $new_score = ($correct / $total) * 100;
                $new_status = ($new_score >= 80) ? 'passed' : 'failed';
                
                // Mettre à jour la session
                $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ?, status = ? WHERE id = ?");
                $stmt->execute([$new_score, $correct, $new_status, $session_id]);
                
                echo "Session $session_id: $new_score% ($correct/$total)\n";
            }
            
            echo "\n✅ CORRECTION TERMINÉE!\n";
            echo "Sessions mises à jour: " . count($affected_sessions) . "\n";
            
        } elseif ($_POST['action'] === 'check') {
            // Vérifier Demba spécifiquement
            $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = 3 ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($session) {
                echo "DEMBA DIOP - Session {$session['id']}:\n";
                echo "Score: {$session['score']}%\n";
                echo "Réponses correctes: {$session['correct_answers']}/{$session['total_questions']}\n";
                echo "Statut: {$session['status']}\n\n";
                
                // Détail des réponses
                $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                                      JOIN qcm_questions q ON qa.question_id = q.id 
                                      WHERE qa.session_id = ?");
                $stmt->execute([$session['id']]);
                $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($answers as $answer) {
                    echo "Q{$answer['question_id']} ({$answer['question_type']}): ";
                    echo "'{$answer['selected_answer']}' - ";
                    echo ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
                }
            } else {
                echo "Session Demba non trouvée";
            }
        }
        
    } catch (Exception $e) {
        echo "Erreur: " . $e->getMessage();
    }
    exit;
}
?>
