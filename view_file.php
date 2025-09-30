<?php
session_start();
require_once 'config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$document_id = $_GET['id'] ?? null;
if (!$document_id) {
    header('Location: admin_dashboard.php');
    exit;
}

// Get document information
$sql = "SELECT * FROM candidate_documents WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$document_id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    $_SESSION['error'] = "Document non trouvé.";
    header('Location: admin_dashboard.php');
    exit;
}

$file_path = $document['file_path'];
if (!file_exists($file_path)) {
    $_SESSION['error'] = "Fichier non trouvé sur le serveur.";
    header('Location: admin_dashboard.php');
    exit;
}

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

if ($ext === 'pdf') {
    // View PDFs inline
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $document['file_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
} else {
    // Download non-PDFs (e.g., Word)
    $mime = 'application/octet-stream';
    if ($ext === 'doc') {
        $mime = 'application/msword';
    } elseif ($ext === 'docx') {
        $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $document['file_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
}

// Output file
readfile($file_path);
exit;
?>
