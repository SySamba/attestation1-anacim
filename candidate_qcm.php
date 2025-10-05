<?php
session_start();
require_once 'config.php';

// Get candidate info and check access
$candidate_id = $_SESSION['candidate_id'] ?? null;
if (!$candidate_id) {
    header('Location: candidate_login.php');
    exit;
}

// Get phase and epreuve from URL parameters
$phase = $_GET['phase'] ?? 'phase1';
$epreuve = $_GET['epreuve'] ?? 'THB';

// Validate phase and epreuve
$valid_phases = ['phase1', 'phase2'];
$valid_epreuves = ['THB', 'THI', 'IMAGERIE'];

if (!in_array($phase, $valid_phases) || !in_array($epreuve, $valid_epreuves)) {
    header('Location: candidate_dashboard.php');
    exit;
}

// Check if epreuve matches phase
if ($phase === 'phase1' && !in_array($epreuve, ['THB', 'THI'])) {
    header('Location: candidate_dashboard.php');
    exit;
}
if ($phase === 'phase2' && $epreuve !== 'IMAGERIE') {
    header('Location: candidate_dashboard.php');
    exit;
}

// Get candidate info
$stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ? AND status = 'accepted'");
$stmt->execute([$candidate_id]);
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidate) {
    die('Candidat non trouvé ou non autorisé à passer le test');
}

// Validate access based on candidate category
if ($phase === 'phase1') {
    if ($candidate['categorie'] == '1' && $epreuve !== 'THI') {
        $_SESSION['error_message'] = "Les candidats de catégorie C1 doivent passer le test THI (Théorie Imagerie).";
        header('Location: candidate_dashboard.php');
        exit;
    }
    if (in_array($candidate['categorie'], ['2', '3', '4', '5']) && $epreuve !== 'THB') {
        $_SESSION['error_message'] = "Les candidats des catégories 2, 3, 4, 5 doivent passer le test THB (Théorie de Base).";
        header('Location: candidate_dashboard.php');
        exit;
    }
}

// Check if candidate already has a completed session for this specific phase/epreuve
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'completed' ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$candidate_id, $phase, $epreuve]);
$completed_session = $stmt->fetch(PDO::FETCH_ASSOC);

if ($completed_session) {
    $_SESSION['error_message'] = "Vous avez déjà passé le test " . $epreuve . ". Une seule tentative est autorisée par épreuve.";
    header('Location: candidate_dashboard.php');
    exit;
}

