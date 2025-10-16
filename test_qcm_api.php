<?php
// Test direct de l'API pour identifier le problème JSON
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Capturer toute sortie HTML avant le JSON
ob_start();

require_once 'config.php';

// Nettoyer le buffer de sortie
ob_clean();

header('Content-Type: application/json');

try {
    // Simuler une requête submit_test
    $session_id = 1; // ID de test
    
    // Vérifier si la session existe, sinon la créer
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        // Créer une session de test
        $pdo->exec("INSERT INTO qcm_sessions (id, candidate_id, phase, epreuve, total_questions, status) VALUES (1, 1, 'phase1', 'THI', 10, 'in_progress') ON DUPLICATE KEY UPDATE status = 'in_progress'");
    }
    
    // Test du processus submit_test
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id = ? AND status = 'in_progress'");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        throw new Exception('Session non trouvée');
    }
    
    // Calculate score
    $stmt = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(is_correct), 0) as correct FROM qcm_answers WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $results = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_questions = $session['total_questions'];
    $correct_count = $results['correct'] ?? 0;
    $score = $total_questions > 0 ? ($correct_count / $total_questions) * 100 : 0;
    
    // Update session
    $stmt = $pdo->prepare("UPDATE qcm_sessions SET completed_at = NOW(), score = ?, correct_answers = ?, status = 'completed' WHERE id = ?");
    $stmt->execute([$score, $correct_count, $session_id]);
    
    // Get session info
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $session_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Vérifier si la colonne session_id existe dans candidate_phase_results
    try {
        $stmt = $pdo->prepare("INSERT INTO candidate_phase_results (candidate_id, phase, epreuve, score, status, session_id, completed_at) 
                              VALUES (?, ?, ?, ?, ?, ?, NOW()) 
                              ON DUPLICATE KEY UPDATE score = ?, status = ?, session_id = ?, completed_at = NOW()");
        $result_status = $score >= 80 ? 'passed' : 'failed';
        $stmt->execute([
            $session_info['candidate_id'], 
            $session_info['phase'], 
            $session_info['epreuve'], 
            $score, 
            $result_status, 
            $session_id,
            $score, 
            $result_status, 
            $session_id
        ]);
    } catch (PDOException $e) {
        // Si erreur session_id, essayer sans
        if (strpos($e->getMessage(), 'session_id') !== false) {
            $stmt = $pdo->prepare("INSERT INTO candidate_phase_results (candidate_id, phase, epreuve, score, status, completed_at) 
                                  VALUES (?, ?, ?, ?, ?, NOW()) 
                                  ON DUPLICATE KEY UPDATE score = ?, status = ?, completed_at = NOW()");
            $stmt->execute([
                $session_info['candidate_id'], 
                $session_info['phase'], 
                $session_info['epreuve'], 
                $score, 
                $result_status,
                $score, 
                $result_status
            ]);
        } else {
            throw $e;
        }
    }
    
    // Réponse JSON propre
    echo json_encode([
        'success' => true,
        'score' => round($score, 2),
        'correct_answers' => $correct_count,
        'total_questions' => $total_questions,
        'passed' => $score >= 80,
        'message' => 'Test soumis avec succès'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}

// Nettoyer le buffer final
ob_end_flush();
?>
