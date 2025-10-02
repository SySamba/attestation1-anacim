<?php
session_start();
require_once 'config/database.php';

// Check if candidate is logged in
if (!isset($_SESSION['candidate_logged_in']) || !$_SESSION['candidate_logged_in']) {
    header('Location: candidate_login.php');
    exit;
}

$candidate_id = $_SESSION['candidate_id'];

// Get candidate info
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if candidate has a QCM session
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$candidate_id]);
$qcm_session = $stmt->fetch(PDO::FETCH_ASSOC);

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
                <p><strong>Matricule :</strong> <?php echo htmlspecialchars($candidate['matricule']); ?></p>
                <p><strong>Catégorie :</strong> 
                    <span class="badge bg-primary">Catégorie <?php echo $candidate['categorie']; ?></span>
                </p>
                <p><strong>Statut :</strong> 
                    <span class="badge bg-success">Accepté</span>
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- QCM Test Card -->
        <div class="card">
            <div class="card-header card-header-anacim">
                <h5><i class="fas fa-clipboard-list"></i> Test QCM - Certification Sûreté Aviation</h5>
            </div>
            <div class="card-body">
                <?php if (!$qcm_session): ?>
                    <!-- No test taken yet -->
                    <div class="text-center">
                        <i class="fas fa-clipboard-list fa-4x text-primary mb-3"></i>
                        <h5>Test QCM Disponible</h5>
                        <p class="text-muted">Vous pouvez maintenant passer votre test de certification.</p>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Informations importantes :</h6>
                            <ul class="mb-0 text-start">
                                <li>Durée du test : <strong>60 minutes</strong></li>
                                <li>Score minimum requis : <strong>80%</strong></li>
                                <li>Une seule tentative autorisée</li>
                                <li>Le test ne peut pas être mis en pause</li>
                            </ul>
                        </div>
                        
                        <a href="candidate_qcm.php?token=<?php echo base64_encode($candidate_id); ?>" 
                           class="btn btn-anacim btn-lg">
                            <i class="fas fa-play me-2"></i>Commencer le Test
                        </a>
                    </div>
                    
                <?php elseif ($qcm_session['status'] === 'in_progress'): ?>
                    <!-- Test in progress -->
                    <div class="text-center">
                        <i class="fas fa-clock fa-4x text-warning mb-3"></i>
                        <h5>Test en Cours</h5>
                        <p class="text-muted">Vous avez un test en cours. Cliquez pour continuer.</p>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Attention :</strong> Votre test a été commencé le 
                            <?php echo date('d/m/Y à H:i', strtotime($qcm_session['started_at'])); ?>.
                            Le temps continue de s'écouler.
                        </div>
                        
                        <a href="candidate_qcm.php?token=<?php echo base64_encode($candidate_id); ?>" 
                           class="btn btn-warning btn-lg">
                            <i class="fas fa-play me-2"></i>Continuer le Test
                        </a>
                    </div>
                    
                <?php elseif ($qcm_session['status'] === 'completed'): ?>
                    <!-- Test completed -->
                    <div class="text-center">
                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                        <h5>Test Terminé</h5>
                        <p class="text-muted">Vous avez terminé votre test de certification.</p>
                        
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Score Obtenu</h6>
                                        <h3 class="<?php echo $qcm_session['score'] >= 80 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo number_format($qcm_session['score'], 1); ?>%
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Bonnes Réponses</h6>
                                        <h3 class="text-info">
                                            <?php echo $qcm_session['correct_answers']; ?>/<?php echo $qcm_session['total_questions']; ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Résultat</h6>
                                        <h5 class="<?php echo $qcm_session['score'] >= 80 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $qcm_session['score'] >= 80 ? 'RÉUSSI' : 'ÉCHEC'; ?>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($qcm_session['score'] >= 80): ?>
                            <div class="alert alert-success mt-4">
                                <i class="fas fa-trophy me-2"></i>
                                <strong>Félicitations !</strong> Vous avez réussi le test avec un score de <?php echo number_format($qcm_session['score'], 1); ?>%.
                                Votre certificat sera traité prochainement par l'administration.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mt-4">
                                <i class="fas fa-times-circle me-2"></i>
                                <strong>Score insuffisant.</strong> Un score minimum de 80% est requis pour obtenir la certification.
                                Veuillez contacter l'administration pour plus d'informations.
                            </div>
                        <?php endif; ?>
                        
                        <p class="text-muted mt-3">
                            Test terminé le <?php echo date('d/m/Y à H:i', strtotime($qcm_session['completed_at'])); ?>
                        </p>
                        
                        <a href="candidate_qcm.php?token=<?php echo base64_encode($candidate_id); ?>" 
                           class="btn btn-outline-primary">
                            <i class="fas fa-eye me-2"></i>Voir les Résultats Détaillés
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
