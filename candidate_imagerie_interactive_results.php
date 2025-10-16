<?php
session_start();
require_once 'config.php';

// Check if candidate is logged in
if (!isset($_SESSION['candidate_id'])) {
    header('Location: candidate_login.php');
    exit();
}

$candidate_id = $_SESSION['candidate_id'];

// Get candidate info
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidate) {
    header('Location: candidate_login.php');
    exit();
}

// Get session results
$stmt = $pdo->prepare("SELECT * FROM imagerie_sessions_interactive WHERE candidate_id = ? AND status IN ('completed', 'timeout') ORDER BY completed_at DESC LIMIT 1");
$stmt->execute([$candidate_id]);
$session = $stmt->fetch();

if (!$session) {
    $_SESSION['error'] = "Aucun résultat d'examen trouvé.";
    header('Location: candidate_dashboard.php');
    exit();
}

// Get detailed responses
$stmt = $pdo->prepare("SELECT ir.*, iq.image_path, iq.correct_answer_type, iq.object_category as correct_category, 
                              iq.correct_x_position, iq.correct_y_position, iq.tolerance_radius
                       FROM imagerie_responses_interactive ir
                       JOIN imagerie_questions_interactive iq ON ir.question_id = iq.id
                       WHERE ir.session_id = ? 
                       ORDER BY ir.question_order");
$stmt->execute([$session['id']]);
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header card-header-anacim">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4><i class="fas fa-chart-line me-2"></i>Résultats - Examen Pratique Imagerie</h4>
                            <p class="mb-0">Candidat: <strong><?php echo htmlspecialchars($candidate['nom'] . ' ' . $candidate['prenom']); ?></strong> | Catégorie: <strong><?php echo htmlspecialchars($candidate['categorie']); ?></strong></p>
                        </div>
                        <a href="candidate_dashboard.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Score Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-center <?php echo $session['score'] >= 70 ? 'border-success' : 'border-danger'; ?>">
                                <div class="card-body">
                                    <h2 class="<?php echo $session['score'] >= 70 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo number_format($session['score'], 1); ?>%
                                    </h2>
                                    <p class="mb-0">Score Final</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h2 class="text-primary"><?php echo $session['correct_answers']; ?>/<?php echo $session['total_questions']; ?></h2>
                                    <p class="mb-0">Réponses Correctes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h2 class="text-info"><?php echo gmdate("i:s", $session['time_spent']); ?></h2>
                                    <p class="mb-0">Temps Total</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <h2 class="<?php echo $session['score'] >= 70 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $session['score'] >= 70 ? 'RÉUSSI' : 'ÉCHOUÉ'; ?>
                                    </h2>
                                    <p class="mb-0">Résultat</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($session['score'] >= 70): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Félicitations !</strong> Vous avez réussi l'examen pratique d'imagerie avec un score de <?php echo number_format($session['score'], 1); ?>%.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle me-2"></i>
                            <strong>Examen non réussi.</strong> Vous avez obtenu <?php echo number_format($session['score'], 1); ?>%. Le score minimum requis est de 70%.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Detailed Results -->
                    <h5 class="mt-4 mb-3">Détail des réponses</h5>
                    
                    <?php foreach ($responses as $index => $response): ?>
                        <div class="card mb-3 <?php echo $response['is_correct'] ? 'border-success' : 'border-danger'; ?>">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Question <?php echo $response['question_order']; ?></h6>
                                    <div>
                                        <?php if ($response['is_correct']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Correct</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Incorrect</span>
                                        <?php endif; ?>
                                        <span class="badge bg-info ms-2"><?php echo $response['time_spent']; ?>s</span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="text-center">
                                            <img src="<?php echo htmlspecialchars($response['image_path']); ?>" 
                                                 class="img-fluid rounded border" 
                                                 style="max-height: 300px; cursor: pointer;"
                                                 onclick="viewResultImage('<?php echo htmlspecialchars($response['image_path']); ?>', <?php echo $response['question_order']; ?>)">
                                            <p class="small text-muted mt-2">Cliquez pour agrandir</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Votre réponse:</h6>
                                        <div class="mb-3">
                                            <?php if ($response['response_type'] === 'pass'): ?>
                                                <span class="badge bg-secondary">Passer - Image correcte</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Objet trouvé</span>
                                                <?php if ($response['selected_category']): ?>
                                                    <br><small class="text-muted">Catégorie: 
                                                        <?php
                                                        $categories = [
                                                            'substances_explosives' => 'Substances explosives',
                                                            'objets_tranchants' => 'Objets tranchants',
                                                            'outils_travail' => 'Outils de travail',
                                                            'equipements_projectiles' => 'Équipements à projectiles',
                                                            'appareils_paralysants' => 'Appareils paralysants',
                                                            'instruments_contondants' => 'Instruments contondants',
                                                            'lags' => 'LAGs'
                                                        ];
                                                        echo $categories[$response['selected_category']] ?? $response['selected_category'];
                                                        ?>
                                                    </small>
                                                <?php endif; ?>
                                                <?php if ($response['clicked_x_position'] && $response['clicked_y_position']): ?>
                                                    <br><small class="text-muted">Position: X: <?php echo $response['clicked_x_position']; ?>, Y: <?php echo $response['clicked_y_position']; ?></small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <h6>Réponse correcte:</h6>
                                        <div class="mb-3">
                                            <?php if ($response['correct_answer_type'] === 'pass'): ?>
                                                <span class="badge bg-success">Passer - Image correcte</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Objet présent</span>
                                                <br><small class="text-muted">Catégorie: 
                                                    <?php echo $categories[$response['correct_category']] ?? $response['correct_category']; ?>
                                                </small>
                                                <br><small class="text-muted">Position: X: <?php echo $response['correct_x_position']; ?>, Y: <?php echo $response['correct_y_position']; ?></small>
                                                <br><small class="text-muted">Tolérance: <?php echo $response['tolerance_radius']; ?>px</small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (!$response['is_correct']): ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Explication:</strong>
                                                <?php if ($response['response_type'] !== $response['correct_answer_type']): ?>
                                                    Vous avez choisi le mauvais type de réponse.
                                                <?php elseif ($response['response_type'] === 'object_found'): ?>
                                                    <?php if ($response['selected_category'] !== $response['correct_category']): ?>
                                                        Catégorie d'objet incorrecte.
                                                    <?php else: ?>
                                                        Position cliquée trop éloignée de l'objet suspect.
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-4">
                        <a href="candidate_dashboard.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-home me-2"></i>Retour au tableau de bord
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour voir l'image -->
<div class="modal fade" id="resultImageModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Image - Question #<span id="resultQuestionNumber"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="resultModalImage" src="" class="img-fluid" style="max-width: 100%; height: auto;">
            </div>
        </div>
    </div>
</div>

<script>
function viewResultImage(imageSrc, questionNumber) {
    document.getElementById('resultModalImage').src = imageSrc;
    document.getElementById('resultQuestionNumber').textContent = questionNumber;
    const modal = new bootstrap.Modal(document.getElementById('resultImageModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>
