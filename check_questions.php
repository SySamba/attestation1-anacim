<?php
require_once 'config/database.php';

echo "=== Vérification des Questions QCM ===\n\n";

try {
    // Vérifier le nombre de questions
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_questions");
    $stmt->execute();
    $question_count = $stmt->fetchColumn();
    
    echo "Nombre total de questions: $question_count\n";
    
    // Vérifier le nombre de choix
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_choices");
    $stmt->execute();
    $choices_count = $stmt->fetchColumn();
    
    echo "Nombre total de choix: $choices_count\n\n";
    
    if ($question_count == 0) {
        echo "❌ Aucune question trouvée. Ajout des questions de test...\n\n";
        
        // Questions de test pour la sûreté aviation
        $test_questions = [
            [
                'question' => 'Quelle est la principale responsabilité d\'un agent de sûreté aéroportuaire ?',
                'choices' => [
                    ['text' => 'Vendre des billets d\'avion', 'correct' => false],
                    ['text' => 'Contrôler l\'accès aux zones sécurisées', 'correct' => true],
                    ['text' => 'Nettoyer les avions', 'correct' => false],
                    ['text' => 'Servir les repas aux passagers', 'correct' => false]
                ]
            ],
            [
                'question' => 'Que signifie l\'acronyme OACI ?',
                'choices' => [
                    ['text' => 'Organisation de l\'Aviation Civile Internationale', 'correct' => true],
                    ['text' => 'Office Africain de Contrôle International', 'correct' => false],
                    ['text' => 'Organisation Administrative de Contrôle Interne', 'correct' => false],
                    ['text' => 'Office d\'Aviation Commerciale Internationale', 'correct' => false]
                ]
            ],
            [
                'question' => 'Quel est le rôle principal de l\'ANACIM ?',
                'choices' => [
                    ['text' => 'Contrôler la météo', 'correct' => false],
                    ['text' => 'Réguler l\'aviation civile au Sénégal', 'correct' => true],
                    ['text' => 'Vendre des billets d\'avion', 'correct' => false],
                    ['text' => 'Former les pilotes', 'correct' => false]
                ]
            ],
            [
                'question' => 'Que doit faire un agent de sûreté en cas d\'objet suspect ?',
                'choices' => [
                    ['text' => 'L\'ignorer', 'correct' => false],
                    ['text' => 'Le toucher pour l\'examiner', 'correct' => false],
                    ['text' => 'Alerter immédiatement les autorités', 'correct' => true],
                    ['text' => 'Le jeter à la poubelle', 'correct' => false]
                ]
            ],
            [
                'question' => 'Quelle est la zone la plus sécurisée d\'un aéroport ?',
                'choices' => [
                    ['text' => 'La zone publique', 'correct' => false],
                    ['text' => 'La zone commerciale', 'correct' => false],
                    ['text' => 'La zone côté piste', 'correct' => true],
                    ['text' => 'Le parking', 'correct' => false]
                ]
            ]
        ];
        
        foreach ($test_questions as $index => $q) {
            // Insérer la question
            $stmt = $pdo->prepare("INSERT INTO qcm_questions (question_text, question_type) VALUES (?, 'single')");
            $stmt->execute([$q['question']]);
            $question_id = $pdo->lastInsertId();
            
            echo "✓ Question " . ($index + 1) . " ajoutée (ID: $question_id)\n";
            
            // Insérer les choix
            foreach ($q['choices'] as $choice) {
                $stmt = $pdo->prepare("INSERT INTO qcm_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
                $stmt->execute([$question_id, $choice['text'], $choice['correct'] ? 1 : 0]);
            }
        }
        
        echo "\n✅ Questions ajoutées avec succès!\n";
    } else {
        echo "✅ Questions trouvées dans la base de données\n";
        
        // Afficher quelques détails
        $stmt = $pdo->prepare("SELECT q.id, q.question_text, COUNT(c.id) as choice_count 
                              FROM qcm_questions q 
                              LEFT JOIN qcm_choices c ON q.id = c.question_id 
                              GROUP BY q.id 
                              LIMIT 5");
        $stmt->execute();
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nPremières questions:\n";
        foreach ($questions as $q) {
            echo "- ID {$q['id']}: " . substr($q['question_text'], 0, 50) . "... ({$q['choice_count']} choix)\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
