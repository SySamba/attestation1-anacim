<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$page_title = "ANACIM - Modifier QCM";

// Validate id
$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($question_id <= 0) {
    header('Location: admin_qcm.php?error=' . urlencode('Question introuvable.'));
    exit;
}

// Ensure tables exist (idempotent)
$pdo->exec("CREATE TABLE IF NOT EXISTS qcm_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    question_type ENUM('single','multiple') NOT NULL DEFAULT 'single',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS qcm_choices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    choice_text TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES qcm_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Load question
$q_stmt = $pdo->prepare("SELECT * FROM qcm_questions WHERE id = ?");
$q_stmt->execute([$question_id]);
$question = $q_stmt->fetch(PDO::FETCH_ASSOC);
if (!$question) {
    header('Location: admin_qcm.php?error=' . urlencode('Question introuvable.'));
    exit;
}

// Load choices
$c_stmt = $pdo->prepare("SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id ASC");
$c_stmt->execute([$question_id]);
$choices = $c_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-anacim d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Modifier la question</h5>
                <div>
                    <a href="admin_qcm.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Modifications enregistrées.</div>
                <?php elseif (isset($_GET['error'])): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php endif; ?>

                <form action="update_qcm_question.php" method="post" id="qcmEditForm">
                    <input type="hidden" name="id" value="<?php echo (int)$question['id']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Question</label>
                        <textarea name="question_text" class="form-control" rows="2" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label me-3">Type de question</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="question_type" id="type_single" value="single" <?php echo $question['question_type']==='single'?'checked':''; ?>>
                            <label class="form-check-label" for="type_single">Choix unique</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="question_type" id="type_multiple" value="multiple" <?php echo $question['question_type']==='multiple'?'checked':''; ?>>
                            <label class="form-check-label" for="type_multiple">Choix multiples</label>
                        </div>
                    </div>

                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <label class="form-label mb-0">Réponses proposées</label>
                        <button type="button" class="btn btn-outline-anacim btn-sm" id="addChoiceBtn"><i class="fas fa-plus"></i> Ajouter une réponse</button>
                    </div>
                    <div id="choicesContainer"></div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-anacim"><i class="fas fa-save"></i> Enregistrer</button>
                    </div>
                </form>
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

    function addChoiceRow(initialText = '', initialCorrect = false) {
        const row = document.createElement('div');
        row.className = 'input-group mb-2';
        const safeVal = (initialText || '').replace(/"/g, '&quot;');
        row.innerHTML = '\
            <span class="input-group-text correctness-cell">\
                <input type="radio" name="correct_single" class="form-check-input d-none">\
                <input type="checkbox" class="form-check-input d-none">\
            </span>\
            <input type="text" name="choices[]" class="form-control" placeholder="Réponse..." required value="' + safeVal + '" />\
            <button class="btn btn-outline-danger" type="button" title="Supprimer"><i class="fas fa-trash"></i></button>';

        // delete button
        row.querySelector('button').addEventListener('click', () => {
            row.remove();
        });

        choicesContainer.appendChild(row);
        refreshCorrectnessInputs();

        // set initial correctness after DOM insertion
        const radio = row.querySelector('input[type="radio"]');
        const checkbox = row.querySelector('input[type="checkbox"]');
        if (typeSingle.checked) {
            radio.checked = !!initialCorrect;
        } else {
            checkbox.checked = !!initialCorrect;
        }
    }

    function refreshCorrectnessInputs() {
        const rows = choicesContainer.querySelectorAll('.input-group');
        rows.forEach((row) => {
            const correctnessCell = row.querySelector('.correctness-cell');
            const radio = correctnessCell.querySelector('input[type="radio"]');
            const checkbox = correctnessCell.querySelector('input[type="checkbox"]');

            // Remove old visible controls if any
            const old = correctnessCell.querySelector('label');
            if (old) old.remove();

            let control;
            if (typeSingle.checked) {
                radio.classList.remove('d-none');
                checkbox.classList.add('d-none');
                radio.name = 'correct_single';
                control = document.createElement('label');
                control.className = 'mb-0';
                control.title = 'Bonne réponse';
                control.innerHTML = '<i class="far fa-circle"></i>';
                control.addEventListener('click', () => { radio.checked = true; });
            } else {
                radio.classList.add('d-none');
                checkbox.classList.remove('d-none');
                checkbox.name = 'correct_multiple[]';
                control = document.createElement('label');
                control.className = 'mb-0';
                control.title = 'Marquer comme correcte';
                control.innerHTML = '<i class="far fa-square"></i>';
                control.addEventListener('click', () => { checkbox.checked = !checkbox.checked; });
            }
            correctnessCell.appendChild(control);
        });
    }

    // Prefill from PHP data
    const initialChoices = <?php echo json_encode(array_map(function($c){return ['text'=>$c['choice_text'],'is_correct'=>(int)$c['is_correct']===1];}, $choices), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
    if (initialChoices.length) {
        initialChoices.forEach(c => addChoiceRow(c.text, c.is_correct));
    } else {
        // fallback at least 2 blank
        addChoiceRow();
        addChoiceRow();
    }

    addChoiceBtn.addEventListener('click', () => addChoiceRow());
    typeSingle.addEventListener('change', refreshCorrectnessInputs);
    typeMultiple.addEventListener('change', refreshCorrectnessInputs);

    // On submit, map selected correct inputs to choices[] indexes
    document.getElementById('qcmEditForm').addEventListener('submit', function(e) {
        const rows = Array.from(choicesContainer.querySelectorAll('.input-group'));
        if (rows.length < 2) {
            e.preventDefault();
            alert('Ajoutez au moins deux réponses.');
            return;
        }
        this.querySelectorAll('input[name="is_correct[]"]').forEach(n => n.remove());
        rows.forEach((row) => {
            const isCorrect = typeSingle.checked
                ? row.querySelector('input[type="radio"]').checked
                : row.querySelector('input[type="checkbox"]').checked;
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'is_correct[]';
            hidden.value = isCorrect ? '1' : '0';
            this.appendChild(hidden);
        });
    });

    // Initial render icons
    refreshCorrectnessInputs();
});
</script>

<?php include 'includes/footer.php'; ?>
