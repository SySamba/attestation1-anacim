<?php
session_start();
require_once 'config.php';

// Create test_authorizations table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS test_authorizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    test_type ENUM('THI', 'THB', 'PRATIQUE_IMAGERIE') NOT NULL,
    authorized TINYINT(1) DEFAULT 1,
    authorized_by INT NULL,
    authorized_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (authorized_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_candidate_test (candidate_id, test_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-authorize THI/THB for all accepted candidates
$pdo->exec("INSERT IGNORE INTO test_authorizations (candidate_id, test_type, authorized) 
           SELECT c.id, 
                  CASE WHEN c.categorie = '1' THEN 'THI' ELSE 'THB' END,
                  1
           FROM candidates c 
           WHERE c.status = 'accepted'");

// Auto-authorize PRATIQUE_IMAGERIE for candidates with score >= 80%
$pdo->exec("INSERT INTO test_authorizations (candidate_id, test_type, authorized, authorized_by, authorized_at) 
           SELECT c.id, 'PRATIQUE_IMAGERIE', 1, 1, NOW()
           FROM candidates c 
           LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
           WHERE c.status = 'accepted' AND qs.score >= 80
           ON DUPLICATE KEY UPDATE authorized = 1, authorized_by = 1, authorized_at = NOW()");

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$page_title = "ANACIM - Autorisations de Tests";

// Handle authorization actions
if ($_POST) {
    $action = $_POST['action'];
    
    if ($action === 'auto_authorize_imagerie') {
        // Auto-authorize all candidates with score >= 80% for PRATIQUE_IMAGERIE
        $sql = "INSERT INTO test_authorizations (candidate_id, test_type, authorized, authorized_by, authorized_at) 
                SELECT c.id, 'PRATIQUE_IMAGERIE', 1, ?, NOW()
                FROM candidates c 
                LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
                WHERE c.status = 'accepted' AND qs.score >= 80
                ON DUPLICATE KEY UPDATE 
                authorized = 1, authorized_by = ?, authorized_at = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['admin_id'], $_SESSION['admin_id']]);
        
        // Get count of affected rows
        $count_sql = "SELECT COUNT(*) as count FROM candidates c 
                      LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
                      WHERE c.status = 'accepted' AND qs.score >= 80";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute();
        $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $_SESSION['success_message'] = "Autorisation mise à jour pour $count candidat(s) avec ≥80%";
    }
    
    header('Location: admin_test_authorizations.php');
    exit;
}

// Get candidates with their test status and scores
$sql = "SELECT c.*, 
        qs.score as phase1_score,
        qs.epreuve as phase1_test,
        qs.status as phase1_status,
        cpr.score as imagerie_score,
        cpr.status as imagerie_status
        FROM candidates c 
        LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id AND qs.phase = 'phase1' AND qs.status = 'completed'
        LEFT JOIN candidate_phase_results cpr ON c.id = cpr.candidate_id AND cpr.phase = 'phase2' AND cpr.epreuve = 'IMAGERIE'
        WHERE c.status = 'accepted'
        ORDER BY c.nom, c.prenom";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing authorizations for each candidate
$auth_sql = "SELECT c.id as candidate_id, c.nom, c.prenom, c.categorie,
             ta_thi.authorized as thi_authorized,
             ta_thb.authorized as thb_authorized, 
             COALESCE(ta_img.authorized, 0) as imagerie_authorized
             FROM candidates c
             LEFT JOIN test_authorizations ta_thi ON c.id = ta_thi.candidate_id AND ta_thi.test_type = 'THI'
             LEFT JOIN test_authorizations ta_thb ON c.id = ta_thb.candidate_id AND ta_thb.test_type = 'THB'
             LEFT JOIN test_authorizations ta_img ON c.id = ta_img.candidate_id AND ta_img.test_type = 'PRATIQUE_IMAGERIE'
             WHERE c.status = 'accepted'
             ORDER BY c.nom, c.prenom";
$auth_stmt = $pdo->prepare($auth_sql);
$auth_stmt->execute();
$authorizations = $auth_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-anacim">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4><i class="fas fa-key"></i> Gestion des Autorisations de Tests</h4>
                        <p class="mb-0">Autoriser l'accès aux tests pour les candidats</p>
                    </div>
                    <div>
                        <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-tachometer-alt"></i> Tableau de bord
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Règles d'autorisation -->
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle"></i> Règles d'Autorisation</h6>
            <ul class="mb-0">
                <li><strong>THI/THB :</strong> Autorisés par défaut pour tous les candidats acceptés</li>
                <li><strong>Pratique Imagerie :</strong> Autorisée automatiquement pour TOUS les candidats ayant ≥80% en Phase 1</li>
            </ul>
            <div class="mt-3">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="auto_authorize_imagerie">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Autoriser tous les candidats avec ≥80% pour la Pratique Imagerie?')">
                        <i class="fas fa-users-cog"></i> Autoriser tous les candidats éligibles
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Candidates List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users"></i> Candidats - Gestion des Autorisations</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Candidat</th>
                                <th>Catégorie</th>
                                <th>Score Phase 1</th>
                                <th>Statut Pratique Imagerie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Merge candidates with authorization data
                            $candidate_auths = [];
                            foreach ($authorizations as $auth) {
                                $candidate_auths[$auth['candidate_id']] = $auth;
                            }
                            
                            foreach ($candidates as $candidate): 
                                $phase1_score = $candidate['phase1_score'] ?? 0;
                                $can_access_imagerie = $phase1_score >= 80;
                                
                                // Force check with explicit type casting
                                $auth_check_sql = "SELECT CAST(authorized AS UNSIGNED) as auth_status FROM test_authorizations WHERE candidate_id = ? AND test_type = 'PRATIQUE_IMAGERIE' LIMIT 1";
                                $auth_check_stmt = $pdo->prepare($auth_check_sql);
                                $auth_check_stmt->execute([$candidate['id']]);
                                $auth_check_result = $auth_check_stmt->fetch(PDO::FETCH_ASSOC);
                                
                                // Debug output (remove after testing)
                                if ($candidate['nom'] == 'BA' && $candidate['prenom'] == 'Coutaille') {
                                    error_log("DEBUG BA Coutaille: " . print_r($auth_check_result, true));
                                }
                                
                                $imagerie_authorized = ($auth_check_result && intval($auth_check_result['auth_status']) === 1) ? 1 : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($candidate['nom'] . ' ' . $candidate['prenom']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($candidate['email']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $candidate['categorie'] == '1' ? 'Catégorie 1 (THI)' : 'Catégorie 2 (THB)'; ?></span>
                                </td>
                                <td>
                                    <?php if ($phase1_score > 0): ?>
                                        <span class="badge <?php echo $phase1_score >= 80 ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo $phase1_score; ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Non passé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($can_access_imagerie): ?>
                                        <?php if ($imagerie_authorized): ?>
                                            <span class="badge bg-success fs-6">
                                                <i class="fas fa-check-circle"></i> AUTORISÉ - Peut passer l'examen
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning fs-6">
                                                <i class="fas fa-clock"></i> ÉLIGIBLE - En attente d'autorisation
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-danger fs-6">
                                            <i class="fas fa-times-circle"></i> NON AUTORISÉ - Score < 80%
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
