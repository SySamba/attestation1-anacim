<?php
session_start();
require_once 'config.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_qcm.php?error=' . urlencode('Méthode non autorisée.'));
    exit;
}

$question_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($question_id <= 0) {
    header('Location: admin_qcm.php?error=' . urlencode('Question introuvable.'));
    exit;
}

try {
    // Ensure tables exist (safety)
    $pdo->exec("CREATE TABLE IF NOT EXISTS qcm_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_text TEXT NOT NULL,
        question_type ENUM('single','multiple') NOT NULL DEFAULT 'single',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS qcm_choices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT NOT NULL,
        choice_text TEXT NOT NULL,
        is_correct TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (question_id) REFERENCES qcm_questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Delete question (choices will cascade)
    $del = $pdo->prepare('DELETE FROM qcm_questions WHERE id = ?');
    $del->execute([$question_id]);

    header('Location: admin_qcm.php?success=1');
    exit;
} catch (Exception $e) {
    header('Location: admin_qcm.php?error=' . urlencode("Erreur lors de la suppression. Veuillez réessayer."));
    exit;
}
