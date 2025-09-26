<?php
session_start();
require_once 'users.json';

$users = json_decode(file_get_contents('users.json'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    foreach ($users as $user) {
        if ($user['username'] === $username && password_verify($password, $user['password_hash'])) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'entreprenor') {
                $_SESSION['entreprenor_firma'] = $user['entreprenor_firma'];
            } else {
                unset($_SESSION['entreprenor_firma']);
            }

            header('Location: dashboard.php');
            exit();
        }
    }
    $error = 'Forkert brugernavn eller adgangskode';
}
?>
<!-- login form continues -->