<?php
require_once 'config.php';

echo "<h2>Debug - Candidats dans la base de données</h2>";

try {
    // Count total candidates
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM candidates");
    $count_stmt->execute();
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Total candidats:</strong> " . $total['total'] . "</p>";
    
    // Get all candidates
    $stmt = $pdo->prepare("SELECT id, nom, prenom, email, categorie, status, created_at FROM candidates ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Derniers 10 candidats:</h3>";
    if (empty($candidates)) {
        echo "<p>Aucun candidat trouvé dans la table candidates</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nom</th><th>Prénom</th><th>Email</th><th>Catégorie</th><th>Statut</th><th>Date création</th></tr>";
        foreach ($candidates as $candidate) {
            echo "<tr>";
            echo "<td>" . $candidate['id'] . "</td>";
            echo "<td>" . htmlspecialchars($candidate['nom']) . "</td>";
            echo "<td>" . htmlspecialchars($candidate['prenom']) . "</td>";
            echo "<td>" . htmlspecialchars($candidate['email']) . "</td>";
            echo "<td>" . $candidate['categorie'] . "</td>";
            echo "<td>" . $candidate['status'] . "</td>";
            echo "<td>" . $candidate['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check table structure
    echo "<h3>Structure de la table candidates:</h3>";
    $struct_stmt = $pdo->prepare("DESCRIBE candidates");
    $struct_stmt->execute();
    $structure = $struct_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr>";
    foreach ($structure as $field) {
        echo "<tr>";
        echo "<td>" . $field['Field'] . "</td>";
        echo "<td>" . $field['Type'] . "</td>";
        echo "<td>" . $field['Null'] . "</td>";
        echo "<td>" . $field['Key'] . "</td>";
        echo "<td>" . $field['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Erreur: " . $e->getMessage() . "</p>";
}
?>
