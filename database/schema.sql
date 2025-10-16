-- Create database for ANACIM certification system
CREATE DATABASE IF NOT EXISTS anacim_certification;
USE anacim_certification;

-- Admin users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Candidates table
CREATE TABLE IF NOT EXISTS candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prenom VARCHAR(100) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    adresse TEXT,
    email VARCHAR(100),
    date_naissance DATE,
    lieu_naissance VARCHAR(100),
    date_contrat DATE,
    type_contrat VARCHAR(50),
    matricule VARCHAR(50) NOT NULL,
    categorie ENUM('1', '2', '3', '4', '5') NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    rejection_reason TEXT NULL,
    temp_password VARCHAR(255) NULL,
    qcm_access BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- Documents table for file uploads
CREATE TABLE IF NOT EXISTS candidate_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    document_type ENUM('cv', 'cni', 'attestation_formation', 'casier', 'certificat_medical', 'formation_base', 'formation_imagerie') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
);

-- QCM Questions table
CREATE TABLE IF NOT EXISTS qcm_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    question_type ENUM('single','multiple') NOT NULL DEFAULT 'single',
    phase ENUM('phase1', 'phase2') NOT NULL DEFAULT 'phase1',
    epreuve ENUM('THB', 'FBAG', 'PLP', 'FMAG', 'IMAGERIE') NOT NULL DEFAULT 'THB',
    category ENUM('1', '2', '3', '4', '5') NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- QCM Choices table (for flexible multiple choice support)
CREATE TABLE IF NOT EXISTS qcm_choices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    choice_text TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES qcm_questions(id) ON DELETE CASCADE
);

-- QCM Sessions table (for tracking candidate test sessions)
CREATE TABLE IF NOT EXISTS qcm_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    phase ENUM('phase1', 'phase2') NOT NULL DEFAULT 'phase1',
    epreuve ENUM('THB', 'FBAG', 'PLP', 'FMAG', 'IMAGERIE') NOT NULL DEFAULT 'THB',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    score DECIMAL(5,2) NULL,
    total_questions INT NOT NULL,
    correct_answers INT DEFAULT 0,
    status ENUM('in_progress', 'completed', 'expired') DEFAULT 'in_progress',
    time_limit_minutes INT DEFAULT 60,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
);

-- Table pour suivre les résultats par épreuve et phase
CREATE TABLE IF NOT EXISTS candidate_phase_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    phase ENUM('phase1', 'phase2') NOT NULL,
    epreuve ENUM('THB', 'FBAG', 'PLP', 'FMAG', 'IMAGERIE') NOT NULL,
    score DECIMAL(5,2) NULL,
    status ENUM('pending', 'passed', 'failed') DEFAULT 'pending',
    session_id INT NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES qcm_sessions(id) ON DELETE SET NULL,
    UNIQUE KEY unique_candidate_phase_epreuve (candidate_id, phase, epreuve)
);

-- QCM Answers table (for storing candidate answers)
CREATE TABLE IF NOT EXISTS qcm_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer VARCHAR(20) NOT NULL,
    is_correct BOOLEAN NOT NULL,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES qcm_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES qcm_questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session_question (session_id, question_id)
);

-- Tables pour le nouveau système d'examen d'imagerie interactif
CREATE TABLE IF NOT EXISTS imagerie_questions_interactive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(500) NOT NULL,
    correct_answer_type ENUM('pass', 'object_found') NOT NULL,
    object_category ENUM(
        'substances_explosives', 
        'objets_tranchants', 
        'outils_travail', 
        'equipements_projectiles', 
        'appareils_paralysants', 
        'instruments_contondants', 
        'lags'
    ) NULL,
    correct_x_position INT NULL,
    correct_y_position INT NULL,
    tolerance_radius INT DEFAULT 50,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS imagerie_sessions_interactive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status ENUM('in_progress', 'completed', 'timeout') DEFAULT 'in_progress',
    total_questions INT DEFAULT 0,
    correct_answers INT DEFAULT 0,
    score DECIMAL(5,2) NULL,
    time_spent INT NULL -- en secondes
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS imagerie_responses_interactive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    question_id INT NOT NULL,
    question_order INT NOT NULL,
    response_type ENUM('pass', 'object_found') NOT NULL,
    selected_category ENUM(
        'substances_explosives', 
        'objets_tranchants', 
        'outils_travail', 
        'equipements_projectiles', 
        'appareils_paralysants', 
        'instruments_contondants', 
        'lags'
    ) NULL,
    clicked_x_position INT NULL,
    clicked_y_position INT NULL,
    time_spent INT NOT NULL, -- temps passé sur cette question en secondes
    is_correct BOOLEAN NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES imagerie_sessions_interactive(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, password, email) VALUES 
('admin', '$2y$10$e0MYzXyjpJS7Pd0RVvHqHOmcYGdkwdJmjsRWwrjCOCOI8t7ZXJQSK', 'admin@anacim.sn');
