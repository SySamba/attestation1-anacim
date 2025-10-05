<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: candidate_login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$matricule = trim($_POST['matricule'] ?? '');

if (empty($email) || empty($matricule)) {
    $_SESSION['error'] = 'Veuillez remplir tous les champs';
    header('Location: candidate_login.php');
    exit;
}

try {
    // Check if candidate exists and is accepted
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE email = ? AND matricule = ? AND status = 'accepted'");
    $stmt->execute([$email, $matricule]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($candidate) {
        // Set session variables
        $_SESSION['candidate_logged_in'] = true;
        $_SESSION['candidate_id'] = $candidate['id'];
        $_SESSION['candidate_name'] = $candidate['prenom'] . ' ' . $candidate['nom'];
        $_SESSION['candidate_email'] = $candidate['email'];
        $_SESSION['candidate_category'] = $candidate['categorie'];
        
        header('Location: candidate_dashboard.php');
        exit;
    } else {
        $_SESSION['error'] = 'Email ou matricule incorrect, ou candidature non acceptée';
        header('Location: candidate_login.php');
        exit;
    }
    
} catch (PDOException $e) {
    error_log("Database error in candidate_auth.php: " . $e->getMessage());
    $_SESSION['error'] = 'Erreur de connexion à la base de données';
    header('Location: candidate_login.php');
    exit;
}
?>
