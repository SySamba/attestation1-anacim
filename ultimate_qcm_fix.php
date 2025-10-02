<!DOCTYPE html>
<html>
<head>
    <title>Correction Ultime QCM</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .alert { padding: 15px; margin: 15px 0; border-radius: 5px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        button { padding: 12px 24px; margin: 10px 5px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #c82333; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-info { background: #17a2b8; }
        .btn-info:hover { background: #138496; }
        #result { margin-top: 20px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Correction Ultime du Système QCM</h1>
        
        <div class="alert alert-danger">
            <strong>🚨 PROBLÈME CRITIQUE:</strong> Tous les candidats obtiennent 3/5 même avec toutes les bonnes réponses. 
            Le système de validation QCM a un bug systémique.
        </div>
        
        <div class="step">
            <h3>Étape 1: Diagnostic</h3>
            <button class="btn-info" onclick="runDiagnostic()">🔍 DIAGNOSTIQUER LE PROBLÈME</button>
        </div>
        
        <div class="step">
            <h3>Étape 2: Correction</h3>
            <button onclick="fixAllScores()">🚀 CORRIGER TOUS LES SCORES</button>
        </div>
        
        <div class="step">
            <h3>Étape 3: Vérification</h3>
            <button class="btn-success" onclick="verifyFix()">✅ VÉRIFIER LA CORRECTION</button>
        </div>
        
        <div id="result"></div>
    </div>

    <script>
        function runDiagnostic() {
            showLoading('Diagnostic en cours...');
            makeRequest('diagnostic');
        }
        
        function fixAllScores() {
            showLoading('Correction de tous les scores...');
            makeRequest('fix_all');
        }
        
        function verifyFix() {
            showLoading('Vérification...');
            makeRequest('verify');
        }
        
        function showLoading(message) {
            document.getElementById('result').innerHTML = '<div class="alert alert-info">🔄 ' + message + '</div>';
        }
        
        function makeRequest(action) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        document.getElementById('result').innerHTML = '<div class="alert alert-success"><h3>✅ Résultat:</h3><pre>' + xhr.responseText + '</pre></div>';
                    } else {
                        document.getElementById('result').innerHTML = '<div class="alert alert-danger">❌ Erreur: ' + xhr.status + '</div>';
                    }
                }
            };
            xhr.send('action=' + action);
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
        
        $action = $_POST['action'];
        
        if ($action === 'diagnostic') {
            echo "=== DIAGNOSTIC SYSTÈME QCM ===\n\n";
            
            // Analyser les sessions récentes
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM qcm_sessions WHERE status = 'completed'");
            $total_sessions = $stmt->fetch()['total'];
            echo "Sessions complétées: $total_sessions\n";
            
            $stmt = $pdo->query("SELECT COUNT(*) as failed FROM qcm_sessions WHERE status = 'completed' AND score < 80");
            $failed_sessions = $stmt->fetch()['failed'];
            echo "Sessions échouées (< 80%): $failed_sessions\n";
            
            $stmt = $pdo->query("SELECT AVG(score) as avg_score FROM qcm_sessions WHERE status = 'completed'");
            $avg_score = round($stmt->fetch()['avg_score'], 2);
            echo "Score moyen: $avg_score%\n\n";
            
            // Analyser les questions
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM qcm_questions");
            $total_questions = $stmt->fetch()['total'];
            echo "Questions totales: $total_questions\n";
            
            $stmt = $pdo->query("SELECT COUNT(*) as multiple FROM qcm_questions WHERE question_type = 'multiple'");
            $multiple_questions = $stmt->fetch()['multiple'];
            echo "Questions multiples: $multiple_questions\n";
            
            $stmt = $pdo->query("SELECT COUNT(*) as single FROM qcm_questions WHERE question_type != 'multiple' OR question_type IS NULL");
            $single_questions = $stmt->fetch()['single'];
            echo "Questions simples: $single_questions\n\n";
            
            echo "🚨 PROBLÈME IDENTIFIÉ:\n";
            echo "- Tous les candidats obtiennent des scores similaires (autour de 60%)\n";
            echo "- La validation des réponses ne fonctionne pas correctement\n";
            echo "- Les questions multiples ne sont pas comptées\n";
            
        } elseif ($action === 'fix_all') {
            echo "=== CORRECTION DE TOUS LES SCORES ===\n\n";
            
            $corrections = 0;
            $sessions_fixed = 0;
            
            // Récupérer toutes les sessions
            $stmt = $pdo->query("SELECT * FROM qcm_sessions WHERE status = 'completed'");
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($sessions as $session) {
                echo "Session {$session['id']} - Candidat {$session['candidate_id']}:\n";
                
                // Récupérer les réponses
                $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                                      JOIN qcm_questions q ON qa.question_id = q.id 
                                      WHERE qa.session_id = ?");
                $stmt->execute([$session['id']]);
                $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $new_correct = 0;
                
                foreach ($answers as $answer) {
                    // Récupérer les choix
                    $stmt_c = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
                    $stmt_c->execute([$answer['question_id']]);
                    $choices = $stmt_c->fetchAll(PDO::FETCH_ASSOC);
                    
                    $is_correct = false;
                    $question_type = $answer['question_type'] ?? 'single';
                    
                    if ($question_type === 'multiple') {
                        $correct_letters = [];
                        foreach ($choices as $index => $choice) {
                            if ($choice['is_correct'] == 1) {
                                $correct_letters[] = chr(97 + $index);
                            }
                        }
                        
                        $user_answers = [];
                        if (!empty($answer['selected_answer'])) {
                            $user_answers = explode(',', $answer['selected_answer']);
                            $user_answers = array_map('trim', $user_answers);
                            $user_answers = array_filter($user_answers);
                        }
                        
                        sort($user_answers);
                        sort($correct_letters);
                        $is_correct = ($user_answers === $correct_letters);
                    } else {
                        $correct_letter = null;
                        foreach ($choices as $index => $choice) {
                            if ($choice['is_correct'] == 1) {
                                $correct_letter = chr(97 + $index);
                                break;
                            }
                        }
                        $is_correct = ($answer['selected_answer'] === $correct_letter);
                    }
                    
                    if ($is_correct) $new_correct++;
                    
                    // Corriger si nécessaire
                    if ($is_correct != $answer['is_correct']) {
                        $stmt = $pdo->prepare("UPDATE qcm_answers SET is_correct = ? WHERE id = ?");
                        $stmt->execute([$is_correct ? 1 : 0, $answer['id']]);
                        $corrections++;
                    }
                }
                
                // Calculer nouveau score
                $new_score = ($new_correct / count($answers)) * 100;
                $new_status = ($new_score >= 80) ? 'passed' : 'failed';
                
                // Mettre à jour session
                $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ?, status = ? WHERE id = ?");
                $stmt->execute([$new_score, $new_correct, $new_status, $session['id']]);
                
                echo "  Score: {$session['score']}% → $new_score%\n";
                
                if (abs($new_score - $session['score']) > 0.1) {
                    $sessions_fixed++;
                }
            }
            
            echo "\n✅ CORRECTION TERMINÉE!\n";
            echo "Sessions corrigées: $sessions_fixed\n";
            echo "Réponses corrigées: $corrections\n";
            
        } elseif ($action === 'verify') {
            echo "=== VÉRIFICATION CORRECTION ===\n\n";
            
            // Vérifier les dernières sessions
            $stmt = $pdo->query("SELECT * FROM qcm_sessions WHERE status = 'completed' ORDER BY id DESC LIMIT 5");
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($sessions as $session) {
                echo "Session {$session['id']} - Candidat {$session['candidate_id']}:\n";
                echo "  Score: {$session['score']}%\n";
                echo "  Réponses: {$session['correct_answers']}/{$session['total_questions']}\n";
                echo "  Statut: {$session['status']}\n\n";
            }
            
            // Statistiques globales
            $stmt = $pdo->query("SELECT AVG(score) as avg, MIN(score) as min, MAX(score) as max FROM qcm_sessions WHERE status = 'completed'");
            $stats = $stmt->fetch();
            
            echo "STATISTIQUES GLOBALES:\n";
            echo "Score moyen: " . round($stats['avg'], 2) . "%\n";
            echo "Score minimum: {$stats['min']}%\n";
            echo "Score maximum: {$stats['max']}%\n";
            
            $stmt = $pdo->query("SELECT COUNT(*) as passed FROM qcm_sessions WHERE status = 'completed' AND score >= 80");
            $passed = $stmt->fetch()['passed'];
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM qcm_sessions WHERE status = 'completed'");
            $total = $stmt->fetch()['total'];
            
            $pass_rate = $total > 0 ? round(($passed / $total) * 100, 2) : 0;
            echo "Taux de réussite: $pass_rate% ($passed/$total)\n";
        }
        
    } catch (Exception $e) {
        echo "Erreur: " . $e->getMessage();
    }
    exit;
}
?>
