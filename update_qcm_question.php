<?php
session_start();
require_once 'config.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

function redirect_back($id, $params = []) {
    $qs = http_build_query($params);
    header('Location: admin_qcm_edit.php?id=' . (int)$id . ($qs ? ('&' . $qs) : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_qcm.php?error=' . urlencode('Méthode non autorisée.'));
    exit;
}

$question_id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$question_text = trim($_POST['question_text'] ?? '');
$question_type = $_POST['question_type'] ?? 'single';
$choices       = $_POST['choices'] ?? [];
$is_correct    = $_POST['is_correct'] ?? [];

if ($question_id <= 0) {
    header('Location: admin_qcm.php?error=' . urlencode('Question introuvable.'));
    exit;
}

if ($question_text === '') {
    redirect_back($question_id, ['error' => 'La question est obligatoire.']);
}
if (!in_array($question_type, ['single', 'multiple'], true)) {
    redirect_back($question_id, ['error' => 'Type de question invalide.']);
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
    redirect_back($question_id, ['error' => 'Ajoutez au moins deux réponses valides.']);
}

// Align correctness flags with cleaned choices
$cleanCorrect = [];
$srcIndex = 0;
foreach ($choices as $idx => $_) {
    if (trim((string)$_) === '') {
        $srcIndex++;
        continue;
    }
    $flag = isset($is_correct[$srcIndex]) ? ($is_correct[$srcIndex] ? 1 : 0) : 0;
    $cleanCorrect[] = (int)$flag;
    $srcIndex++;
}

if (count($cleanCorrect) !== count($cleanChoices)) {
    redirect_back($question_id, ['error' => 'Incohérence dans les réponses/corrections.']);
}

$correctCount = array_sum($cleanCorrect);
if ($question_type === 'single') {
    if ($correctCount !== 1) {
        redirect_back($question_id, ['error' => 'Pour un choix unique, sélectionnez exactement une bonne réponse.']);
    }
} else { // multiple
    if ($correctCount < 1) {
        redirect_back($question_id, ['error' => 'Sélectionnez au moins une bonne réponse.']);
    }
}

try {
    // Ensure tables exist outside transaction
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

    // Verify question exists
    $check = $pdo->prepare('SELECT id FROM qcm_questions WHERE id = ?');
    $check->execute([$question_id]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        header('Location: admin_qcm.php?error=' . urlencode('Question introuvable.'));
        exit;
    }

    $pdo->beginTransaction();

    // Update question
    $u = $pdo->prepare('UPDATE qcm_questions SET question_text = ?, question_type = ? WHERE id = ?');
    $u->execute([$question_text, $question_type, $question_id]);

    // Replace choices: delete then re-insert to keep it simple
    $pdo->prepare('DELETE FROM qcm_choices WHERE question_id = ?')->execute([$question_id]);

    $ins = $pdo->prepare('INSERT INTO qcm_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)');
    foreach ($cleanChoices as $i => $txt) {
        $ins->execute([$question_id, $txt, $cleanCorrect[$i]]);
    }

    $pdo->commit();
    header('Location: admin_qcm_edit.php?id=' . $question_id . '&success=1');
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (Exception $ignored) {}
    }
    header('Location: admin_qcm_edit.php?id=' . $question_id . '&error=' . urlencode("Erreur lors de l'enregistrement. Veuillez réessayer."));
    exit;
}
