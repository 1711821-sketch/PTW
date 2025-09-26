<?php
// Log the user out by destroying the session and redirecting to the login page
session_start();
session_unset();
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;