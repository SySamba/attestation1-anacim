<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if candidate is logged in
if (!isset($_SESSION['candidate_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit();
}

$candidate_id = $_SESSION['candidate_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

try {
    $session_id = $_POST['session_id'];
    $question_id = $_POST['question_id'];
    $question_order = $_POST['question_order'];
    $response_type = $_POST['response_type'];
    $selected_category = $_POST['selected_category'] ?? null;
    $clicked_x = $_POST['clicked_x'] ?? null;
    $clicked_y = $_POST['clicked_y'] ?? null;
    $time_spent = $_POST['time_spent'];
    
    // Validate session belongs to candidate
    $stmt = $pdo->prepare("SELECT * FROM imagerie_sessions_interactive WHERE id = ? AND candidate_id = ? AND status = 'in_progress'");
    $stmt->execute([$session_id, $candidate_id]);
    $session = $stmt->fetch();
    
    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Session invalide']);
        exit();
    }
    
    // Check if response already exists for this question
    $stmt = $pdo->prepare("SELECT id FROM imagerie_responses_interactive WHERE session_id = ? AND question_id = ?");
    $stmt->execute([$session_id, $question_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Réponse déjà enregistrée']);
        exit();
    }
    
    // Get correct answer for scoring
    $stmt = $pdo->prepare("SELECT * FROM imagerie_questions_interactive WHERE id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch();
    
    if (!$question) {
        echo json_encode(['success' => false, 'message' => 'Question introuvable']);
        exit();
    }
    
    // Calculate if answer is correct
    $is_correct = false;
    
    if ($response_type === 'pass' && $question['correct_answer_type'] === 'pass') {
        $is_correct = true;
    } elseif ($response_type === 'object_found' && $question['correct_answer_type'] === 'object_found') {
        // Check category match
        if ($selected_category === $question['object_category']) {
            // Check position accuracy
            $distance = sqrt(
                pow($clicked_x - $question['correct_x_position'], 2) + 
                pow($clicked_y - $question['correct_y_position'], 2)
            );
            
            if ($distance <= $question['tolerance_radius']) {
                $is_correct = true;
            }
        }
    }
    
    // Insert response
    $stmt = $pdo->prepare("INSERT INTO imagerie_responses_interactive 
        (session_id, question_id, question_order, response_type, selected_category, clicked_x_position, clicked_y_position, time_spent, is_correct) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $session_id, 
        $question_id, 
        $question_order, 
        $response_type, 
        $selected_category, 
        $clicked_x, 
        $clicked_y, 
        $time_spent, 
        $is_correct
    ]);
    
    // Check if this was the last question
    $stmt = $pdo->prepare("SELECT COUNT(*) as answered FROM imagerie_responses_interactive WHERE session_id = ?");
    $stmt->execute([$session_id]);
    $answered_count = $stmt->fetch()['answered'];
    
    $total_questions = $session['total_questions'];
    $next_question = $answered_count < $total_questions;
    
    if (!$next_question) {
        // Calculate final score
        $stmt = $pdo->prepare("SELECT COUNT(*) as correct FROM imagerie_responses_interactive WHERE session_id = ? AND is_correct = 1");
        $stmt->execute([$session_id]);
        $correct_answers = $stmt->fetch()['correct'];
        
        $score = ($correct_answers / $total_questions) * 100;
        
        // Calculate total time spent
        $stmt = $pdo->prepare("SELECT SUM(time_spent) as total_time FROM imagerie_responses_interactive WHERE session_id = ?");
        $stmt->execute([$session_id]);
        $total_time = $stmt->fetch()['total_time'];
        
        // Update session
        $stmt = $pdo->prepare("UPDATE imagerie_sessions_interactive SET 
            status = 'completed', 
            completed_at = NOW(), 
            correct_answers = ?, 
            score = ?, 
            time_spent = ? 
            WHERE id = ?");
        $stmt->execute([$correct_answers, $score, $total_time, $session_id]);
        
        // Create or update candidate_phase_results
        $stmt = $pdo->prepare("INSERT INTO candidate_phase_results 
            (candidate_id, phase, epreuve, score, status, completed_at) 
            VALUES (?, 'phase2', 'IMAGERIE', ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
            score = VALUES(score), 
            status = VALUES(status), 
            completed_at = VALUES(completed_at)");
        
        $status = $score >= 70 ? 'passed' : 'failed'; // Assuming 70% is passing
        $stmt->execute([$candidate_id, $score, $status]);
    }
    
    echo json_encode([
        'success' => true, 
        'next_question' => $next_question,
        'is_correct' => $is_correct
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>
