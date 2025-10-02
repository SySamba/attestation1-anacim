<?php
require_once 'config/database.php';

echo "=== Correction du système QCM ===\n\n";

try {
    // 1. Vérifier les tables existantes
    echo "1. Vérification des tables...\n";
    
    $tables = ['qcm_questions', 'qcm_choices', 'qcm_sessions', 'qcm_answers'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            echo "✓ Table '$table' existe\n";
        } else {
            echo "❌ Table '$table' manquante\n";
            
            // Créer les tables manquantes
            if ($table === 'qcm_sessions') {
                $pdo->exec("CREATE TABLE qcm_sessions (
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
                echo "✓ Table '$table' créée\n";
            }
            
            if ($table === 'qcm_answers') {
                $pdo->exec("CREATE TABLE qcm_answers (
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
                echo "✓ Table '$table' créée\n";
            }
        }
    }
    
    // 2. Vérifier et ajouter des questions
    echo "\n2. Vérification des questions...\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_questions");
    $stmt->execute();
    $question_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_choices");
    $stmt->execute();
    $choices_count = $stmt->fetchColumn();
    
    echo "Questions existantes: $question_count\n";
    echo "Choix existants: $choices_count\n";
    
    if ($question_count == 0 || $choices_count == 0) {
        echo "Ajout de questions de test...\n";
        
        // Supprimer les anciennes données si elles existent
        $pdo->exec("DELETE FROM qcm_choices");
        $pdo->exec("DELETE FROM qcm_questions");
        
        $questions = [
            [
                'question' => 'Quelle est la principale responsabilité d\'un agent de sûreté aéroportuaire ?',
                'choices' => [
                    'Vendre des billets d\'avion',
                    'Contrôler l\'accès aux zones sécurisées',
                    'Nettoyer les avions',
                    'Servir les repas aux passagers'
                ],
                'correct' => 1 // Index de la bonne réponse (0-based)
            ],
            [
                'question' => 'Que signifie l\'acronyme OACI ?',
                'choices' => [
                    'Organisation de l\'Aviation Civile Internationale',
                    'Office Africain de Contrôle International',
                    'Organisation Administrative de Contrôle Interne',
                    'Office d\'Aviation Commerciale Internationale'
                ],
                'correct' => 0
            ],
            [
                'question' => 'Quel est le rôle principal de l\'ANACIM ?',
                'choices' => [
                    'Contrôler la météo',
                    'Réguler l\'aviation civile au Sénégal',
                    'Vendre des billets d\'avion',
                    'Former les pilotes'
                ],
                'correct' => 1
            ],
            [
                'question' => 'Que doit faire un agent de sûreté en cas d\'objet suspect ?',
                'choices' => [
                    'L\'ignorer',
                    'Le toucher pour l\'examiner',
                    'Alerter immédiatement les autorités',
                    'Le jeter à la poubelle'
                ],
                'correct' => 2
            ],
            [
                'question' => 'Quelle est la zone la plus sécurisée d\'un aéroport ?',
                'choices' => [
                    'La zone publique',
                    'La zone commerciale',
                    'La zone côté piste',
                    'Le parking'
                ],
                'correct' => 2
            ]
        ];
        
        foreach ($questions as $index => $q) {
            // Insérer la question
            $stmt = $pdo->prepare("INSERT INTO qcm_questions (question_text, question_type) VALUES (?, 'single')");
            $stmt->execute([$q['question']]);
            $question_id = $pdo->lastInsertId();
            
            echo "✓ Question " . ($index + 1) . " ajoutée (ID: $question_id)\n";
            
            // Insérer les choix
            foreach ($q['choices'] as $choice_index => $choice_text) {
                $is_correct = ($choice_index === $q['correct']) ? 1 : 0;
                $stmt = $pdo->prepare("INSERT INTO qcm_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                $stmt->execute([$question_id, $choice_text, $is_correct]);
            }
        }
        
        echo "✓ " . count($questions) . " questions ajoutées avec succès\n";
    }
    
    // 3. Vérification finale
    echo "\n3. Vérification finale...\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_questions");
    $stmt->execute();
    $final_questions = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_choices");
    $stmt->execute();
    $final_choices = $stmt->fetchColumn();
    
    echo "Questions finales: $final_questions\n";
    echo "Choix finaux: $final_choices\n";
    
    if ($final_questions >= 3) {
        echo "\n✅ Le système QCM est maintenant opérationnel!\n";
        echo "Vous pouvez tester le QCM en acceptant un candidat.\n";
    } else {
        echo "\n❌ Problème persistant avec les questions\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
