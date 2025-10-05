<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Veuillez remplir tous les champs.";
        header('Location: admin_login.php');
        exit;
    }
    
    try {
        $sql = "SELECT id, username, password FROM admin_users WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $_SESSION['error'] = "Nom d'utilisateur ou mot de passe incorrect.";
            header('Location: admin_login.php');
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de connexion à la base de données.";
        header('Location: admin_login.php');
        exit;
    }
} else {
    header('Location: admin_login.php');
    exit;
}
?>
