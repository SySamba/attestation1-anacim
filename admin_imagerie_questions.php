<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$page_title = "ANACIM - Questions Imagerie avec Images";

// Create imagerie_questions table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS imagerie_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    question_type ENUM('single','multiple') NOT NULL DEFAULT 'single',
    phase ENUM('phase1','phase2') NOT NULL DEFAULT 'phase2',
    epreuve ENUM('THB','THI','FBAG','PLP','FMAG','IMAGERIE') NOT NULL DEFAULT 'IMAGERIE',
    category INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS imagerie_choices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    choice_text TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES imagerie_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $question_id = (int)$_GET['id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get image path before deletion
        $stmt = $pdo->prepare("SELECT image_path FROM imagerie_questions WHERE id = ?");
        $stmt->execute([$question_id]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($question) {
            // Delete image file if exists
            if ($question['image_path'] && file_exists($question['image_path'])) {
                unlink($question['image_path']);
            }
            
            // Delete question (choices will be deleted automatically due to CASCADE)
            $stmt = $pdo->prepare("DELETE FROM imagerie_questions WHERE id = ?");
            $stmt->execute([$question_id]);
            
            $pdo->commit();
            $_SESSION['success_message'] = "Question supprimée avec succès.";
        } else {
            $_SESSION['error_message'] = "Question non trouvée.";
        }
    } catch (Exception $e) {
        $pdo->rollback();
        $_SESSION['error_message'] = "Erreur lors de la suppression : " . $e->getMessage();
    }
    
    header('Location: admin_imagerie_questions.php');
    exit;
}

