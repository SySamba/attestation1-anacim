<?php
session_start();
require_once 'config/database.php';

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
    $stmt = $pdo->prepare("SELECT id, prenom, nom, email, status FROM candidates WHERE id = ?");
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
        $subject = $action === 'accept' 
            ? "ANACIM - Candidature acceptée" 
            : "ANACIM - Candidature refusée";
        
        if ($action === 'accept') {
            $message = "Bonjour " . $candidate['prenom'] . " " . $candidate['nom'] . ",\n\n";
            $message .= "Nous avons le plaisir de vous informer que votre candidature pour la certification de sûreté aviation a été acceptée.\n\n";
            $message .= "Vous pouvez maintenant passer votre test QCM en ligne en vous connectant sur notre plateforme.\n\n";
            $message .= "Lien pour passer le test: " . $_SERVER['HTTP_HOST'] . "/candidate_qcm.php?token=" . base64_encode($candidate_id) . "\n\n";
            $message .= "Cordialement,\nL'équipe ANACIM";
        } else {
            $message = "Bonjour " . $candidate['prenom'] . " " . $candidate['nom'] . ",\n\n";
            $message .= "Nous regrettons de vous informer que votre candidature pour la certification de sûreté aviation a été refusée.\n\n";
            $message .= "Raison: " . $rejection_reason . "\n\n";
            $message .= "Vous pouvez soumettre une nouvelle candidature après avoir corrigé les points mentionnés.\n\n";
            $message .= "Cordialement,\nL'équipe ANACIM";
        }
        
        $headers = "From: noreply@anacim.sn\r\n";
        $headers .= "Reply-To: admin@anacim.sn\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Note: In production, use a proper email service
        // mail($candidate['email'], $subject, $message, $headers);
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
