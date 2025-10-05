<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$page_title = "ANACIM - Modifier une Question";
$question_id = $_GET['id'] ?? 0;

// Get question details
$sql = "SELECT * FROM qcm_questions WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$question_id]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    $_SESSION['error_message'] = "Question non trouvée.";
    header('Location: admin_questions.php');
    exit;
}

// Get choices
$choices_sql = "SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id";
$choices_stmt = $pdo->prepare($choices_sql);
$choices_stmt->execute([$question_id]);
$choices = $choices_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_POST) {
    $question_text = trim($_POST['question_text']);
    $question_type = $_POST['question_type'];
    $category = $_POST['category'];
    $choice_texts = $_POST['choices'] ?? [];
    $correct_answers = $_POST['correct_answers'] ?? [];
    
    $errors = [];
    
    // Validation
    if (empty($question_text)) {
        $errors[] = "Le texte de la question est requis.";
    }
    
    if (empty($choice_texts) || count($choice_texts) < 2) {
        $errors[] = "Au moins 2 choix de réponse sont requis.";
    }
    
    if (empty($correct_answers)) {
        $errors[] = "Au moins une réponse correcte doit être sélectionnée.";
    }
    
    // Validate choice texts
    foreach ($choice_texts as $choice) {
        if (empty(trim($choice))) {
            $errors[] = "Tous les choix de réponse doivent avoir un texte.";
            break;
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update question
            $update_sql = "UPDATE qcm_questions SET question_text = ?, question_type = ?, category = ? WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$question_text, $question_type, $category, $question_id]);
            
            // Delete existing choices
            $delete_choices = $pdo->prepare("DELETE FROM qcm_choices WHERE question_id = ?");
            $delete_choices->execute([$question_id]);
            
            // Insert new choices
            $choice_sql = "INSERT INTO qcm_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)";
            $choice_stmt = $pdo->prepare($choice_sql);
            
            foreach ($choice_texts as $index => $choice_text) {
                $is_correct = in_array($index, $correct_answers) ? 1 : 0;
                $choice_stmt->execute([$question_id, trim($choice_text), $is_correct]);
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = "Question modifiée avec succès.";
            header("Location: admin_questions.php?subject=" . $question['epreuve']);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-anacim">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4><i class="fas fa-edit"></i> Modifier la Question #<?php echo $question['id']; ?> - <?php echo $question['epreuve']; ?></h4>
                        <p class="mb-0">Modification d'une question existante</p>
                    </div>
                    <div>
                        <a href="admin_questions.php?subject=<?php echo $question['epreuve']; ?>" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-arrow-left"></i> Retour à la liste
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Error Messages -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <h6><i class="fas fa-exclamation-triangle me-2"></i>Erreurs détectées :</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-edit"></i> Formulaire de Modification</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="questionForm">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="question_text" class="form-label">Texte de la Question *</label>
                                <textarea name="question_text" id="question_text" class="form-control" rows="4" 
                                          placeholder="Saisissez le texte de votre question..." required><?php echo htmlspecialchars($_POST['question_text'] ?? $question['question_text']); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="question_type" class="form-label">Type de Question *</label>
                                <select name="question_type" id="question_type" class="form-select" required>
                                    <option value="single" <?php echo ($_POST['question_type'] ?? $question['question_type']) === 'single' ? 'selected' : ''; ?>>
                                        Choix unique
                                    </option>
                                    <option value="multiple" <?php echo ($_POST['question_type'] ?? $question['question_type']) === 'multiple' ? 'selected' : ''; ?>>
                                        Choix multiples
                                    </option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Catégorie</label>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <?php if ($question['epreuve'] === 'THI'): ?>
                                        <strong>THI (Théorie Imagerie)</strong> - Automatiquement assigné à la catégorie C1
                                        <input type="hidden" name="category" value="1">
                                    <?php else: ?>
                                        <strong>THB (Théorie de Base)</strong> - Automatiquement assigné aux catégories 2, 3, 4, 5
                                        <input type="hidden" name="category" value="<?php echo $question['category']; ?>">
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><i class="fas fa-list"></i> Choix de Réponses</h6>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addChoice()">
                                <i class="fas fa-plus"></i> Ajouter un choix
                            </button>
                        </div>
                        
                        <div id="choicesContainer">
                            <?php 
                            if ($_POST) {
                                $choice_texts = $_POST['choices'] ?? [];
                                $correct_answers = $_POST['correct_answers'] ?? [];
                                foreach ($choice_texts as $index => $choice_text): 
                            ?>
                            <div class="choice-item mb-2" data-index="<?php echo $index; ?>">
                                <div class="input-group">
                                    <div class="input-group-text">
                                        <input type="checkbox" name="correct_answers[]" value="<?php echo $index; ?>" 
                                               <?php echo in_array($index, $correct_answers) ? 'checked' : ''; ?>
                                               title="Cochez si cette réponse est correcte">
                                    </div>
                                    <input type="text" name="choices[]" class="form-control" 
                                           placeholder="Texte du choix <?php echo $index + 1; ?>" 
                                           value="<?php echo htmlspecialchars($choice_text); ?>" required>
                                    <button type="button" class="btn btn-outline-danger" onclick="removeChoice(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php 
                                endforeach;
                            } else {
                                foreach ($choices as $index => $choice): 
                            ?>
                            <div class="choice-item mb-2" data-index="<?php echo $index; ?>">
                                <div class="input-group">
                                    <div class="input-group-text">
                                        <input type="checkbox" name="correct_answers[]" value="<?php echo $index; ?>" 
                                               <?php echo $choice['is_correct'] ? 'checked' : ''; ?>
                                               title="Cochez si cette réponse est correcte">
                                    </div>
                                    <input type="text" name="choices[]" class="form-control" 
                                           placeholder="Texte du choix <?php echo $index + 1; ?>" 
                                           value="<?php echo htmlspecialchars($choice['choice_text']); ?>" required>
                                    <button type="button" class="btn btn-outline-danger" onclick="removeChoice(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php 
                                endforeach;
                            }
                            ?>
                        </div>
                        
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            Cochez la case à gauche pour marquer une réponse comme correcte. 
                            Pour les questions à choix multiples, vous pouvez cocher plusieurs cases.
                        </small>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="admin_questions.php?subject=<?php echo $question['epreuve']; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                        <button type="submit" class="btn btn-anacim">
                            <i class="fas fa-save"></i> Enregistrer les Modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let choiceIndex = <?php echo $_POST ? count($_POST['choices'] ?? []) : count($choices); ?>;

function addChoice() {
    const container = document.getElementById('choicesContainer');
    const choiceHtml = `
        <div class="choice-item mb-2" data-index="${choiceIndex}">
            <div class="input-group">
                <div class="input-group-text">
                    <input type="checkbox" name="correct_answers[]" value="${choiceIndex}" 
                           title="Cochez si cette réponse est correcte">
                </div>
                <input type="text" name="choices[]" class="form-control" 
                       placeholder="Texte du choix ${choiceIndex + 1}" required>
                <button type="button" class="btn btn-outline-danger" onclick="removeChoice(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', choiceHtml);
    choiceIndex++;
}

function removeChoice(button) {
    const choiceItem = button.closest('.choice-item');
    const container = document.getElementById('choicesContainer');
    
    // Don't allow removing if only 2 choices remain
    if (container.children.length <= 2) {
        alert('Au moins 2 choix de réponse sont requis.');
        return;
    }
    
    choiceItem.remove();
    updateChoiceIndexes();
}

function updateChoiceIndexes() {
    const choiceItems = document.querySelectorAll('.choice-item');
    choiceItems.forEach((item, index) => {
        const checkbox = item.querySelector('input[type="checkbox"]');
        const textInput = item.querySelector('input[type="text"]');
        
        checkbox.value = index;
        textInput.placeholder = `Texte du choix ${index + 1}`;
        item.setAttribute('data-index', index);
    });
}

// Update question type behavior
document.getElementById('question_type').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('input[name="correct_answers[]"]');
    const isMultiple = this.value === 'multiple';
    
    if (!isMultiple) {
        // For single choice, uncheck all but the first checked
        let firstChecked = false;
        checkboxes.forEach(checkbox => {
            if (checkbox.checked && !firstChecked) {
                firstChecked = true;
            } else if (checkbox.checked && firstChecked) {
                checkbox.checked = false;
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
