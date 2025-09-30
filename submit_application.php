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
        // Added 'formulaire_surete' which can be .doc, .docx or .pdf
        $document_types = ['cni', 'cv', 'attestation_formation', 'casier', 'certificat_medical', 'formation_base', 'formation_imagerie', 'formulaire_surete'];
        
        foreach ($document_types as $doc_type) {
            if (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$doc_type];
                
                // Validate file type by extension and MIME
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $mime_type = $file['type'];

                if ($doc_type === 'formulaire_surete') {
                    $allowed_extensions = ['pdf', 'doc', 'docx'];
                    $allowed_mimes = [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    ];
                    if (!in_array($file_extension, $allowed_extensions) || !in_array($mime_type, $allowed_mimes)) {
                        throw new Exception("Le fichier pour 'Formulaire Sûreté' doit être en PDF ou Word (.doc/.docx).");
                    }
                } else {
                    // All other documents must be PDF
                    if ($file_extension !== 'pdf' || $mime_type !== 'application/pdf') {
                        throw new Exception("Seuls les fichiers PDF sont autorisés pour $doc_type.");
                    }
                }
                
                // Generate unique filename
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

        // Send confirmation email to candidate (non-blocking if it fails)
        if (!empty($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $to = $_POST['email'];
            $reference = str_pad($candidate_id, 6, '0', STR_PAD_LEFT);
            $subject = 'Accusé de réception - Dossier de certification (' . $reference . ')';

            // Build a basic summary of received documents
            $doc_labels = [
                'cni' => 'CNI (Carte Nationale d\'Identité)',
                'cv' => 'CV',
                'attestation_formation' => 'Attestation de Formation Périodique',
                'casier' => 'Casier Judiciaire',
                'certificat_medical' => 'Certificat Médical',
                'formation_base' => 'Formation de Base',
                'formation_imagerie' => 'Formation Imagerie',
                'formulaire_surete' => 'Formulaire Sûreté (rempli)'
            ];
            $received_list = '';
            foreach ($document_types as $doc_type) {
                if (isset($_FILES[$doc_type]) && $_FILES[$doc_type]['error'] === UPLOAD_ERR_OK) {
                    $received_list .= '<li>' . ($doc_labels[$doc_type] ?? $doc_type) . '</li>';
                }
            }
            if ($received_list) {
                $received_list = '<ul style="margin:8px 0 0 20px;">' . $received_list . '</ul>';
            }

            $html = '<div style="font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#111;">'
                . '<p>Bonjour ' . htmlspecialchars($_POST['prenom'] . ' ' . $_POST['nom']) . ',</p>'
                . '<p>Nous vous confirmons la bonne réception de votre dossier de certification du personnel de sûreté.</p>'
                . '<p><strong>Référence de votre dossier:</strong> <span style="background:#f6df33;padding:2px 6px;border-radius:4px;">' . $reference . '</span></p>'
                . '<p><strong>Catégorie:</strong> ' . htmlspecialchars($_POST['categorie']) . '</p>'
                . ($received_list ? '<p><strong>Documents reçus:</strong>' . $received_list . '</p>' : '')
                . '<p>Notre équipe va étudier votre dossier. Vous serez recontacté(e) par email en cas d\'information complémentaire ou pour la suite de la procédure.</p>'
                . '<p>Cordialement,<br>Service Certification – ANACIM</p>'
                . '<hr style="border:none;border-top:1px solid #eee">'
                . '<p style="font-size:12px;color:#555;">Ceci est un message automatique, merci de ne pas y répondre. Pour toute question, veuillez contacter le service compétent.</p>'
                . '</div>';

            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: ANACIM Certification <no-reply@anacim.sn>\r\n";
            $headers .= "Reply-To: no-reply@anacim.sn\r\n";

            // Attempt to send email; ignore result to avoid blocking the flow
            @mail($to, $subject, $html, $headers);
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
