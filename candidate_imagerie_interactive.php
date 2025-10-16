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

// Check if candidate can access Pratique Imagerie
$can_access_imagerie = false;
if ($candidate['categorie'] == '1') {
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed' AND score >= 80");
    $stmt->execute([$candidate_id]);
    $can_access_imagerie = $stmt->fetch() ? true : false;
} else {
    $stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THB' AND status = 'completed' AND score >= 80");
    $stmt->execute([$candidate_id]);
    $can_access_imagerie = $stmt->fetch() ? true : false;
}

if (!$can_access_imagerie) {
    $_SESSION['error'] = "Vous devez d'abord réussir " . ($candidate['categorie'] == '1' ? 'THI' : 'THB') . " avec un score minimum de 80% pour accéder à la Pratique Imagerie.";
    header('Location: candidate_dashboard.php');
    exit();
}

// Check if already completed
$stmt = $pdo->prepare("SELECT * FROM imagerie_sessions_interactive WHERE candidate_id = ? AND status IN ('completed', 'timeout')");
$stmt->execute([$candidate_id]);
$existing_session = $stmt->fetch();

if ($existing_session) {
    header('Location: candidate_imagerie_interactive_results.php');
    exit();
}

// Get active questions
$stmt = $pdo->prepare("SELECT * FROM imagerie_questions_interactive WHERE is_active = 1 ORDER BY RAND()");
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    $_SESSION['error'] = "Aucune question d'imagerie disponible pour le moment.";
    header('Location: candidate_dashboard.php');
    exit();
}

// Check if session is in progress
$stmt = $pdo->prepare("SELECT * FROM imagerie_sessions_interactive WHERE candidate_id = ? AND status = 'in_progress'");
$stmt->execute([$candidate_id]);
$current_session = $stmt->fetch();

if (!$current_session) {
    // Create new session
    $stmt = $pdo->prepare("INSERT INTO imagerie_sessions_interactive (candidate_id, total_questions) VALUES (?, ?)");
    $stmt->execute([$candidate_id, count($questions)]);
    $session_id = $pdo->lastInsertId();
} else {
    $session_id = $current_session['id'];
}

// Get current question index
$stmt = $pdo->prepare("SELECT COUNT(*) as answered FROM imagerie_responses_interactive WHERE session_id = ?");
$stmt->execute([$session_id]);
$answered_count = $stmt->fetch()['answered'];

if ($answered_count >= count($questions)) {
    // All questions answered, finalize session
    $stmt = $pdo->prepare("UPDATE imagerie_sessions_interactive SET status = 'completed', completed_at = NOW() WHERE id = ?");
    $stmt->execute([$session_id]);
    header('Location: candidate_imagerie_interactive_results.php');
    exit();
}

