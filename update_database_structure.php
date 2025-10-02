<?php
require_once 'config/database.php';

echo "<h2>Mise à jour de la structure de base de données</h2>";

try {
    // 1. Rendre le matricule obligatoire
    $pdo->exec("ALTER TABLE candidates MODIFY COLUMN matricule VARCHAR(50) NOT NULL");
    echo "<p>✓ Matricule rendu obligatoire</p>";
    
    // 2. Ajouter colonnes pour mot de passe temporaire et accès QCM
    $pdo->exec("ALTER TABLE candidates ADD COLUMN IF NOT EXISTS temp_password VARCHAR(255) NULL");
    echo "<p>✓ Colonne temp_password ajoutée</p>";
    
    $pdo->exec("ALTER TABLE candidates ADD COLUMN IF NOT EXISTS qcm_access BOOLEAN DEFAULT 0");
    echo "<p>✓ Colonne qcm_access ajoutée</p>";
    
    // 3. Modifier la structure des réponses QCM pour supporter les réponses multiples
    $pdo->exec("ALTER TABLE qcm_answers MODIFY COLUMN selected_answer TEXT");
    echo "<p>✓ Structure qcm_answers mise à jour pour réponses multiples</p>";
    
    echo "<div style='background: #d4edda; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<h3 style='color: #155724'>✅ Mise à jour terminée avec succès !</h3>";
    echo "<p>La base de données est maintenant prête pour :</p>";
    echo "<ul>";
    echo "<li>Matricule obligatoire pour tous les candidats</li>";
    echo "<li>Génération automatique de mots de passe temporaires</li>";
    echo "<li>Gestion des accès QCM pour candidats acceptés</li>";
    echo "<li>Support des questions à choix multiples</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; margin: 20px 0; border-radius: 5px;'>";
    echo "<p style='color: #721c24'><strong>Erreur :</strong> " . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
