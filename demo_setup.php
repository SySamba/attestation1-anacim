<?php
require_once 'config/database.php';

echo "=== ANACIM - Configuration de démonstration ===\n\n";

try {
    // 1. Exécuter la mise à jour de la base de données
    echo "1. Mise à jour de la base de données...\n";
    include 'update_database.php';
    echo "\n";
    
    // 2. Créer un candidat de test s'il n'existe pas
    echo "2. Création d'un candidat de test...\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE email = 'test@example.com'");
    $stmt->execute();
    $exists = $stmt->fetchColumn();
    
    if ($exists == 0) {
        $stmt = $pdo->prepare("INSERT INTO candidates (prenom, nom, email, categorie, adresse, date_naissance, lieu_naissance, matricule, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'Jean',
            'Dupont',
            'test@example.com',
            '1',
            '123 Rue de la Paix, Dakar',
            '1990-01-15',
            'Dakar',
            'MAT001',
            'pending'
        ]);
        $candidate_id = $pdo->lastInsertId();
        echo "✓ Candidat de test créé (ID: $candidate_id)\n";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM candidates WHERE email = 'test@example.com'");
        $stmt->execute();
        $candidate_id = $stmt->fetchColumn();
        echo "✓ Candidat de test existant trouvé (ID: $candidate_id)\n";
    }
    
    // 3. Ajouter des documents fictifs pour le candidat
    echo "3. Ajout de documents fictifs...\n";
    
    $documents = [
        ['cv', 'cv_jean_dupont.pdf'],
        ['cni', 'cni_jean_dupont.pdf'],
        ['attestation_formation', 'attestation_jean_dupont.pdf'],
        ['casier', 'casier_jean_dupont.pdf'],
        ['certificat_medical', 'certificat_jean_dupont.pdf']
    ];
    
    foreach ($documents as $doc) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidate_documents WHERE candidate_id = ? AND document_type = ?");
        $stmt->execute([$candidate_id, $doc[0]]);
        
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO candidate_documents (candidate_id, document_type, file_name, file_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$candidate_id, $doc[0], $doc[1], 'uploads/' . $doc[1]]);
            echo "  ✓ Document {$doc[0]} ajouté\n";
        }
    }
    
    // 4. Vérifier les questions QCM
    echo "4. Vérification des questions QCM...\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_questions WHERE category = '1'");
    $stmt->execute();
    $question_count = $stmt->fetchColumn();
    
    if ($question_count < 5) {
        echo "  ⚠️  Seulement $question_count questions trouvées pour la catégorie 1\n";
        echo "  Ajout de questions supplémentaires...\n";
        
        $additional_questions = [
            [
                'question' => 'Quel est le rôle principal de l\'ANACIM ?',
                'a' => 'Contrôler la météo',
                'b' => 'Réguler l\'aviation civile au Sénégal',
                'c' => 'Vendre des billets d\'avion',
                'd' => 'Former les pilotes',
                'correct' => 'b',
                'category' => '1'
            ],
            [
                'question' => 'Que doit faire un agent de sûreté en cas d\'objet suspect ?',
                'a' => 'L\'ignorer',
                'b' => 'Le toucher pour l\'examiner',
                'c' => 'Alerter immédiatement les autorités',
                'd' => 'Le jeter à la poubelle',
                'correct' => 'c',
                'category' => '1'
            ],
            [
                'question' => 'Quelle est la zone la plus sécurisée d\'un aéroport ?',
                'a' => 'La zone publique',
                'b' => 'La zone commerciale',
                'c' => 'La zone côté piste',
                'd' => 'Le parking',
                'correct' => 'c',
                'category' => '1'
            ]
        ];
        
        foreach ($additional_questions as $q) {
            $stmt = $pdo->prepare("INSERT INTO qcm_questions (question_text, option_a, option_b, option_c, option_d, correct_answer, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$q['question'], $q['a'], $q['b'], $q['c'], $q['d'], $q['correct'], $q['category']]);
        }
        echo "  ✓ Questions supplémentaires ajoutées\n";
    } else {
        echo "  ✓ $question_count questions disponibles pour la catégorie 1\n";
    }
    
    // 5. Afficher les informations de connexion
    echo "\n=== INFORMATIONS DE CONNEXION ===\n";
    echo "Interface Admin: http://localhost/Anacim-formation/admin_login.php\n";
    echo "Utilisateur: admin\n";
    echo "Mot de passe: admin123\n\n";
    
    echo "Candidat de test créé:\n";
    echo "- Nom: Jean Dupont\n";
    echo "- Email: test@example.com\n";
    echo "- Catégorie: 1\n";
    echo "- Statut: En attente\n\n";
    
    echo "=== ÉTAPES DE TEST ===\n";
    echo "1. Connectez-vous à l'interface admin\n";
    echo "2. Allez dans le tableau de bord\n";
    echo "3. Trouvez le candidat 'Jean Dupont'\n";
    echo "4. Cliquez sur 'Accepter' pour approuver sa candidature\n";
    echo "5. Une fois accepté, cliquez sur 'Test QCM' pour voir l'interface de test\n";
    echo "6. Le candidat peut passer le test via le lien généré\n\n";
    
    echo "Token de test QCM pour Jean Dupont: " . base64_encode($candidate_id) . "\n";
    echo "Lien direct: http://localhost/Anacim-formation/candidate_qcm.php?token=" . base64_encode($candidate_id) . "\n\n";
    
    echo "🎉 Configuration de démonstration terminée avec succès !\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>
