<?php
// Simple placeholder Forgot Password page
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password – CoreInventory</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="auth-logo-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.2"
             stroke-linecap="round" stroke-linejoin="round">
          <rect width="20" height="5" x="2" y="3" rx="1"/>
          <path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/>
          <path d="M10 12h4"/>
        </svg>
      </div>
      <div>
        <div class="auth-logo-name">CoreInventory</div>
        <div class="auth-logo-sub">Stock Management System</div>
      </div>
    </div>

    <h1 class="auth-heading">Forgot password</h1>
    <p class="auth-subheading">Password reset is not configured yet. Please contact your administrator to reset your password.</p>

    <p class="auth-footer">
      <a href="index.php">Back to login</a>
    </p>
  </div>
</div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
