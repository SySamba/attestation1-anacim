<?php
session_start();
$page_title = "ANACIM - Connexion Candidat";

// Redirect if already logged in
if (isset($_SESSION['candidate_logged_in']) && $_SESSION['candidate_logged_in']) {
    header('Location: candidate_dashboard.php');
    exit;
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-header card-header-anacim text-center">
                <h4><i class="fas fa-user"></i> Espace Candidat</h4>
                <p class="mb-0">Connexion pour passer l'examen</p>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="candidate_auth.php" method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="matricule" class="form-label">Matricule</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control" id="matricule" name="matricule" required>
                        </div>
                        <small class="text-muted">Utilisez le matricule fourni lors de votre inscription</small>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-anacim">
                            <i class="fas fa-sign-in-alt"></i> Se Connecter
                        </button>
                    </div>
                </form>

                <hr class="my-3">
                <div class="text-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> Accès réservé aux candidats acceptés
                    </small>
                </div>
                
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
