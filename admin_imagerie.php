<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit();
}

// Create tables if they don't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS imagerie_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        candidate_id INT NOT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        status ENUM('in_progress', 'completed') DEFAULT 'in_progress'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS imagerie_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        question_id INT NOT NULL,
        response TEXT NOT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        score DECIMAL(5,2) NULL,
        admin_feedback TEXT NULL,
        scored_by INT NULL,
        scored_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Add columns to candidate_phase_results if they don't exist
    $columns = $pdo->query("SHOW COLUMNS FROM candidate_phase_results")->fetchAll();
    $existing_columns = array_column($columns, 'Field');
    
    if (!in_array('admin_notes', $existing_columns)) {
        $pdo->exec("ALTER TABLE candidate_phase_results ADD COLUMN admin_notes TEXT NULL");
    }
    if (!in_array('scored_by', $existing_columns)) {
        $pdo->exec("ALTER TABLE candidate_phase_results ADD COLUMN scored_by INT NULL");
    }
    if (!in_array('scored_at', $existing_columns)) {
        $pdo->exec("ALTER TABLE candidate_phase_results ADD COLUMN scored_at TIMESTAMP NULL");
    }
} catch (Exception $e) {
    // Silent fail - tables might already exist
}

// Handle scoring submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'score_response') {
        $response_id = $_POST['response_id'];
        $score = $_POST['score'];
        $feedback = $_POST['feedback'];
        $admin_id = $_SESSION['admin_id'] ?? 1;
        
        try {
            $stmt = $pdo->prepare("UPDATE imagerie_responses SET score = ?, admin_feedback = ?, scored_by = ?, scored_at = NOW() WHERE id = ?");
            $stmt->execute([$score, $feedback, $admin_id, $response_id]);
            
            $_SESSION['success'] = "Note attribuée avec succès.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de l'attribution de la note : " . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'finalize_candidate') {
        $candidate_id = $_POST['candidate_id'];
        $final_score = $_POST['final_score'];
        $admin_notes = $_POST['admin_notes'];
        $status = $_POST['status'];
        $admin_id = $_SESSION['admin_id'] ?? 1;
        
        try {
            $stmt = $pdo->prepare("UPDATE candidate_phase_results SET score = ?, status = ?, admin_notes = ?, scored_by = ?, scored_at = NOW() WHERE candidate_id = ? AND phase = 'phase2' AND epreuve = 'IMAGERIE'");
            $stmt->execute([$final_score, $status, $admin_notes, $admin_id, $candidate_id]);
            
            $_SESSION['success'] = "Évaluation finale enregistrée avec succès.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
    
    header('Location: admin_imagerie.php');
    exit();
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query for candidates with imagerie submissions
$where_conditions = ["cpr.phase = 'phase2'", "cpr.epreuve = 'IMAGERIE'"];
$params = [];

if ($filter_status !== 'all') {
    $where_conditions[] = "cpr.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $where_conditions[] = "(c.nom LIKE ? OR c.prenom LIKE ? OR c.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$sql = "SELECT cpr.*, c.nom, c.prenom, c.email, c.categorie,
               is_session.started_at as session_started,
               is_session.completed_at as session_completed
        FROM candidate_phase_results cpr
        JOIN candidates c ON cpr.candidate_id = c.id
        LEFT JOIN imagerie_sessions is_session ON is_session.candidate_id = c.id
        $where_clause
        ORDER BY cpr.completed_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header card-header-anacim">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-camera me-2"></i>Gestion Pratique Imagerie</h4>
                        <a href="admin_dashboard.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-2"></i>Retour
                        </a>
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
                    
                    <!-- Filters -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="GET" class="d-flex">
                                <select name="status" class="form-select me-2">
                                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>En attente de notation</option>
                                    <option value="passed" <?php echo $filter_status === 'passed' ? 'selected' : ''; ?>>Réussi</option>
                                    <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Échoué</option>
                                </select>
                                <input type="text" name="search" class="form-control me-2" placeholder="Rechercher un candidat..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="btn-group">
                                <span class="badge bg-warning me-2">En attente: <?php echo count(array_filter($candidates, fn($c) => $c['status'] === 'pending')); ?></span>
                                <span class="badge bg-success me-2">Réussi: <?php echo count(array_filter($candidates, fn($c) => $c['status'] === 'passed')); ?></span>
                                <span class="badge bg-danger">Échoué: <?php echo count(array_filter($candidates, fn($c) => $c['status'] === 'failed')); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($candidates)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Aucune soumission trouvée</h5>
                            <p class="text-muted">Aucun candidat n'a encore soumis l'épreuve de Pratique Imagerie.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Candidat</th>
                                        <th>Catégorie</th>
                                        <th>Soumis le</th>
                                        <th>Statut</th>
                                        <th>Score</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($candidates as $candidate): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($candidate['nom'] . ' ' . $candidate['prenom']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($candidate['email']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($candidate['categorie']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo $candidate['completed_at'] ? date('d/m/Y H:i', strtotime($candidate['completed_at'])) : '-'; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'pending' => 'warning',
                                                    'passed' => 'success',
                                                    'failed' => 'danger'
                                                ];
                                                $status_text = [
                                                    'pending' => 'En attente',
                                                    'passed' => 'Réussi',
                                                    'failed' => 'Échoué'
                                                ];
                                                ?>
                                                <span class="badge bg-<?php echo $status_class[$candidate['status']]; ?>">
                                                    <?php echo $status_text[$candidate['status']]; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($candidate['status'] !== 'pending'): ?>
                                                    <strong><?php echo number_format($candidate['score'], 1); ?>%</strong>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-primary btn-sm" onclick="viewResponses(<?php echo $candidate['candidate_id']; ?>)">
                                                    <i class="fas fa-eye me-1"></i>Évaluer
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

<!-- Modal for viewing and scoring responses -->
<div class="modal fade" id="responsesModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Évaluation des Réponses</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="responsesContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function viewResponses(candidateId) {
    // Show loading
    document.getElementById('responsesContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Chargement...</p></div>';
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('responsesModal'));
    modal.show();
    
    // Load responses via AJAX
    fetch('admin_imagerie_ajax.php?action=get_responses&candidate_id=' + candidateId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('responsesContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('responsesContent').innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des réponses.</div>';
        });
}

function scoreResponse(responseId) {
    const score = document.getElementById('score_' + responseId).value;
    const feedback = document.getElementById('feedback_' + responseId).value;
    
    if (!score) {
        alert('Veuillez saisir une note.');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'score_response');
    formData.append('response_id', responseId);
    formData.append('score', score);
    formData.append('feedback', feedback);
    
    fetch('admin_imagerie.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        // Reload the responses
        const candidateId = document.getElementById('candidate_id_hidden').value;
        viewResponses(candidateId);
    });
}

function finalizeEvaluation(candidateId) {
    const finalScore = document.getElementById('final_score').value;
    const adminNotes = document.getElementById('admin_notes').value;
    const status = document.getElementById('final_status').value;
    
    if (!finalScore || !status) {
        alert('Veuillez remplir tous les champs obligatoires.');
        return;
    }
    
    if (confirm('Êtes-vous sûr de vouloir finaliser cette évaluation ?')) {
        const formData = new FormData();
        formData.append('action', 'finalize_candidate');
        formData.append('candidate_id', candidateId);
        formData.append('final_score', finalScore);
        formData.append('admin_notes', adminNotes);
        formData.append('status', status);
        
        fetch('admin_imagerie.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(() => {
            location.reload();
        });
    }
}

// Function to open image modal for admin
function openAdminImageModal(imageSrc) {
    document.getElementById('adminModalImage').src = imageSrc;
    var imageModal = new bootstrap.Modal(document.getElementById('adminImageModal'));
    imageModal.show();
}
</script>

<!-- Modal pour agrandir l'image - Interface Admin -->
<div class="modal fade" id="adminImageModal" tabindex="-1" aria-labelledby="adminImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adminImageModalLabel">Image d'analyse - Vue agrandie (Évaluation Admin)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="adminModalImage" src="" class="img-fluid" style="max-width: 100%; height: auto;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
