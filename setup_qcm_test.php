<?php
require_once 'config/database.php';

echo "=== Configuration QCM pour Test ===\n\n";

try {
    // 1. Créer les tables QCM si elles n'existent pas
    echo "1. Création des tables QCM...\n";
    
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
    
    echo "✓ Tables QCM créées\n";
    
    // 2. Vérifier les questions existantes
    echo "\n2. Vérification des questions...\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_questions");
    $stmt->execute();
    $question_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_choices");
    $stmt->execute();
    $choices_count = $stmt->fetchColumn();
    
    echo "Questions existantes: $question_count\n";
    echo "Choix existants: $choices_count\n";
    
    // 3. Ajouter des questions si nécessaire
    if ($question_count == 0 || $choices_count < 12) {
        echo "\n3. Ajout de questions de test...\n";
        
        // Nettoyer les anciennes données
        $pdo->exec("DELETE FROM qcm_choices");
        $pdo->exec("DELETE FROM qcm_questions");
        
        $questions = [
            [
                'text' => 'Quelle est la principale responsabilité d\'un agent de sûreté aéroportuaire ?',
                'type' => 'single',
                'choices' => [
                    'Vendre des billets d\'avion',
                    'Contrôler l\'accès aux zones sécurisées',
                    'Nettoyer les avions',
                    'Servir les repas aux passagers'
                ],
                'correct' => [1]
            ],
            [
                'text' => 'Que signifie l\'acronyme OACI ?',
                'type' => 'single',
                'choices' => [
                    'Organisation de l\'Aviation Civile Internationale',
                    'Office Africain de Contrôle International',
                    'Organisation Administrative de Contrôle Interne',
                    'Office d\'Aviation Commerciale Internationale'
                ],
                'correct' => [0]
            ],
            [
                'text' => 'Quelles sont les responsabilités d\'un agent de sûreté ? (Plusieurs réponses possibles)',
                'type' => 'multiple',
                'choices' => [
                    'Contrôler les accès aux zones sécurisées',
                    'Vérifier les documents d\'identité',
                    'Servir les repas aux passagers',
                    'Surveiller les équipements de sécurité'
                ],
                'correct' => [0, 1, 3]
            ],
            [
                'text' => 'Que doit faire un agent de sûreté en cas d\'objet suspect ?',
                'type' => 'single',
                'choices' => [
                    'L\'ignorer',
                    'Le toucher pour l\'examiner',
                    'Alerter immédiatement les autorités',
                    'Le jeter à la poubelle'
                ],
                'correct' => [2]
            ],
            [
                'text' => 'Quels équipements sont utilisés pour la sécurité aéroportuaire ? (Plusieurs réponses possibles)',
                'type' => 'multiple',
                'choices' => [
                    'Détecteurs de métaux',
                    'Scanners à rayons X',
                    'Machines à café',
                    'Caméras de surveillance'
                ],
                'correct' => [0, 1, 3]
            ],
            [
                'text' => 'Quelle est la zone la plus sécurisée d\'un aéroport ?',
                'type' => 'single',
                'choices' => [
                    'La zone publique',
                    'La zone commerciale',
                    'La zone côté piste',
                    'Le parking'
                ],
                'correct' => [2]
            ]
        ];
        
        foreach ($questions as $index => $question) {
            // Insérer la question
            $stmt = $pdo->prepare("INSERT INTO qcm_questions (question_text, question_type) VALUES (?, ?)");
            $stmt->execute([$question['text'], $question['type']]);
            $question_id = $pdo->lastInsertId();
            
            echo "✓ Question " . ($index + 1) . " ajoutée (ID: $question_id, Type: {$question['type']})\n";
            
            // Insérer les choix
            foreach ($question['choices'] as $choice_index => $choice_text) {
                $is_correct = in_array($choice_index, $question['correct']) ? 1 : 0;
                $stmt = $pdo->prepare("INSERT INTO qcm_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                $stmt->execute([$question_id, $choice_text, $is_correct]);
            }
        }
        
        echo "✓ " . count($questions) . " questions avec choix ajoutées\n";
    }
    
    // 4. Vérification finale
    echo "\n4. Vérification finale...\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_questions");
    $stmt->execute();
    $final_questions = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_choices WHERE is_correct = 1");
    $stmt->execute();
    $correct_answers = $stmt->fetchColumn();
    
    echo "Questions totales: $final_questions\n";
    echo "Bonnes réponses: $correct_answers\n";
    
    if ($final_questions >= 3 && $correct_answers >= 3) {
        echo "\n✅ SUCCÈS! Le système QCM est opérationnel\n";
        echo "\nPour tester:\n";
        echo "1. Allez sur: http://localhost/Anacim-formation/admin_dashboard.php\n";
        echo "2. Connectez-vous (admin/admin123)\n";
        echo "3. Acceptez un candidat\n";
        echo "4. Cliquez sur 'Test QCM'\n";
    } else {
        echo "\n❌ Problème: pas assez de questions ou de bonnes réponses\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
