<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo '<div class="alert alert-danger">Accès non autorisé</div>';
    exit();
}

$session_id = $_GET['session_id'] ?? 0;

if (!$session_id) {
    echo '<div class="alert alert-danger">ID de session manquant</div>';
    exit();
}

// Get session info
$stmt = $pdo->prepare("SELECT sis.*, c.nom, c.prenom, c.email, c.categorie
                       FROM imagerie_sessions_interactive sis
                       JOIN candidates c ON sis.candidate_id = c.id
                       WHERE sis.id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    echo '<div class="alert alert-danger">Session introuvable</div>';
    exit();
}

// Get detailed responses
$stmt = $pdo->prepare("SELECT ir.*, iq.image_path, iq.correct_answer_type, iq.object_category as correct_category, 
                              iq.correct_x_position, iq.correct_y_position, iq.tolerance_radius
                       FROM imagerie_responses_interactive ir
                       JOIN imagerie_questions_interactive iq ON ir.question_id = iq.id
                       WHERE ir.session_id = ? 
                       ORDER BY ir.question_order");
$stmt->execute([$session_id]);
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = [
    'substances_explosives' => 'Substances explosives',
    'objets_tranchants' => 'Objets tranchants',
    'outils_travail' => 'Outils de travail',
    'equipements_projectiles' => 'Équipements à projectiles',
    'appareils_paralysants' => 'Appareils paralysants',
    'instruments_contondants' => 'Instruments contondants',
    'lags' => 'LAGs'
];
?>

<div class="container-fluid">
    <!-- Session Summary -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Résumé de la session</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Candidat:</strong> <?php echo htmlspecialchars($session['nom'] . ' ' . $session['prenom']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($session['email']); ?></p>
                            <p><strong>Catégorie:</strong> <?php echo htmlspecialchars($session['categorie']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Score final:</strong> 
                                <span class="<?php echo $session['score'] >= 70 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo number_format($session['score'], 1); ?>%
                                </span>
                            </p>
                            <p><strong>Réponses correctes:</strong> <?php echo $session['correct_answers']; ?> / <?php echo $session['total_questions']; ?></p>
                            <p><strong>Temps total:</strong> <?php echo gmdate("i:s", $session['time_spent']); ?></p>
                            <p><strong>Complété le:</strong> <?php echo date('d/m/Y H:i', strtotime($session['completed_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detailed Responses -->
    <div class="row">
        <div class="col-12">
            <h6 class="mb-3">Détail des réponses par question</h6>
            
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
                            <div class="col-md-4">
                                <div class="text-center">
                                    <img src="<?php echo htmlspecialchars($response['image_path']); ?>" 
                                         class="img-fluid rounded border" 
                                         style="max-height: 200px; cursor: pointer;"
                                         onclick="viewAdminImage('<?php echo htmlspecialchars($response['image_path']); ?>', <?php echo $response['question_order']; ?>)">
                                    <p class="small text-muted mt-2">Cliquez pour agrandir</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h6>Réponse du candidat:</h6>
                                <div class="mb-3">
                                    <?php if ($response['response_type'] === 'pass'): ?>
                                        <span class="badge bg-secondary">Passer - Image correcte</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Objet trouvé</span>
                                        <?php if ($response['selected_category']): ?>
                                            <br><small class="text-muted">Catégorie: <?php echo $categories[$response['selected_category']] ?? $response['selected_category']; ?></small>
                                        <?php endif; ?>
                                        <?php if ($response['clicked_x_position'] && $response['clicked_y_position']): ?>
                                            <br><small class="text-muted">Position cliquée: X: <?php echo $response['clicked_x_position']; ?>, Y: <?php echo $response['clicked_y_position']; ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <h6>Réponse correcte:</h6>
                                <div class="mb-3">
                                    <?php if ($response['correct_answer_type'] === 'pass'): ?>
                                        <span class="badge bg-success">Passer - Image correcte</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Objet présent</span>
                                        <br><small class="text-muted">Catégorie: <?php echo $categories[$response['correct_category']] ?? $response['correct_category']; ?></small>
                                        <br><small class="text-muted">Position correcte: X: <?php echo $response['correct_x_position']; ?>, Y: <?php echo $response['correct_y_position']; ?></small>
                                        <br><small class="text-muted">Tolérance: <?php echo $response['tolerance_radius']; ?>px</small>
                                        
                                        <?php if ($response['response_type'] === 'object_found' && $response['clicked_x_position'] && $response['clicked_y_position']): ?>
                                            <?php
                                            $distance = sqrt(
                                                pow($response['clicked_x_position'] - $response['correct_x_position'], 2) + 
                                                pow($response['clicked_y_position'] - $response['correct_y_position'], 2)
                                            );
                                            ?>
                                            <br><small class="text-muted">Distance du clic: <?php echo round($distance); ?>px</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!$response['is_correct']): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Analyse de l'erreur:</strong>
                                <?php if ($response['response_type'] !== $response['correct_answer_type']): ?>
                                    Le candidat a choisi le mauvais type de réponse.
                                <?php elseif ($response['response_type'] === 'object_found'): ?>
                                    <?php if ($response['selected_category'] !== $response['correct_category']): ?>
                                        Le candidat a identifié un objet mais s'est trompé de catégorie.
                                    <?php else: ?>
                                        Le candidat a identifié la bonne catégorie mais a cliqué trop loin de l'objet suspect.
                                        <?php if (isset($distance)): ?>
                                            (Distance: <?php echo round($distance); ?>px, Tolérance: <?php echo $response['tolerance_radius']; ?>px)
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal pour voir l'image en grand -->
<div class="modal fade" id="adminImageModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Image - Question #<span id="adminQuestionNumber"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="adminModalImage" src="" class="img-fluid" style="max-width: 100%; height: auto;">
            </div>
        </div>
    </div>
</div>

<script>
function viewAdminImage(imageSrc, questionNumber) {
    document.getElementById('adminModalImage').src = imageSrc;
    document.getElementById('adminQuestionNumber').textContent = questionNumber;
    const modal = new bootstrap.Modal(document.getElementById('adminImageModal'));
    modal.show();
}
</script>