$current_question_index = $answered_count;
$current_question = $questions[$current_question_index];

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header card-header-anacim">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4><i class="fas fa-camera me-2"></i>Examen Pratique Imagerie Interactive</h4>
                            <p class="mb-0">Candidat: <strong><?php echo htmlspecialchars($candidate['nom'] . ' ' . $candidate['prenom']); ?></strong> | Catégorie: <strong><?php echo htmlspecialchars($candidate['categorie']); ?></strong></p>
                        </div>
                        <div class="text-end">
                            <div class="badge bg-info fs-6 mb-2">
                                Question <?php echo $current_question_index + 1; ?> / <?php echo count($questions); ?>
                            </div>
                            <div id="timer" class="badge bg-warning fs-5">
                                <i class="fas fa-clock me-1"></i><span id="timeLeft">30</span>s
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Instructions:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Vous avez <strong>30 secondes maximum</strong> pour analyser chaque image</li>
                            <li>Utilisez les outils de zoom et d'orientation pour examiner l'image</li>
                            <li>Si l'image est correcte (aucun objet suspect), cliquez sur <strong>"Passer"</strong></li>
                            <li>Si vous trouvez un objet suspect, sélectionnez sa catégorie et cliquez sur sa position exacte dans l'image</li>
                        </ul>
                    </div>
                    
                    <div class="row">
                        <!-- Image Section -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Image d'analyse</h6>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary" onclick="zoomOut()">
                                                <i class="fas fa-search-minus"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="resetZoom()">
                                                <i class="fas fa-expand-arrows-alt"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="zoomIn()">
                                                <i class="fas fa-search-plus"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="rotateImage()">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-2">
                                    <div id="imageContainer" class="position-relative overflow-auto border rounded" style="height: 500px; background: #f8f9fa;">
                                        <img id="analysisImage" 
                                             src="<?php echo htmlspecialchars($current_question['image_path']); ?>" 
                                             class="position-absolute" 
                                             style="cursor: crosshair; transform-origin: center; transition: transform 0.3s ease;"
                                             onclick="selectPosition(event)">
                                        <div id="clickMarker" class="position-absolute" style="display: none; width: 20px; height: 20px; background: red; border: 2px solid white; border-radius: 50%; transform: translate(-50%, -50%); z-index: 10;">
                                            <div class="position-absolute top-50 start-50 translate-middle" style="width: 4px; height: 4px; background: white; border-radius: 50%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Controls Section -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Votre réponse</h6>
                                </div>
                                <div class="card-body">
                                    <form id="responseForm">
                                        <input type="hidden" id="sessionId" value="<?php echo $session_id; ?>">
                                        <input type="hidden" id="questionId" value="<?php echo $current_question['id']; ?>">
                                        <input type="hidden" id="questionOrder" value="<?php echo $current_question_index + 1; ?>">
                                        <input type="hidden" id="clickedX" value="">
                                        <input type="hidden" id="clickedY" value="">
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Type de réponse:</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="responseType" id="responsePass" value="pass" onchange="toggleObjectSelection()">
                                                <label class="form-check-label" for="responsePass">
                                                    <i class="fas fa-check-circle text-success me-1"></i>
                                                    <strong>Passer</strong> - L'image est correcte
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="responseType" id="responseObject" value="object_found" onchange="toggleObjectSelection()">
                                                <label class="form-check-label" for="responseObject">
                                                    <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                                    <strong>Objet trouvé</strong> - J'ai identifié un objet suspect
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div id="objectSelection" style="display: none;">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Catégorie d'objet:</label>
                                                <select id="objectCategory" class="form-select">
                                                    <option value="">Sélectionnez une catégorie...</option>
                                                    <option value="substances_explosives">Substances ou engins explosifs ou incendiaires</option>
                                                    <option value="objets_tranchants">Objets tranchants</option>
                                                    <option value="outils_travail">Outils de travail</option>
                                                    <option value="equipements_projectiles">Équipements émettant des projectiles</option>
                                                    <option value="appareils_paralysants">Appareils à effets paralysants</option>
                                                    <option value="instruments_contondants">Instruments contondants</option>
                                                    <option value="lags">LAGs</option>
                                                </select>
                                            </div>
                                            
                                            <div class="alert alert-warning">
                                                <i class="fas fa-mouse-pointer me-2"></i>
                                                <strong>Cliquez sur l'image</strong> pour indiquer la position exacte de l'objet suspect.
                                                <div id="positionInfo" class="mt-2" style="display: none;">
                                                    <small>Position sélectionnée: X: <span id="selectedX">-</span>, Y: <span id="selectedY">-</span></small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="button" id="submitBtn" class="btn btn-primary btn-lg" onclick="submitResponse()" disabled>
                                                <i class="fas fa-paper-plane me-2"></i>Valider ma réponse
                                            </button>
                                            <small class="text-muted text-center">
                                                Temps restant: <span id="timeLeftText">30</span> secondes
                                            </small>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Progress -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h6>Progression</h6>
                                    <div class="progress mb-2">
                                        <div class="progress-bar" style="width: <?php echo (($current_question_index) / count($questions)) * 100; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo $current_question_index; ?> / <?php echo count($questions); ?> questions traitées</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let timeLeft = 30;
let timer;
let currentZoom = 1;
let currentRotation = 0;
let imageLoaded = false;

// Initialize timer
function startTimer() {
    timer = setInterval(() => {
        timeLeft--;
        document.getElementById('timeLeft').textContent = timeLeft;
        document.getElementById('timeLeftText').textContent = timeLeft;
        
        if (timeLeft <= 10) {
            document.getElementById('timer').className = 'badge bg-danger fs-5';
        }
        
        if (timeLeft <= 0) {
            clearInterval(timer);
            autoSubmit();
        }
    }, 1000);
}

// Auto-submit when time runs out
function autoSubmit() {
    // If no response selected, default to "pass"
    if (!document.querySelector('input[name="responseType"]:checked')) {
        document.getElementById('responsePass').checked = true;
    }
    submitResponse();
}

// Image manipulation functions
function zoomIn() {
    currentZoom = Math.min(currentZoom * 1.2, 3);
    updateImageTransform();
}

function zoomOut() {
    currentZoom = Math.max(currentZoom / 1.2, 0.5);
    updateImageTransform();
}

function resetZoom() {
    currentZoom = 1;
    currentRotation = 0;
    updateImageTransform();
}

function rotateImage() {
    currentRotation = (currentRotation + 90) % 360;
    updateImageTransform();
}

function updateImageTransform() {
    const img = document.getElementById('analysisImage');
    img.style.transform = `scale(${currentZoom}) rotate(${currentRotation}deg)`;
}

