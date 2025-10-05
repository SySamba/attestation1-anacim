<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_qcm.php?error=Méthode non autorisée');
    exit;
}

try {
    $question_text = trim($_POST['question_text'] ?? '');
    $question_type = $_POST['question_type'] ?? 'single';
    $phase = $_POST['phase'] ?? 'phase1';
    $epreuve = $_POST['epreuve'] ?? 'THB';
    $category = $_POST['category'] ?? '1';
    $choices = $_POST['choices'] ?? [];
    $is_correct = $_POST['is_correct'] ?? [];
    
    if (empty($question_text)) {
        throw new Exception('Le texte de la question est requis');
    }
    
    if (count($choices) < 2) {
        throw new Exception('Au moins deux choix sont requis');
    }
    
    // Clean up choices
    $choices = array_filter(array_map('trim', $choices));
    if (count($choices) < 2) {
        throw new Exception('Au moins deux choix valides sont requis');
    }
    
    // Validate correctness
    $correct_count = array_sum($is_correct);
    if ($question_type === 'single' && $correct_count !== 1) {
        throw new Exception('Une seule réponse correcte est requise pour les questions à choix unique');
    }
    if ($question_type === 'multiple' && $correct_count < 1) {
        throw new Exception('Au moins une réponse correcte est requise pour les questions à choix multiples');
    }
    
    // Ensure tables exist with new structure
    $pdo->exec("CREATE TABLE IF NOT EXISTS qcm_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_text TEXT NOT NULL,
        question_type ENUM('single','multiple') NOT NULL DEFAULT 'single',
        phase ENUM('phase1', 'phase2') NOT NULL DEFAULT 'phase1',
        epreuve ENUM('THB', 'FBAG', 'PLP', 'FMAG', 'IMAGERIE') NOT NULL DEFAULT 'THB',
        category ENUM('1', '2', '3', '4', '5') NOT NULL DEFAULT '1',
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
    
    $pdo->beginTransaction();
    
    // Insert question with phase and epreuve
    $stmt = $pdo->prepare("INSERT INTO qcm_questions (question_text, question_type, phase, epreuve, category) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$question_text, $question_type, $phase, $epreuve, $category]);
    $question_id = $pdo->lastInsertId();
    
    // Insert choices
    $stmt = $pdo->prepare("INSERT INTO qcm_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
    foreach ($choices as $index => $choice_text) {
        $is_correct_flag = isset($is_correct[$index]) && $is_correct[$index] == '1' ? 1 : 0;
        $stmt->execute([$question_id, $choice_text, $is_correct_flag]);
    }
    
    $pdo->commit();
    
    header('Location: admin_qcm.php?success=1');
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: admin_qcm.php?error=' . urlencode($e->getMessage()));
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: admin_qcm.php?error=Erreur de base de données');
    exit;
}
?>
