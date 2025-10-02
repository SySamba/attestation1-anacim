<?php
require_once 'config/database.php';

echo "Ajout de questions de test pour le QCM...\n\n";

try {
    // Vérifier si des questions existent déjà
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_questions");
    $stmt->execute();
    $existing_count = $stmt->fetchColumn();
    
    if ($existing_count > 0) {
        echo "Questions existantes trouvées: $existing_count\n";
        echo "Vérification des choix associés...\n";
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_choices");
        $stmt->execute();
        $choices_count = $stmt->fetchColumn();
        
        if ($choices_count > 0) {
            echo "Choix existants trouvés: $choices_count\n";
            echo "Le système QCM devrait déjà fonctionner.\n";
            exit;
        }
    }
    
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
        ],
        [
            'question' => 'Quelle est la durée de validité d\'un certificat de sûreté ?',
            'choices' => [
                ['text' => '1 an', 'correct' => false],
                ['text' => '2 ans', 'correct' => false],
                ['text' => '3 ans', 'correct' => true],
                ['text' => '5 ans', 'correct' => false]
            ]
        ],
        [
            'question' => 'Qui peut accéder aux zones de sûreté restreintes ?',
            'choices' => [
                ['text' => 'Tous les passagers', 'correct' => false],
                ['text' => 'Le personnel autorisé uniquement', 'correct' => true],
                ['text' => 'Les visiteurs avec accompagnateur', 'correct' => false],
                ['text' => 'Toute personne avec un billet', 'correct' => false]
            ]
        ],
        [
            'question' => 'Quel équipement est obligatoire pour un agent de sûreté ?',
            'choices' => [
                ['text' => 'Badge d\'identification', 'correct' => true],
                ['text' => 'Téléphone portable', 'correct' => false],
                ['text' => 'Ordinateur portable', 'correct' => false],
                ['text' => 'Appareil photo', 'correct' => false]
            ]
        ],
        [
            'question' => 'En cas d\'urgence, quelle est la première action à effectuer ?',
            'choices' => [
                ['text' => 'Prendre des photos', 'correct' => false],
                ['text' => 'Alerter les services d\'urgence', 'correct' => true],
                ['text' => 'Évacuer sans prévenir', 'correct' => false],
                ['text' => 'Attendre les instructions', 'correct' => false]
            ]
        ],
        [
            'question' => 'Quelle formation est requise pour devenir agent de sûreté ?',
            'choices' => [
                ['text' => 'Aucune formation spécifique', 'correct' => false],
                ['text' => 'Formation en sûreté aviation certifiée', 'correct' => true],
                ['text' => 'Diplôme universitaire uniquement', 'correct' => false],
                ['text' => 'Expérience militaire uniquement', 'correct' => false]
            ]
        ]
    ];
    
    echo "Ajout de " . count($test_questions) . " questions de test...\n";
    
    foreach ($test_questions as $index => $q) {
        // Insérer la question
        $stmt = $pdo->prepare("INSERT INTO qcm_questions (question_text, question_type) VALUES (?, 'single')");
        $stmt->execute([$q['question']]);
        $question_id = $pdo->lastInsertId();
        
        echo "Question " . ($index + 1) . " ajoutée (ID: $question_id)\n";
        
        // Insérer les choix
        foreach ($q['choices'] as $choice) {
            $stmt = $pdo->prepare("INSERT INTO qcm_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)");
            $stmt->execute([$question_id, $choice['text'], $choice['correct'] ? 1 : 0]);
        }
        
        echo "  - " . count($q['choices']) . " choix ajoutés\n";
    }
    
    echo "\n✅ Questions de test ajoutées avec succès !\n";
    echo "Le système QCM est maintenant prêt à être testé.\n\n";
    
    // Vérification finale
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_questions");
    $stmt->execute();
    $total_questions = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_choices");
    $stmt->execute();
    $total_choices = $stmt->fetchColumn();
    
    echo "Total des questions: $total_questions\n";
    echo "Total des choix: $total_choices\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>
