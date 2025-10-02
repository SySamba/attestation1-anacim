<?php
require_once 'config/database.php';

echo "<h2>Debug des résultats QCM</h2>";

// Vérifier les sessions QCM
$stmt = $pdo->query("SELECT c.prenom, c.nom, qs.score, qs.correct_answers, qs.total_questions, qs.status 
                     FROM candidates c 
                     LEFT JOIN qcm_sessions qs ON c.id = qs.candidate_id 
                     WHERE c.status = 'accepted'
                     ORDER BY c.id");

echo "<h3>Sessions QCM:</h3>";
echo "<table border='1'>";
echo "<tr><th>Candidat</th><th>Score</th><th>Bonnes réponses</th><th>Total questions</th><th>Statut session</th></tr>";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['prenom'] . ' ' . $row['nom']) . "</td>";
    echo "<td>" . ($row['score'] ?? 'NULL') . "%</td>";
    echo "<td>" . ($row['correct_answers'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['total_questions'] ?? 'NULL') . "</td>";
    echo "<td>" . ($row['status'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Vérifier les réponses individuelles
echo "<h3>Réponses individuelles (échantillon):</h3>";
$stmt = $pdo->query("SELECT qa.session_id, qa.question_id, qa.selected_answer, qa.is_correct 
                     FROM qcm_answers qa 
                     LIMIT 10");

echo "<table border='1'>";
echo "<tr><th>Session ID</th><th>Question ID</th><th>Réponse sélectionnée</th><th>Correct</th></tr>";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['session_id'] . "</td>";
    echo "<td>" . $row['question_id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['selected_answer']) . "</td>";
    echo "<td>" . ($row['is_correct'] ? 'OUI' : 'NON') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Vérifier les questions et leurs bonnes réponses
echo "<h3>Questions et bonnes réponses (échantillon):</h3>";
$stmt = $pdo->query("SELECT q.id, q.question_text, q.question_type, 
                            GROUP_CONCAT(CONCAT(c.choice_text, ' (', IF(c.is_correct, 'CORRECT', 'FAUX'), ')') SEPARATOR ' | ') as choices
                     FROM qcm_questions q 
                     LEFT JOIN qcm_choices c ON q.id = c.question_id 
                     GROUP BY q.id 
                     LIMIT 5");

echo "<table border='1'>";
echo "<tr><th>Question ID</th><th>Type</th><th>Question</th><th>Choix</th></tr>";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['question_type'] . "</td>";
    echo "<td>" . htmlspecialchars(substr($row['question_text'], 0, 100)) . "...</td>";
    echo "<td>" . htmlspecialchars($row['choices']) . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
