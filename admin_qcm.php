<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$page_title = "ANACIM - Gestion QCM";

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

// Fetch existing questions with choices (ascending for sequential display 1,2,3,...)
$q_sql = "SELECT * FROM qcm_questions ORDER BY id ASC";
$q_stmt = $pdo->prepare($q_sql);
$q_stmt->execute();
$questions = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

$choicesByQuestion = [];
if (!empty($questions)) {
    $ids = array_column($questions, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $c_sql = "SELECT * FROM qcm_choices WHERE question_id IN ($in) ORDER BY id ASC";
    $c_stmt = $pdo->prepare($c_sql);
    $c_stmt->execute($ids);
    while ($row = $c_stmt->fetch(PDO::FETCH_ASSOC)) {
        $choicesByQuestion[$row['question_id']][] = $row;
    }
}

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-anacim d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list-check"></i> Gestion des QCM</h5>
                <a href="admin_dashboard.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Question enregistrée avec succès.</div>
                <?php elseif (isset($_GET['error'])): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php endif; ?>

                <h6 class="mb-3">Ajouter une nouvelle question</h6>
                <form action="save_qcm_question.php" method="post" id="qcmForm">
                    <div class="mb-3">
                        <label class="form-label">Question</label>
                        <textarea name="question_text" class="form-control" rows="2" placeholder="Saisissez la question..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label me-3">Type de question</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="question_type" id="type_single" value="single" checked>
                            <label class="form-check-label" for="type_single">Choix unique</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="question_type" id="type_multiple" value="multiple">
                            <label class="form-check-label" for="type_multiple">Choix multiples</label>
                        </div>
                    </div>

                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <label class="form-label mb-0">Réponses proposées</label>
                        <button type="button" class="btn btn-outline-anacim btn-sm" id="addChoiceBtn"><i class="fas fa-plus"></i> Ajouter une réponse</button>
                    </div>
                    <div id="choicesContainer"></div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-anacim"><i class="fas fa-save"></i> Enregistrer la question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-anacim">
                <h6 class="mb-0"><i class="fas fa-database"></i> Questions existantes</h6>
            </div>
            <div class="card-body">
                <?php if (empty($questions)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <div>Aucune question enregistrée pour le moment.</div>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($questions as $idx => $q): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="badge bg-secondary me-2">#<?php echo $idx + 1; ?></span>
                                        <strong><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></strong>
                                        <span class="badge bg-info ms-2"><?php echo $q['question_type'] === 'single' ? 'Choix unique' : 'Choix multiples'; ?></span>
                                        <div class="mt-2">
                                            <?php if (!empty($choicesByQuestion[$q['id']])): ?>
                                                <ul class="mb-0">
                                                    <?php foreach ($choicesByQuestion[$q['id']] as $ch): ?>
                                                        <li>
                                                            <?php if ($q['question_type'] === 'single'): ?>
                                                                <i class="far fa-circle me-1"></i>
                                                            <?php else: ?>
                                                                <i class="far fa-square me-1"></i>
                                                            <?php endif; ?>
                                                            <?php echo htmlspecialchars($ch['choice_text']); ?>
                                                            <?php if ((int)$ch['is_correct'] === 1): ?>
                                                                <span class="badge bg-success ms-2"><i class="fas fa-check"></i> Correct</span>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <small class="text-muted">Aucun choix enregistré.</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <!-- Future actions: edit/delete -->
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

    function addChoiceRow(initialText = '') {
        const idx = Date.now() + Math.floor(Math.random()*1000);
        const row = document.createElement('div');
        row.className = 'input-group mb-2';
        row.innerHTML = `
            <span class="input-group-text correctness-cell">
                <input type="radio" name="correct_single" class="form-check-input d-none">
                <input type="checkbox" class="form-check-input d-none">
            </span>
            <input type="text" name="choices[]" class="form-control" placeholder="Réponse..." required value="${initialText.replace(/"/g,'&quot;')}">
            <button class="btn btn-outline-danger" type="button" title="Supprimer"><i class="fas fa-trash"></i></button>
        `;

        // delete button
        row.querySelector('button').addEventListener('click', () => {
            row.remove();
        });

        choicesContainer.appendChild(row);
        refreshCorrectnessInputs();
    }

    function refreshCorrectnessInputs() {
        const rows = choicesContainer.querySelectorAll('.input-group');
        rows.forEach((row, i) => {
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
                radio.value = i; // index among visible rows
                control = document.createElement('label');
                control.className = 'mb-0';
                control.title = 'Bonne réponse';
                control.innerHTML = '<i class="far fa-circle"></i>';
                control.addEventListener('click', () => { radio.checked = true; });
            } else {
                radio.classList.add('d-none');
                checkbox.classList.remove('d-none');
                checkbox.name = 'correct_multiple[]';
                checkbox.value = i;
                control = document.createElement('label');
                control.className = 'mb-0';
                control.title = 'Marquer comme correcte';
                control.innerHTML = '<i class="far fa-square"></i>';
                control.addEventListener('click', () => { checkbox.checked = !checkbox.checked; });
            }
            correctnessCell.appendChild(control);
        });
    }

    // Initialize with 3 blank choices
    addChoiceRow();
    addChoiceRow();
    addChoiceRow();

    addChoiceBtn.addEventListener('click', () => addChoiceRow());
    typeSingle.addEventListener('change', refreshCorrectnessInputs);
    typeMultiple.addEventListener('change', refreshCorrectnessInputs);

    // On submit, map selected correct inputs to choices[] indexes
    document.getElementById('qcmForm').addEventListener('submit', function(e) {
        const rows = Array.from(choicesContainer.querySelectorAll('.input-group'));
        if (rows.length < 2) {
            e.preventDefault();
            alert('Ajoutez au moins deux réponses.');
            return;
        }
        // Build hidden inputs for correctness aligned with choices order
        // Clear old hidden inputs
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
});
</script>

<?php include 'includes/footer.php'; ?>
