<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_question') {
            $image_path = '';
            $correct_answer_type = $_POST['correct_answer_type'];
            $object_category = $_POST['object_category'] ?? null;
            $correct_x = $_POST['correct_x'] ?? null;
            $correct_y = $_POST['correct_y'] ?? null;
            $tolerance_radius = $_POST['tolerance_radius'] ?? 50;
            $admin_id = $_SESSION['admin_id'] ?? 1;
            
            // Handle file upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/imagerie/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $filename = 'img_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $image_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                        // Insert question into database
                        try {
                            $stmt = $pdo->prepare("INSERT INTO imagerie_questions_interactive 
                                (image_path, correct_answer_type, object_category, correct_x_position, correct_y_position, tolerance_radius, created_by) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$image_path, $correct_answer_type, $object_category, $correct_x, $correct_y, $tolerance_radius, $admin_id]);
                            
                            $_SESSION['success'] = "Question d'imagerie ajoutée avec succès.";
                        } catch (Exception $e) {
                            $_SESSION['error'] = "Erreur lors de l'ajout : " . $e->getMessage();
                        }
                    } else {
                        $_SESSION['error'] = "Erreur lors du téléchargement de l'image.";
                    }
                } else {
                    $_SESSION['error'] = "Format d'image non autorisé. Utilisez JPG, JPEG, PNG ou GIF.";
                }
            } else {
                $_SESSION['error'] = "Veuillez sélectionner une image.";
            }
        }
        
        if ($_POST['action'] === 'delete_question') {
            $question_id = $_POST['question_id'];
            
            try {
                // Get image path to delete file
                $stmt = $pdo->prepare("SELECT image_path FROM imagerie_questions_interactive WHERE id = ?");
                $stmt->execute([$question_id]);
                $question = $stmt->fetch();
                
                if ($question && file_exists($question['image_path'])) {
                    unlink($question['image_path']);
                }
                
                // Delete question
                $stmt = $pdo->prepare("DELETE FROM imagerie_questions_interactive WHERE id = ?");
                $stmt->execute([$question_id]);
                
                $_SESSION['success'] = "Question supprimée avec succès.";
            } catch (Exception $e) {
                $_SESSION['error'] = "Erreur lors de la suppression : " . $e->getMessage();
            }
        }
        
        if ($_POST['action'] === 'toggle_status') {
            $question_id = $_POST['question_id'];
            $new_status = $_POST['new_status'];
            
            try {
                $stmt = $pdo->prepare("UPDATE imagerie_questions_interactive SET is_active = ? WHERE id = ?");
                $stmt->execute([$new_status, $question_id]);
                
                $_SESSION['success'] = "Statut de la question mis à jour.";
            } catch (Exception $e) {
                $_SESSION['error'] = "Erreur lors de la mise à jour : " . $e->getMessage();
            }
        }
    }
    
    header('Location: admin_imagerie_interactive.php');
    exit();
}

