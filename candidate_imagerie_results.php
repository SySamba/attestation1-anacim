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

// Get imagerie session
$stmt = $pdo->prepare("SELECT * FROM imagerie_sessions WHERE candidate_id = ? AND status = 'completed' ORDER BY id DESC LIMIT 1");
$stmt->execute([$candidate_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    $_SESSION['error'] = "Aucune session d'imagerie trouvée.";
    header('Location: candidate_dashboard.php');
    exit();
}

// Get responses
$stmt = $pdo->prepare("SELECT * FROM imagerie_responses WHERE session_id = ? ORDER BY question_id");
$stmt->execute([$session['id']]);
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get questions
$stmt = $pdo->prepare("SELECT * FROM imagerie_questions ORDER BY id");
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build questions array
$questions_data = [];
foreach ($questions as $q) {
    $questions_data[$q['id']] = $q;
}

// Get evaluation result
$stmt = $pdo->prepare("SELECT * FROM candidate_phase_results WHERE candidate_id = ? AND phase = 'phase2' AND epreuve = 'IMAGERIE'");
$stmt->execute([$candidate_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header card-header-anacim">
                    <h4><i class="fas fa-eye me-2"></i>Mes Réponses - Pratique Imagerie</h4>
                    <p class="mb-0">Candidat: <strong><?php echo htmlspecialchars($candidate['nom'] . ' ' . $candidate['prenom']); ?></strong> | Soumis le: <strong><?php echo date('d/m/Y à H:i', strtotime($session['completed_at'])); ?></strong></p>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Statut de votre évaluation:</strong>
                        <?php if ($result): ?>
                            <span class="badge bg-<?php echo $result['status'] === 'pending' ? 'warning' : ($result['status'] === 'passed' ? 'success' : 'danger'); ?> ms-2">
                                <?php echo $result['status'] === 'pending' ? 'En cours d\'évaluation' : ($result['status'] === 'passed' ? 'Réussi (' . number_format($result['score'], 1) . '%)' : 'Échoué (' . number_format($result['score'], 1) . '%)'); ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning ms-2">En attente d'évaluation</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php foreach ($responses as $index => $response): ?>
                        <?php $question = $questions_data[$response['question_id']] ?? null; ?>
                        <?php if ($question): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5>Question <?php echo $index + 1; ?> sur <?php echo count($responses); ?></h5>
                                    <small class="text-muted">Soumis le <?php echo date('d/m/Y à H:i', strtotime($response['submitted_at'])); ?></small>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="text-center mb-3">
                                                <?php if (!empty($question['image_path']) && file_exists($question['image_path'])): ?>
                                                    <div class="border rounded p-2" style="min-height: 500px;">
                                                        <img src="<?php echo htmlspecialchars($question['image_path']); ?>" 
                                                             class="img-fluid rounded" 
                                                             style="max-height: 480px; width: auto; cursor: pointer;" 
                                                             alt="Image radiographique"
                                                             onclick="openImageModal(this.src)">
                                                        <p class="small text-muted mt-2">
                                                            <i class="fas fa-camera"></i>
                                                            Image analysée - Cliquez pour agrandir
                                                        </p>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="border rounded p-3 bg-light" style="min-height: 500px; display: flex; align-items: center; justify-content: center;">
                                                        <div class="text-muted">
                                                            <i class="fas fa-image fa-4x mb-3"></i>
                                                            <p><strong>Image Radiographique</strong></p>
                                                            <p class="small">Image non disponible</p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold"><?php echo htmlspecialchars($question['question_text']); ?></label>
                                                <div class="bg-light p-3 rounded" style="min-height: 300px;">
                                                    <strong>Votre réponse:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($response['response'])); ?>
                                                </div>
                                            </div>
                                            
                                            <?php if ($response['score'] !== null): ?>
                                                <div class="alert alert-<?php echo $response['score'] >= 12 ? 'success' : 'warning'; ?>">
                                                    <strong>Note obtenue: <?php echo $response['score']; ?>/20</strong>
                                                    <?php if ($response['admin_feedback']): ?>
                                                        <hr>
                                                        <strong>Commentaire de l'évaluateur:</strong><br>
                                                        <?php echo nl2br(htmlspecialchars($response['admin_feedback'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-clock me-2"></i>
                                                    <strong>En cours d'évaluation</strong><br>
                                                    Votre réponse sera notée par un administrateur.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='candidate_dashboard.php'">
                            <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour agrandir l'image -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Image d'analyse - Vue agrandie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" class="img-fluid" style="max-width: 100%; height: auto;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
// Function to open image modal
function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    var imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
    imageModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>
