<?php
$host = 'localhost';
$dbname = 'anacim_certification';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== CORRECTION DU BUG DE SCORING ===\n\n";
    
    // 1. Analyser le problème avec les IDs des choix
    echo "1. Analyse du problème:\n";
    $stmt = $pdo->prepare("SELECT q.id as question_id, q.question_text, c.id as choice_id, c.choice_text, c.is_correct 
                          FROM qcm_questions q 
                          JOIN qcm_choices c ON q.id = c.question_id 
                          ORDER BY q.id, c.id");
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $questions = [];
    foreach ($data as $row) {
        $questions[$row['question_id']][] = $row;
    }
    
    foreach ($questions as $q_id => $choices) {
        echo "\nQuestion $q_id:\n";
        $first_choice_id = $choices[0]['choice_id'];
        
        foreach ($choices as $index => $choice) {
            $expected_letter = chr(97 + $index); // a, b, c, d basé sur l'index
            $actual_letter = chr(97 + ($choice['choice_id'] - $first_choice_id)); // basé sur l'ID réel
            $correct = $choice['is_correct'] ? '✓' : '';
            
            echo "  Index $index -> Lettre attendue: $expected_letter, Lettre réelle: $actual_letter {$choice['choice_text']} $correct\n";
        }
    }
    
    echo "\n2. Le problème identifié:\n";
    echo "Le code utilise l'index du tableau (0,1,2,3) pour calculer les lettres (a,b,c,d)\n";
    echo "Mais il devrait utiliser la position relative des IDs dans la base de données\n\n";
    
    echo "3. Test de la correction:\n";
    // Simuler la logique corrigée
    $test_question_id = array_keys($questions)[0];
    $test_choices = $questions[$test_question_id];
    
    echo "Test avec question $test_question_id:\n";
    
    // Ancienne logique (incorrecte)
    echo "Ancienne logique:\n";
    foreach ($test_choices as $index => $choice) {
        $letter = chr(97 + $index);
        echo "  Choix $index -> $letter: {$choice['choice_text']} " . ($choice['is_correct'] ? '✓' : '') . "\n";
    }
    
    // Nouvelle logique (correcte)
    echo "\nNouvelle logique (basée sur l'ordre des IDs):\n";
    $sorted_choices = $test_choices;
    usort($sorted_choices, function($a, $b) { return $a['choice_id'] - $b['choice_id']; });
    
    foreach ($sorted_choices as $index => $choice) {
        $letter = chr(97 + $index);
        echo "  ID {$choice['choice_id']} -> $letter: {$choice['choice_text']} " . ($choice['is_correct'] ? '✓' : '') . "\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>
