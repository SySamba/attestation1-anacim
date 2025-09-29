<?php
require_once 'config/database.php';

// Test direct connection and query
echo "Testing database connection...\n";

try {
    // Check if database exists and is accessible
    $sql = "SELECT DATABASE()";
    $stmt = $pdo->query($sql);
    $db = $stmt->fetchColumn();
    echo "Connected to database: $db\n";
    
    // Check candidate_documents table
    $sql = "SELECT COUNT(*) FROM candidate_documents";
    $stmt = $pdo->query($sql);
    $count = $stmt->fetchColumn();
    echo "Total documents: $count\n";
    
    // Check CNI documents specifically
    $sql = "SELECT COUNT(*) FROM candidate_documents WHERE document_type = 'cni'";
    $stmt = $pdo->query($sql);
    $cni_count = $stmt->fetchColumn();
    echo "CNI documents: $cni_count\n";
    
    // List all document types
    $sql = "SELECT DISTINCT document_type FROM candidate_documents";
    $stmt = $pdo->query($sql);
    $types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Document types found: " . implode(', ', $types) . "\n";
    
    // Show all CNI records
    $sql = "SELECT cd.*, c.prenom, c.nom FROM candidate_documents cd JOIN candidates c ON cd.candidate_id = c.id WHERE cd.document_type = 'cni'";
    $stmt = $pdo->query($sql);
    $cni_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nCNI Documents found:\n";
    foreach ($cni_docs as $doc) {
        echo "- Candidate: {$doc['prenom']} {$doc['nom']}, File: {$doc['file_name']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
