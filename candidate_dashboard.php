<?php
session_start();
require_once 'config.php';

// Check if candidate is logged in
if (!isset($_SESSION['candidate_logged_in']) || !isset($_SESSION['candidate_id'])) {
    header('Location: candidate_login.php');
    exit;
}

$candidate_id = $_SESSION['candidate_id'];

// Get candidate info
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

// Get candidate's QCM results
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND status = 'completed' ORDER BY id DESC");
$stmt->execute([$candidate_id]);
$phase_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check current phase progress
$phase1_results = [];
$phase2_results = [];
foreach ($phase_results as $result) {
    if ($result['phase'] === 'phase1') {
        $phase1_results[$result['epreuve']] = $result;
    } else {
        $phase2_results[$result['epreuve']] = $result;
    }
}

// Check if candidate has access to Phase 2
$phase1_completed = count($phase1_results) >= 4;
$phase1_all_passed = true;
foreach (['THB', 'FBAG', 'PLP', 'FMAG'] as $epreuve) {
    if (!isset($phase1_results[$epreuve]) || $phase1_results[$epreuve]['status'] !== 'passed') {
        $phase1_all_passed = false;
        break;
    }
}
$phase2_access = $phase1_all_passed;

$page_title = "ANACIM - Espace Candidat - " . $_SESSION['candidate_name'];
include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-anacim">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4><i class="fas fa-user"></i> Espace Candidat</h4>
                        <p class="mb-0">Bienvenue, <?php echo htmlspecialchars($_SESSION['candidate_name']); ?></p>
                    </div>
                    <div>
                        <a href="candidate_logout.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <!-- Candidate Info Card -->
        <div class="card">
            <div class="card-header card-header-anacim">
                <h6><i class="fas fa-id-card"></i> Mes Informations</h6>
            </div>
            <div class="card-body">
                <p><strong>Nom :</strong> <?php echo htmlspecialchars($candidate['nom']); ?></p>
                <p><strong>Prénom :</strong> <?php echo htmlspecialchars($candidate['prenom']); ?></p>
                <p><strong>Email :</strong> <?php echo htmlspecialchars($candidate['email']); ?></p>
                <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($candidate['telephone'] ?? 'Non renseigné'); ?></p>
                <p><strong>Catégorie :</strong> <?php echo htmlspecialchars($candidate['categorie']); ?></p>
                <p><strong>Statut :</strong> 
                    <span class="badge bg-<?php echo $candidate['status'] === 'accepted' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($candidate['status']); ?>
                    </span>
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Phase 1 Tests -->
        <?php if ($candidate['categorie'] == '1'): ?>
        <!-- THI for Category 1 -->
        <div class="card">
            <div class="card-header card-header-anacim">
                <h5><i class="fas fa-camera"></i> Phase 1 - Théorie Imagerie (THI)</h5>
                <small class="text-light">Pour la catégorie 1</small>
            </div>
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <?php 
                        $thi_result = null;
                        foreach ($phase_results as $result) {
                            if ($result['phase'] === 'phase1' && $result['epreuve'] === 'THI') {
                                $thi_result = $result;
                                break;
                            }
                        }
                        $thi_status = $thi_result ? ($thi_result['score'] >= 80 ? 'passed' : 'failed') : 'pending';
                        $thi_score = $thi_result ? $thi_result['score'] : null;
                        
                        // Count attempts for THI
                        $attempts_stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THI' AND status = 'completed'");
                        $attempts_stmt->execute([$candidate_id]);
                        $thi_attempts = $attempts_stmt->fetch(PDO::FETCH_ASSOC)['attempts'];
                        ?>
                        
                        <div class="card h-100 <?php echo $thi_status === 'passed' ? 'border-success' : ($thi_status === 'failed' ? 'border-danger' : 'border-primary'); ?>">
                            <div class="card-body text-center">
                                <i class="fas fa-camera fa-3x mb-3 <?php echo $thi_status === 'passed' ? 'text-success' : ($thi_status === 'failed' ? 'text-danger' : 'text-primary'); ?>"></i>
                                <h5 class="card-title">Théorie Imagerie (THI)</h5>
                                
                                <?php if ($thi_status === 'passed'): ?>
                                    <span class="badge bg-success mb-2">✅ RÉUSSI</span>
                                    <p class="mb-2"><strong><?php echo number_format($thi_score, 1); ?>%</strong></p>
                                    <?php if ($thi_score >= 80): ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-unlock me-2"></i>
                                            <strong>Pratique Imagerie débloquée !</strong>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Score insuffisant pour la Pratique Imagerie (minimum 80%)
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($thi_status === 'failed'): ?>
                                    <span class="badge bg-danger mb-2">❌ TERMINÉ</span>
                                    <p class="mb-2"><strong><?php echo number_format($thi_score, 1); ?>%</strong></p>
                                    <p class="small text-muted">Test complété - Une seule tentative autorisée</p>
                                    <button class="btn btn-secondary btn-sm" disabled>
                                        <i class="fas fa-check"></i> Complété
                                    </button>
                                <?php else: ?>
                                    <?php if ($thi_attempts > 0): ?>
                                        <span class="badge bg-warning mb-2">⚠️ DÉJÀ PASSÉ</span>
                                        <p class="small text-muted">Test déjà effectué</p>
                                        <button class="btn btn-secondary btn-sm" disabled>
                                            <i class="fas fa-check"></i> Complété
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-primary mb-2">🎯 DISPONIBLE</span>
                                        <p class="text-muted mb-3">Test théorique sur l'imagerie de sécurité</p>
                                        <a href="candidate_qcm.php?phase=phase1&epreuve=THI" class="btn btn-anacim btn-lg">
                                            <i class="fas fa-play me-2"></i>Commencer THI
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- THB for Categories 2,3,4,5 -->
        <div class="card">
            <div class="card-header card-header-anacim">
                <h5><i class="fas fa-book"></i> Phase 1 - Théorie de Base (THB)</h5>
                <small class="text-light">Pour les catégories 2, 3, 4, 5</small>
            </div>
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <?php 
                        $thb_result = null;
                        foreach ($phase_results as $result) {
                            if ($result['phase'] === 'phase1' && $result['epreuve'] === 'THB') {
                                $thb_result = $result;
                                break;
                            }
                        }
                        $thb_status = $thb_result ? ($thb_result['score'] >= 80 ? 'passed' : 'failed') : 'pending';
                        $thb_score = $thb_result ? $thb_result['score'] : null;
                        
                        // Count attempts for THB
                        $attempts_stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM qcm_sessions WHERE candidate_id = ? AND phase = 'phase1' AND epreuve = 'THB' AND status = 'completed'");
                        $attempts_stmt->execute([$candidate_id]);
                        $thb_attempts = $attempts_stmt->fetch(PDO::FETCH_ASSOC)['attempts'];
                        ?>
                        
                        <div class="card h-100 <?php echo $thb_status === 'passed' ? 'border-success' : ($thb_status === 'failed' ? 'border-danger' : 'border-primary'); ?>">
                            <div class="card-body text-center">
                                <i class="fas fa-book fa-3x mb-3 <?php echo $thb_status === 'passed' ? 'text-success' : ($thb_status === 'failed' ? 'text-danger' : 'text-primary'); ?>"></i>
                                <h5 class="card-title">Théorie de Base (THB)</h5>
                                
                                <?php if ($thb_status === 'passed'): ?>
                                    <span class="badge bg-success mb-2">✅ RÉUSSI</span>
                                    <p class="mb-2"><strong><?php echo number_format($thb_score, 1); ?>%</strong></p>
                                    <?php if ($thb_score >= 80): ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-unlock me-2"></i>
                                            <strong>Pratique Imagerie débloquée !</strong>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Score insuffisant pour la Pratique Imagerie (minimum 80%)
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($thb_status === 'failed'): ?>
                                    <span class="badge bg-danger mb-2">❌ TERMINÉ</span>
                                    <p class="mb-2"><strong><?php echo number_format($thb_score, 1); ?>%</strong></p>
                                    <p class="small text-muted">Test complété - Une seule tentative autorisée</p>
                                    <button class="btn btn-secondary btn-sm" disabled>
                                        <i class="fas fa-check"></i> Complété
                                    </button>
                                <?php else: ?>
                                    <?php if ($thb_attempts > 0): ?>
                                        <span class="badge bg-warning mb-2">⚠️ DÉJÀ PASSÉ</span>
                                        <p class="small text-muted">Test déjà effectué</p>
                                        <button class="btn btn-secondary btn-sm" disabled>
                                            <i class="fas fa-check"></i> Complété
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-primary mb-2">🎯 DISPONIBLE</span>
                                        <p class="text-muted mb-3">Test théorique de base</p>
                                        <a href="candidate_qcm.php?phase=phase1&epreuve=THB" class="btn btn-anacim btn-lg">
                                            <i class="fas fa-play me-2"></i>Commencer THB
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
                
        <!-- Pratique Imagerie -->
        <?php 
        // Initialize variables to avoid undefined errors
        $thi_score = 0;
        $thi_status = 'not_taken';
        $thb_score = 0;
        $thb_status = 'not_taken';
        
        // Get scores from phase results
        foreach ($phase_results as $result) {
            if ($result['phase'] === 'phase1') {
                if ($result['epreuve'] === 'THI') {
                    $thi_score = $result['score'];
                    $thi_status = $result['score'] >= 80 ? 'passed' : 'failed';
                } elseif ($result['epreuve'] === 'THB') {
                    $thb_score = $result['score'];
                    $thb_status = $result['score'] >= 80 ? 'passed' : 'failed';
                }
            }
        }
        
        // Direct access based on score - no authorization system needed
        if ($candidate['categorie'] == '1') {
            // C1 needs THI with 80%+
            $can_access_imagerie = ($thi_score >= 80);
        } else {
            // Categories 2,3,4,5 need THB with 80%+
            $can_access_imagerie = ($thb_score >= 80);
        }
        
        // Get Pratique Imagerie result
        $imagerie_result = null;
        foreach ($phase_results as $result) {
            if ($result['phase'] === 'phase2' && $result['epreuve'] === 'IMAGERIE') {
                $imagerie_result = $result;
                break;
            }
        }
        $imagerie_status = $imagerie_result ? $imagerie_result['status'] : 'pending';
        $imagerie_score = $imagerie_result ? $imagerie_result['score'] : null;
        ?>
        
        <div class="card">
            <div class="card-header card-header-anacim">
                <h5><i class="fas fa-camera"></i> Pratique Imagerie</h5>
            </div>
            <div class="card-body">
                <?php if (!$can_access_imagerie): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-lock fa-3x mb-3"></i>
                        <h6>Pratique Imagerie Verrouillée</h6>
                        <p>Vous devez d'abord réussir <?php echo $candidate['categorie'] == '1' ? 'THI' : 'THB'; ?> avec un score minimum de 80% pour accéder à la Pratique Imagerie.</p>
                    </div>
                <?php else: ?>
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="card h-100 <?php echo $imagerie_status === 'passed' ? 'border-success' : ($imagerie_status === 'failed' ? 'border-danger' : 'border-primary'); ?>">
                                <div class="card-body text-center">
                                    <i class="fas fa-camera fa-3x mb-3 <?php echo $imagerie_status === 'passed' ? 'text-success' : ($imagerie_status === 'failed' ? 'text-danger' : 'text-primary'); ?>"></i>
                                    <h5 class="card-title">Pratique Imagerie</h5>
                                    
                                    <?php if ($imagerie_status === 'passed'): ?>
                                        <span class="badge bg-success mb-2">✅ RÉUSSI</span>
                                        <p class="mb-2"><strong><?php echo number_format($imagerie_score, 1); ?>%</strong></p>
                                        <div class="alert alert-success">
                                            <i class="fas fa-certificate me-2"></i>
                                            <strong>CERTIFICATION COMPLÈTE !</strong><br>
                                            Votre certificat sera délivré prochainement.
                                        </div>
                                    <?php elseif ($imagerie_status === 'failed'): ?>
                                        <span class="badge bg-danger mb-2">❌ TERMINÉ</span>
                                        <p class="mb-2"><strong><?php echo number_format($imagerie_score, 1); ?>%</strong></p>
                                        <p class="small text-muted">Épreuve terminée - En attente de notation</p>
                                        <button class="btn btn-secondary" disabled>
                                            <i class="fas fa-check"></i> Complété
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-primary mb-2">🎯 DISPONIBLE</span>
                                        <p class="text-muted mb-3">Épreuve pratique d'analyse d'images</p>
                                        <a href="candidate_imagerie_interactive.php" class="btn btn-anacim btn-lg">
                                            <i class="fas fa-play me-2"></i>Commencer la Pratique Imagerie
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
