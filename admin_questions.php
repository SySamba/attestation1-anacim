<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$page_title = "ANACIM - Gestion des Questions";

// Get selected subject filter
$subject_filter = $_GET['subject'] ?? 'THB';
$category_filter = $_GET['category'] ?? 'all';

// Pagination settings
$questions_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $questions_per_page;

// Build WHERE clause for filters
$where_conditions = ["epreuve = ?"];
$params = [$subject_filter];

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM qcm_questions $where_clause";
$count_stmt = $pdo->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->execute($params);
} else {
    $count_stmt->execute();
}
$total_questions = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_questions / $questions_per_page);

// Get questions for current page
$sql = "SELECT q.*, 
               (SELECT COUNT(*) FROM qcm_choices c WHERE c.question_id = q.id) as choice_count,
               (SELECT COUNT(*) FROM qcm_choices c WHERE c.question_id = q.id AND c.is_correct = 1) as correct_count
        FROM qcm_questions q 
        $where_clause
        ORDER BY q.created_at DESC
        LIMIT $questions_per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle question deletion
if (isset($_POST['delete_question'])) {
    $question_id = $_POST['question_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete choices first
        $delete_choices = $pdo->prepare("DELETE FROM qcm_choices WHERE question_id = ?");
        $delete_choices->execute([$question_id]);
        
        // Delete question
        $delete_question = $pdo->prepare("DELETE FROM qcm_questions WHERE id = ?");
        $delete_question->execute([$question_id]);
        
        $pdo->commit();
        $_SESSION['success_message'] = "Question supprimée avec succès.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Erreur lors de la suppression : " . $e->getMessage();
    }
    
    header("Location: admin_questions.php?subject=$subject_filter&category=$category_filter");
    exit;
}

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-anacim">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4><i class="fas fa-question-circle"></i> Gestion des Questions - Phase 1</h4>
                        <p class="mb-0">Administration des questions par matière</p>
                    </div>
                    <div>
                        <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm me-2">
                            <i class="fas fa-tachometer-alt"></i> Tableau de bord
                        </a>
                        <a href="admin_results.php" class="btn btn-outline-light btn-sm me-2">
                            <i class="fas fa-chart-bar"></i> Résultats
                        </a>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subject Navigation -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="btn-group" role="group">
                            <a href="?subject=THB&category=<?php echo $category_filter; ?>" 
                               class="btn <?php echo $subject_filter === 'THB' ? 'btn-anacim' : 'btn-outline-secondary'; ?>">
                                <i class="fas fa-book"></i> THB - Théorie Base
                                <small class="d-block">Catégories 2,3,4,5</small>
                            </a>
                            <a href="?subject=THI&category=<?php echo $category_filter; ?>" 
                               class="btn <?php echo $subject_filter === 'THI' ? 'btn-anacim' : 'btn-outline-secondary'; ?>">
                                <i class="fas fa-camera"></i> THI - Théorie Imagerie
                                <small class="d-block">Catégorie C1</small>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <a href="admin_question_add.php?subject=<?php echo $subject_filter; ?>" class="btn btn-success">
                            <i class="fas fa-plus"></i> Ajouter une question
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Success/Error Messages -->
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- Questions List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-anacim">
                <h5><i class="fas fa-list"></i> Questions - <?php echo $subject_filter; ?></h5>
            </div>
            <div class="card-body">
                <?php if (empty($questions)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucune question trouvée</h5>
                        <p class="text-muted">Aucune question ne correspond aux critères sélectionnés.</p>
                        <a href="admin_question_add.php?subject=<?php echo $subject_filter; ?>" class="btn btn-anacim">
                            <i class="fas fa-plus"></i> Ajouter la première question
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <div class="list-group">
                            <?php foreach ($questions as $idx => $question): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <span class="badge bg-secondary me-2">#<?php echo ($offset + $idx + 1); ?></span>
                                            <strong><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></strong>
                                            <span class="badge bg-info ms-2"><?php echo $question['question_type'] === 'single' ? 'Choix unique' : 'Choix multiples'; ?></span>
                                            
                                            <?php
                                            // Récupérer les choix pour cette question
                                            $choices_sql = "SELECT * FROM qcm_choices WHERE question_id = ? ORDER BY id";
                                            $choices_stmt = $pdo->prepare($choices_sql);
                                            $choices_stmt->execute([$question['id']]);
                                            $choices = $choices_stmt->fetchAll(PDO::FETCH_ASSOC);
                                            ?>
                                            
                                            <div class="mt-2">
                                                <?php if (!empty($choices)): ?>
                                                    <ul class="mb-0">
                                                        <?php foreach ($choices as $choice): ?>
                                                            <li>
                                                                <?php if ($question['question_type'] === 'single'): ?>
                                                                    <i class="far fa-circle me-1"></i>
                                                                <?php else: ?>
                                                                    <i class="far fa-square me-1"></i>
                                                                <?php endif; ?>
                                                                <?php echo htmlspecialchars($choice['choice_text']); ?>
                                                                <?php if ((int)$choice['is_correct'] === 1): ?>
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
                                            <a href="admin_question_edit.php?id=<?php echo (int)$question['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i> Modifier
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted">
                            Affichage de <?php echo $offset + 1; ?> à <?php echo min($offset + $questions_per_page, $total_questions); ?> 
                            sur <?php echo $total_questions; ?> questions
                        </div>
                        <nav aria-label="Navigation des pages">
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?subject=<?php echo $subject_filter; ?>&category=<?php echo $category_filter; ?>&page=<?php echo $current_page - 1; ?>">
                                            <i class="fas fa-chevron-left"></i> Précédent
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?subject=<?php echo $subject_filter; ?>&category=<?php echo $category_filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?subject=<?php echo $subject_filter; ?>&category=<?php echo $category_filter; ?>&page=<?php echo $current_page + 1; ?>">
                                            Suivant <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer cette question ?</p>
                <p class="text-danger"><strong>Cette action est irréversible.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="question_id" id="deleteQuestionId">
                    <button type="submit" name="delete_question" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(questionId) {
    document.getElementById('deleteQuestionId').value = questionId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
