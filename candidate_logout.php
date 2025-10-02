<?php
session_start();

// Clear all candidate session variables
unset($_SESSION['candidate_logged_in']);
unset($_SESSION['candidate_id']);
unset($_SESSION['candidate_name']);
unset($_SESSION['candidate_email']);
unset($_SESSION['candidate_category']);

// Destroy the session
session_destroy();

// Redirect to candidate login
header('Location: candidate_login.php');
exit;
?>
