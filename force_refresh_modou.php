<?php
// Force refresh des données pour Modou Sy
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "FORCE REFRESH MODOU SY\n\n";
    
    // 1. Vérifier les données actuelles
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = 10 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
        echo "Session {$session['id']} - Score actuel: {$session['score']}%\n";
        
        // 2. Compter les vraies réponses correctes
        $stmt = $pdo->prepare("SELECT COUNT(*) as correct FROM qcm_answers WHERE session_id = ? AND is_correct = 1");
        $stmt->execute([$session['id']]);
        $correct = $stmt->fetch()['correct'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM qcm_answers WHERE session_id = ?");
        $stmt->execute([$session['id']]);
        $total = $stmt->fetch()['total'];
        
        echo "Réponses correctes en base: $correct/$total\n";
        
        // 3. FORCER la mise à jour avec les vraies données
        $real_score = ($correct / $total) * 100;
        $status = ($real_score >= 80) ? 'passed' : 'failed';
        
        $stmt = $pdo->prepare("UPDATE qcm_sessions SET 
                              score = ?, 
                              correct_answers = ?, 
                              status = ?,
                              completed_at = NOW()
                              WHERE id = ?");
        $stmt->execute([$real_score, $correct, $status, $session['id']]);
        
        echo "FORCÉ: Score = $real_score%, Statut = $status\n";
        
        // 4. Vérifier que la mise à jour a pris
        $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id = ?");
        $stmt->execute([$session['id']]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Vérification: Score = {$updated['score']}%, Statut = {$updated['status']}\n";
        
        echo "\n✅ DONNÉES FORCÉES!\n";
        echo "Videz le cache du navigateur et rechargez la page candidat.\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
