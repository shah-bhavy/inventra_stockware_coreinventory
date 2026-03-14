<?php
// Auth gate – include at the top of every protected page
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
