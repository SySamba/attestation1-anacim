<!DOCTYPE html>
<html>
<head>
    <title>Analyse Complète QCM</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .section { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        button { padding: 10px 20px; margin: 5px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; white-space: pre-wrap; max-height: 400px; overflow-y: auto; }
        #result { margin-top: 20px; }
        .alert { padding: 15px; margin: 15px 0; border-radius: 5px; }
        .alert-info { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Analyse Complète QCM - Modou Sy</h1>
        
        <div class="alert alert-info">
            Analysons les questions QCM et les réponses du candidat Modou Sy pour identifier le problème de validation.
        </div>
        
        <div class="section">
            <h3>1. Vérification des Questions QCM</h3>
            <button onclick="analyzeQuestions()">📋 ANALYSER TOUTES LES QUESTIONS</button>
        </div>
        
        <div class="section">
            <h3>2. Analyse Candidat Modou Sy</h3>
            <button class="btn-warning" onclick="analyzeModou()">👤 ANALYSER MODOU SY</button>
        </div>
        
        <div class="section">
            <h3>3. Comparaison Réponses vs Attendues</h3>
            <button class="btn-success" onclick="compareAnswers()">⚖️ COMPARER RÉPONSES</button>
        </div>
        
        <div class="section">
            <h3>4. Correction si Nécessaire</h3>
            <button class="btn-danger" onclick="fixIssues()">🔧 CORRIGER PROBLÈMES</button>
        </div>
        
        <div id="result"></div>
    </div>

    <script>
        function analyzeQuestions() {
            showLoading('Analyse des questions...');
            makeRequest('questions');
        }
        
        function analyzeModou() {
            showLoading('Analyse de Modou Sy...');
            makeRequest('modou');
        }
        
        function compareAnswers() {
            showLoading('Comparaison des réponses...');
            makeRequest('compare');
        }
        
        function fixIssues() {
            showLoading('Correction en cours...');
            makeRequest('fix');
        }
        
        function showLoading(message) {
            document.getElementById('result').innerHTML = '<div class="alert alert-info">🔄 ' + message + '</div>';
        }
        
        function makeRequest(action) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById('result').innerHTML = '<pre>' + xhr.responseText + '</pre>';
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
        
        if ($action === 'questions') {
            echo "=== ANALYSE DES QUESTIONS QCM ===\n\n";
            
            $stmt = $pdo->query("SELECT * FROM qcm_questions ORDER BY id");
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($questions as $q) {
                echo "Question {$q['id']} ({$q['question_type']}):\n";
                echo substr($q['question_text'], 0, 60) . "...\n";
                
                $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
                $stmt->execute([$q['id']]);
                $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $correct_letters = [];
                foreach ($choices as $index => $choice) {
                    $letter = chr(97 + $index);
                    if ($choice['is_correct']) $correct_letters[] = $letter;
                    echo "  $letter) {$choice['choice_text']} " . ($choice['is_correct'] ? '✓' : '') . "\n";
                }
                echo "Bonnes réponses: [" . implode(',', $correct_letters) . "]\n\n";
            }
            
        } elseif ($action === 'modou') {
            echo "=== ANALYSE MODOU SY ===\n\n";
            
            // Trouver Modou Sy ou le dernier candidat
            $stmt = $pdo->query("SELECT * FROM candidates WHERE nom LIKE '%sy%' OR prenom LIKE '%modou%' ORDER BY id DESC LIMIT 1");
            $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$candidate) {
                $stmt = $pdo->query("SELECT * FROM candidates ORDER BY id DESC LIMIT 1");
                $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            if ($candidate) {
                echo "Candidat: {$candidate['prenom']} {$candidate['nom']} (ID: {$candidate['id']})\n\n";
                
                $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$candidate['id']]);
                $session = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($session) {
                    echo "Session {$session['id']}:\n";
                    echo "Score: {$session['score']}%\n";
                    echo "Réponses correctes: {$session['correct_answers']}/{$session['total_questions']}\n\n";
                    
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
                    echo "Aucune session trouvée\n";
                }
            } else {
                echo "Aucun candidat trouvé\n";
            }
            
        } elseif ($action === 'compare') {
            echo "=== COMPARAISON RÉPONSES ===\n\n";
            
            $stmt = $pdo->query("SELECT * FROM candidates ORDER BY id DESC LIMIT 1");
            $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($candidate) {
                $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$candidate['id']]);
                $session = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($session) {
                    $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                                          JOIN qcm_questions q ON qa.question_id = q.id 
                                          WHERE qa.session_id = ?");
                    $stmt->execute([$session['id']]);
                    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $problems = 0;
                    $should_be_correct = 0;
                    
                    foreach ($answers as $answer) {
                        echo "Question {$answer['question_id']}:\n";
                        
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
                        
                        echo "  Attendu: [" . implode(',', $correct_letters) . "]\n";
                        echo "  Donné: '{$answer['selected_answer']}'\n";
                        echo "  Marqué: " . ($answer['is_correct'] ? 'CORRECT' : 'INCORRECT') . "\n";
                        
                        // Validation manuelle
                        $really_correct = false;
                        if ($answer['question_type'] === 'multiple') {
                            $user_answers = array_filter(array_map('trim', explode(',', $answer['selected_answer'])));
                            sort($user_answers);
                            sort($correct_letters);
                            $really_correct = ($user_answers === $correct_letters);
                        } else {
                            $really_correct = in_array($answer['selected_answer'], $correct_letters);
                        }
                        
                        echo "  Devrait être: " . ($really_correct ? 'CORRECT' : 'INCORRECT') . "\n";
                        
                        if ($really_correct) $should_be_correct++;
                        if ($really_correct != $answer['is_correct']) {
                            echo "  🚨 PROBLÈME DE VALIDATION!\n";
                            $problems++;
                        }
                        echo "\n";
                    }
                    
                    $should_score = ($should_be_correct / count($answers)) * 100;
                    echo "RÉSUMÉ:\n";
                    echo "Score actuel: {$session['score']}%\n";
                    echo "Score correct: $should_score%\n";
                    echo "Problèmes détectés: $problems\n";
                }
            }
            
        } elseif ($action === 'fix') {
            echo "=== CORRECTION DES PROBLÈMES ===\n\n";
            
            $stmt = $pdo->query("SELECT * FROM candidates ORDER BY id DESC LIMIT 1");
            $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($candidate) {
                $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$candidate['id']]);
                $session = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($session) {
                    $stmt = $pdo->prepare("SELECT qa.*, q.question_type FROM qcm_answers qa 
                                          JOIN qcm_questions q ON qa.question_id = q.id 
                                          WHERE qa.session_id = ?");
                    $stmt->execute([$session['id']]);
                    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $corrections = 0;
                    $new_correct = 0;
                    
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
                        
                        // Validation correcte
                        $really_correct = false;
                        if ($answer['question_type'] === 'multiple') {
                            $user_answers = array_filter(array_map('trim', explode(',', $answer['selected_answer'])));
                            sort($user_answers);
                            sort($correct_letters);
                            $really_correct = ($user_answers === $correct_letters);
                        } else {
                            $really_correct = in_array($answer['selected_answer'], $correct_letters);
                        }
                        
                        if ($really_correct) $new_correct++;
                        
                        // Corriger si nécessaire
                        if ($really_correct != $answer['is_correct']) {
                            $stmt = $pdo->prepare("UPDATE qcm_answers SET is_correct = ? WHERE id = ?");
                            $stmt->execute([$really_correct ? 1 : 0, $answer['id']]);
                            $corrections++;
                            echo "Q{$answer['question_id']}: Corrigé\n";
                        }
                    }
                    
                    // Mettre à jour le score
                    $new_score = ($new_correct / count($answers)) * 100;
                    $new_status = ($new_score >= 80) ? 'passed' : 'failed';
                    
                    $stmt = $pdo->prepare("UPDATE qcm_sessions SET score = ?, correct_answers = ?, status = ? WHERE id = ?");
                    $stmt->execute([$new_score, $new_correct, $new_status, $session['id']]);
                    
                    echo "\nCORRECTION TERMINÉE:\n";
                    echo "Réponses corrigées: $corrections\n";
                    echo "Nouveau score: $new_score%\n";
                    echo "Nouveau statut: $new_status\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "Erreur: " . $e->getMessage();
    }
    exit;
}
?>
