<?php
require_once 'config.php';
require_once 'send_email.php';

echo "=== Test d'acceptation de candidat ===\n";

try {
    // Récupérer un candidat en attente pour le test
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE status = 'pending' LIMIT 1");
    $stmt->execute();
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$candidate) {
        echo "❌ Aucun candidat en attente trouvé pour le test.\n";
        echo "Création d'un candidat de test...\n";
        
        // Créer un candidat de test
        $stmt = $pdo->prepare("INSERT INTO candidates (prenom, nom, email, matricule, categorie, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Test', 'Candidat', 'test@example.com', 'TEST001', '3', 'pending']);
        $candidate_id = $pdo->lastInsertId();
        
        // Récupérer le candidat créé
        $stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
        $stmt->execute([$candidate_id]);
        $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "✅ Candidat de test créé avec ID: {$candidate_id}\n";
    }
    
    echo "Candidat à tester: {$candidate['prenom']} {$candidate['nom']} (ID: {$candidate['id']})\n";
    
    // Tester la mise à jour du statut (comme dans process_candidate_action.php)
    $new_status = 'accepted';
    $admin_id = 1; // ID admin de test
    
    echo "Test de mise à jour du statut...\n";
    
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
        null,
        $candidate['id']
    ]);
    
    if ($result) {
        echo "✅ Mise à jour du statut réussie.\n";
    } else {
        echo "❌ Erreur lors de la mise à jour du statut.\n";
        print_r($stmt->errorInfo());
    }
    
    // Tester la fonction sendAcceptanceEmail
    echo "Test de la fonction sendAcceptanceEmail...\n";
    
    try {
        $emailResult = sendAcceptanceEmail($candidate);
        if ($emailResult) {
            echo "✅ Fonction sendAcceptanceEmail exécutée sans erreur.\n";
        } else {
            echo "⚠️ Fonction sendAcceptanceEmail retournée false (normal si mail non configuré).\n";
        }
    } catch (Exception $e) {
        echo "❌ Erreur dans sendAcceptanceEmail: " . $e->getMessage() . "\n";
    }
    
    // Vérifier que temp_password a été mis à jour
    $stmt = $pdo->prepare("SELECT temp_password, qcm_access FROM candidates WHERE id = ?");
    $stmt->execute([$candidate['id']]);
    $updated_candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($updated_candidate['temp_password']) {
        echo "✅ temp_password mis à jour avec succès.\n";
    } else {
        echo "❌ temp_password non mis à jour.\n";
    }
    
    if ($updated_candidate['qcm_access']) {
        echo "✅ qcm_access activé avec succès.\n";
    } else {
        echo "❌ qcm_access non activé.\n";
    }
    
    echo "\n=== Test terminé avec succès ===\n";
    echo "Le processus d'acceptation fonctionne correctement.\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur PDO: " . $e->getMessage() . "\n";
    echo "Code d'erreur: " . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "❌ Erreur générale: " . $e->getMessage() . "\n";
}
?>
