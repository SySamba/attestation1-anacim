<?php
// Disable error display to prevent HTML pollution in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clean any output buffer to prevent HTML pollution
if (ob_get_level()) {
    ob_clean();
}

// Set proper headers for AJAX response
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

session_start();

// Log function for debugging
function logDebug($message) {
    error_log("[CANDIDATE_ACTION] " . date('Y-m-d H:i:s') . " - " . $message);
}

logDebug("Script démarré");

try {
    require_once 'config.php';
    logDebug("Config chargé avec succès");
} catch (Exception $e) {
    logDebug("Erreur chargement config: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de configuration: ' . $e->getMessage()]);
    exit;
}

try {
    require_once 'send_email.php';
    logDebug("Send_email chargé avec succès");
} catch (Exception $e) {
    logDebug("Erreur chargement send_email: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de chargement email: ' . $e->getMessage()]);
    exit;
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    logDebug("Session admin non valide");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé - Session invalide']);
    exit;
}

logDebug("Session admin valide - Admin ID: " . ($_SESSION['admin_id'] ?? 'non défini'));

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logDebug("Méthode non POST: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Get JSON input
$raw_input = file_get_contents('php://input');
logDebug("Raw input reçu: " . $raw_input);

$input = json_decode($raw_input, true);

if (!$input) {
    logDebug("Erreur décodage JSON: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides - Erreur JSON: ' . json_last_error_msg()]);
    exit;
}

logDebug("Input décodé: " . print_r($input, true));

$candidate_id = $input['candidate_id'] ?? null;
$action = $input['action'] ?? null;
$rejection_reason = $input['rejection_reason'] ?? null;

logDebug("Paramètres extraits - ID: $candidate_id, Action: $action, Raison: $rejection_reason");

// Validate input
if (!$candidate_id || !$action || !in_array($action, ['accept', 'reject'])) {
    logDebug("Validation échouée - Paramètres invalides");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants ou invalides']);
    exit;
}

if ($action === 'reject' && empty($rejection_reason)) {
    logDebug("Validation échouée - Raison de refus manquante");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Raison du refus requise']);
    exit;
}

logDebug("Validation réussie, début du traitement");

try {
    // Check if candidate exists
    logDebug("Recherche du candidat ID: $candidate_id");
    $stmt = $pdo->prepare("SELECT id, prenom, nom, email, matricule, status FROM candidates WHERE id = ?");
    $stmt->execute([$candidate_id]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidate) {
        logDebug("Candidat non trouvé");
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Candidat non trouvé']);
        exit;
    }
    
    logDebug("Candidat trouvé: " . $candidate['prenom'] . ' ' . $candidate['nom']);
    
    // Vérifier si les colonnes nécessaires existent
    try {
        $check_columns = $pdo->query("SHOW COLUMNS FROM candidates LIKE 'temp_password'");
        if ($check_columns->rowCount() == 0) {
            logDebug("Colonne temp_password manquante, ajout en cours...");
            $pdo->exec("ALTER TABLE candidates ADD COLUMN temp_password VARCHAR(255) NULL");
        }
        
        $check_columns = $pdo->query("SHOW COLUMNS FROM candidates LIKE 'qcm_access'");
        if ($check_columns->rowCount() == 0) {
            logDebug("Colonne qcm_access manquante, ajout en cours...");
            $pdo->exec("ALTER TABLE candidates ADD COLUMN qcm_access TINYINT(1) DEFAULT 0");
        }
    } catch (Exception $e) {
        logDebug("Erreur lors de la vérification des colonnes: " . $e->getMessage());
    }
    
    // Update candidate status
    $new_status = $action === 'accept' ? 'accepted' : 'rejected';
    $admin_id = $_SESSION['admin_id'] ?? null;
    
    logDebug("Mise à jour du statut vers: $new_status");
    
    $update_sql = "UPDATE candidates SET 
                   status = ?, 
                   reviewed_at = NOW(), 
                   reviewed_by = ?, 
                   rejection_reason = ? 
                   WHERE id = ?";
    
    $stmt = $pdo->prepare($update_sql);
    $result = $stmt->execute([
        $new_status,
        $admin_id,
        $action === 'reject' ? $rejection_reason : null,
        $candidate_id
    ]);
    
    if (!$result) {
        logDebug("Erreur lors de la mise à jour du candidat");
        throw new Exception("Échec de la mise à jour du candidat");
    }
    
    logDebug("Candidat mis à jour avec succès");
    
    // Send email notification if email exists
    if ($candidate['email']) {
        logDebug("Envoi d'email à: " . $candidate['email']);
        
        if ($action === 'accept') {
            try {
                // Envoyer email d'acceptation avec identifiants QCM
                $emailSent = sendAcceptanceEmail($candidate);
                if ($emailSent) {
                    logDebug("Email d'acceptation envoyé avec succès");
                } else {
                    logDebug("Échec envoi email d'acceptation");
                }
            } catch (Exception $e) {
                logDebug("Erreur lors de l'envoi de l'email d'acceptation: " . $e->getMessage());
            }
        } else {
            try {
                // Email de refus (simple)
                $subject = "ANACIM - Candidature refusée";
                $message = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
                    <div style='background: #dc2626; color: white; padding: 20px; text-align: center;'>
                        <h2>Candidature Refusée</h2>
                    </div>
                    <div style='padding: 20px;'>
                        <p>Bonjour <strong>" . htmlspecialchars($candidate['prenom'] . ' ' . $candidate['nom']) . "</strong>,</p>
                        <p>Nous regrettons de vous informer que votre candidature pour la certification de sûreté aviation a été refusée.</p>
                        <p><strong>Raison :</strong> " . htmlspecialchars($rejection_reason) . "</p>
                        <p>Vous pouvez soumettre une nouvelle candidature après avoir corrigé les points mentionnés.</p>
                        <p>Cordialement,<br>L'équipe ANACIM</p>
                    </div>
                </body>
                </html>";
                
                $emailSent = sendEmail($candidate['email'], $subject, $message, true);
                if ($emailSent) {
                    logDebug("Email de refus envoyé avec succès");
                } else {
                    logDebug("Échec envoi email de refus");
                }
            } catch (Exception $e) {
                logDebug("Erreur lors de l'envoi de l'email de refus: " . $e->getMessage());
            }
        }
    } else {
        logDebug("Aucun email pour ce candidat, pas d'envoi");
    }
    
    logDebug("Traitement terminé avec succès");
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode([
        'success' => true, 
        'message' => $action === 'accept' 
            ? 'Candidat accepté avec succès' 
            : 'Candidat refusé avec succès',
        'new_status' => $new_status
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    logDebug("Erreur PDO: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    logDebug("Erreur générale: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur système'], JSON_UNESCAPED_UNICODE);
}

// Ensure no additional output
exit;
?>
