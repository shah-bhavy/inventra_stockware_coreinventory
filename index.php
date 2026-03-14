<?php
// ── Login page ────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!empty($_SESSION['user_id'])) redirect('dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verifyCsrf();
  $loginId  = trim($_POST['login_id']  ?? '');
  $password = trim($_POST['password'] ?? '');

  if ($loginId === '' || $password === '') {
    $error = 'Please fill in all fields.';
  } else {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE login_id = ?');
    $stmt->execute([$loginId]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
      session_regenerate_id(true);
      $_SESSION['user_id']    = $user['id'];
      $_SESSION['user_name']  = $user['name'];
      $_SESSION['user_email'] = $user['email'];
      redirect('dashboard.php');
    } else {
      $error = 'Invalid Login Id or Password';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – CoreInventory</title>
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

    <h1 class="auth-heading">Welcome back</h1>
    <p class="auth-subheading">Sign in to manage your inventory</p>

    <?php if ($error): ?>
    <div class="alert alert-danger">
      <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <?= e($error) ?>
    </div>
    <?php endif; ?>

    <?php $msg = getFlash('success'); if ($msg): ?>
    <div class="alert alert-success" data-auto>
      <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24"
           fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
      <?= e($msg) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="index.php">
      <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">

      <div class="form-group">
        <label for="login_id">Login ID</label>
        <input type="text" id="login_id" name="login_id" class="form-control"
               placeholder="Enter Login ID" value="<?= e($_POST['login_id'] ?? '') ?>" required autofocus>
      </div>

      <div class="form-group" style="margin-bottom: 24px;">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-primary btn-lg w-full">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
          <polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
        </svg>
        Sign In
      </button>
    </form>

    <p class="auth-footer">
      Don't have an account? <a href="register.php">Sign up</a> ·
      <a href="forgot_password.php">Forgot password?</a>
    </p>
  </div>
</div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