// Position selection
function selectPosition(event) {
    if (!document.getElementById('responseObject').checked) {
        return;
    }
    
    const img = event.target;
    const rect = img.getBoundingClientRect();
    const container = document.getElementById('imageContainer');
    const containerRect = container.getBoundingClientRect();
    
    // Calculate position relative to original image size
    const scaleX = img.naturalWidth / img.offsetWidth;
    const scaleY = img.naturalHeight / img.offsetHeight;
    
    const x = Math.round((event.clientX - rect.left) * scaleX / currentZoom);
    const y = Math.round((event.clientY - rect.top) * scaleY / currentZoom);
    
    // Store coordinates
    document.getElementById('clickedX').value = x;
    document.getElementById('clickedY').value = y;
    document.getElementById('selectedX').textContent = x;
    document.getElementById('selectedY').textContent = y;
    document.getElementById('positionInfo').style.display = 'block';
    
    // Show marker
    const marker = document.getElementById('clickMarker');
    marker.style.left = (event.clientX - containerRect.left) + 'px';
    marker.style.top = (event.clientY - containerRect.top) + 'px';
    marker.style.display = 'block';
    
    validateForm();
}

// Toggle object selection fields
function toggleObjectSelection() {
    const objectSelection = document.getElementById('objectSelection');
    const isObjectFound = document.getElementById('responseObject').checked;
    
    objectSelection.style.display = isObjectFound ? 'block' : 'none';
    
    if (!isObjectFound) {
        // Clear position data
        document.getElementById('clickedX').value = '';
        document.getElementById('clickedY').value = '';
        document.getElementById('clickMarker').style.display = 'none';
        document.getElementById('positionInfo').style.display = 'none';
        document.getElementById('objectCategory').value = '';
    }
    
    validateForm();
}

// Form validation
function validateForm() {
    const responseType = document.querySelector('input[name="responseType"]:checked');
    let isValid = false;
    
    if (responseType) {
        if (responseType.value === 'pass') {
            isValid = true;
        } else if (responseType.value === 'object_found') {
            const category = document.getElementById('objectCategory').value;
            const hasPosition = document.getElementById('clickedX').value && document.getElementById('clickedY').value;
            isValid = category && hasPosition;
        }
    }
    
    document.getElementById('submitBtn').disabled = !isValid;
}

// Submit response
function submitResponse() {
    clearInterval(timer);
    
    const responseType = document.querySelector('input[name="responseType"]:checked')?.value || 'pass';
    const category = document.getElementById('objectCategory').value;
    const x = document.getElementById('clickedX').value;
    const y = document.getElementById('clickedY').value;
    const timeSpent = 30 - timeLeft;
    
    const formData = new FormData();
    formData.append('session_id', document.getElementById('sessionId').value);
    formData.append('question_id', document.getElementById('questionId').value);
    formData.append('question_order', document.getElementById('questionOrder').value);
    formData.append('response_type', responseType);
    formData.append('selected_category', category);
    formData.append('clicked_x', x);
    formData.append('clicked_y', y);
    formData.append('time_spent', timeSpent);
    
    // Disable form
    document.getElementById('responseForm').style.pointerEvents = 'none';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Envoi en cours...';
    
    fetch('candidate_imagerie_interactive_submit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.next_question) {
                // Reload page for next question
                window.location.reload();
            } else {
                // Exam completed
                window.location.href = 'candidate_imagerie_interactive_results.php';
            }
        } else {
            alert('Erreur lors de l\'envoi: ' + data.message);
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur de connexion. Rechargement de la page...');
        window.location.reload();
    });
}

// Event listeners
document.getElementById('objectCategory').addEventListener('change', validateForm);
document.querySelectorAll('input[name="responseType"]').forEach(radio => {
    radio.addEventListener('change', validateForm);
});

// Initialize when image loads
document.getElementById('analysisImage').addEventListener('load', function() {
    imageLoaded = true;
    startTimer();
});

// Handle page visibility change (prevent cheating)
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // Page is hidden, pause timer
        clearInterval(timer);
    } else {
        // Page is visible again, resume timer if time left
        if (timeLeft > 0 && imageLoaded) {
            startTimer();
        }
    }
});

// Prevent right-click context menu on image
document.getElementById('analysisImage').addEventListener('contextmenu', function(e) {
    e.preventDefault();
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !document.getElementById('submitBtn').disabled) {
        submitResponse();
    }
    if (e.key === '1') {
        document.getElementById('responsePass').checked = true;
        toggleObjectSelection();
    }
    if (e.key === '2') {
        document.getElementById('responseObject').checked = true;
        toggleObjectSelection();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
