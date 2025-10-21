<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: view_wo.php');
    exit();
}

// Redirect to login page
header('Location: login.php');
exit();
