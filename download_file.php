<?php
session_start();
require_once 'config.php';

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
$sql = "SELECT cd.*, c.prenom, c.nom FROM candidate_documents cd 
        JOIN candidates c ON cd.candidate_id = c.id 
        WHERE cd.id = ?";
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

// Determine mime type and extension
$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if ($ext === 'pdf') {
    $mime = 'application/pdf';
} elseif ($ext === 'doc') {
    $mime = 'application/msword';
} elseif ($ext === 'docx') {
    $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
}

// Set headers for file download
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $document['prenom'] . '_' . $document['nom'] . '_' . $document['document_type'] . '.' . $ext . '"');
header('Content-Length: ' . filesize($file_path));

// Output file
readfile($file_path);
exit;
?>
