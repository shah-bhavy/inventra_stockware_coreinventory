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
<title>Forgot Password – Inventra</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="auth-logo-icon" style="background:none;padding:0;">
        <img src="assets/images/logo.png" alt="Inventra logo" style="width:40px;height:40px;border-radius:var(--r-lg);object-fit:cover;">
      </div>
      <div>
        <div class="auth-logo-name">Inventra</div>
        <div class="auth-logo-sub">StockWare</div>
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
