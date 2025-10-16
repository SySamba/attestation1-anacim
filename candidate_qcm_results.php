<?php
session_start();
require_once 'config.php';

// Check if candidate is logged in
if (!isset($_SESSION['candidate_id'])) {
    header('Location: candidate_login.php');
    exit;
}

$candidate_id = $_SESSION['candidate_id'];
$phase = $_GET['phase'] ?? '';
$epreuve = $_GET['epreuve'] ?? '';

if (!$phase || !$epreuve) {
    header('Location: candidate_dashboard.php');
    exit;
}

// Get candidate info
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidate) {
    header('Location: candidate_login.php');
    exit;
}

// Get completed session
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'completed' ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$candidate_id, $phase, $epreuve]);
$completed_session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$completed_session) {
    $_SESSION['error_message'] = "Aucun test complété trouvé pour cette épreuve.";
    header('Location: candidate_dashboard.php');
    exit;
}

$page_title = "ANACIM - Résultats Test QCM - " . $candidate['prenom'] . " " . $candidate['nom'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .navbar-anacim {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        .card-header-anacim {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
        }
        .bg-anacim {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-anacim">
        <div class="container">
            <a class="navbar-brand text-white" href="#">
                <img src="logo-anacim.png" alt="ANACIM" height="40" class="me-2">
                ANACIM - Résultats QCM
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    <i class="fas fa-user me-2"></i>
                    <?php echo htmlspecialchars($candidate['prenom'] . ' ' . $candidate['nom']); ?>
                </span>
                <a href="candidate_logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Results Display -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h4 class="text-primary mb-0">Résultats Test QCM - <?php echo $epreuve; ?></h4>
                        <small class="text-muted"><?php echo $candidate['prenom'] . ' ' . $candidate['nom']; ?></small>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="candidate_dashboard.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
                        </a>
                        <a href="candidate_logout.php" class="btn btn-outline-danger">
                            <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                        </a>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header card-header-anacim text-center">
                        <h4><i class="fas fa-certificate"></i> Test Terminé</h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h5>Félicitations ! Vous avez terminé le test QCM</h5>
                        <div class="row mt-4">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Score</h6>
                                        <h3 class="text-primary"><?php echo number_format($completed_session['score'], 1); ?>%</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Bonnes Réponses</h6>
                                        <h3 class="text-success"><?php echo $completed_session['correct_answers']; ?>/<?php echo $completed_session['total_questions']; ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Statut</h6>
                                        <h5 class="<?php echo $completed_session['score'] >= 80 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $completed_session['score'] >= 80 ? 'Réussi' : 'Échec'; ?>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-muted">
                                Test terminé le <?php echo date('d/m/Y à H:i', strtotime($completed_session['completed_at'])); ?>
                            </p>
                            <?php if ($completed_session['score'] >= 80): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-trophy me-2"></i>
                                    Félicitations ! Vous avez réussi le test avec un score de <?php echo number_format($completed_session['score'], 1); ?>%.
                                    Votre certificat sera traité prochainement.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Score insuffisant. Un score minimum de 80% est requis pour obtenir la certification.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
