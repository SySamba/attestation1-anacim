<?php
require_once 'config.php';

echo "=== ANACIM - Correction de la colonne temp_password ===\n";

try {
    // Vérifier si la colonne temp_password existe
    $stmt = $pdo->query("DESCRIBE candidates");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('temp_password', $columns)) {
        echo "✅ La colonne 'temp_password' existe déjà dans la table 'candidates'.\n";
    } else {
        echo "❌ La colonne 'temp_password' n'existe pas. Ajout en cours...\n";
        
        // Ajouter la colonne temp_password
        $pdo->exec("ALTER TABLE candidates ADD COLUMN temp_password VARCHAR(255) NULL AFTER rejection_reason");
        echo "✅ Colonne 'temp_password' ajoutée avec succès.\n";
    }
    
    // Vérifier si la colonne qcm_access existe
    if (in_array('qcm_access', $columns)) {
        echo "✅ La colonne 'qcm_access' existe déjà dans la table 'candidates'.\n";
    } else {
        echo "❌ La colonne 'qcm_access' n'existe pas. Ajout en cours...\n";
        
        // Ajouter la colonne qcm_access
        $pdo->exec("ALTER TABLE candidates ADD COLUMN qcm_access BOOLEAN DEFAULT 0 AFTER temp_password");
        echo "✅ Colonne 'qcm_access' ajoutée avec succès.\n";
    }
    
    echo "\n=== Structure de la table 'candidates' mise à jour ===\n";
    
    // Afficher la structure finale
    $stmt = $pdo->query("DESCRIBE candidates");
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($structure as $column) {
        echo "- {$column['Field']} ({$column['Type']}) " . 
             ($column['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . 
             ($column['Default'] ? " DEFAULT {$column['Default']}" : '') . "\n";
    }
    
    echo "\n✅ Correction terminée avec succès !\n";
    echo "Vous pouvez maintenant accepter des candidats sans erreur.\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
