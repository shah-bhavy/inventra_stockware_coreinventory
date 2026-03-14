<?php
// ── Register page ─────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!empty($_SESSION['user_id'])) redirect('dashboard.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verifyCsrf();
  $loginId  = trim($_POST['login_id']  ?? '');
  $email    = trim($_POST['email']     ?? '');
  $password = trim($_POST['password']  ?? '');
  $confirm  = trim($_POST['confirm']   ?? '');

  if ($loginId === '' || $email === '' || $password === '' || $confirm === '') {
    $error = 'Please fill in all fields.';
  } elseif (!preg_match('/^[A-Za-z0-9_]{6,12}$/', $loginId)) {
    $error = 'Login ID must be 6-12 characters (letters, numbers or underscore).';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } elseif (strlen($password) <= 8
    || !preg_match('/[a-z]/', $password)
    || !preg_match('/[A-Z]/', $password)
    || !preg_match('/[^a-zA-Z0-9]/', $password)) {
    $error = 'Password must be more than 8 characters and include lowercase, uppercase and a special character.';
  } elseif (strcasecmp($password, $loginId) === 0) {
    $error = 'Password must be different from the Login ID.';
  } elseif ($password !== $confirm) {
    $error = 'Passwords do not match.';
  } else {
    $db = getDB();

    // Check unique login ID
    $st = $db->prepare('SELECT id FROM users WHERE login_id = ?');
    $st->execute([$loginId]);
    if ($st->fetch()) {
      $error = 'That Login ID is already taken.';
    } else {
      // Check unique email
      $exists = $db->prepare('SELECT id FROM users WHERE email = ?');
      $exists->execute([strtolower($email)]);

      if ($exists->fetch()) {
        $error = 'An account with that email already exists.';
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare('INSERT INTO users (login_id, name, email, password) VALUES (?,?,?,?)')
           ->execute([$loginId, $loginId, strtolower($email), $hash]);
        flash('success', 'Account created. Please sign in.');
        redirect('index.php');
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account – CoreInventory</title>
<title>Create Account – Inventra</title>
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

    <h1 class="auth-heading">Create your account</h1>
    <p class="auth-subheading">Start managing your inventory today</p>

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

        <form method="POST" action="register.php">
      <input type="hidden" name="_csrf" value="<?= e(csrfToken()) ?>">

      <div class="form-group">
       <label for="login_id">Login ID</label>
       <input type="text" id="login_id" name="login_id" class="form-control"
         placeholder="6-12 characters" value="<?= e($_POST['login_id'] ?? '') ?>" required autofocus>
      </div>

      <div class="form-group">
       <label for="email">Email ID</label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="you@company.com" value="<?= e($_POST['email'] ?? '') ?>" required>
      </div>

      <div class="form-group">
         <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control"
           placeholder="More than 8 characters with a-z, A-Z and special" required>
      </div>

      <div class="form-group" style="margin-bottom: 24px;">
        <label for="confirm">Confirm password</label>
        <input type="password" id="confirm" name="confirm" class="form-control"
               placeholder="Repeat password" required>
      </div>

      <button type="submit" class="btn btn-primary btn-lg w-full">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
        </svg>
        Create Account
      </button>
    </form>

    <p class="auth-footer">
      Already have an account? <a href="index.php">Sign in</a>
    </p>
  </div>
</div>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
