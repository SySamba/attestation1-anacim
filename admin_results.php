<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$page_title = "ANACIM - Résultats des Candidats";

// Pagination settings
$results_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $results_per_page;

// Filter parameters
$category_filter = $_GET['category'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// Build WHERE clause for filters
$where_conditions = [];
$params = [];

if ($category_filter !== 'all') {
    $where_conditions[] = "c.categorie = ?";
    $params[] = $category_filter;
}

if ($status_filter !== 'all') {
    if ($status_filter === 'completed') {
        $where_conditions[] = "qs.status = 'completed'";
    } elseif ($status_filter === 'not_taken') {
        $where_conditions[] = "qs.id IS NULL";
    } elseif ($status_filter === 'passed') {
        $where_conditions[] = "qs.status = 'completed' AND qs.score >= 80";
    } elseif ($status_filter === 'failed') {
        $where_conditions[] = "qs.status = 'completed' AND qs.score < 80";
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT c.id) as total 
              FROM candidates c 
              LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.status = 'completed'
              $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_results = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_results / $results_per_page);

// Get candidates with their QCM results
$sql = "SELECT c.id, c.prenom, c.nom, c.email, c.matricule, c.categorie, c.status as candidate_status,
               qs.id as session_id, qs.score, qs.correct_answers, qs.total_questions, 
               qs.completed_at, qs.status as qcm_status
        FROM candidates c 
        LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.status = 'completed'
        $where_clause
        ORDER BY qs.completed_at DESC, c.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param);
}
$stmt->bindValue(':limit', $results_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_sql = "SELECT 
    COUNT(DISTINCT c.id) as total_candidates,
    COUNT(DISTINCT CASE WHEN c.status = 'accepted' THEN c.id END) as accepted_candidates,
    COUNT(DISTINCT CASE WHEN qs.status = 'completed' THEN c.id END) as completed_tests,
    COUNT(DISTINCT CASE WHEN qs.status = 'completed' AND qs.score >= 80 THEN c.id END) as passed_tests,
    COUNT(DISTINCT CASE WHEN qs.status = 'completed' AND qs.score < 80 THEN c.id END) as failed_tests,
    AVG(CASE WHEN qs.status = 'completed' THEN qs.score END) as average_score
    FROM candidates c 
    LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.status = 'completed'
    WHERE c.status = 'accepted'";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-anacim">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4><i class="fas fa-chart-bar"></i> Résultats des Examens QCM</h4>
                        <p class="mb-0">Tableau de bord des notes et performances</p>
                    </div>
                    <div>
                        <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm me-2">
                            <i class="fas fa-users"></i> Gestion Candidats
                        </a>
                        <a href="logout.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                <h4 class="text-primary"><?php echo $stats['accepted_candidates']; ?></h4>
                <small class="text-muted">Candidats Acceptés</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-clipboard-check fa-2x text-info mb-2"></i>
                <h4 class="text-info"><?php echo $stats['completed_tests']; ?></h4>
                <small class="text-muted">Tests Terminés</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-trophy fa-2x text-success mb-2"></i>
                <h4 class="text-success"><?php echo $stats['passed_tests']; ?></h4>
                <small class="text-muted">Réussites</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                <h4 class="text-danger"><?php echo $stats['failed_tests']; ?></h4>
                <small class="text-muted">Échecs</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-percentage fa-2x text-warning mb-2"></i>
                <h4 class="text-warning"><?php echo $stats['average_score'] ? number_format($stats['average_score'], 1) : '0'; ?>%</h4>
                <small class="text-muted">Score Moyen</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-chart-line fa-2x text-secondary mb-2"></i>
                <h4 class="text-secondary"><?php echo $stats['completed_tests'] > 0 ? number_format(($stats['passed_tests'] / $stats['completed_tests']) * 100, 1) : '0'; ?>%</h4>
                <small class="text-muted">Taux de Réussite</small>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="category" class="form-label">Catégorie</label>
                        <select name="category" id="category" class="form-select">
                            <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>Toutes les catégories</option>
                            <option value="1" <?php echo $category_filter === '1' ? 'selected' : ''; ?>>Catégorie 1</option>
                            <option value="2" <?php echo $category_filter === '2' ? 'selected' : ''; ?>>Catégorie 2</option>
                            <option value="3" <?php echo $category_filter === '3' ? 'selected' : ''; ?>>Catégorie 3</option>
                            <option value="4" <?php echo $category_filter === '4' ? 'selected' : ''; ?>>Catégorie 4</option>
                            <option value="5" <?php echo $category_filter === '5' ? 'selected' : ''; ?>>Catégorie 5</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Statut du Test</label>
                        <select name="status" id="status" class="form-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Test terminé</option>
                            <option value="not_taken" <?php echo $status_filter === 'not_taken' ? 'selected' : ''; ?>>Test non passé</option>
                            <option value="passed" <?php echo $status_filter === 'passed' ? 'selected' : ''; ?>>Réussi (≥80%)</option>
                            <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Échoué (<80%)</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-anacim me-2">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        <a href="admin_results.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Réinitialiser
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Results Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-anacim">
                <h5><i class="fas fa-table"></i> Résultats des Candidats</h5>
            </div>
            <div class="card-body">
                <?php if (empty($results)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucun résultat trouvé</h5>
                        <p class="text-muted">Aucun candidat ne correspond aux critères sélectionnés.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Candidat</th>
                                    <th>Matricule</th>
                                    <th>Email</th>
                                    <th>Catégorie</th>
                                    <th>Statut Test</th>
                                    <th>Score</th>
                                    <th>Détails</th>
                                    <th>Date Test</th>
                                    <th>Résultat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($result['prenom'] . ' ' . $result['nom']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($result['matricule']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($result['email']); ?></td>
                                    <td>
                                        <span class="badge bg-primary">Cat. <?php echo $result['categorie']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($result['qcm_status'] === 'completed'): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>Terminé
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="fas fa-clock me-1"></i>Non passé
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($result['score'] !== null): ?>
                                            <span class="badge <?php echo $result['score'] >= 80 ? 'bg-success' : 'bg-danger'; ?> fs-6">
                                                <?php echo number_format($result['score'], 1); ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($result['correct_answers'] !== null): ?>
                                            <small class="text-muted">
                                                <?php echo $result['correct_answers']; ?>/<?php echo $result['total_questions']; ?> bonnes réponses
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($result['completed_at']): ?>
                                            <small><?php echo date('d/m/Y H:i', strtotime($result['completed_at'])); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($result['score'] !== null): ?>
                                            <?php if ($result['score'] >= 80): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-trophy me-1"></i>RÉUSSI
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times me-1"></i>ÉCHEC
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-minus me-1"></i>N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="text-muted">
                        Affichage de <?php echo $offset + 1; ?> à <?php echo min($offset + $results_per_page, $total_results); ?> 
                        sur <?php echo $total_results; ?> résultats
                    </div>
                    <nav aria-label="Navigation des pages">
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>">
                                        <i class="fas fa-chevron-left"></i> Précédent
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>">
                                        Suivant <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
