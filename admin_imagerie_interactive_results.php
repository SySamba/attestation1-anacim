<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit();
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query for candidates with interactive imagerie sessions
$where_conditions = ["sis.status IN ('completed', 'timeout')"];
$params = [];

if ($filter_status !== 'all') {
    if ($filter_status === 'passed') {
        $where_conditions[] = "sis.score >= 70";
    } elseif ($filter_status === 'failed') {
        $where_conditions[] = "sis.score < 70";
    }
}

if (!empty($search)) {
    $where_conditions[] = "(c.nom LIKE ? OR c.prenom LIKE ? OR c.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$sql = "SELECT sis.*, c.nom, c.prenom, c.email, c.categorie
        FROM imagerie_sessions_interactive sis
        JOIN candidates c ON sis.candidate_id = c.id
        $where_clause
        ORDER BY sis.completed_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header card-header-anacim">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-chart-bar me-2"></i>Résultats Imagerie Interactive</h4>
                        <div>
                            <a href="admin_imagerie_interactive.php" class="btn btn-outline-light me-2">
                                <i class="fas fa-cog me-2"></i>Gérer les questions
                            </a>
                            <a href="admin_dashboard.php" class="btn btn-outline-light">
                                <i class="fas fa-arrow-left me-2"></i>Retour
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="GET" class="d-flex">
                                <select name="status" class="form-select me-2">
                                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Tous les résultats</option>
                                    <option value="passed" <?php echo $filter_status === 'passed' ? 'selected' : ''; ?>>Réussi (≥70%)</option>
                                    <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Échoué (<70%)</option>
                                </select>
                                <input type="text" name="search" class="form-control me-2" placeholder="Rechercher un candidat..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="btn-group">
                                <span class="badge bg-success me-2">Réussi: <?php echo count(array_filter($sessions, fn($s) => $s['score'] >= 70)); ?></span>
                                <span class="badge bg-danger">Échoué: <?php echo count(array_filter($sessions, fn($s) => $s['score'] < 70)); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($sessions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucun résultat trouvé</h5>
                            <p class="text-muted">Aucun candidat n'a encore complété l'examen d'imagerie interactive.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Candidat</th>
                                        <th>Catégorie</th>
                                        <th>Score</th>
                                        <th>Réponses correctes</th>
                                        <th>Temps total</th>
                                        <th>Complété le</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions as $session): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($session['nom'] . ' ' . $session['prenom']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($session['email']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($session['categorie']); ?></span>
                                            </td>
                                            <td>
                                                <strong class="<?php echo $session['score'] >= 70 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo number_format($session['score'], 1); ?>%
                                                </strong>
                                            </td>
                                            <td>
                                                <?php echo $session['correct_answers']; ?> / <?php echo $session['total_questions']; ?>
                                            </td>
                                            <td>
                                                <?php echo gmdate("i:s", $session['time_spent']); ?>
                                            </td>
                                            <td>
                                                <?php echo date('d/m/Y H:i', strtotime($session['completed_at'])); ?>
                                            </td>
                                            <td>
                                                <?php if ($session['score'] >= 70): ?>
                                                    <span class="badge bg-success">Réussi</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Échoué</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-primary btn-sm" onclick="viewDetailedResults(<?php echo $session['id']; ?>)">
                                                    <i class="fas fa-eye me-1"></i>Détails
                                                </button>
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
</div>

<!-- Modal for detailed results -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Résultats détaillés</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function viewDetailedResults(sessionId) {
    // Show loading
    document.getElementById('detailsContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Chargement...</p></div>';
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    modal.show();
    
    // Load detailed results via AJAX
    fetch('admin_imagerie_interactive_details.php?session_id=' + sessionId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailsContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('detailsContent').innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des détails.</div>';
        });
}
</script>

<?php include 'includes/footer.php'; ?>
