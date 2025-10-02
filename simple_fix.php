<!DOCTYPE html>
<html>
<head>
    <title>Correction Simple QCM</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { color: #dc3545; text-align: center; }
        .problem { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .solution { background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin: 20px 0; }
        button { width: 100%; padding: 15px; font-size: 18px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 10px 0; }
        button:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        #result { margin-top: 20px; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .loading { background: #d1ecf1; color: #0c5460; }
        pre { white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Correction QCM</h1>
        
        <div class="problem">
            <h3>❌ PROBLÈME</h3>
            <p>Tous les candidats obtiennent 3/5 même quand ils trouvent tout. Le système ne valide pas correctement les réponses.</p>
        </div>
        
        <div class="solution">
            <h3>✅ SOLUTION</h3>
            <p>Cliquez sur le bouton pour corriger automatiquement tous les scores.</p>
        </div>
        
        <button onclick="fixAll()">🚀 CORRIGER MAINTENANT</button>
        <button class="btn-success" onclick="verify()">📊 VÉRIFIER RÉSULTATS</button>
        
        <div id="result"></div>
    </div>

    <script>
        function fixAll() {
            document.getElementById('result').innerHTML = '<div class="loading">🔄 Correction en cours...</div>';
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=fix'
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('result').innerHTML = '<div class="success"><h3>✅ Correction terminée!</h3><pre>' + data + '</pre></div>';
            });
        }
        
        function verify() {
            document.getElementById('result').innerHTML = '<div class="loading">🔍 Vérification...</div>';
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=verify'
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('result').innerHTML = '<div class="success"><h3>📊 Résultats:</h3><pre>' + data + '</pre></div>';
            });
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
            echo "CORRECTION AUTOMATIQUE EN COURS...\n\n";
            
            // Étape 1: Corriger toutes les réponses
            $stmt = $pdo->query("SELECT * FROM qcm_sessions WHERE status = 'completed'");
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total_fixed = 0;
            
            foreach ($sessions as $session) {
                $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                                      JOIN qcm_questions q ON qa.question_id = q.id 
                                      WHERE qa.session_id = ?");
                $stmt->execute([$session['id']]);
                $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $correct_count = 0;
                
                foreach ($answers as $answer) {
                    // Récupérer les bonnes réponses
                    $stmt_c = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
                    $stmt_c->execute([$answer['question_id']]);
                    $choices = $stmt_c->fetchAll(PDO::FETCH_ASSOC);
                    
                    $correct_letters = [];
                    foreach ($choices as $index => $choice) {
                        if ($choice['is_correct']) {
                            $correct_letters[] = chr(97 + $index);
                        }
                    }
                    
                    // Valider correctement
                    $is_correct = false;
                    if (($answer['question_type'] ?? 'single') === 'multiple') {
                        $user_answers = array_filter(array_map('trim', explode(',', $answer['selected_answer'])));
                        sort($user_answers);
                        sort($correct_letters);
                        $is_correct = ($user_answers === $correct_letters);
                    } else {
                        $is_correct = in_array($answer['selected_answer'], $correct_letters);
                    }
                    
                    if ($is_correct) $correct_count++;
                    
                    // Mettre à jour si différent
                    if ($is_correct != $answer['is_correct']) {
                        $stmt = $pdo->prepare("UPDATE qcm_answers SET is_correct = ? WHERE id = ?");
                        $stmt->execute([$is_correct ? 1 : 0, $answer['id']]);
                    }
                }
                
                // Recalculer le score
                $new_score = ($correct_count / count($answers)) * 100;
                $new_status = ($new_score >= 80) ? 'passed' : 'failed';
                
                // Mettre à jour la session
                $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ?, status = ? WHERE id = ?");
                $stmt->execute([$new_score, $correct_count, $new_status, $session['id']]);
                
                if (abs($new_score - $session['score']) > 0.1) {
                    $total_fixed++;
                    echo "Session {$session['id']}: {$session['score']}% → $new_score%\n";
                }
            }
            
            echo "\n✅ CORRECTION TERMINÉE!\n";
            echo "Sessions corrigées: $total_fixed\n";
            echo "Tous les scores ont été recalculés correctement.\n";
            
        } elseif ($_POST['action'] === 'verify') {
            echo "VÉRIFICATION DES RÉSULTATS:\n\n";
            
            $stmt = $pdo->query("SELECT s.*, c.prenom, c.nom FROM qcm_sessions s 
                                JOIN candidates c ON s.candidate_id = c.id 
                                WHERE s.status = 'completed' 
                                ORDER BY s.id DESC LIMIT 10");
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($sessions as $session) {
                echo "{$session['prenom']} {$session['nom']}: {$session['score']}% ({$session['correct_answers']}/{$session['total_questions']}) - {$session['status']}\n";
            }
            
            $stmt = $pdo->query("SELECT COUNT(*) as passed FROM qcm_sessions WHERE status = 'completed' AND score >= 80");
            $passed = $stmt->fetch()['passed'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM qcm_sessions WHERE status = 'completed'");
            $total = $stmt->fetch()['total'];
            
            echo "\nSTATISTIQUES:\n";
            echo "Candidats réussis: $passed/$total\n";
            echo "Taux de réussite: " . round(($passed/$total)*100, 1) . "%\n";
        }
        
    } catch (Exception $e) {
        echo "Erreur: " . $e->getMessage();
    }
    exit;
}
?>
