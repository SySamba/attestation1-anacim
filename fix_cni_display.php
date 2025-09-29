<?php
// Script pour corriger l'affichage des CNI
require_once 'config/database.php';

// Vérifier si la base de données existe et contient des données
echo "=== Diagnostic CNI ===\n";

// 1. Vérifier la connexion
try {
    $pdo->query("SELECT 1");
    echo "✓ Connexion base de données OK\n";
} catch (Exception $e) {
    echo "✗ Erreur connexion: " . $e->getMessage() . "\n";
    exit;
}

// 2. Vérifier les candidats
$sql = "SELECT COUNT(*) FROM candidates";
$count = $pdo->query($sql)->fetchColumn();
echo "✓ Candidats trouvés: $count\n";

// 3. Vérifier les documents
$sql = "SELECT COUNT(*) FROM candidate_documents";
$count = $pdo->query($sql)->fetchColumn();
echo "✓ Documents trouvés: $count\n";

// 4. Vérifier spécifiquement les CNI
$sql = "SELECT COUNT(*) FROM candidate_documents WHERE document_type = 'cni'";
$count = $pdo->query($sql)->fetchColumn();
echo "✓ Documents CNI trouvés: $count\n";

// 5. Lister tous les types de documents
$sql = "SELECT document_type, COUNT(*) as nb FROM candidate_documents GROUP BY document_type";
$stmt = $pdo->query($sql);
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\n=== Types de documents ===\n";
foreach ($types as $type) {
    echo "- {$type['document_type']}: {$type['nb']}\n";
}

// 6. Tester la requête du dashboard
echo "\n=== Test requête dashboard ===\n";
$sql = "SELECT c.id, c.prenom, c.nom,
        MAX(CASE WHEN cd.document_type = 'cni' THEN cd.file_name END) as cni_file_name,
        MAX(CASE WHEN cd.document_type = 'cni' THEN cd.id END) as cni_doc_id
        FROM candidates c 
        LEFT JOIN candidate_documents cd ON c.id = cd.candidate_id 
        GROUP BY c.id";
        
$stmt = $pdo->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $row) {
    $status = $row['cni_file_name'] ? "✓ CNI OK" : "✗ CNI MANQUANTE";
    echo "Candidat {$row['id']}: {$row['prenom']} {$row['nom']} - $status\n";
    if ($row['cni_file_name']) {
        echo "  Fichier: {$row['cni_file_name']}\n";
    }
}

echo "\n=== Fin diagnostic ===\n";
?>
