<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['prenom', 'nom', 'categorie'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ $field est obligatoire.");
            }
        }
        
        // Validate required files
        if (!isset($_FILES['cni']) || $_FILES['cni']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Le fichier CNI (PDF) est obligatoire.");
        }

        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Insert candidate data
        $sql = "INSERT INTO candidates (prenom, nom, adresse, email, date_naissance, lieu_naissance, date_contrat, type_contrat, matricule, categorie) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['prenom'],
            $_POST['nom'],
            $_POST['adresse'] ?? null,
            $_POST['email'] ?? null,
            $_POST['date_naissance'] ?? null,
            $_POST['lieu_naissance'] ?? null,
            $_POST['date_contrat'] ?? null,
            $_POST['type_contrat'] ?? null,
            $_POST['matricule'] ?? null,
            $_POST['categorie']
        ]);

        $candidate_id = $pdo->lastInsertId();

        // Handle file uploads
        $document_types = ['cni', 'cv', 'attestation_formation', 'casier', 'certificat_medical', 'formation_base', 'formation_imagerie'];
        
        foreach ($document_types as $doc_type) {
            if (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$doc_type];
                
                // Validate file type
                $allowed_types = ['application/pdf'];
                if (!in_array($file['type'], $allowed_types)) {
                    throw new Exception("Seuls les fichiers PDF sont autorisés pour $doc_type.");
                }
                
                // Generate unique filename
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = $candidate_id . '_' . $doc_type . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $new_filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    // Save file info to database
                    $sql = "INSERT INTO candidate_documents (candidate_id, document_type, file_name, file_path) VALUES (?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$candidate_id, $doc_type, $file['name'], $file_path]);
                } else {
                    throw new Exception("Erreur lors de l'upload du fichier $doc_type.");
                }
            }
        }

        $_SESSION['success'] = "Votre dossier a été soumis avec succès. Numéro de référence: " . str_pad($candidate_id, 6, '0', STR_PAD_LEFT);
        header('Location: index.php');
        exit;

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: index.php');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
?>
