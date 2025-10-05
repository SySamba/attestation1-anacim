<?php
session_start();
require_once 'config.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$type = $_GET['type'] ?? 'all';
$category = $_GET['category'] ?? null;

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Build query based on type
    if ($type === 'category' && $category) {
        $sql = "SELECT prenom, nom, adresse, email, date_naissance, lieu_naissance, 
                       date_contrat, type_contrat, matricule, categorie, created_at
                FROM candidates 
                WHERE categorie = :category 
                ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':category', $category);
        $filename = "candidats_categorie_{$category}_" . date('Y-m-d_H-i-s') . ".csv";
    } else {
        $sql = "SELECT prenom, nom, adresse, email, date_naissance, lieu_naissance, 
                       date_contrat, type_contrat, matricule, categorie, created_at
                FROM candidates 
                ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $filename = "tous_candidats_" . date('Y-m-d_H-i-s') . ".csv";
    }
    
    $stmt->execute();
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV headers
    $headers = [
        'Prénom',
        'Nom',
        'Adresse',
        'Email',
        'Date de Naissance',
        'Lieu de Naissance',
        'Date de Contrat',
        'Type de Contrat',
        'Matricule',
        'Catégorie',
        'Date de Soumission'
    ];
    
    fputcsv($output, $headers, ';');
    
    // Add data rows
    foreach ($candidates as $candidate) {
        $row = [
            $candidate['prenom'],
            $candidate['nom'],
            $candidate['adresse'],
            $candidate['email'],
            $candidate['date_naissance'] ? date('d/m/Y', strtotime($candidate['date_naissance'])) : '',
            $candidate['lieu_naissance'],
            $candidate['date_contrat'] ? date('d/m/Y', strtotime($candidate['date_contrat'])) : '',
            $candidate['type_contrat'],
            $candidate['matricule'],
            'Catégorie ' . $candidate['categorie'],
            date('d/m/Y H:i', strtotime($candidate['created_at']))
        ];
        
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit();
    
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>
