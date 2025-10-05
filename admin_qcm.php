<?php
session_start();
require_once 'config.php';

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

// Pagination for questions
$q_per_page = 10;
$q_current_page = isset($_GET['qpage']) ? max(1, (int)$_GET['qpage']) : 1;
$q_offset = ($q_current_page - 1) * $q_per_page;

// Total count
$q_count_sql = "SELECT COUNT(*) AS total FROM qcm_questions";
$q_count_stmt = $pdo->prepare($q_count_sql);
$q_count_stmt->execute();
$q_total = (int)$q_count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$q_total_pages = max(1, (int)ceil($q_total / $q_per_page));

// Fetch existing questions with choices (ascending for sequential display 1,2,3,...)
// Note: Some MySQL/PDO configs don't allow bound params in LIMIT/OFFSET. Inject validated ints instead.
$safe_limit = (int)$q_per_page;
$safe_offset = (int)$q_offset;
$q_sql = "SELECT * FROM qcm_questions ORDER BY id ASC LIMIT $safe_offset, $safe_limit";
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
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Phase</label>
                            <select name="phase" class="form-select" id="phaseSelect" required>
                                <option value="phase1">Phase 1 - Filtrage des personnes et bagages</option>
                                <option value="phase2">Phase 2 - Imagerie</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Épreuve</label>
                            <select name="epreuve" class="form-select" id="epreuveSelect" required>
                                <option value="THB">TH.B - Théorie de Base</option>
                                <option value="FBAG">FBAG - Filtrage Bagages</option>
                                <option value="PLP">PLP - Palpation</option>
                                <option value="FMAG">FMAG - Filtrage Magnétomètre</option>
                                <option value="IMAGERIE" style="display:none;">Imagerie</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Catégorie</label>
                            <select name="category" class="form-select" required>
                                <option value="1">Catégorie 1</option>
                                <option value="2">Catégorie 2</option>
                                <option value="3">Catégorie 3</option>
                                <option value="4">Catégorie 4</option>
                                <option value="5">Catégorie 5</option>
                            </select>
                        </div>
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
                                        <span class="badge bg-secondary me-2">#<?php echo ($q_offset + $idx + 1); ?></span>
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
                                    <div class="ms-3 d-flex align-items-start gap-2">
                                        <a href="admin_qcm_edit.php?id=<?php echo (int)$q['id']; ?>" class="btn btn-sm btn-outline-anacim">
                                            <i class="fas fa-edit"></i> Modifier
                                        </a>
                                        <form action="delete_qcm_question.php" method="post" onsubmit="return confirm('Supprimer cette question ? Cette action est irréversible.');">
                                            <input type="hidden" name="id" value="<?php echo (int)$q['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($q_total_pages > 1): ?>
                    <nav aria-label="Pagination des questions" class="mt-3">
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($q_current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?qpage=<?php echo $q_current_page - 1; ?>">
                                        <i class="fas fa-chevron-left"></i> Précédent
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i> Précédent</span></li>
                            <?php endif; ?>

                            <?php
                            $q_start = max(1, $q_current_page - 2);
                            $q_end = min($q_total_pages, $q_current_page + 2);
                            if ($q_start > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?qpage=1">1</a></li>';
                                if ($q_start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            for ($i = $q_start; $i <= $q_end; $i++) {
                                $active = $i === $q_current_page ? ' active' : '';
                                echo '<li class="page-item' . $active . '"><a class="page-link" href="?qpage=' . $i . '">' . $i . '</a></li>';
                            }
                            if ($q_end < $q_total_pages) {
                                if ($q_end < $q_total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                echo '<li class="page-item"><a class="page-link" href="?qpage=' . $q_total_pages . '">' . $q_total_pages . '</a></li>';
                            }
                            ?>

                            <?php if ($q_current_page < $q_total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?qpage=<?php echo $q_current_page + 1; ?>">
                                        Suivant <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link">Suivant <i class="fas fa-chevron-right"></i></span></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
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
    
    // Gérer l'affichage des épreuves selon la phase sélectionnée
    const phaseSelect = document.getElementById('phaseSelect');
    const epreuveSelect = document.getElementById('epreuveSelect');
    
    phaseSelect.addEventListener('change', function() {
        const phase = this.value;
        const options = epreuveSelect.querySelectorAll('option');
        
        options.forEach(option => {
            if (phase === 'phase1') {
                if (['THB', 'FBAG', 'PLP', 'FMAG'].includes(option.value)) {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            } else if (phase === 'phase2') {
                if (option.value === 'IMAGERIE') {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
        });
        
        // Sélectionner la première option visible
        const firstVisible = Array.from(options).find(opt => opt.style.display !== 'none');
        if (firstVisible) {
            epreuveSelect.value = firstVisible.value;
        }
    });

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