// Get all questions
$stmt = $pdo->prepare("SELECT * FROM imagerie_questions_interactive ORDER BY created_at DESC");
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header card-header-anacim">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-camera me-2"></i>Gestion Questions Imagerie Interactive</h4>
                        <div>
                            <button class="btn btn-light me-2" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                                <i class="fas fa-plus me-2"></i>Ajouter une question
                            </button>
                            <a href="admin_dashboard.php" class="btn btn-outline-light">
                                <i class="fas fa-arrow-left me-2"></i>Retour
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Nouveau système d'examen d'imagerie :</strong>
                        <ul class="mb-0 mt-2">
                            <li>Les candidats ont 30 secondes maximum par image</li>
                            <li>Ils peuvent zoomer, orienter et cliquer sur l'image</li>
                            <li>Deux types de réponses : "Passer" (image correcte) ou "Objet trouvé" (sélectionner catégorie + position)</li>
                            <li>Catégories : Substances explosives, Objets tranchants, Outils de travail, Équipements à projectiles, Appareils paralysants, Instruments contondants, LAGs</li>
                        </ul>
                    </div>
                    
                    <?php if (empty($questions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-camera fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucune question créée</h5>
                            <p class="text-muted">Commencez par ajouter des questions d'imagerie interactive.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                                <i class="fas fa-plus me-2"></i>Ajouter la première question
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($questions as $question): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Question #<?php echo $question['id']; ?></small>
                                            <div>
                                                <?php if ($question['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="card-body p-2">
                                            <div class="text-center mb-3">
                                                <img src="<?php echo htmlspecialchars($question['image_path']); ?>" 
                                                     class="img-fluid rounded" 
                                                     style="max-height: 200px; width: auto; cursor: pointer;" 
                                                     onclick="viewQuestionImage('<?php echo htmlspecialchars($question['image_path']); ?>', <?php echo $question['id']; ?>)">
                                            </div>
                                            
                                            <div class="mb-2">
                                                <strong>Type de réponse :</strong>
                                                <?php if ($question['correct_answer_type'] === 'pass'): ?>
                                                    <span class="badge bg-success">Passer (Image correcte)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Objet trouvé</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if ($question['correct_answer_type'] === 'object_found'): ?>
                                                <div class="mb-2">
                                                    <strong>Catégorie :</strong>
                                                    <span class="badge bg-info">
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
                                                        echo $categories[$question['object_category']] ?? $question['object_category'];
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Position :</strong> X: <?php echo $question['correct_x_position']; ?>, Y: <?php echo $question['correct_y_position']; ?>
                                                    <br><small class="text-muted">Tolérance: <?php echo $question['tolerance_radius']; ?>px</small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <small class="text-muted">
                                                Créée le <?php echo date('d/m/Y H:i', strtotime($question['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="card-footer">
                                            <div class="btn-group w-100">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="toggleQuestionStatus(<?php echo $question['id']; ?>, <?php echo $question['is_active'] ? '0' : '1'; ?>)">
                                                    <i class="fas fa-<?php echo $question['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                    <?php echo $question['is_active'] ? 'Désactiver' : 'Activer'; ?>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteQuestion(<?php echo $question['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter une question -->
<div class="modal fade" id="addQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une question d'imagerie</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="addQuestionForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_question">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Image *</label>
                                <input type="file" name="image" class="form-control" accept="image/*" required id="imageUpload" onchange="previewImage(event)">
                                <div class="form-text">Formats acceptés : JPG, JPEG, PNG, GIF</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Type de réponse correcte *</label>
                                <select name="correct_answer_type" class="form-select" id="answerType" onchange="toggleObjectFields()" required>
                                    <option value="">Sélectionnez...</option>
                                    <option value="pass">Passer (Image correcte, aucun objet suspect)</option>
                                    <option value="object_found">Objet trouvé (Image contient un objet suspect)</option>
                                </select>
                            </div>
                            
                            <div id="objectFields" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Catégorie d'objet *</label>
                                    <select name="object_category" class="form-select">
                                        <option value="">Sélectionnez...</option>
                                        <option value="substances_explosives">Substances ou engins explosifs ou incendiaires</option>
                                        <option value="objets_tranchants">Objets tranchants</option>
                                        <option value="outils_travail">Outils de travail</option>
                                        <option value="equipements_projectiles">Équipements émettant des projectiles</option>
                                        <option value="appareils_paralysants">Appareils à effets paralysants</option>
                                        <option value="instruments_contondants">Instruments contondants</option>
                                        <option value="lags">LAGs</option>
                                    </select>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label">Position X *</label>
                                        <input type="number" name="correct_x" class="form-control" min="0" id="positionX" readonly>
                                        <div class="form-text">Position horizontale</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Position Y *</label>
                                        <input type="number" name="correct_y" class="form-control" min="0" id="positionY" readonly>
                                        <div class="form-text">Position verticale</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Tolérance (px)</label>
                                        <input type="number" name="tolerance_radius" class="form-control" value="50" min="10" max="200">
                                        <div class="form-text">Rayon de tolérance</div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-mouse-pointer me-2"></i>
                                    <strong>Instructions :</strong> Cliquez directement sur l'objet suspect dans l'image pour définir sa position exacte.
                                    <div id="positionStatus" class="mt-2" style="display: none;">
                                        <span class="badge bg-success">Position définie : X=<span id="statusX">0</span>, Y=<span id="statusY">0</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Aperçu de l'image</label>
                                <div id="imagePreviewContainer" class="border rounded p-3 text-center" style="min-height: 400px; background: #f8f9fa;">
                                    <div id="noImageMessage" class="text-muted">
                                        <i class="fas fa-image fa-3x mb-3"></i>
                                        <p>Sélectionnez une image pour voir l'aperçu</p>
                                    </div>
                                    <div id="imagePreview" style="display: none; position: relative;">
                                        <img id="previewImg" src="" class="img-fluid" style="max-height: 400px; cursor: crosshair;" onclick="setObjectPosition(event)">
                                        <div id="objectMarker" class="position-absolute" style="display: none; width: 20px; height: 20px; background: red; border: 3px solid white; border-radius: 50%; transform: translate(-50%, -50%); z-index: 10; box-shadow: 0 0 10px rgba(255,0,0,0.5);">
                                            <div class="position-absolute top-50 start-50 translate-middle" style="width: 6px; height: 6px; background: white; border-radius: 50%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Ajouter la question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour voir l'image -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Image d'analyse - Question #<span id="questionNumber"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div class="position-relative d-inline-block">
                    <img id="modalImage" src="" class="img-fluid" style="max-width: 100%; height: auto; cursor: crosshair;" onclick="getImageCoordinates(event)">
                    <div id="coordinatesDisplay" class="position-absolute top-0 end-0 bg-dark text-white p-2 rounded" style="display: none;">
                        <small>X: <span id="coordX">0</span>, Y: <span id="coordY">0</span></small>
                    </div>
                </div>
                <div class="mt-3">
                    <small class="text-muted">Cliquez sur l'image pour obtenir les coordonnées</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('noImageMessage').style.display = 'none';
            document.getElementById('imagePreview').style.display = 'block';
            
            // Reset position marker
            document.getElementById('objectMarker').style.display = 'none';
            document.getElementById('positionX').value = '';
            document.getElementById('positionY').value = '';
            document.getElementById('positionStatus').style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
}

function setObjectPosition(event) {
    const answerType = document.getElementById('answerType').value;
    if (answerType !== 'object_found') {
        alert('Veuillez d\'abord sélectionner "Objet trouvé" comme type de réponse.');
        return;
    }
    
    const img = event.target;
    const rect = img.getBoundingClientRect();
    const container = document.getElementById('imagePreview');
    const containerRect = container.getBoundingClientRect();
    
    // Calculate position relative to original image size
    const scaleX = img.naturalWidth / img.offsetWidth;
    const scaleY = img.naturalHeight / img.offsetHeight;
    
    const x = Math.round((event.clientX - rect.left) * scaleX);
    const y = Math.round((event.clientY - rect.top) * scaleY);
    
    // Update form fields
    document.getElementById('positionX').value = x;
    document.getElementById('positionY').value = y;
    document.getElementById('statusX').textContent = x;
    document.getElementById('statusY').textContent = y;
    document.getElementById('positionStatus').style.display = 'block';
    
    // Show marker on image
    const marker = document.getElementById('objectMarker');
    marker.style.left = (event.clientX - containerRect.left) + 'px';
    marker.style.top = (event.clientY - containerRect.top) + 'px';
    marker.style.display = 'block';
    
    // Show success message
    const notification = document.createElement('div');
    notification.className = 'alert alert-success position-fixed';
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; padding: 10px 15px;';
    notification.innerHTML = '<i class="fas fa-check me-2"></i>Position définie : X=' + x + ', Y=' + y;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (document.body.contains(notification)) {
            document.body.removeChild(notification);
        }
    }, 3000);
}

function toggleObjectFields() {
    const answerType = document.getElementById('answerType').value;
    const objectFields = document.getElementById('objectFields');
    
    if (answerType === 'object_found') {
        objectFields.style.display = 'block';
        // Make fields required
        document.querySelector('select[name="object_category"]').required = true;
        document.querySelector('input[name="correct_x"]').required = true;
        document.querySelector('input[name="correct_y"]').required = true;
    } else {
        objectFields.style.display = 'none';
        // Remove required
        document.querySelector('select[name="object_category"]').required = false;
        document.querySelector('input[name="correct_x"]').required = false;
        document.querySelector('input[name="correct_y"]').required = false;
        
        // Reset position data
        document.getElementById('positionX').value = '';
        document.getElementById('positionY').value = '';
        document.getElementById('objectMarker').style.display = 'none';
        document.getElementById('positionStatus').style.display = 'none';
    }
}

function viewQuestionImage(imageSrc, questionId) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('questionNumber').textContent = questionId;
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    modal.show();
}

function getImageCoordinates(event) {
    const img = event.target;
    const rect = img.getBoundingClientRect();
    const x = Math.round(event.clientX - rect.left);
    const y = Math.round(event.clientY - rect.top);
    
    document.getElementById('coordX').textContent = x;
    document.getElementById('coordY').textContent = y;
    document.getElementById('coordinatesDisplay').style.display = 'block';
    
    // Copy to clipboard
    navigator.clipboard.writeText(`X: ${x}, Y: ${y}`).then(() => {
        // Show temporary notification
        const notification = document.createElement('div');
        notification.className = 'alert alert-success position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; padding: 10px 15px;';
        notification.innerHTML = '<i class="fas fa-copy me-2"></i>Coordonnées copiées !';
        document.body.appendChild(notification);
        
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 2000);
    });
}

function toggleQuestionStatus(questionId, newStatus) {
    if (confirm('Êtes-vous sûr de vouloir modifier le statut de cette question ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="question_id" value="${questionId}">
            <input type="hidden" name="new_status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteQuestion(questionId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette question ? Cette action est irréversible.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_question">
            <input type="hidden" name="question_id" value="${questionId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
