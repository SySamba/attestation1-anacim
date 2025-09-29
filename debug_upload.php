<?php
require_once 'config/database.php';

echo "<h2>Debug Upload - État de la base de données</h2>";

// Vérifier les candidats
$sql = "SELECT * FROM candidates ORDER BY id DESC LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Derniers candidats :</h3>";
foreach ($candidates as $candidate) {
    echo "<p>ID: {$candidate['id']} - {$candidate['prenom']} {$candidate['nom']} - Catégorie: {$candidate['categorie']}</p>";
}

// Vérifier les documents
$sql = "SELECT cd.*, c.prenom, c.nom FROM candidate_documents cd 
        JOIN candidates c ON cd.candidate_id = c.id 
        ORDER BY cd.id DESC LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Derniers documents :</h3>";
if (empty($documents)) {
    echo "<p style='color: red;'>AUCUN DOCUMENT TROUVÉ DANS LA BASE !</p>";
} else {
    foreach ($documents as $doc) {
        echo "<p>Candidat: {$doc['prenom']} {$doc['nom']} - Type: {$doc['document_type']} - Fichier: {$doc['file_name']}</p>";
    }
}

// Vérifier la structure de la table
$sql = "SHOW CREATE TABLE candidate_documents";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<h3>Structure table candidate_documents :</h3>";
echo "<pre>" . htmlspecialchars($result['Create Table']) . "</pre>";

// Vérifier les fichiers dans le dossier uploads
echo "<h3>Fichiers dans uploads/ :</h3>";
$uploadDir = 'uploads/';
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "<p>$file</p>";
        }
    }
} else {
    echo "<p style='color: red;'>Dossier uploads/ n'existe pas !</p>";
}
?>