// Handle form submission
if ($_POST) {
    $question_text = trim($_POST['question_text']);
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;
    
    $errors = [];
    
    // Validation
    if (empty($question_text)) {
        $errors[] = "Le texte de la question est requis.";
    }
    
    // Validation de l'image (obligatoire seulement pour nouvelle question)
    if (!$edit_id && (!isset($_FILES['question_image']) || $_FILES['question_image']['error'] !== UPLOAD_ERR_OK)) {
        $errors[] = "Une image est obligatoire pour les questions d'imagerie.";
    }
    
    // Handle image upload
    $image_path = null;
    if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/imagerie/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['question_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $filename = 'img_' . time() . '_' . uniqid() . '.' . $file_extension;
            $image_path = $upload_dir . $filename;
            
            if (!move_uploaded_file($_FILES['question_image']['tmp_name'], $image_path)) {
                $errors[] = "Erreur lors du téléchargement de l'image.";
                $image_path = null;
            }
        } else {
            $errors[] = "Format d'image non supporté. Utilisez JPG, PNG, GIF ou WebP.";
        }
    }
    
    if (empty($errors)) {
        try {
            if ($edit_id) {
                // Update existing question
                if ($image_path) {
                    // Get old image path to delete it
                    $stmt = $pdo->prepare("SELECT image_path FROM imagerie_questions WHERE id = ?");
                    $stmt->execute([$edit_id]);
                    $old_question = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($old_question && $old_question['image_path'] && file_exists($old_question['image_path'])) {
                        unlink($old_question['image_path']);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE imagerie_questions SET question_text = ?, image_path = ? WHERE id = ?");
                    $stmt->execute([$question_text, $image_path, $edit_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE imagerie_questions SET question_text = ? WHERE id = ?");
                    $stmt->execute([$question_text, $edit_id]);
                }
                
                $_SESSION['success_message'] = "Question modifiée avec succès.";
            } else {
                // Insert new question
                $stmt = $pdo->prepare("INSERT INTO imagerie_questions (question_text, image_path, question_type, phase, epreuve, category) VALUES (?, ?, 'descriptive', 'phase2', 'IMAGERIE', 1)");
                $stmt->execute([$question_text, $image_path]);
                
                $_SESSION['success_message'] = "Question d'imagerie ajoutée avec succès.";
            }
            
            header('Location: admin_imagerie_questions.php');
            exit;
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}

// Get question to edit if specified
$edit_question = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM imagerie_questions WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_question = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get existing questions
$questions_sql = "SELECT iq.*, 
                         (SELECT COUNT(*) FROM imagerie_choices ic WHERE ic.question_id = iq.id) as choice_count,
                         (SELECT COUNT(*) FROM imagerie_choices ic WHERE ic.question_id = iq.id AND ic.is_correct = 1) as correct_count
                  FROM imagerie_questions iq 
                  ORDER BY iq.created_at DESC";
$questions_stmt = $pdo->prepare($questions_sql);
$questions_stmt->execute();
$questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-anacim d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-camera"></i> Questions Imagerie avec Images</h5>
                <a href="admin_dashboard.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <h6 class="mb-3"><?php echo $edit_question ? 'Modifier la question d\'imagerie' : 'Ajouter une nouvelle question d\'imagerie'; ?></h6>
                <form method="post" enctype="multipart/form-data" id="imagerieForm">
                    <?php if ($edit_question): ?>
                        <input type="hidden" name="edit_id" value="<?php echo $edit_question['id']; ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Question *</label>
                                <textarea name="question_text" class="form-control" rows="3" placeholder="Saisissez la question d'imagerie..." required><?php echo htmlspecialchars($edit_question ? $edit_question['question_text'] : ($_POST['question_text'] ?? '')); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Image <?php echo $edit_question ? '(optionnelle pour modification)' : '(obligatoire) *'; ?></label>
                                <input type="file" name="question_image" class="form-control" accept="image/*" id="imageInput" <?php echo $edit_question ? '' : 'required'; ?>>
                                <?php if ($edit_question): ?>
                                    <div class="form-text text-info">⚠️ Laissez vide pour conserver l'image actuelle</div>
                                <?php else: ?>
                                    <div class="form-text text-danger">⚠️ Une image est obligatoire pour chaque question d'imagerie</div>
                                <?php endif; ?>
                                <div class="form-text">Formats supportés: JPG, PNG, GIF, WebP. Taille max: 5MB</div>
                                
                                <?php if ($edit_question && $edit_question['image_path'] && file_exists($edit_question['image_path'])): ?>
                                    <div class="mt-2">
                                        <strong>Image actuelle:</strong><br>
                                        <img src="<?php echo htmlspecialchars($edit_question['image_path']); ?>" alt="Image actuelle" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">
                                    </div>
                                <?php endif; ?>
                                
                                <div id="imagePreview" class="mt-2"></div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Question Descriptive</strong><br>
                                    Les questions d'imagerie sont des questions ouvertes où le candidat décrit ce qu'il observe sur l'image. Aucun choix prédéfini n'est nécessaire.
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="alert alert-warning">
                                <i class="fas fa-camera me-2"></i>
                                <strong>Test Pratique Imagerie</strong><br>
                                • Pour candidats ayant réussi THI ou THB<br>
                                • <strong>Image obligatoire</strong><br>
                                • Questions descriptives ouvertes<br>
                                • Formats: JPG, PNG, GIF, WebP<br>
                                • Taille max: 5MB
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> <?php echo $edit_question ? 'Modifier' : 'Enregistrer'; ?> la question
                        </button>
                        <?php if ($edit_question): ?>
                            <a href="admin_imagerie_questions.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Annuler
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Existing Questions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-list"></i> Questions Existantes (<?php echo count($questions); ?>)</h6>
            </div>
            <div class="card-body">
                <?php if (empty($questions)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <div>Aucune question d'imagerie enregistrée pour le moment.</div>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($questions as $idx => $q): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div class="flex-grow-1">
                                        <span class="badge bg-secondary me-2">#<?php echo ($idx + 1); ?></span>
                                        <strong><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></strong>
                                        <span class="badge bg-info ms-2">Question descriptive</span>
                                        
                                        <?php if ($q['image_path']): ?>
                                            <span class="badge bg-success ms-1"><i class="fas fa-image"></i> Avec image</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($q['image_path'] && file_exists($q['image_path'])): ?>
                                            <div class="mt-2">
                                                <img src="<?php echo htmlspecialchars($q['image_path']); ?>" alt="Question Image" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-2">
                                            <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Le candidat décrit ce qu'il observe sur l'image</small>
                                        </div>
                                    </div>
                                    <div class="ms-3 d-flex align-items-start gap-2">
                                        <a href="admin_imagerie_questions.php?edit=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> Modifier
                                        </a>
                                        <a href="admin_imagerie_questions.php?action=delete&id=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette question ?')">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </a>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const choicesContainer = document.getElementById('choicesContainer');
    const addChoiceBtn = document.getElementById('addChoiceBtn');
    const typeSingle = document.getElementById('type_single');
    const typeMultiple = document.getElementById('type_multiple');
    const imageInput = document.getElementById('imageInput');

    // Image preview
    imageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        const preview = document.getElementById('imagePreview');
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">`;
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '';
        }
    });

    function addChoiceRow(initialText = '') {
        const row = document.createElement('div');
        row.className = 'input-group mb-2';

        const correctnessSpan = document.createElement('span');
        correctnessSpan.className = 'input-group-text correctness-cell';

        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'correct_single';
        radio.className = 'form-check-input d-none';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = 'correct_answers[]';
        checkbox.className = 'form-check-input d-none';

        correctnessSpan.appendChild(radio);
        correctnessSpan.appendChild(checkbox);

        const input = document.createElement('input');
        input.type = 'text';
        input.name = 'choices[]';
        input.className = 'form-control';
        input.placeholder = 'Réponse...';
        input.required = true;
        if (initialText) input.value = initialText;

        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'btn btn-outline-danger';
        delBtn.title = 'Supprimer';
        delBtn.innerHTML = '<i class="fas fa-trash"></i>';
        delBtn.addEventListener('click', () => row.remove());

        row.appendChild(correctnessSpan);
        row.appendChild(input);
        row.appendChild(delBtn);

        choicesContainer.appendChild(row);
        refreshCorrectnessInputs();
    }

    function refreshCorrectnessInputs() {
        const rows = choicesContainer.querySelectorAll('.input-group');
        const isSingle = typeSingle.checked;

        rows.forEach((row, index) => {
            const radio = row.querySelector('input[type="radio"]');
            const checkbox = row.querySelector('input[type="checkbox"]');

            if (isSingle) {
                radio.classList.remove('d-none');
                checkbox.classList.add('d-none');
                radio.value = index;
            } else {
                radio.classList.add('d-none');
                checkbox.classList.remove('d-none');
                checkbox.value = index;
            }
        });
    }

    addChoiceBtn.addEventListener('click', () => addChoiceRow());
    typeSingle.addEventListener('change', refreshCorrectnessInputs);
    typeMultiple.addEventListener('change', refreshCorrectnessInputs);

    // Add initial choices
    addChoiceRow();
    addChoiceRow();
});

function resetForm() {
    document.getElementById('imagerieForm').reset();
    document.getElementById('choicesContainer').innerHTML = '';
    document.getElementById('imagePreview').innerHTML = '';
    
    // Re-add initial choices
    setTimeout(() => {
        const addChoiceBtn = document.getElementById('addChoiceBtn');
        addChoiceBtn.click();
        addChoiceBtn.click();
    }, 100);
}

function editQuestion(questionId) {
    // TODO: Implement edit functionality
    alert('Fonctionnalité de modification en cours de développement.');
}

function deleteQuestion(questionId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette question ? Cette action est irréversible.')) {
        // TODO: Implement delete functionality
        alert('Fonctionnalité de suppression en cours de développement.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
