<?php
// Prevent any HTML output before JSON
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

session_start();
require_once 'config.php';

// Clean any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action manquante']);
    exit;
}

$action = $input['action'];

try {
    switch ($action) {
        case 'start_session':
            $candidate_id = $input['candidate_id'] ?? null;
            $category = $input['category'] ?? null;
            $phase = $input['phase'] ?? 'phase1';
            $epreuve = $input['epreuve'] ?? 'THB';
            
            if (!$candidate_id || !$category) {
                throw new Exception('Paramètres manquants');
            }
            
            // Check if candidate is accepted
            $stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ? AND status = 'accepted'");
            $stmt->execute([$candidate_id]);
            $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$candidate) {
                throw new Exception('Candidat non autorisé');
            }
            
            // Check if already has a session for this phase/epreuve
            $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status IN ('in_progress', 'completed')");
            $stmt->execute([$candidate_id, $phase, $epreuve]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                throw new Exception('Une session existe déjà pour cette épreuve');
            }
            
            // Validate category access based on epreuve
            if ($epreuve === 'THI' && $category !== '1') {
                throw new Exception('THI est réservé à la catégorie C1');
            }
            if ($epreuve === 'THB' && $category === '1') {
                throw new Exception('THB est pour les catégories 2, 3, 4, 5');
            }
            
            // Get questions for specific phase and epreuve
            if ($epreuve === 'THI') {
                // THI is only for category 1
                $stmt = $pdo->prepare("SELECT * FROM qcm_questions WHERE phase = ? AND epreuve = ? AND category = '1' ORDER BY RAND() LIMIT 20");
                $stmt->execute([$phase, $epreuve]);
            } else {
                // THB and other tests - get questions for all categories or specific category
                $stmt = $pdo->prepare("SELECT * FROM qcm_questions WHERE phase = ? AND epreuve = ? ORDER BY RAND() LIMIT 20");
                $stmt->execute([$phase, $epreuve]);
            }
            
            $questions_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Transform questions to expected format
            $questions = [];
            foreach ($questions_raw as $q) {
                // Get choices for this question
                $stmt_choices = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
                $stmt_choices->execute([$q['id']]);
                $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($choices) >= 2) {
                    $question = [
                        'id' => $q['id'],
                        'question_text' => $q['question_text'],
                        'question_type' => $q['question_type'] ?? 'single',
                        'category' => '1', // Default category
                        'option_a' => $choices[0]['choice_text'] ?? '',
                        'option_b' => $choices[1]['choice_text'] ?? '',
                        'option_c' => $choices[2]['choice_text'] ?? '',
                        'option_d' => $choices[3]['choice_text'] ?? '',
                        'correct_answer' => 'a' // Will be determined from is_correct
                    ];
                    
                    // Find correct answer
                    // Trier par ID pour avoir l'ordre correct
                    $sorted_choices = $choices;
                    usort($sorted_choices, function($a, $b) { return $a['id'] - $b['id']; });
                    
                    foreach ($sorted_choices as $index => $choice) {
                        if ($choice['is_correct']) {
                            $letters = ['a', 'b', 'c', 'd'];
                            $question['correct_answer'] = $letters[$index] ?? 'a';
                            break;
                        }
                    }
                    
                    $questions[] = $question;
                }
            }
            
            if (count($questions) < 3) {
                throw new Exception('Pas assez de questions disponibles pour cette catégorie');
            }
            
            // Create new session with phase and epreuve
            $stmt = $pdo->prepare("INSERT INTO qcm_sessions (candidate_id, phase, epreuve, total_questions, time_limit_minutes) VALUES (?, ?, ?, ?, 60)");
            $stmt->execute([$candidate_id, $phase, $epreuve, count($questions)]);
            $session_id = $pdo->lastInsertId();
            
            // Get the created session
            $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id = ?");
            $stmt->execute([$session_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'session' => $session,
                'questions' => $questions
            ]);
            break;
            
        case 'resume_session':
            $session_id = $input['session_id'] ?? null;
            
            if (!$session_id) {
                throw new Exception('ID de session manquant');
            }
            
            // Get session
            $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id = ? AND status = 'in_progress'");
            $stmt->execute([$session_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                throw new Exception('Session non trouvée ou terminée');
            }
            
            // Calculate time remaining
            $started_time = strtotime($session['started_at']);
            $time_limit = $session['time_limit_minutes'] * 60;
            $elapsed = time() - $started_time;
            $time_remaining = max(0, $time_limit - $elapsed);
            
            if ($time_remaining <= 0) {
                // Time expired, mark as expired
                $stmt = $pdo->prepare("UPDATE qcm_sessions SET status = 'expired' WHERE id = ?");
                $stmt->execute([$session_id]);
                throw new Exception('Le temps est écoulé');
            }
            
            // Get questions for this session - adapt to existing structure
            $stmt = $pdo->prepare("SELECT * FROM qcm_questions ORDER BY RAND() LIMIT ?");
            $stmt->execute([$session['total_questions']]);
            $questions_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Transform questions to expected format
            $questions = [];
            foreach ($questions_raw as $q) {
                // Get choices for this question
                $stmt_choices = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
                $stmt_choices->execute([$q['id']]);
                $choices = $stmt_choices->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($choices) >= 2) {
                    $question = [
                        'id' => $q['id'],
                        'question_text' => $q['question_text'],
                        'question_type' => $q['question_type'] ?? 'single',
                        'category' => '1',
                        'option_a' => $choices[0]['choice_text'] ?? '',
                        'option_b' => $choices[1]['choice_text'] ?? '',
                        'option_c' => $choices[2]['choice_text'] ?? '',
                        'option_d' => $choices[3]['choice_text'] ?? '',
                        'correct_answer' => 'a'
                    ];
                    
                    // Find correct answer
                    // Trier par ID pour avoir l'ordre correct
                    $sorted_choices = $choices;
                    usort($sorted_choices, function($a, $b) { return $a['id'] - $b['id']; });
                    
                    foreach ($sorted_choices as $index => $choice) {
                        if ($choice['is_correct']) {
                            $letters = ['a', 'b', 'c', 'd'];
                            $question['correct_answer'] = $letters[$index] ?? 'a';
                            break;
                        }
                    }
                    
                    $questions[] = $question;
                }
            }
            
            // Get existing answers
            $stmt = $pdo->prepare("SELECT question_id, selected_answer FROM qcm_answers WHERE session_id = ?");
            $stmt->execute([$session_id]);
            $answer_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $answers = [];
            foreach ($answer_rows as $row) {
                $answers[$row['question_id']] = $row['selected_answer'];
            }
            
            echo json_encode([
                'success' => true,
                'session' => $session,
                'questions' => $questions,
                'answers' => $answers,
                'time_remaining' => $time_remaining
            ]);
            break;
            
        case 'save_answer':
            $session_id = $input['session_id'] ?? null;
            $question_id = $input['question_id'] ?? null;
            $answer = $input['answer'] ?? null;
            $question_type = $input['question_type'] ?? 'single';
            
            if (!$session_id || !$question_id || !$answer) {
                throw new Exception('Paramètres manquants');
            }
            
            // Check if session is still active
            $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id = ? AND status = 'in_progress'");
            $stmt->execute([$session_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                throw new Exception('Session non active');
            }
            
            // Get question info and choices to determine correct answer
            $stmt = $pdo->prepare("SELECT question_type FROM qcm_questions WHERE id = ?");
            $stmt->execute([$question_id]);
            $question_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($question_info) {
                $question_type = $question_info['question_type'] ?? 'single';
            }
            
            $stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id");
            $stmt->execute([$question_id]);
            $choices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $is_correct = false;
            
            if ($question_type === 'multiple') {
                // Pour les questions à choix multiples
                $correct_choices = [];
                // Trier les choix par ID pour avoir l'ordre correct
                $sorted_choices = $choices;
                usort($sorted_choices, function($a, $b) { return $a['id'] - $b['id']; });
                
                foreach ($sorted_choices as $index => $choice) {
                    if ($choice['is_correct'] == 1) {
                        $correct_choices[] = chr(97 + $index); // 'a', 'b', 'c', 'd'
                    }
                }
                
                // Nettoyer et valider la réponse utilisateur
                if (!is_array($answer)) {
                    $answer = [$answer];
                }
                
                // Nettoyer les réponses vides et les espaces
                $answer = array_filter(array_map('trim', $answer));
                
                // Trier pour comparaison
                sort($answer);
                sort($correct_choices);
                
                // Debug log pour tracer le problème
                error_log("Multiple choice validation - Question: $question_id, User: [" . implode(',', $answer) . "], Correct: [" . implode(',', $correct_choices) . "]");
                
                $is_correct = ($answer === $correct_choices);
                
                $answer_string = implode(',', $answer);
            } else {
                // Pour les questions à choix unique
                $sorted_choices = $choices;
                usort($sorted_choices, function($a, $b) { return $a['id'] - $b['id']; });
                
                $correct_answer = null;
                foreach ($sorted_choices as $index => $choice) {
                    if ($choice['is_correct'] == 1) {
                        $correct_answer = chr(97 + $index); // 'a', 'b', 'c', 'd'
                        break;
                    }
                }
                
                $is_correct = ($answer === $correct_answer);
                $answer_string = $answer;
            }
            
            // Save or update answer
            $stmt = $pdo->prepare("INSERT INTO qcm_answers (session_id, question_id, selected_answer, is_correct) 
                                  VALUES (?, ?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE selected_answer = ?, is_correct = ?");
            $stmt->execute([$session_id, $question_id, $answer_string, $is_correct, $answer_string, $is_correct]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'submit_test':
            $session_id = $input['session_id'] ?? null;
            
            if (!$session_id) {
                throw new Exception('ID de session manquant');
            }
            
            // Get session
            $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id = ? AND status = 'in_progress'");
            $stmt->execute([$session_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                throw new Exception('Session non trouvée');
            }
            
            // Calculate score
            $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(is_correct) as correct FROM qcm_answers WHERE session_id = ?");
            $stmt->execute([$session_id]);
            $results = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $total_questions = $session['total_questions'];
            $correct_count = $results['correct'] ?? 0;
            $score = ($correct_count / $total_questions) * 100;
            
            $stmt = $pdo->prepare("UPDATE qcm_sessions SET completed_at = NOW(), score = ?, correct_answers = ?, status = 'completed' WHERE id = ?");
            $stmt->execute([$score, $correct_count, $session_id]);
            
            // Get session info for phase/epreuve
            $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE id = ?");
            $stmt->execute([$session_id]);
            $session_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update candidate phase results - handle session_id column existence
            $result_status = $score >= 80 ? 'passed' : 'failed';
            
            // Log for debugging
            error_log("QCM API: Saving result for candidate {$session_info['candidate_id']}, phase {$session_info['phase']}, epreuve {$session_info['epreuve']}, score $score, status $result_status");
            
            try {
                $stmt = $pdo->prepare("INSERT INTO candidate_phase_results (candidate_id, phase, epreuve, score, status, session_id, completed_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, NOW()) 
                                      ON DUPLICATE KEY UPDATE score = ?, status = ?, session_id = ?, completed_at = NOW()");
                $result = $stmt->execute([
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
                
                if ($result) {
                    error_log("QCM API: Successfully saved result with session_id");
                } else {
                    error_log("QCM API: Failed to save result with session_id");
                }
                
            } catch (PDOException $e) {
                error_log("QCM API: Error with session_id: " . $e->getMessage());
                
                // If session_id column doesn't exist, try without it
                if (strpos($e->getMessage(), 'session_id') !== false) {
                    $stmt = $pdo->prepare("INSERT INTO candidate_phase_results (candidate_id, phase, epreuve, score, status, completed_at) 
                                          VALUES (?, ?, ?, ?, ?, NOW()) 
                                          ON DUPLICATE KEY UPDATE score = ?, status = ?, completed_at = NOW()");
                    $result = $stmt->execute([
                        $session_info['candidate_id'], 
                        $session_info['phase'], 
                        $session_info['epreuve'], 
                        $score, 
                        $result_status,
                        $score, 
                        $result_status
                    ]);
                    
                    if ($result) {
                        error_log("QCM API: Successfully saved result without session_id");
                    } else {
                        error_log("QCM API: Failed to save result without session_id");
                    }
                } else {
                    error_log("QCM API: Different error, rethrowing: " . $e->getMessage());
                    throw $e;
                }
            }
            
            // Récupérer les infos du candidat pour l'email
            $stmt = $pdo->prepare("
                SELECT c.id, c.prenom, c.nom, c.email, c.matricule 
                FROM candidates c 
                JOIN qcm_sessions qs ON c.id = qs.candidate_id 
                WHERE qs.id = ?
            ");
            $stmt->execute([$session_id]);
            $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if candidate has completed all Phase 1 tests and passed them
            $phase1_passed = false;
            if ($session_info['phase'] === 'phase1' && $score >= 80) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as total_passed 
                    FROM candidate_phase_results 
                    WHERE candidate_id = ? AND phase = 'phase1' AND status = 'passed'
                ");
                $stmt->execute([$session_info['candidate_id']]);
                $phase1_results = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if all 4 Phase 1 tests are passed
                if ($phase1_results['total_passed'] >= 4) {
                    $phase1_passed = true;
                }
            }
            
            // Envoyer email selon le résultat (optionnel, ne pas bloquer si erreur)
            if ($candidate && $candidate['email']) {
                try {
                    require_once 'send_email.php';
                    
                    if ($score >= 80) {
                        if ($phase1_passed) {
                            sendPhase2AdmissionEmail($candidate, $session_info['epreuve'], round($score, 2));
                        } else {
                            sendEpreuveSuccessEmail($candidate, $session_info['epreuve'], round($score, 2));
                        }
                    } else {
                        sendEpreuveFailureEmail($candidate, $session_info['epreuve'], round($score, 2));
                    }
                } catch (Exception $email_error) {
                    // Log error but don't fail the submission
                    error_log("Email error: " . $email_error->getMessage());
                }
            }
            
            echo json_encode([
                'success' => true,
                'score' => round($score, 2),
                'correct_answers' => $correct_count,
                'total_questions' => $total_questions,
                'passed' => $score >= 80,
                'redirect_url' => 'candidate_qcm_results.php?phase=' . urlencode($session_info['phase']) . '&epreuve=' . urlencode($session_info['epreuve'])
            ]);
            break;
            
        default:
            throw new Exception('Action non reconnue');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
}
?>
