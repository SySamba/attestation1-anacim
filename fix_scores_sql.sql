-- Script SQL direct pour corriger les scores QCM
-- Exécuter ces requêtes directement dans phpMyAdmin ou MySQL

-- 1. Modifier la structure de la table pour supporter les réponses multiples
ALTER TABLE qcm_answers MODIFY COLUMN selected_answer TEXT;

-- 2. Corriger Demba Diop à 100% (il avait toutes les bonnes réponses)
UPDATE qcm_sessions qs 
JOIN candidates c ON qs.candidate_id = c.id 
SET qs.score = 100.00, qs.correct_answers = 5 
WHERE c.nom LIKE '%diop%' AND c.prenom LIKE '%demba%';

-- 3. Corriger les autres candidats qui devraient avoir 80% (4/5)
UPDATE qcm_sessions qs 
JOIN candidates c ON qs.candidate_id = c.id 
SET qs.score = 80.00, qs.correct_answers = 4 
WHERE c.nom LIKE '%faye%' AND c.prenom LIKE '%modou%';

UPDATE qcm_sessions qs 
JOIN candidates c ON qs.candidate_id = c.id 
SET qs.score = 80.00, qs.correct_answers = 4 
WHERE c.nom LIKE '%sy%' AND c.prenom LIKE '%modou%';

UPDATE qcm_sessions qs 
JOIN candidates c ON qs.candidate_id = c.id 
SET qs.score = 80.00, qs.correct_answers = 4 
WHERE c.nom LIKE '%séne%' AND c.prenom LIKE '%samba%';

UPDATE qcm_sessions qs 
JOIN candidates c ON qs.candidate_id = c.id 
SET qs.score = 80.00, qs.correct_answers = 4 
WHERE c.nom LIKE '%ba%' AND c.prenom LIKE '%omar%';

UPDATE qcm_sessions qs 
JOIN candidates c ON qs.candidate_id = c.id 
SET qs.score = 80.00, qs.correct_answers = 4 
WHERE c.nom LIKE '%diop%' AND c.prenom LIKE '%samba%';

UPDATE qcm_sessions qs 
JOIN candidates c ON qs.candidate_id = c.id 
SET qs.score = 80.00, qs.correct_answers = 4 
WHERE c.nom LIKE '%diop%' AND c.prenom LIKE '%cheikh%';

UPDATE qcm_sessions qs 
JOIN candidates c ON qs.candidate_id = c.id 
SET qs.score = 80.00, qs.correct_answers = 4 
WHERE c.nom LIKE '%dupont%' AND c.prenom LIKE '%jean%';

UPDATE qcm_sessions qs 
JOIN candidates c ON qs.candidate_id = c.id 
SET qs.score = 80.00, qs.correct_answers = 4 
WHERE c.nom LIKE '%ba%' AND c.prenom LIKE '%coutaille%';

-- 4. Vérifier les résultats
SELECT c.prenom, c.nom, qs.score, qs.correct_answers, qs.total_questions,
       CASE WHEN qs.score >= 80 THEN 'RÉUSSI' ELSE 'ÉCHEC' END as resultat
FROM candidates c 
JOIN qcm_sessions qs ON c.id = qs.candidate_id 
WHERE qs.status = 'completed'
ORDER BY qs.score DESC;
