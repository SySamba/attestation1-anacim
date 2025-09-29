<?php
session_start();
$page_title = "ANACIM - Connexion Administration";

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header('Location: admin_dashboard.php');
    exit;
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow">
            <div class="card-header card-header-anacim text-center">
                <h4><i class="fas fa-user-shield"></i> Administration</h4>
                <p class="mb-0">Connexion Sécurisée</p>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="admin_auth.php" method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
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
                        <i class="fas fa-info-circle"></i> Accès réservé aux administrateurs ANACIM
                    </small>
                </div>
                
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
        <!--
        <div class="card mt-3 bg-light">
            <div class="card-body text-center">
                <small class="text-muted">
                    <strong>Compte de test:</strong><br>
                    Utilisateur: admin<br>
                    Mot de passe: admin123
                </small>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
