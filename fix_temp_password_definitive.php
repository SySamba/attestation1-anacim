<?php
require_once 'config.php';

echo "=== ANACIM - Correction Définitive temp_password ===\n";

try {
    // 1. Vérifier et créer la colonne temp_password si nécessaire
    $stmt = $pdo->query("SHOW COLUMNS FROM candidates LIKE 'temp_password'");
    $temp_password_exists = $stmt->rowCount() > 0;
    
    if (!$temp_password_exists) {
        echo "Ajout de la colonne temp_password...\n";
        $pdo->exec("ALTER TABLE candidates ADD COLUMN temp_password VARCHAR(255) NULL AFTER rejection_reason");
        echo "✅ Colonne temp_password ajoutée\n";
    } else {
        echo "✅ Colonne temp_password existe déjà\n";
    }
    
    // 2. Vérifier et créer la colonne qcm_access si nécessaire
    $stmt = $pdo->query("SHOW COLUMNS FROM candidates LIKE 'qcm_access'");
    $qcm_access_exists = $stmt->rowCount() > 0;
    
    if (!$qcm_access_exists) {
        echo "Ajout de la colonne qcm_access...\n";
        $pdo->exec("ALTER TABLE candidates ADD COLUMN qcm_access BOOLEAN DEFAULT 0 AFTER temp_password");
        echo "✅ Colonne qcm_access ajoutée\n";
    } else {
        echo "✅ Colonne qcm_access existe déjà\n";
    }
    
    // 3. Test complet du processus d'acceptation
    echo "\nTest du processus d'acceptation...\n";
    
    // Créer ou mettre à jour un candidat de test
    $pdo->exec("INSERT INTO candidates (id, prenom, nom, email, matricule, categorie, status) 
               VALUES (99999, 'Test', 'Fix', 'test@fix.com', 'FIX001', '3', 'pending')
               ON DUPLICATE KEY UPDATE status = 'pending'");
    
    // Test 1: Mise à jour du statut (process_candidate_action.php)
    $update_sql = "UPDATE candidates SET 
                   status = 'accepted', 
                   reviewed_at = NOW(), 
                   reviewed_by = 1, 
                   rejection_reason = NULL 
                   WHERE id = 99999";
    
    $stmt = $pdo->prepare($update_sql);
    $result1 = $stmt->execute();
    
    if ($result1) {
        echo "✅ Test 1 - Mise à jour statut: RÉUSSI\n";
    } else {
        echo "❌ Test 1 - Mise à jour statut: ÉCHEC\n";
        print_r($stmt->errorInfo());
    }
    
    // Test 2: Mise à jour temp_password (sendAcceptanceEmail)
    $tempPassword = password_hash('test123', PASSWORD_DEFAULT);
    $stmt2 = $pdo->prepare("UPDATE candidates SET temp_password = ?, qcm_access = 1 WHERE id = 99999");
    $result2 = $stmt2->execute([$tempPassword]);
    
    if ($result2) {
        echo "✅ Test 2 - Mise à jour temp_password: RÉUSSI\n";
    } else {
        echo "❌ Test 2 - Mise à jour temp_password: ÉCHEC\n";
        print_r($stmt2->errorInfo());
    }
    
    // 4. Vérification finale
    $stmt = $pdo->prepare("SELECT status, temp_password IS NOT NULL as has_temp_pwd, qcm_access FROM candidates WHERE id = 99999");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        echo "\nVérification finale:\n";
        echo "- Status: {$result['status']}\n";
        echo "- Temp password défini: " . ($result['has_temp_pwd'] ? 'OUI' : 'NON') . "\n";
        echo "- QCM access: " . ($result['qcm_access'] ? 'OUI' : 'NON') . "\n";
    }
    
    // 5. Nettoyer le candidat de test
    $pdo->exec("DELETE FROM candidates WHERE id = 99999");
    
    echo "\n✅ CORRECTION TERMINÉE AVEC SUCCÈS\n";
    echo "Le système d'acceptation des candidats devrait maintenant fonctionner correctement.\n";
    echo "\nVous pouvez maintenant:\n";
    echo "1. Aller sur admin_dashboard.php\n";
    echo "2. Accepter un candidat\n";
    echo "3. Le processus devrait fonctionner sans erreur temp_password\n";
    
} catch (PDOException $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    
    // Si l'erreur concerne toujours temp_password, forcer la création
    if (strpos($e->getMessage(), 'temp_password') !== false) {
        echo "\nTentative de correction forcée...\n";
        try {
            $pdo->exec("ALTER TABLE candidates ADD COLUMN temp_password VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE candidates ADD COLUMN qcm_access BOOLEAN DEFAULT 0");
            echo "✅ Colonnes ajoutées avec succès\n";
        } catch (PDOException $e2) {
            echo "❌ Impossible d'ajouter les colonnes: " . $e2->getMessage() . "\n";
        }
    }
}
?>
