<?php
require_once 'config.php';

try {
    echo "Création des tables pour le système d'imagerie interactive...\n\n";
    
    // Table pour les questions d'imagerie interactive
    $sql1 = "CREATE TABLE IF NOT EXISTS imagerie_questions_interactive (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(500) NOT NULL,
        correct_answer_type ENUM('pass', 'object_found') NOT NULL,
        object_category ENUM(
            'substances_explosives', 
            'objets_tranchants', 
            'outils_travail', 
            'equipements_projectiles', 
            'appareils_paralysants', 
            'instruments_contondants', 
            'lags'
        ) NULL,
        correct_x_position INT NULL,
        correct_y_position INT NULL,
        tolerance_radius INT DEFAULT 50,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT NULL,
        is_active BOOLEAN DEFAULT TRUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql1);
    echo "✓ Table 'imagerie_questions_interactive' créée avec succès\n";
    
    // Table pour les sessions d'imagerie interactive
    $sql2 = "CREATE TABLE IF NOT EXISTS imagerie_sessions_interactive (
        id INT AUTO_INCREMENT PRIMARY KEY,
        candidate_id INT NOT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        status ENUM('in_progress', 'completed', 'timeout') DEFAULT 'in_progress',
        total_questions INT DEFAULT 0,
        correct_answers INT DEFAULT 0,
        score DECIMAL(5,2) NULL,
        time_spent INT NULL,
        FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql2);
    echo "✓ Table 'imagerie_sessions_interactive' créée avec succès\n";
    
    // Table pour les réponses d'imagerie interactive
    $sql3 = "CREATE TABLE IF NOT EXISTS imagerie_responses_interactive (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        question_id INT NOT NULL,
        question_order INT NOT NULL,
        response_type ENUM('pass', 'object_found') NOT NULL,
        selected_category ENUM(
            'substances_explosives', 
            'objets_tranchants', 
            'outils_travail', 
            'equipements_projectiles', 
            'appareils_paralysants', 
            'instruments_contondants', 
            'lags'
        ) NULL,
        clicked_x_position INT NULL,
        clicked_y_position INT NULL,
        time_spent INT NOT NULL,
        is_correct BOOLEAN NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES imagerie_sessions_interactive(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql3);
    echo "✓ Table 'imagerie_responses_interactive' créée avec succès\n";
    
    // Créer le dossier pour les images si il n'existe pas
    $upload_dir = 'uploads/imagerie/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
        echo "✓ Dossier '$upload_dir' créé avec succès\n";
    } else {
        echo "✓ Dossier '$upload_dir' existe déjà\n";
    }
    
    echo "\n🎉 Toutes les tables ont été créées avec succès !\n";
    echo "Vous pouvez maintenant accéder à l'interface d'administration : admin_imagerie_interactive.php\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la création des tables : " . $e->getMessage() . "\n";
    exit(1);
}
?>
