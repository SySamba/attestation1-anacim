<?php
session_start();
require_once 'config.php';
require_once 'send_email.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$candidate_id = $input['candidate_id'] ?? null;
$action = $input['action'] ?? null;
$rejection_reason = $input['rejection_reason'] ?? null;

// Validate input
if (!$candidate_id || !$action || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants ou invalides']);
    exit;
}

if ($action === 'reject' && empty($rejection_reason)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Raison du refus requise']);
    exit;
}

try {
    // Check if candidate exists
    $stmt = $pdo->prepare("SELECT id, prenom, nom, email, matricule, status FROM candidates WHERE id = ?");
    $stmt->execute([$candidate_id]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidate) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Candidat non trouvé']);
        exit;
    }
    
    // Update candidate status
    $new_status = $action === 'accept' ? 'accepted' : 'rejected';
    $admin_id = $_SESSION['admin_id'] ?? null;
    
    $update_sql = "UPDATE candidates SET 
                   status = ?, 
                   reviewed_at = NOW(), 
                   reviewed_by = ?, 
                   rejection_reason = ? 
                   WHERE id = ?";
    
    $stmt = $pdo->prepare($update_sql);
    $stmt->execute([
        $new_status,
        $admin_id,
        $action === 'reject' ? $rejection_reason : null,
        $candidate_id
    ]);
    
    // Send email notification if email exists
    if ($candidate['email']) {
        if ($action === 'accept') {
            // Envoyer email d'acceptation avec identifiants QCM
            $emailSent = sendAcceptanceEmail($candidate);
            if (!$emailSent) {
                error_log("Erreur envoi email d'acceptation pour candidat ID: " . $candidate_id);
            }
        } else {
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
            
            sendEmail($candidate['email'], $subject, $message, true);
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $action === 'accept' 
            ? 'Candidat accepté avec succès' 
            : 'Candidat refusé avec succès',
        'new_status' => $new_status
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?>
