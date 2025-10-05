<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: admin_login.php');
    exit;
}

$candidate_id = $_GET['id'] ?? null;
if (!$candidate_id) {
    header('Location: admin_dashboard.php');
    exit;
}

// Get candidate information
$sql = "SELECT * FROM candidates WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidate) {
    $_SESSION['error'] = "Candidat non trouvé.";
    header('Location: admin_dashboard.php');
    exit;
}

// Get candidate documents
$doc_sql = "SELECT * FROM candidate_documents WHERE candidate_id = ?";
$doc_stmt = $pdo->prepare($doc_sql);
$doc_stmt->execute([$candidate_id]);
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($documents)) {
    $_SESSION['error'] = "Aucun document trouvé pour ce candidat.";
    header('Location: view_candidate.php?id=' . $candidate_id);
    exit;
}

// Create ZIP file
$zip = new ZipArchive();
$zip_filename = 'dossier_' . $candidate['prenom'] . '_' . $candidate['nom'] . '_' . date('Y-m-d') . '.zip';
$zip_path = sys_get_temp_dir() . '/' . $zip_filename;

if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
    $_SESSION['error'] = "Impossible de créer l'archive ZIP.";
    header('Location: view_candidate.php?id=' . $candidate_id);
    exit;
}

// Add documents to ZIP
foreach ($documents as $doc) {
    if (file_exists($doc['file_path'])) {
        $doc_labels = [
            'cni' => 'CNI',
            'cv' => 'CV',
            'attestation_formation' => 'Attestation_Formation',
            'casier' => 'Casier_Judiciaire',
            'certificat_medical' => 'Certificat_Medical',
            'formation_base' => 'Formation_Base',
            'formation_imagerie' => 'Formation_Imagerie',
            'formulaire_surete' => 'Formulaire_Surete_Remplis'
        ];

        $ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
        $label = ($doc_labels[$doc['document_type']] ?? $doc['document_type']);
        $new_name = $label . '.' . $ext;
        $zip->addFile($doc['file_path'], $new_name);
    }
}

$zip->close();

// Set headers for ZIP download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
header('Content-Length: ' . filesize($zip_path));

// Output ZIP file
readfile($zip_path);

// Clean up temporary file
unlink($zip_path);
exit;
?>
