<?php
session_start();
require_once 'config/database.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

function redirect_with($params) {
    $qs = http_build_query($params);
    header('Location: admin_qcm.php' . ($qs ? ('?' . $qs) : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with(['error' => 'Méthode non autorisée.']);
}

$question_text = trim($_POST['question_text'] ?? '');
$question_type = $_POST['question_type'] ?? 'single';
$choices       = $_POST['choices'] ?? [];
$is_correct    = $_POST['is_correct'] ?? [];

// Basic validations
if ($question_text === '') {
    redirect_with(['error' => 'La question est obligatoire.']);
}
if (!in_array($question_type, ['single', 'multiple'], true)) {
    redirect_with(['error' => 'Type de question invalide.']);
}

// Normalize choices
$cleanChoices = [];
foreach ($choices as $idx => $txt) {
    $t = trim((string)$txt);
    if ($t !== '') {
        $cleanChoices[] = $t;
    }
}

if (count($cleanChoices) < 2) {
    redirect_with(['error' => 'Ajoutez au moins deux réponses valides.']);
}

// Align correctness flags with cleaned choices
$cleanCorrect = [];
$srcIndex = 0;
foreach ($choices as $idx => $_) {
    if (trim((string)$_) === '') {
        // skip corresponding correctness flag
        $srcIndex++;
        continue;
    }
    $flag = isset($is_correct[$srcIndex]) ? ($is_correct[$srcIndex] ? 1 : 0) : 0;
    $cleanCorrect[] = (int)$flag;
    $srcIndex++;
}

if (count($cleanCorrect) !== count($cleanChoices)) {
    redirect_with(['error' => 'Incohérence dans les réponses/corrections.']);
}

$correctCount = array_sum($cleanCorrect);
if ($question_type === 'single') {
    if ($correctCount !== 1) {
        redirect_with(['error' => 'Pour un choix unique, sélectionnez exactement une bonne réponse.']);
    }
} else { // multiple
    if ($correctCount < 1) {
        redirect_with(['error' => 'Sélectionnez au moins une bonne réponse.']);
    }
}

try {
    // Ensure tables exist (idempotent safety). Run DDL OUTSIDE any transaction to avoid implicit commits.
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

    // Start transaction for the data inserts only
    $pdo->beginTransaction();

    // Insert question
    $insQ = $pdo->prepare("INSERT INTO qcm_questions (question_text, question_type) VALUES (?, ?)");
    $insQ->execute([$question_text, $question_type]);
    $questionId = (int)$pdo->lastInsertId();

    // Insert choices
    $insC = $pdo->prepare("INSERT INTO qcm_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
    foreach ($cleanChoices as $i => $txt) {
        $insC->execute([$questionId, $txt, $cleanCorrect[$i]]);
    }

    $pdo->commit();
    redirect_with(['success' => 1]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (Exception $ignored) {}
    }
    // Avoid exposing internal driver messages directly; provide concise error
    redirect_with(['error' => "Erreur lors de l'enregistrement. Veuillez réessayer."]); 
}
