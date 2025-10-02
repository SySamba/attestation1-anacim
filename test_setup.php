<?php
require_once 'config/database.php';

echo "=== Test de la Configuration ANACIM ===\n\n";

try {
    // 1. Vérifier la connexion à la base de données
    echo "1. Test de connexion à la base de données...\n";
    $stmt = $pdo->query("SELECT 1");
    echo "✓ Connexion réussie\n\n";
    
    // 2. Vérifier les tables existantes
    echo "2. Vérification des tables...\n";
    $tables = ['candidates', 'candidate_documents', 'admin_users'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            echo "✓ Table '$table' existe\n";
        } else {
            echo "❌ Table '$table' manquante\n";
        }
    }
    echo "\n";
    
    // 3. Vérifier les colonnes de la table candidates
    echo "3. Vérification des colonnes candidates...\n";
    $stmt = $pdo->query("DESCRIBE candidates");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['status', 'reviewed_at', 'reviewed_by', 'rejection_reason'];
    foreach ($required_columns as $col) {
        if (in_array($col, $columns)) {
            echo "✓ Colonne '$col' existe\n";
        } else {
            echo "❌ Colonne '$col' manquante - Ajout...\n";
            try {
                switch ($col) {
                    case 'status':
                        $pdo->exec("ALTER TABLE candidates ADD COLUMN status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending'");
                        echo "✓ Colonne 'status' ajoutée\n";
                        break;
                    case 'reviewed_at':
                        $pdo->exec("ALTER TABLE candidates ADD COLUMN reviewed_at TIMESTAMP NULL");
                        echo "✓ Colonne 'reviewed_at' ajoutée\n";
                        break;
                    case 'reviewed_by':
                        $pdo->exec("ALTER TABLE candidates ADD COLUMN reviewed_by INT NULL");
                        echo "✓ Colonne 'reviewed_by' ajoutée\n";
                        break;
                    case 'rejection_reason':
                        $pdo->exec("ALTER TABLE candidates ADD COLUMN rejection_reason TEXT NULL");
                        echo "✓ Colonne 'rejection_reason' ajoutée\n";
                        break;
                }
            } catch (PDOException $e) {
                echo "⚠️  Erreur lors de l'ajout de '$col': " . $e->getMessage() . "\n";
            }
        }
    }
    echo "\n";
    
    // 4. Créer les tables QCM si elles n'existent pas
    echo "4. Création des tables QCM...\n";
    
    // Table qcm_questions (compatible avec l'existante)
    $pdo->exec("CREATE TABLE IF NOT EXISTS qcm_questions_new (
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
    echo "✓ Table 'qcm_questions_new' créée\n";
    
    // Table qcm_sessions
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
    
    // Table qcm_answers
    $pdo->exec("CREATE TABLE IF NOT EXISTS qcm_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        question_id INT NOT NULL,
        selected_answer ENUM('a', 'b', 'c', 'd') NOT NULL,
        is_correct BOOLEAN NOT NULL,
        answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES qcm_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (question_id) REFERENCES qcm_questions_new(id) ON DELETE CASCADE,
        UNIQUE KEY unique_session_question (session_id, question_id)
    )");
    echo "✓ Table 'qcm_answers' créée\n\n";
    
    // 5. Ajouter des questions de test
    echo "5. Ajout de questions de test...\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qcm_questions_new");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $questions = [
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
                'question' => 'Quel est le rôle principal de l\'ANACIM ?',
                'a' => 'Contrôler la météo',
                'b' => 'Réguler l\'aviation civile au Sénégal',
                'c' => 'Vendre des billets d\'avion',
                'd' => 'Former les pilotes',
                'correct' => 'b',
                'category' => '1'
            ]
        ];
        
        foreach ($questions as $q) {
            $stmt = $pdo->prepare("INSERT INTO qcm_questions_new (question_text, option_a, option_b, option_c, option_d, correct_answer, category) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$q['question'], $q['a'], $q['b'], $q['c'], $q['d'], $q['correct'], $q['category']]);
        }
        echo "✓ " . count($questions) . " questions ajoutées\n";
    } else {
        echo "✓ $count questions déjà présentes\n";
    }
    
    // 6. Créer un candidat de test
    echo "\n6. Création d'un candidat de test...\n";
    $stmt = $pdo->prepare("SELECT id FROM candidates WHERE email = 'test@example.com'");
    $stmt->execute();
    $candidate = $stmt->fetch();
    
    if (!$candidate) {
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
        $candidate_id = $candidate['id'];
        echo "✓ Candidat de test existant (ID: $candidate_id)\n";
    }
    
    echo "\n=== INSTRUCTIONS DE TEST ===\n";
    echo "1. Ouvrez votre navigateur et allez sur:\n";
    echo "   http://localhost/Anacim-formation/admin_login.php\n\n";
    echo "2. Connectez-vous avec:\n";
    echo "   Utilisateur: admin\n";
    echo "   Mot de passe: admin123\n\n";
    echo "3. Dans le tableau de bord, vous devriez voir:\n";
    echo "   - Le candidat 'Jean Dupont' avec le statut 'En Attente'\n";
    echo "   - Des boutons 'Accepter' et 'Refuser'\n";
    echo "   - Un filtre par statut sur la gauche\n\n";
    echo "4. Testez l'acceptation:\n";
    echo "   - Cliquez sur 'Accepter' pour Jean Dupont\n";
    echo "   - Le statut devrait changer à 'Accepté'\n";
    echo "   - Un bouton 'Test QCM' devrait apparaître\n\n";
    echo "5. Testez le QCM:\n";
    echo "   - Cliquez sur 'Test QCM' ou allez directement sur:\n";
    echo "   http://localhost/Anacim-formation/candidate_qcm.php?token=" . base64_encode($candidate_id) . "\n\n";
    echo "🎉 Configuration terminée avec succès !\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>
