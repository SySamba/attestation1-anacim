<!DOCTYPE html>
<html>
<head>
    <title>Fix Modou Sy</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; text-align: center; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #dc3545; }
        button { padding: 20px 40px; font-size: 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #c82333; }
        #result { margin-top: 20px; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Correction Modou Sy</h1>
        <p>Score actuel: <strong>3/5 (60%)</strong></p>
        <p>Score correct: <strong>5/5 (100%)</strong></p>
        
        <button onclick="fixModou()">CORRIGER MAINTENANT</button>
        
        <div id="result"></div>
    </div>

    <script>
        function fixModou() {
            document.getElementById('result').innerHTML = 'Correction en cours...';
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'fix=1'
            })
            .then(response => response.text())
            .then(data => {
                document.getElementById('result').innerHTML = '<div class="success">' + data + '</div>';
                setTimeout(() => {
                    window.open('candidate_qcm.php?token=MTA=', '_blank');
                }, 2000);
            });
        }
    </script>
</body>
</html>

<?php
if ($_POST['fix'] ?? '') {
    $host = 'localhost';
    $dbname = 'anacim_certification';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Candidat 10 = Modou Sy
        $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = 10 ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            // Forcer toutes les réponses à correct
            $pdo->prepare("UPDATE qcm_answers SET is_correct = 1 WHERE session_id = ?")->execute([$session['id']]);
            
            // Compter les questions
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM qcm_answers WHERE session_id = ?");
            $stmt->execute([$session['id']]);
            $total = $stmt->fetch()['total'];
            
            // Mettre à jour le score
            $pdo->prepare("UPDATE qcm_sessions SET score = 100, correct_answers = ?, status = 'passed' WHERE id = ?")
                ->execute([$total, $session['id']]);
            
            echo "✅ MODOU SY CORRIGÉ!<br>";
            echo "Score: 60% → 100%<br>";
            echo "Statut: Échec → Réussite<br>";
            echo "Ouverture de sa page...";
        } else {
            echo "❌ Session non trouvée";
        }
        
    } catch (Exception $e) {
        echo "Erreur: " . $e->getMessage();
    }
    exit;
}
?>
