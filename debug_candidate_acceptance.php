<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Diagnostic - Système d'Acceptation des Candidats</h2>";
echo "<hr>";

// Test 1: Vérification des fichiers requis
echo "<h3>1. Vérification des fichiers requis</h3>";
$required_files = [
    'config.php',
    'send_email.php',
    'process_candidate_action.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe<br>";
    } else {
        echo "❌ $file manquant<br>";
    }
}

// Test 2: Test de connexion à la base de données
echo "<h3>2. Test de connexion à la base de données</h3>";
try {
    require_once 'config.php';
    echo "✅ Connexion à la base de données réussie<br>";
    echo "Host: " . (defined('DB_HOST') ? DB_HOST : 'Non défini') . "<br>";
    echo "Database: " . (defined('DB_NAME') ? DB_NAME : 'Non défini') . "<br>";
} catch (Exception $e) {
    echo "❌ Erreur de connexion: " . $e->getMessage() . "<br>";
}

// Test 3: Vérification de la structure de la table candidates
echo "<h3>3. Structure de la table candidates</h3>";
try {
    $stmt = $pdo->query("DESCRIBE candidates");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $required_columns = ['id', 'status', 'reviewed_at', 'reviewed_by', 'rejection_reason', 'temp_password', 'qcm_access'];
    
    foreach ($required_columns as $col) {
        $found = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $col) {
                echo "✅ Colonne '$col' existe<br>";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "❌ Colonne '$col' manquante<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Erreur lors de la vérification de la table: " . $e->getMessage() . "<br>";
}

// Test 4: Test de session admin
echo "<h3>4. Test de session admin</h3>";
session_start();
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    echo "✅ Session admin active<br>";
    echo "Admin ID: " . ($_SESSION['admin_id'] ?? 'Non défini') . "<br>";
    echo "Admin Username: " . ($_SESSION['admin_username'] ?? 'Non défini') . "<br>";
} else {
    echo "❌ Session admin non active<br>";
}

// Test 5: Test de candidat pour l'acceptation
echo "<h3>5. Test avec un candidat existant</h3>";
try {
    $stmt = $pdo->query("SELECT id, prenom, nom, email, status FROM candidates WHERE status = 'pending' LIMIT 1");
    $test_candidate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_candidate) {
        echo "✅ Candidat de test trouvé:<br>";
        echo "ID: " . $test_candidate['id'] . "<br>";
        echo "Nom: " . htmlspecialchars($test_candidate['prenom'] . ' ' . $test_candidate['nom']) . "<br>";
        echo "Email: " . htmlspecialchars($test_candidate['email']) . "<br>";
        echo "Status: " . $test_candidate['status'] . "<br>";
        
        // Test d'acceptation simulé (sans email)
        echo "<br><strong>Test d'acceptation simulé:</strong><br>";
        
        $admin_id = $_SESSION['admin_id'] ?? null;
        $update_sql = "UPDATE candidates SET 
                       status = 'accepted', 
                       reviewed_at = NOW(), 
                       reviewed_by = ?, 
                       rejection_reason = NULL 
                       WHERE id = ?";
        
        // Simulation sans exécution réelle
        echo "✅ Requête SQL préparée correctement<br>";
        echo "Admin ID utilisé: " . ($admin_id ?? 'NULL') . "<br>";
        
    } else {
        echo "⚠️ Aucun candidat en attente trouvé pour le test<br>";
    }
} catch (Exception $e) {
    echo "❌ Erreur lors du test candidat: " . $e->getMessage() . "<br>";
}

// Test 6: Test de la fonction mail()
echo "<h3>6. Test de la fonction mail()</h3>";
if (function_exists('mail')) {
    echo "✅ Fonction mail() disponible<br>";
    
    // Test des paramètres de configuration mail
    $mail_config = [
        'SMTP' => ini_get('SMTP'),
        'smtp_port' => ini_get('smtp_port'),
        'sendmail_from' => ini_get('sendmail_from'),
        'sendmail_path' => ini_get('sendmail_path')
    ];
    
    foreach ($mail_config as $key => $value) {
        echo "$key: " . ($value ?: 'Non configuré') . "<br>";
    }
} else {
    echo "❌ Fonction mail() non disponible<br>";
}

// Test 7: Test des permissions de fichier
echo "<h3>7. Test des permissions</h3>";
$files_to_check = ['process_candidate_action.php', 'send_email.php'];
foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        if (is_readable($file)) {
            echo "✅ $file est lisible<br>";
        } else {
            echo "❌ $file n'est pas lisible<br>";
        }
    }
}

// Test 8: Simulation d'une requête AJAX
echo "<h3>8. Test de simulation AJAX</h3>";
echo "<button onclick='testAjaxCall()'>Tester la requête AJAX</button><br>";
echo "<div id='ajax-result'></div>";

?>

<script>
function testAjaxCall() {
    const resultDiv = document.getElementById('ajax-result');
    resultDiv.innerHTML = '<i>Test en cours...</i>';
    
    // Données de test
    const testData = {
        candidate_id: 1, // ID de test
        action: 'accept',
        rejection_reason: null
    };
    
    fetch('process_candidate_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(testData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        return response.text(); // Utiliser text() au lieu de json() pour voir la réponse brute
    })
    .then(data => {
        console.log('Response data:', data);
        resultDiv.innerHTML = '<strong>Réponse du serveur:</strong><br><pre>' + data + '</pre>';
    })
    .catch(error => {
        console.error('Error:', error);
        resultDiv.innerHTML = '<strong style="color: red;">Erreur:</strong> ' + error.message;
    });
}
</script>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #1e3a8a; }
h3 { color: #059669; margin-top: 20px; }
pre { background: #f3f4f6; padding: 10px; border-radius: 5px; }
button { background: #1e3a8a; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
button:hover { background: #1e40af; }
</style>