// Check for in-progress session
$stmt = $pdo->prepare("SELECT * FROM qcm_sessions WHERE candidate_id = ? AND phase = ? AND epreuve = ? AND status = 'in_progress' ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$candidate_id, $phase, $epreuve]);
$existing_session = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = "ANACIM - Test QCM - " . $candidate['prenom'] . " " . $candidate['nom'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --anacim-blue: #1e3a8a;
            --anacim-yellow: #fbbf24;
        }
        
        .navbar-anacim {
            background: linear-gradient(135deg, var(--anacim-blue) 0%, #3b82f6 100%);
        }
        
        .card-header-anacim {
            background: linear-gradient(135deg, var(--anacim-blue) 0%, #3b82f6 100%);
            color: white;
        }
        
        .btn-anacim {
            background: linear-gradient(135deg, var(--anacim-blue) 0%, #3b82f6 100%);
            border: none;
            color: white;
        }
        
        .btn-anacim:hover {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            color: white;
        }
        
        .question-card {
            border-left: 4px solid var(--anacim-blue);
        }
        
        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .progress-bar-anacim {
            background: linear-gradient(135deg, var(--anacim-blue) 0%, #3b82f6 100%);
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-anacim">
        <div class="container">
            <a class="navbar-brand text-white" href="#">
                <img src="logo-anacim.png" alt="ANACIM" height="40" class="me-2">
                ANACIM - Test QCM
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white">
                    <i class="fas fa-user me-2"></i>
                    <?php echo htmlspecialchars($candidate['prenom'] . ' ' . $candidate['nom']); ?>
                </span>
            </div>
        </div>
    </nav>

    <!-- Timer (will be shown during test) -->
    <div id="timer" class="timer" style="display: none;">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h5 class="card-title text-warning mb-1">
                    <i class="fas fa-clock"></i> Temps Restant
                </h5>
                <div id="time-display" class="h4 text-danger mb-0">60:00</div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <?php if ($existing_session && $existing_session['status'] === 'completed'): ?>
            <!-- Show results if test is completed -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header card-header-anacim text-center">
                            <h4><i class="fas fa-certificate"></i> Test Terminé</h4>
                        </div>
                        <div class="card-body text-center">
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h5>Félicitations ! Vous avez terminé le test QCM</h5>
                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Score</h6>
                                            <h3 class="text-primary"><?php echo number_format($existing_session['score'], 1); ?>%</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Bonnes Réponses</h6>
                                            <h3 class="text-success"><?php echo $existing_session['correct_answers']; ?>/<?php echo $existing_session['total_questions']; ?></h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Statut</h6>
                                            <h5 class="<?php echo $existing_session['score'] >= 80 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $existing_session['score'] >= 80 ? 'Réussi' : 'Échec'; ?>
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <p class="text-muted">
                                    Test terminé le <?php echo date('d/m/Y à H:i', strtotime($existing_session['completed_at'])); ?>
                                </p>
                                <?php if ($existing_session['score'] >= 80): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-trophy me-2"></i>
                                        Félicitations ! Vous avez réussi le test avec un score de <?php echo number_format($existing_session['score'], 1); ?>%.
                                        Votre certificat sera traité prochainement.
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Score insuffisant. Un score minimum de 80% est requis pour obtenir la certification.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        
        <?php elseif ($existing_session && $existing_session['status'] === 'in_progress'): ?>
            <!-- Continue existing test -->
            <div id="test-interface" style="display: block;">
                <!-- Test interface will be loaded here -->
            </div>
        
        <?php else: ?>
            <!-- Start new test -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header card-header-anacim text-center">
                            <h4><i class="fas fa-clipboard-list"></i> Test QCM - Certification Sûreté Aviation</h4>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <i class="fas fa-info-circle text-info" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="text-center mb-4">Bienvenue <?php echo htmlspecialchars($candidate['prenom']); ?></h5>
                            
                            <div class="alert alert-info">
                                <h6><i class="fas fa-exclamation-circle me-2"></i>Instructions importantes :</h6>
                                <ul class="mb-0">
                                    <li>Ce test contient des questions sur la sûreté aviation</li>
                                    <li>Durée limitée : <strong>60 minutes</strong></li>
                                    <li>Score minimum requis : <strong>80%</strong></li>
                                    <li>Une seule tentative autorisée</li>
                                    <li>Toutes les questions doivent être répondues</li>
                                </ul>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-clock me-2"></i>
                                <strong>Attention :</strong> Une fois le test commencé, le chronomètre ne peut pas être mis en pause.
                                Assurez-vous d'avoir suffisamment de temps avant de commencer.
                            </div>
                            
                            <div class="text-center">
                                <button id="start-test" class="btn btn-anacim btn-lg">
                                    <i class="fas fa-play me-2"></i>Commencer le Test
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Test interface (hidden initially) -->
            <div id="test-interface" style="display: none;">
                <!-- Will be populated by JavaScript -->
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentSession = null;
        let questions = [];
        let currentQuestionIndex = 0;
        let answers = {};
        let timeRemaining = 3600; // 60 minutes in seconds
        let timerInterval = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            const startButton = document.getElementById('start-test');
            if (startButton) {
                startButton.addEventListener('click', startTest);
            }
            
            <?php if ($existing_session && $existing_session['status'] === 'in_progress'): ?>
                // Resume existing test
                resumeTest(<?php echo $existing_session['id']; ?>);
            <?php endif; ?>
        });
        
        function startTest() {
            fetch('qcm_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'start_session',
                    candidate_id: <?php echo $candidate_id; ?>,
                    category: '<?php echo $candidate['categorie']; ?>',
                    phase: '<?php echo $phase; ?>',
                    epreuve: '<?php echo $epreuve; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentSession = data.session;
                    questions = data.questions;
                    startTimer();
                    showTestInterface();
                    loadQuestion(0);
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur de communication avec le serveur');
            });
        }
        
        function resumeTest(sessionId) {
            fetch('qcm_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'resume_session',
                    session_id: sessionId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentSession = data.session;
                    questions = data.questions;
                    answers = data.answers || {};
                    timeRemaining = data.time_remaining;
                    startTimer();
                    showTestInterface();
                    loadQuestion(0);
                } else {
                    alert('Erreur: ' + data.message);
                }
            });
        }
        
        function showTestInterface() {
            document.querySelector('.row.justify-content-center').style.display = 'none';
            document.getElementById('test-interface').style.display = 'block';
            document.getElementById('timer').style.display = 'block';
        }
        
        function startTimer() {
            updateTimerDisplay();
            timerInterval = setInterval(() => {
                timeRemaining--;
                updateTimerDisplay();
                
                if (timeRemaining <= 0) {
                    clearInterval(timerInterval);
                    submitTest();
                }
            }, 1000);
        }
        
        function updateTimerDisplay() {
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            document.getElementById('time-display').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
            // Change color when time is running low
            const display = document.getElementById('time-display');
            if (timeRemaining <= 300) { // 5 minutes
                display.className = 'h4 text-danger mb-0';
            } else if (timeRemaining <= 600) { // 10 minutes
                display.className = 'h4 text-warning mb-0';
            }
        }
        
        function loadQuestion(index) {
            currentQuestionIndex = index;
            const question = questions[index];
            
            const progressPercent = ((index + 1) / questions.length) * 100;
            
            document.getElementById('test-interface').innerHTML = `
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <div class="card question-card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Question ${index + 1} sur ${questions.length}</h5>
                                    <span class="badge bg-primary">Catégorie ${question.category}</span>
                                </div>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar progress-bar-anacim" style="width: ${progressPercent}%"></div>
                                </div>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title">${question.question_text}</h6>
                                <div class="mt-4">
                                    ${question.question_type === 'multiple' ? 
                                        `<div class="alert alert-info mb-3">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Question à choix multiples :</strong> Plusieurs réponses peuvent être correctes.
                                        </div>` : ''
                                    }
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="${question.question_type === 'multiple' ? 'checkbox' : 'radio'}" 
                                               name="${question.question_type === 'multiple' ? 'answer_multiple[]' : 'answer'}" 
                                               id="option_a" value="a" 
                                               ${question.question_type === 'multiple' ? 
                                                   (answers[question.id] && answers[question.id].includes('a') ? 'checked' : '') : 
                                                   (answers[question.id] === 'a' ? 'checked' : '')}>
                                        <label class="form-check-label" for="option_a">
                                            <strong>A.</strong> ${question.option_a}
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="${question.question_type === 'multiple' ? 'checkbox' : 'radio'}" 
                                               name="${question.question_type === 'multiple' ? 'answer_multiple[]' : 'answer'}" 
                                               id="option_b" value="b" 
                                               ${question.question_type === 'multiple' ? 
                                                   (answers[question.id] && answers[question.id].includes('b') ? 'checked' : '') : 
                                                   (answers[question.id] === 'b' ? 'checked' : '')}>
                                        <label class="form-check-label" for="option_b">
                                            <strong>B.</strong> ${question.option_b}
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="${question.question_type === 'multiple' ? 'checkbox' : 'radio'}" 
                                               name="${question.question_type === 'multiple' ? 'answer_multiple[]' : 'answer'}" 
                                               id="option_c" value="c" 
                                               ${question.question_type === 'multiple' ? 
                                                   (answers[question.id] && answers[question.id].includes('c') ? 'checked' : '') : 
                                                   (answers[question.id] === 'c' ? 'checked' : '')}>
                                        <label class="form-check-label" for="option_c">
                                            <strong>C.</strong> ${question.option_c}
                                        </label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="${question.question_type === 'multiple' ? 'checkbox' : 'radio'}" 
                                               name="${question.question_type === 'multiple' ? 'answer_multiple[]' : 'answer'}" 
                                               id="option_d" value="d" 
                                               ${question.question_type === 'multiple' ? 
                                                   (answers[question.id] && answers[question.id].includes('d') ? 'checked' : '') : 
                                                   (answers[question.id] === 'd' ? 'checked' : '')}>
                                        <label class="form-check-label" for="option_d">
                                            <strong>D.</strong> ${question.option_d}
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between">
                                    <button class="btn btn-outline-secondary" onclick="previousQuestion()" ${index === 0 ? 'disabled' : ''}>
                                        <i class="fas fa-chevron-left me-2"></i>Précédent
                                    </button>
                                    <div>
                                        ${index < questions.length - 1 ? 
                                            `<button class="btn btn-anacim" onclick="nextQuestion()">
                                                Suivant<i class="fas fa-chevron-right ms-2"></i>
                                            </button>` :
                                            `<button class="btn btn-success" onclick="submitTest()">
                                                <i class="fas fa-check me-2"></i>Terminer le Test
                                            </button>`
                                        }
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add event listeners for radio buttons and checkboxes
            document.querySelectorAll('input[name="answer"]').forEach(radio => {
                radio.addEventListener('change', saveAnswer);
            });
            document.querySelectorAll('input[name="answer_multiple[]"]').forEach(checkbox => {
                checkbox.addEventListener('change', saveAnswer);
            });
        }
        
        function saveAnswer() {
            const questionId = questions[currentQuestionIndex].id;
            const question = questions[currentQuestionIndex];
            
            if (question.question_type === 'multiple') {
                // Pour les questions à choix multiples
                const selectedAnswers = Array.from(document.querySelectorAll('input[name="answer_multiple[]"]:checked'))
                    .map(input => input.value);
                
                if (selectedAnswers.length > 0) {
                    answers[questionId] = selectedAnswers;
                    
                    // Save to server
                    fetch('qcm_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'save_answer',
                            session_id: currentSession.id,
                            question_id: questionId,
                            answer: selectedAnswers,
                            question_type: 'multiple'
                        })
                    });
                }
            } else {
                // Pour les questions à choix unique
                const selectedAnswer = document.querySelector('input[name="answer"]:checked');
                
                if (selectedAnswer) {
                    answers[questionId] = selectedAnswer.value;
                    
                    // Save to server
                    fetch('qcm_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'save_answer',
                            session_id: currentSession.id,
                            question_id: questionId,
                            answer: selectedAnswer.value,
                            question_type: 'single'
                        })
                    });
                }
            }
        }
        
        function nextQuestion() {
            if (currentQuestionIndex < questions.length - 1) {
                loadQuestion(currentQuestionIndex + 1);
            }
        }
        
        function previousQuestion() {
            if (currentQuestionIndex > 0) {
                loadQuestion(currentQuestionIndex - 1);
            }
        }
        
        function submitTest() {
            if (confirm('Êtes-vous sûr de vouloir terminer le test ? Cette action est irréversible.')) {
                clearInterval(timerInterval);
                
                fetch('qcm_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'submit_test',
                        session_id: currentSession.id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        // Prevent page refresh/close during test
        window.addEventListener('beforeunload', function(e) {
            if (currentSession && timerInterval) {
                e.preventDefault();
                e.returnValue = 'Êtes-vous sûr de vouloir quitter le test ? Votre progression sera perdue.';
                return e.returnValue;
            }
        });

        // Prevent right-click context menu
        document.addEventListener('contextmenu', function(e) {
            if (currentSession && timerInterval) {
                e.preventDefault();
            }
        });

        // Prevent F12, Ctrl+Shift+I, Ctrl+U
        document.addEventListener('keydown', function(e) {
            if (currentSession && timerInterval) {
                // F12
                if (e.keyCode === 123) {
                    e.preventDefault();
                    return false;
                }
                // Ctrl+Shift+I
                if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
                    e.preventDefault();
                    return false;
                }
                // Ctrl+U
                if (e.ctrlKey && e.keyCode === 85) {
                    e.preventDefault();
                    return false;
                }
                // Ctrl+Shift+C
                if (e.ctrlKey && e.shiftKey && e.keyCode === 67) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Detect when user leaves/returns to tab
        document.addEventListener('visibilitychange', function() {
            if (currentSession && timerInterval) {
                if (document.hidden) {
                    // User left the tab - could pause timer or log this
                    console.log('Candidat a quitté l\'onglet');
                } else {
                    // User returned to tab
                    console.log('Candidat est revenu à l\'onglet');
                }
            }
        });

        // Prevent text selection during test
        document.addEventListener('selectstart', function(e) {
            if (currentSession && timerInterval) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
