<?php
require_once 'config.php';

echo "=== Vérification structure base de données ===\n\n";

try {
    // Vérifier la connexion
    echo "✅ Connexion à la base de données: OK\n";
    echo "Base de données: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "\n\n";
    
    // Vérifier si la table candidates existe
    $tables = $pdo->query("SHOW TABLES LIKE 'candidates'")->fetchAll();
    if (empty($tables)) {
        echo "❌ La table 'candidates' n'existe pas!\n";
        exit;
    }
    
    echo "✅ Table 'candidates' existe\n\n";
    
    // Afficher la structure complète de la table candidates
    echo "=== Structure de la table 'candidates' ===\n";
    $stmt = $pdo->query("DESCRIBE candidates");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo sprintf("%-20s %-15s %-8s %-10s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key'],
            $column['Default'] ?? 'NULL'
        );
    }
    
    // Vérifier spécifiquement temp_password
    $temp_password_exists = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'temp_password') {
            $temp_password_exists = true;
            echo "\n✅ Colonne 'temp_password' trouvée: {$column['Type']}, NULL: {$column['Null']}\n";
            break;
        }
    }
    
    if (!$temp_password_exists) {
        echo "\n❌ Colonne 'temp_password' MANQUANTE!\n";
        echo "Ajout de la colonne...\n";
        $pdo->exec("ALTER TABLE candidates ADD COLUMN temp_password VARCHAR(255) NULL");
        echo "✅ Colonne 'temp_password' ajoutée\n";
    }
    
    // Tester une requête UPDATE simple
    echo "\n=== Test requête UPDATE ===\n";
    try {
        $test_sql = "UPDATE candidates SET temp_password = ? WHERE id = 999999";
        $stmt = $pdo->prepare($test_sql);
        $stmt->execute(['test']);
        echo "✅ Requête UPDATE temp_password: OK\n";
    } catch (PDOException $e) {
        echo "❌ Erreur UPDATE temp_password: " . $e->getMessage() . "\n";
    }
    
    // Compter les candidats
    $count = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
    echo "\nNombre total de candidats: $count\n";
    
    // Afficher quelques candidats pour test
    $stmt = $pdo->query("SELECT id, prenom, nom, status FROM candidates LIMIT 3");
    $candidates = $stmt->fetchAll();
    
    echo "\nExemples de candidats:\n";
    foreach ($candidates as $candidate) {
        echo "- ID: {$candidate['id']}, {$candidate['prenom']} {$candidate['nom']}, Status: {$candidate['status']}\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Erreur PDO: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
?>
