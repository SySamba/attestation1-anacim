<?php
require_once 'config/database.php';

echo "Mise à jour de la base de données...\n";

try {
    // Add status column to candidates table if it doesn't exist
    $pdo->exec("ALTER TABLE candidates ADD COLUMN IF NOT EXISTS status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending'");
    echo "✓ Colonne 'status' ajoutée à la table candidates\n";
    
    // Add reviewed_at column
    $pdo->exec("ALTER TABLE candidates ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP NULL");
    echo "✓ Colonne 'reviewed_at' ajoutée à la table candidates\n";
    
    // Add reviewed_by column
    $pdo->exec("ALTER TABLE candidates ADD COLUMN IF NOT EXISTS reviewed_by INT NULL");
    echo "✓ Colonne 'reviewed_by' ajoutée à la table candidates\n";
    
    // Add rejection_reason column
    $pdo->exec("ALTER TABLE candidates ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL");
    echo "✓ Colonne 'rejection_reason' ajoutée à la table candidates\n";
    
    // Add foreign key constraint for reviewed_by (if not exists)
    try {
        $pdo->exec("ALTER TABLE candidates ADD CONSTRAINT fk_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL");
        echo "✓ Contrainte de clé étrangère ajoutée pour 'reviewed_by'\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            throw $e;
        }
        echo "✓ Contrainte de clé étrangère déjà existante pour 'reviewed_by'\n";
    }
    
    // Create QCM Questions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS qcm_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_text TEXT NOT NULL,
        option_a VARCHAR(500) NOT NULL,
        option_b VARCHAR(500) NOT NULL,
        option_c VARCHAR(500) NOT NULL,
        option_d VARCHAR(500) NOT NULL,
        correct_answer ENUM('a', 'b', 'c', 'd') NOT NULL,
        category ENUM('1', '2', '3', '4', '5') NOT NULL,
        difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "✓ Table 'qcm_questions' créée\n";
    
    // Create QCM Sessions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS qcm_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        candidate_id INT NOT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        score DECIMAL(5,2) NULL,
        total_questions INT NOT NULL,
        correct_answers INT DEFAULT 0,
        status ENUM('in_progress', 'completed', 'expired') DEFAULT 'in_progress',
        time_limit_minutes INT DEFAULT 60,
        FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
    )");
    echo "✓ Table 'qcm_sessions' créée\n";
    
    // Create QCM Answers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS qcm_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        question_id INT NOT NULL,
        selected_answer ENUM('a', 'b', 'c', 'd') NOT NULL,
        is_correct BOOLEAN NOT NULL,
        answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES qcm_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES qcm_questions(id) ON DELETE CASCADE,
        UNIQUE KEY unique_session_question (session_id, question_id)
    )");
    echo "✓ Table 'qcm_answers' créée\n";
    
    // Check if we need to migrate from old qcm_questions structure to new one
    $stmt = $pdo->prepare("SHOW COLUMNS FROM qcm_questions LIKE 'option_a'");
    $stmt->execute();
    $has_options = $stmt->fetch();
    
    if (!$has_options) {
        // Old structure exists, we need to add compatibility
        echo "✓ Structure QCM compatible détectée\n";
    } else {
        // Insert some sample QCM questions in new format
        $sample_questions = [
            [
                'question' => 'Quelle est la principale responsabilité d\'un agent de sûreté aéroportuaire ?',
                'a' => 'Vendre des billets d\'avion',
                'b' => 'Contrôler l\'accès aux zones sécurisées',
                'c' => 'Nettoyer les avions',
                'd' => 'Servir les repas aux passagers',
                'correct' => 'b',
                'category' => '1'
            ],
            [
                'question' => 'Que signifie l\'acronyme OACI ?',
                'a' => 'Organisation de l\'Aviation Civile Internationale',
                'b' => 'Office Africain de Contrôle International',
                'c' => 'Organisation Administrative de Contrôle Interne',
                'd' => 'Office d\'Aviation Commerciale Internationale',
                'correct' => 'a',
                'category' => '1'
            ],
            [
                'question' => 'Quelle est la durée maximale de validité d\'un certificat de sûreté ?',
                'a' => '1 an',
                'b' => '2 ans',
                'c' => '3 ans',
                'd' => '5 ans',
                'correct' => 'c',
                'category' => '2'
            ]
        ];
        
        // Check if questions already exist
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_questions WHERE option_a IS NOT NULL");
        $stmt->execute();
        $question_count = $stmt->fetchColumn();
        
        if ($question_count == 0) {
            foreach ($sample_questions as $q) {
                $stmt = $pdo->prepare("INSERT INTO qcm_questions (question_text, option_a, option_b, option_c, option_d, correct_answer, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$q['question'], $q['a'], $q['b'], $q['c'], $q['d'], $q['correct'], $q['category']]);
            }
            echo "✓ Questions d'exemple ajoutées au QCM\n";
        } else {
            echo "✓ Questions QCM déjà présentes\n";
        }
    }
    
    echo "\n🎉 Mise à jour de la base de données terminée avec succès !\n";
    
} catch (PDOException $e) {
    echo "❌ Erreur lors de la mise à jour : " . $e->getMessage() . "\n";
    exit(1);
}
?>
