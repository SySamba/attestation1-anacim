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
    matricule VARCHAR(50),
    categorie ENUM('1', '2', '3', '4', '5') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, password, email) VALUES 
('admin', '$2y$10$e0MYzXyjpJS7Pd0RVvHqHOmcYGdkwdJmjsRWwrjCOCOI8t7ZXJQSK', 'admin@anacim.sn');
