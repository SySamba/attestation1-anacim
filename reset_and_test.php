<?php
// Script pour réinitialiser complètement la base et tester
$host = 'localhost';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== RESET COMPLET BASE DE DONNÉES ===<br>";
    
    // Supprimer et recréer la base
    $pdo->exec("DROP DATABASE IF EXISTS anacim_certification");
    $pdo->exec("CREATE DATABASE anacim_certification");
    $pdo->exec("USE anacim_certification");
    echo "✓ Base de données recréée<br>";
    
    // Créer admin_users
    $sql = "CREATE TABLE admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "✓ Table admin_users créée<br>";
    
    // Créer candidates
    $sql = "CREATE TABLE candidates (
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
    echo "✓ Table candidates créée<br>";
    
    // Créer candidate_documents avec CNI en premier
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
    echo "✓ Table candidate_documents créée avec CNI<br>";
    
    // Créer admin
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['admin', $password_hash, 'admin@anacim.sn']);
    echo "✓ Admin créé (admin/admin123)<br>";
    
    // Tester en insérant un candidat test
    $sql = "INSERT INTO candidates (prenom, nom, categorie) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['Test', 'Candidat', '1']);
    $candidate_id = $pdo->lastInsertId();
    echo "✓ Candidat test créé (ID: $candidate_id)<br>";
    
    // Tester en insérant un document CNI test
    $sql = "INSERT INTO candidate_documents (candidate_id, document_type, file_name, file_path) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$candidate_id, 'cni', 'test_cni.pdf', 'uploads/test_cni.pdf']);
    echo "✓ Document CNI test créé<br>";
    
    // Vérifier la requête du dashboard
    $sql = "SELECT c.*, 
            COUNT(cd.id) as document_count,
            MAX(CASE WHEN cd.document_type = 'cni' THEN cd.file_name END) as cni_file_name,
            MAX(CASE WHEN cd.document_type = 'cni' THEN cd.id END) as cni_doc_id
            FROM candidates c 
            LEFT JOIN candidate_documents cd ON c.id = cd.candidate_id 
            GROUP BY c.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<br>=== TEST REQUÊTE DASHBOARD ===<br>";
    echo "Candidat: {$result['prenom']} {$result['nom']}<br>";
    echo "Nombre documents: {$result['document_count']}<br>";
    echo "CNI fichier: " . ($result['cni_file_name'] ?: 'AUCUN') . "<br>";
    echo "CNI ID: " . ($result['cni_doc_id'] ?: 'AUCUN') . "<br>";
    
    echo "<br>✅ <strong>BASE RÉINITIALISÉE AVEC SUCCÈS</strong><br>";
    echo "Vous pouvez maintenant tester l'application !<br>";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
}
?>
