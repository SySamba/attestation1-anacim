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

// Check if candidate can access Pratique Imagerie using qcm_sessions table
$can_access_imagerie = false;
if ($candidate['categorie'] == '1') {
    // C1 needs THI with 80%+
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed' AND score >= 80");
    $stmt->execute([$candidate_id]);
    $can_access_imagerie = $stmt->fetch() ? true : false;
} else {
    // Categories 2,3,4,5 need THB with 80%+
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THB' AND status = 'completed' AND score >= 80");
    $stmt->execute([$candidate_id]);
    $can_access_imagerie = $stmt->fetch() ? true : false;
}

if (!$can_access_imagerie) {
    $_SESSION['error'] = "Vous devez d'abord réussir " . ($candidate['categorie'] == '1' ? 'THI' : 'THB') . " avec un score minimum de 80% pour accéder à la Pratique Imagerie.";
    header('Location: candidate_dashboard.php');
    exit();
}

// Check if already completed using imagerie_sessions table
$stmt = $pdo->prepare("SELECT * FROM imagerie_sessions WHERE candidate_id = ? AND status = 'completed'");
$stmt->execute([$candidate_id]);
$existing_session = $stmt->fetch();

if ($existing_session) {
    // Redirect to results page to show saved responses
    header('Location: candidate_imagerie_results.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $responses = $_POST['responses'] ?? [];
    
    if (empty($responses)) {
        $error = "Veuillez répondre à toutes les questions.";
    } else {
        // Save responses to database
        try {
            $pdo->beginTransaction();
            
            // Create imagerie session with completed_at timestamp
            $stmt = $pdo->prepare("INSERT INTO imagerie_sessions (candidate_id, started_at, completed_at, status) VALUES (?, NOW(), NOW(), 'completed')");
            $stmt->execute([$candidate_id]);
            $session_id = $pdo->lastInsertId();
            
            // Save responses
            foreach ($responses as $question_id => $response) {
                $stmt = $pdo->prepare("INSERT INTO imagerie_responses (session_id, question_id, response, submitted_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$session_id, $question_id, $response]);
            }
            
            // Create pending result (will be scored by admin)
            $stmt = $pdo->prepare("INSERT INTO candidate_phase_results (candidate_id, phase, epreuve, status, score, completed_at) VALUES (?, 'phase2', 'IMAGERIE', 'pending', 0, NOW())");
            $stmt->execute([$candidate_id]);
            
            $pdo->commit();
            
            $_SESSION['success'] = "Votre épreuve de Pratique Imagerie a été soumise avec succès. Vos réponses seront évaluées par un administrateur.";
            header('Location: candidate_dashboard.php');
            exit();
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = "Erreur lors de la soumission : " . $e->getMessage();
        }
    }
}

// Get imagerie questions from database
$stmt = $pdo->prepare("SELECT * FROM imagerie_questions WHERE epreuve = 'IMAGERIE' ORDER BY id ASC");
$stmt->execute();
$imagerie_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no questions in database, use fallback questions
if (empty($imagerie_questions)) {
    $imagerie_questions = [
        [
            'id' => 1,
            'question_text' => 'Analysez cette image radiographique et identifiez tous les objets suspects ou interdits visibles.',
            'image_path' => null,
            'description' => 'Image de bagages passés au scanner - Identifiez les éléments problématiques'
        ],
        [
            'id' => 2,
            'question_text' => 'Décrivez les mesures de sécurité à prendre suite à l\'analyse de cette image.',
            'image_path' => null,
            'description' => 'Bagage contenant des objets métalliques - Évaluez le niveau de risque'
        ],
        [
            'id' => 3,
            'question_text' => 'Cette image présente-t-elle un risque de sécurité ? Justifiez votre réponse.',
            'image_path' => null,
            'description' => 'Analyse d\'un sac à main - Déterminez la conformité'
        ]
    ];
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header card-header-anacim">
                    <h4><i class="fas fa-camera me-2"></i>Pratique Imagerie - Analyse de Radiographies</h4>
                    <p class="mb-0">Candidat: <strong><?php echo htmlspecialchars($candidate['nom'] . ' ' . $candidate['prenom']); ?></strong> | Catégorie: <strong><?php echo htmlspecialchars($candidate['categorie']); ?></strong></p>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Instructions:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Analysez attentivement chaque image radiographique</li>
                            <li>Répondez de manière détaillée à chaque question</li>
                            <li>Identifiez tous les objets suspects ou interdits</li>
                            <li>Proposez les mesures de sécurité appropriées</li>
                            <li>Vos réponses seront évaluées par un administrateur</li>
                        </ul>
                    </div>
                    
                    <form method="POST" id="imagerie-form">
                        <?php foreach ($imagerie_questions as $index => $question): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5>Question <?php echo $index + 1; ?> sur <?php echo count($imagerie_questions); ?></h5>
                                    <small class="text-muted"><?php echo isset($question['description']) ? htmlspecialchars($question['description']) : 'Question d\'analyse d\'image'; ?></small>
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
                                                            Image d'analyse - Cliquez pour agrandir
                                                        </p>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="border rounded p-3 bg-light" style="min-height: 500px; display: flex; align-items: center; justify-content: center;">
                                                        <div class="text-muted">
                                                            <i class="fas fa-image fa-4x mb-3"></i>
                                                            <p><strong>Image Radiographique</strong></p>
                                                            <p class="small">Image non disponible</p>
                                                            <p class="small text-info">
                                                                <i class="fas fa-info-circle"></i>
                                                                Répondez selon vos connaissances théoriques
                                                            </p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold"><?php echo htmlspecialchars($question['question_text']); ?></label>
                                                <textarea 
                                                    name="responses[<?php echo $question['id']; ?>]" 
                                                    class="form-control" 
                                                    rows="12" 
                                                    placeholder="Saisissez votre analyse détaillée ici..."
                                                    required
                                                ><?php echo isset($_POST['responses'][$question['id']]) ? htmlspecialchars($_POST['responses'][$question['id']]) : ''; ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-4">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Attention:</strong> Une fois soumises, vos réponses ne pourront plus être modifiées.
                            </div>
                            
                            <button type="button" class="btn btn-secondary me-3" onclick="window.location.href='candidate_dashboard.php'">
                                <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
                            </button>
                            
                            <button type="submit" class="btn btn-anacim btn-lg" onclick="return confirm('Êtes-vous sûr de vouloir soumettre vos réponses ? Cette action est irréversible.')">
                                <i class="fas fa-paper-plane me-2"></i>Soumettre mes réponses
                            </button>
                        </div>
                    </form>
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
// Auto-save functionality
let autoSaveTimer;
const autoSaveDelay = 30000; // 30 seconds

function autoSave() {
    const formData = new FormData(document.getElementById('imagerie-form'));
    const responses = {};
    
    for (let [key, value] of formData.entries()) {
        if (key.startsWith('responses[')) {
            responses[key] = value;
        }
    }
    
    localStorage.setItem('imagerie_responses_<?php echo $candidate_id; ?>', JSON.stringify(responses));
    
    // Show auto-save indicator
    const indicator = document.createElement('div');
    indicator.className = 'alert alert-success position-fixed';
    indicator.style.cssText = 'top: 20px; right: 20px; z-index: 9999; padding: 10px 15px;';
    indicator.innerHTML = '<i class="fas fa-save me-2"></i>Réponses sauvegardées automatiquement';
    document.body.appendChild(indicator);
    
    setTimeout(() => {
        document.body.removeChild(indicator);
    }, 2000);
}

// Load saved responses
document.addEventListener('DOMContentLoaded', function() {
    const saved = localStorage.getItem('imagerie_responses_<?php echo $candidate_id; ?>');
    if (saved) {
        const responses = JSON.parse(saved);
        for (let [key, value] of Object.entries(responses)) {
            const textarea = document.querySelector(`textarea[name="${key}"]`);
            if (textarea && !textarea.value) {
                textarea.value = value;
            }
        }
    }
    
    // Set up auto-save
    document.querySelectorAll('textarea').forEach(textarea => {
        textarea.addEventListener('input', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(autoSave, autoSaveDelay);
        });
    });
});

// Clear saved data on successful submission
document.getElementById('imagerie-form').addEventListener('submit', function() {
    localStorage.removeItem('imagerie_responses_<?php echo $candidate_id; ?>');
});

// Function to open image modal
function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    var imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
    imageModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>
