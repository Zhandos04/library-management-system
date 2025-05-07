<?php
// Include auth system
require_once 'config/database.php';
require_once 'includes/auth.php';

// Initialize database connection and auth
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Logout the user
$auth->logout();

// Redirect to login page
header("Location: login.php");
exit();
?>