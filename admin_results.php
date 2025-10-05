<?php
session_start();
require_once 'config.php';

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

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM candidates WHERE status = 'accepted'";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute();
$total_results = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_results / $results_per_page);

// Get all candidates first, then filter by results
$sql = "SELECT c.id, c.prenom, c.nom, c.email, c.matricule, c.categorie, c.status as candidate_status
        FROM candidates c 
        WHERE c.status = 'accepted'
        ORDER BY c.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $results_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get Phase 1 results for all candidates (THB for categories 2,3,4,5 and THI for category C1)
$results = [];

foreach ($candidates as $candidate) {
    $candidate_results = [
        'candidate' => $candidate,
        'phase1' => null,
        'phase2' => null
    ];
    
    // Determine which Phase 1 test based on category
    $phase1_test = ($candidate['categorie'] == '1') ? 'THI' : 'THB';
    
    // Get Phase 1 result
    $phase1_sql = "SELECT qs.score, qs.correct_answers, qs.total_questions, qs.completed_at, qs.status
                   FROM qcm_sessions qs 
                   WHERE qs.candidate_id = ? AND qs.phase = 'phase1' AND qs.epreuve = ?
                   ORDER BY qs.completed_at DESC LIMIT 1";
    $phase1_stmt = $pdo->prepare($phase1_sql);
    $phase1_stmt->execute([$candidate['id'], $phase1_test]);
    $phase1_result = $phase1_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($phase1_result) {
        $phase1_result['test_type'] = $phase1_test;
        $candidate_results['phase1'] = $phase1_result;
    }
    
    // Get Phase 2 (Pratique Imagerie) result
    $phase2_sql = "SELECT cpr.score, cpr.status, cpr.completed_at
                   FROM candidate_phase_results cpr 
                   WHERE cpr.candidate_id = ? AND cpr.phase = 'phase2' AND cpr.epreuve = 'IMAGERIE'
                   ORDER BY cpr.completed_at DESC LIMIT 1";
    $phase2_stmt = $pdo->prepare($phase2_sql);
    $phase2_stmt->execute([$candidate['id']]);
    $phase2_result = $phase2_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($phase2_result) {
        $candidate_results['phase2'] = $phase2_result;
    }
    
    // Only include candidates who have Phase 1 results (THI/THB completed)
    if ($phase1_result) {
        $results[] = $candidate_results;
    }
}

// Get statistics for Phase 1
$stats_sql = "SELECT 
    COUNT(DISTINCT c.id) as total_candidates,
    COUNT(DISTINCT CASE WHEN c.status = 'accepted' THEN c.id END) as accepted_candidates,
    COUNT(DISTINCT CASE WHEN qs.status = 'completed' THEN c.id END) as completed_tests,
    COUNT(DISTINCT CASE WHEN qs.status = 'completed' AND qs.score >= 80 THEN c.id END) as passed_tests,
    COUNT(DISTINCT CASE WHEN qs.status = 'completed' AND qs.score < 80 THEN c.id END) as failed_tests,
    AVG(CASE WHEN qs.status = 'completed' THEN qs.score END) as average_score
    FROM candidates c 
    LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.status = 'completed' AND qs.phase = 'phase1'
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
                        <h4><i class="fas fa-chart-bar"></i> Résultats Complets - THI/THB + Pratique Imagerie</h4>
<p class="mb-0">Candidats ayant passé la pratique imagerie - Éligibilité pour l'oral</p>
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
                    <div class="col-md-4">
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
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-anacim me-2">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        <a href="admin_results.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Réinitialiser
                        </a>
                    </div>
                    <div class="col-md-4">
                        <div class="alert alert-info mb-0">
                            <small><i class="fas fa-info-circle me-1"></i>
                            <strong>Phase 1:</strong> THI (C1) ou THB (2,3,4,5) - <strong>Phase 2:</strong> Pratique Imagerie</small>
                        </div>
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
                <h5><i class="fas fa-table"></i> Résultats des Candidats - Phase 1 & 2</h5>
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
                                    <th>Catégorie</th>
                                    <th class="text-center">Phase 1 (THI/THB)</th>
                                    <th class="text-center">Pratique Imagerie</th>
                                    <th class="text-center">Éligible Oral</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): 
                                    $candidate = $result['candidate'];
                                    $phase1 = $result['phase1'];
                                    $phase2 = $result['phase2'];
                                    
                                    // Determine eligibility for oral exam (≥80% in pratique imagerie)
                                    $eligible_oral = false;
                                    $status_text = 'Non éligible';
                                    $status_class = 'danger';
                                    $status_icon = 'times-circle';
                                    
                                    if ($phase2 && $phase2['status'] === 'completed' && $phase2['score'] >= 80) {
                                        $eligible_oral = true;
                                        $status_text = '✅ ÉLIGIBLE ORAL';
                                        $status_class = 'success';
                                        $status_icon = 'microphone';
                                    } elseif ($phase2 && $phase2['status'] === 'completed' && $phase2['score'] < 80) {
                                        $status_text = '❌ Échec Imagerie';
                                        $status_class = 'danger';
                                        $status_icon = 'times-circle';
                                    } elseif ($phase2 && $phase2['status'] === 'pending') {
                                        $status_text = '⏳ En attente notation';
                                        $status_class = 'warning';
                                        $status_icon = 'clock';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($candidate['prenom'] . ' ' . $candidate['nom']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($candidate['email']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($candidate['matricule']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">Cat. <?php echo $candidate['categorie']; ?></span>
                                    </td>
                                    
                                    <!-- Phase 1 -->
                                    <td class="text-center">
                                        <?php if ($phase1): ?>
                                            <div class="mb-1">
                                                <span class="badge bg-info"><?php echo $phase1['test_type']; ?></span>
                                            </div>
                                            <strong class="fs-5"><?php echo number_format($phase1['score'], 1); ?>%</strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo $phase1['correct_answers']; ?>/<?php echo $phase1['total_questions']; ?> questions
                                            </small>
                                            <br>
                                            <?php if ($phase1['score'] >= 80): ?>
                                                <span class="badge bg-success">✅ Réussi</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">❌ Échec</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Non passé</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Phase 2 -->
                                    <td class="text-center">
                                        <?php if ($phase2): ?>
                                            <?php if ($phase2['status'] === 'pending'): ?>
                                                <span class="badge bg-warning">⏳ En attente notation</span>
                                            <?php elseif ($phase2['status'] === 'completed'): ?>
                                                <strong class="fs-4 <?php echo $phase2['score'] >= 80 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo number_format($phase2['score'], 1); ?>%
                                                </strong>
                                                <br>
                                                <?php if ($phase2['score'] >= 80): ?>
                                                    <span class="badge bg-success">✅ Réussi</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">❌ Échec</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Non passé</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Éligible Oral -->
                                    <td class="text-center">
                                        <span class="badge bg-<?php echo $status_class; ?> fs-6">
                                            <i class="fas fa-<?php echo $status_icon; ?> me-1"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
