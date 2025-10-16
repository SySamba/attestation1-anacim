<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>ANACIM - Debug temp_password Issue</h2>";

try {
    // 1. Vérifier la connexion DB
    echo "<h3>1. Connexion Base de Données</h3>";
    echo "✅ Connexion réussie<br>";
    echo "Base: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "<br><br>";
    
    // 2. Vérifier structure table candidates
    echo "<h3>2. Structure Table 'candidates'</h3>";
    $stmt = $pdo->query("SHOW COLUMNS FROM candidates");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    $temp_password_found = false;
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
        
        if ($col['Field'] === 'temp_password') {
            $temp_password_found = true;
        }
    }
    echo "</table><br>";
    
    if ($temp_password_found) {
        echo "✅ Colonne 'temp_password' existe<br><br>";
    } else {
        echo "❌ Colonne 'temp_password' MANQUANTE!<br>";
        echo "Ajout de la colonne...<br>";
        $pdo->exec("ALTER TABLE candidates ADD COLUMN temp_password VARCHAR(255) NULL AFTER rejection_reason");
        echo "✅ Colonne ajoutée<br><br>";
    }
    
    // 3. Test requête UPDATE exacte du code
    echo "<h3>3. Test Requête UPDATE (comme dans send_email.php)</h3>";
    try {
        // Simuler exactement ce qui se passe dans sendAcceptanceEmail
        $test_candidate_id = 1; // ID de test
        $test_password = password_hash('test123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE candidates SET temp_password = ?, qcm_access = 1 WHERE id = ?");
        $result = $stmt->execute([$test_password, $test_candidate_id]);
        
        if ($result) {
            echo "✅ UPDATE temp_password réussi<br>";
        } else {
            echo "❌ UPDATE temp_password échoué<br>";
            print_r($stmt->errorInfo());
        }
    } catch (PDOException $e) {
        echo "❌ Erreur UPDATE: " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "Code erreur: " . $e->getCode() . "<br>";
    }
    
    // 4. Tester le processus complet d'acceptation
    echo "<h3>4. Test Processus Acceptation Complet</h3>";
    
    // Créer un candidat de test
    try {
        $pdo->exec("INSERT IGNORE INTO candidates (id, prenom, nom, email, matricule, categorie, status) 
                   VALUES (9999, 'Test', 'Debug', 'test@debug.com', 'DEBUG001', '3', 'pending')");
        
        // Simuler process_candidate_action.php
        $candidate_id = 9999;
        $new_status = 'accepted';
        $admin_id = 1;
        
        $update_sql = "UPDATE candidates SET 
                       status = ?, 
                       reviewed_at = NOW(), 
                       reviewed_by = ?, 
                       rejection_reason = ? 
                       WHERE id = ?";
        
        $stmt = $pdo->prepare($update_sql);
        $result1 = $stmt->execute([$new_status, $admin_id, null, $candidate_id]);
        
        if ($result1) {
            echo "✅ Étape 1 - Mise à jour statut: OK<br>";
        } else {
            echo "❌ Étape 1 - Mise à jour statut: ÉCHEC<br>";
        }
        
        // Simuler sendAcceptanceEmail
        $tempPassword = 'debug123';
        $stmt2 = $pdo->prepare("UPDATE candidates SET temp_password = ?, qcm_access = 1 WHERE id = ?");
        $result2 = $stmt2->execute([password_hash($tempPassword, PASSWORD_DEFAULT), $candidate_id]);
        
        if ($result2) {
            echo "✅ Étape 2 - Mise à jour temp_password: OK<br>";
        } else {
            echo "❌ Étape 2 - Mise à jour temp_password: ÉCHEC<br>";
            echo "Erreur: " . implode(' | ', $stmt2->errorInfo()) . "<br>";
        }
        
    } catch (PDOException $e) {
        echo "❌ Erreur processus complet: " . htmlspecialchars($e->getMessage()) . "<br>";
    }
    
    // 5. Vérifier les candidats existants
    echo "<h3>5. Candidats Existants</h3>";
    $stmt = $pdo->query("SELECT id, prenom, nom, status, temp_password IS NOT NULL as has_temp_pwd FROM candidates LIMIT 5");
    $candidates = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Nom</th><th>Status</th><th>Has temp_password</th></tr>";
    foreach ($candidates as $candidate) {
        echo "<tr>";
        echo "<td>{$candidate['id']}</td>";
        echo "<td>{$candidate['prenom']} {$candidate['nom']}</td>";
        echo "<td>{$candidate['status']}</td>";
        echo "<td>" . ($candidate['has_temp_pwd'] ? 'Oui' : 'Non') . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    echo "<h3>✅ Diagnostic Terminé</h3>";
    echo "Si vous voyez ce message, la base de données fonctionne correctement.<br>";
    echo "L'erreur pourrait venir d'une différence entre environnements ou d'un cache.<br>";
    
} catch (PDOException $e) {
    echo "<h3>❌ Erreur Critique</h3>";
    echo "Message: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "Code: " . $e->getCode() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
}
?>
