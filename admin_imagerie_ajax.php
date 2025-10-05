<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(403);
    exit('Accès non autorisé');
}

if ($_GET['action'] === 'get_responses' && isset($_GET['candidate_id'])) {
    $candidate_id = $_GET['candidate_id'];
    
    // Get candidate info
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
    $stmt->execute([$candidate_id]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get candidate result
    $stmt = $pdo->prepare("SELECT * FROM candidate_phase_results WHERE candidate_id = ? AND phase = 'phase2' AND epreuve = 'IMAGERIE'");
    $stmt->execute([$candidate_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get imagerie session
    $stmt = $pdo->prepare("SELECT * FROM imagerie_sessions WHERE candidate_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$candidate_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get responses
    $stmt = $pdo->prepare("SELECT * FROM imagerie_responses WHERE session_id = ? ORDER BY question_id");
    $stmt->execute([$session['id']]);
    $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get dynamic questions from database
    $stmt = $pdo->prepare("SELECT * FROM imagerie_questions ORDER BY id");
    $stmt->execute();
    $db_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build questions data array
    $questions_data = [];
    $default_max_score = 20; // Default score per question
    
    foreach ($db_questions as $q) {
        $questions_data[$q['id']] = [
            'question' => $q['question_text'],
            'description' => substr($q['question_text'], 0, 80) . '...',
            'max_score' => $default_max_score,
            'image_path' => $q['image_path']
        ];
    }
    
    $total_max_score = array_sum(array_column($questions_data, 'max_score'));
    $total_scored = array_sum(array_filter(array_column($responses, 'score')));
    $all_scored = count(array_filter($responses, fn($r) => $r['score'] !== null)) === count($responses);
    
    ?>
    <input type="hidden" id="candidate_id_hidden" value="<?php echo $candidate_id; ?>">
    
    <div class="row mb-4">
        <div class="col-md-6">
            <h6><i class="fas fa-user me-2"></i>Informations du candidat</h6>
            <p><strong>Nom:</strong> <?php echo htmlspecialchars($candidate['nom'] . ' ' . $candidate['prenom']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($candidate['email']); ?></p>
            <p><strong>Catégorie:</strong> <?php echo htmlspecialchars($candidate['categorie']); ?></p>
        </div>
        <div class="col-md-6">
            <h6><i class="fas fa-info-circle me-2"></i>Statut de l'évaluation</h6>
            <p><strong>Soumis le:</strong> <?php echo date('d/m/Y H:i', strtotime($session['completed_at'])); ?></p>
            <p><strong>Statut actuel:</strong> 
                <span class="badge bg-<?php echo $result['status'] === 'pending' ? 'warning' : ($result['status'] === 'passed' ? 'success' : 'danger'); ?>">
                    <?php echo $result['status'] === 'pending' ? 'En attente' : ($result['status'] === 'passed' ? 'Réussi' : 'Échoué'); ?>
                </span>
            </p>
            <?php if ($result['status'] !== 'pending'): ?>
                <p><strong>Score final:</strong> <?php echo number_format($result['score'], 1); ?>%</p>
            <?php endif; ?>
        </div>
    </div>
    
    <hr>
    
    <h6><i class="fas fa-clipboard-list me-2"></i>Réponses du candidat</h6>
    
    <?php foreach ($responses as $response): ?>
        <div class="card mb-3">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Question <?php echo $response['question_id']; ?> - <?php echo $questions_data[$response['question_id']]['description']; ?></h6>
                    <span class="badge bg-info">Max: <?php echo $questions_data[$response['question_id']]['max_score']; ?> pts</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <p class="fw-bold text-primary"><?php echo htmlspecialchars($questions_data[$response['question_id']]['question']); ?></p>
                        
                        <?php if (isset($questions_data[$response['question_id']]['image_path']) && $questions_data[$response['question_id']]['image_path'] && file_exists($questions_data[$response['question_id']]['image_path'])): ?>
                            <div class="mb-3">
                                <strong>Image de la question:</strong><br>
                                <img src="<?php echo htmlspecialchars($questions_data[$response['question_id']]['image_path']); ?>" 
                                     alt="Question Image" 
                                     class="img-thumbnail" 
                                     style="max-width: 500px; max-height: 400px; cursor: pointer;" 
                                     onclick="openAdminImageModal(this.src)">
                                <p class="small text-muted mt-1">
                                    <i class="fas fa-search-plus"></i> Cliquez pour agrandir l'image
                                </p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="bg-light p-3 rounded">
                            <strong>Réponse du candidat:</strong><br>
                            <?php echo nl2br(htmlspecialchars($response['response'])); ?>
                        </div>
                        
                        <?php if ($response['admin_feedback']): ?>
                            <div class="mt-3">
                                <strong>Commentaire admin:</strong><br>
                                <div class="bg-warning bg-opacity-10 p-2 rounded">
                                    <?php echo nl2br(htmlspecialchars($response['admin_feedback'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3">
                            <h6>Notation</h6>
                            
                            <?php if ($response['score'] !== null): ?>
                                <div class="alert alert-success">
                                    <strong>Note attribuée: <?php echo $response['score']; ?>/<?php echo $questions_data[$response['question_id']]['max_score']; ?></strong><br>
                                    <small>Par admin le <?php echo date('d/m/Y H:i', strtotime($response['scored_at'])); ?></small>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Note (sur <?php echo $questions_data[$response['question_id']]['max_score']; ?>)</label>
                                <input type="number" class="form-control" id="score_<?php echo $response['id']; ?>" 
                                       min="0" max="<?php echo $questions_data[$response['question_id']]['max_score']; ?>" 
                                       step="0.5" value="<?php echo $response['score']; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Commentaire</label>
                                <textarea class="form-control" id="feedback_<?php echo $response['id']; ?>" rows="3"><?php echo htmlspecialchars($response['admin_feedback']); ?></textarea>
                            </div>
                            
                            <button class="btn btn-primary btn-sm w-100" onclick="scoreResponse(<?php echo $response['id']; ?>)">
                                <i class="fas fa-save me-1"></i>Enregistrer la note
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <hr>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="fas fa-calculator me-2"></i>Évaluation finale</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <strong>Récapitulatif des notes:</strong><br>
                        Total des points: <?php echo $total_scored; ?>/<?php echo $total_max_score; ?><br>
                        Pourcentage: <?php echo $total_max_score > 0 ? number_format(($total_scored / $total_max_score) * 100, 1) : 0; ?>%<br>
                        <small class="text-muted">
                            <?php echo $all_scored ? '✅ Toutes les questions notées' : '⚠️ Notation incomplète'; ?>
                        </small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Score final (%)</label>
                        <input type="number" class="form-control" id="final_score" min="0" max="100" step="0.1" 
                               value="<?php echo $result['score']; ?>" <?php echo $all_scored ? '' : 'readonly'; ?>>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Statut final</label>
                        <select class="form-select" id="final_status">
                            <option value="passed" <?php echo $result['status'] === 'passed' ? 'selected' : ''; ?>>Réussi</option>
                            <option value="failed" <?php echo $result['status'] === 'failed' ? 'selected' : ''; ?>>Échoué</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes administratives</label>
                        <textarea class="form-control" id="admin_notes" rows="3"><?php echo htmlspecialchars($result['admin_notes']); ?></textarea>
                    </div>
                    
                    <button class="btn btn-success w-100" onclick="finalizeEvaluation(<?php echo $candidate_id; ?>)" 
                            <?php echo $all_scored ? '' : 'disabled'; ?>>
                        <i class="fas fa-check me-2"></i>Finaliser l'évaluation
                    </button>
                    
                    <?php if (!$all_scored): ?>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle me-1"></i>Veuillez noter toutes les questions avant de finaliser.
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Auto-calculate final score when individual scores change
    document.querySelectorAll('input[id^="score_"]').forEach(input => {
        input.addEventListener('input', function() {
            let totalScore = 0;
            let maxScore = <?php echo $total_max_score; ?>;
            
            document.querySelectorAll('input[id^="score_"]').forEach(scoreInput => {
                totalScore += parseFloat(scoreInput.value) || 0;
            });
            
            let percentage = maxScore > 0 ? (totalScore / maxScore) * 100 : 0;
            document.getElementById('final_score').value = percentage.toFixed(1);
            
            // Auto-set status based on score
            document.getElementById('final_status').value = percentage >= 60 ? 'passed' : 'failed';
        });
    });
    </script>
    
    <?php
}
?>
