<?php
require_once 'config/database.php';

echo "<h3>Debug CNI - Vérification des données</h3>";

// Vérifier les candidats
$sql = "SELECT * FROM candidates ORDER BY id";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h4>Candidats dans la base :</h4>";
foreach ($candidates as $candidate) {
    echo "ID: {$candidate['id']} - {$candidate['prenom']} {$candidate['nom']}<br>";
}

// Vérifier les documents CNI
$sql = "SELECT cd.*, c.prenom, c.nom FROM candidate_documents cd 
        JOIN candidates c ON cd.candidate_id = c.id 
        WHERE cd.document_type = 'cni'";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$cni_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h4>Documents CNI trouvés :</h4>";
if (empty($cni_docs)) {
    echo "Aucun document CNI trouvé dans la base !<br>";
} else {
    foreach ($cni_docs as $doc) {
        echo "Candidat: {$doc['prenom']} {$doc['nom']} - Fichier: {$doc['file_name']}<br>";
    }
}

// Vérifier tous les documents
$sql = "SELECT document_type, COUNT(*) as count FROM candidate_documents GROUP BY document_type";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$doc_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h4>Répartition des documents :</h4>";
foreach ($doc_counts as $count) {
    echo "{$count['document_type']}: {$count['count']} documents<br>";
}

// Tester la requête du dashboard
$sql = "SELECT c.*, 
        COUNT(cd.id) as document_count,
        GROUP_CONCAT(cd.document_type) as document_types,
        MAX(CASE WHEN cd.document_type = 'cni' THEN cd.file_name END) as cni_file_name,
        MAX(CASE WHEN cd.document_type = 'cni' THEN cd.id END) as cni_doc_id
        FROM candidates c 
        LEFT JOIN candidate_documents cd ON c.id = cd.candidate_id 
        GROUP BY c.id 
        ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$dashboard_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h4>Données du dashboard :</h4>";
foreach ($dashboard_data as $candidate) {
    echo "ID: {$candidate['id']} - {$candidate['prenom']} {$candidate['nom']}<br>";
    echo "Documents: {$candidate['document_types']}<br>";
    echo "CNI file: " . ($candidate['cni_file_name'] ? $candidate['cni_file_name'] : 'AUCUN') . "<br>";
    echo "CNI doc ID: " . ($candidate['cni_doc_id'] ? $candidate['cni_doc_id'] : 'AUCUN') . "<br><br>";
}
?>
