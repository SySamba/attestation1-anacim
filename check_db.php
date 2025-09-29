<?php
require_once 'config/database.php';

try {
    // Vérifier la structure de la table candidate_documents
    $sql = "DESCRIBE candidate_documents";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Structure de la table candidate_documents:\n";
    foreach ($structure as $column) {
        echo $column['Field'] . " - " . $column['Type'] . "\n";
    }
    
    echo "\n";
    
    // Vérifier les types de documents autorisés
    $sql = "SHOW COLUMNS FROM candidate_documents LIKE 'document_type'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $column_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Types de documents autorisés: " . $column_info['Type'] . "\n\n";
    
    // Vérifier tous les documents
    $sql = "SELECT * FROM candidate_documents";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Documents dans la base:\n";
    foreach ($docs as $doc) {
        echo "ID: {$doc['id']}, Candidat: {$doc['candidate_id']}, Type: {$doc['document_type']}, Fichier: {$doc['file_name']}\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
