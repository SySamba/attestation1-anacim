<?php
// Script pour créer/mettre à jour la base de données
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connexion sans spécifier de base de données
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Configuration Base de Données ANACIM ===\n";
    
    // Créer la base de données
    $pdo->exec("CREATE DATABASE IF NOT EXISTS anacim_certification");
    echo "✓ Base de données 'anacim_certification' créée\n";
    
    // Utiliser la base de données
    $pdo->exec("USE anacim_certification");
    
    // Créer la table admin_users
    $sql = "CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "✓ Table 'admin_users' créée\n";
    
    // Créer la table candidates
    $sql = "CREATE TABLE IF NOT EXISTS candidates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prenom VARCHAR(100) NOT NULL,
        nom VARCHAR(100) NOT NULL,
        adresse TEXT,
        email VARCHAR(100),
        date_naissance DATE,
        lieu_naissance VARCHAR(100),
        date_contrat DATE,
        type_contrat VARCHAR(50),
        matricule VARCHAR(50),
        categorie ENUM('1', '2', '3', '4', '5') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "✓ Table 'candidates' créée\n";
    
    // Supprimer l'ancienne table candidate_documents si elle existe
    $pdo->exec("DROP TABLE IF EXISTS candidate_documents");
    echo "✓ Ancienne table 'candidate_documents' supprimée\n";
    
    // Créer la nouvelle table candidate_documents avec CNI
    $sql = "CREATE TABLE candidate_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        candidate_id INT NOT NULL,
        document_type ENUM('cni', 'cv', 'attestation_formation', 'casier', 'certificat_medical', 'formation_base', 'formation_imagerie') NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "✓ Table 'candidate_documents' créée avec support CNI\n";
    
    // Créer l'utilisateur admin
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE password = VALUES(password)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['admin', $password_hash, 'admin@anacim.sn']);
    echo "✓ Utilisateur admin créé/mis à jour\n";
    
    echo "\n=== Configuration terminée avec succès ===\n";
    echo "Vous pouvez maintenant utiliser l'application:\n";
    echo "- Application: http://localhost/Anacim-formation/\n";
    echo "- Administration: http://localhost/Anacim-formation/admin_login.php\n";
    echo "- Login: admin / admin123\n";
    
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
