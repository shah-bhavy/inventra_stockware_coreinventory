<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'My Profile';
$activeNav = 'profile';
$userId    = (int)$_SESSION['user_id'];
$db        = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name']  ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!$name || !$email) {
            flash('error', 'Name and email are required.');
            redirect('profile.php');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Invalid email address.');
            redirect('profile.php');
        }
        // Check uniqueness
        $existing = $db->prepare("SELECT id FROM users WHERE email=? AND id != ?");
        $existing->execute([$email, $userId]);
        if ($existing->fetch()) {
            flash('error', 'Email is already taken by another account.');
            redirect('profile.php');
        }
        $db->prepare("UPDATE users SET name=?, email=? WHERE id=?")
           ->execute([$name, $email, $userId]);
        $_SESSION['user_name']  = $name;
        $_SESSION['user_email'] = $email;
        flash('success', 'Profile updated successfully.');
        redirect('profile.php');
    }

    if ($action === 'change_password') {
        $current  = $_POST['current_password']  ?? '';
        $newPass  = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        if (!$current || !$newPass || !$confirm) {
            flash('error', 'All password fields are required.');
            redirect('profile.php');
        }
        if ($newPass !== $confirm) {
            flash('error', 'New passwords do not match.');
            redirect('profile.php');
        }
        if (strlen($newPass) < 8) {
            flash('error', 'New password must be at least 8 characters.');
            redirect('profile.php');
        }

        $row = $db->prepare("SELECT password FROM users WHERE id=?");
        $row->execute([$userId]);
        $hash = $row->fetchColumn();

        if (!password_verify($current, $hash)) {
            flash('error', 'Current password is incorrect.');
            redirect('profile.php');
        }
        $db->prepare("UPDATE users SET password=? WHERE id=?")
           ->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
        flash('success', 'Password changed successfully.');
        redirect('profile.php');
    }

    redirect('profile.php');
}

$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Activity summary
$receiptsCreated   = $db->prepare("SELECT COUNT(*) FROM receipts   WHERE created_by=?"); $receiptsCreated->execute([$userId]);
$deliveriesCreated = $db->prepare("SELECT COUNT(*) FROM deliveries WHERE created_by=?"); $deliveriesCreated->execute([$userId]);
$transfersCreated  = $db->prepare("SELECT COUNT(*) FROM transfers  WHERE created_by=?"); $transfersCreated->execute([$userId]);
$adjustCreated     = $db->prepare("SELECT COUNT(*) FROM adjustments WHERE created_by=?"); $adjustCreated->execute([$userId]);

include 'includes/header.php';
?>

<?php $succ = getFlash('success'); if ($succ): ?>
<div class="alert alert-success" data-auto><i data-lucide="check-circle"></i> <?= e($succ) ?></div>
<?php endif; ?>
<?php $err = getFlash('error'); if ($err): ?>
<div class="alert alert-danger" data-auto><i data-lucide="alert-circle"></i> <?= e($err) ?></div>
<?php endif; ?>

<div class="page-header">
  <div class="page-header-text">
    <h1>My Profile</h1>
    <p>Manage your account information and password</p>
  </div>
</div>

<div class="profile-layout">

  <!-- Left column: avatar + stats -->
  <div class="profile-sidebar-card">
    <div class="profile-avatar-wrap">
      <div class="profile-avatar-large"><?= mb_strtoupper(mb_substr($user['name'] ?? 'U', 0, 1)) ?></div>
      <div class="profile-name"><?= e($user['name'] ?? '') ?></div>
      <div class="profile-email"><?= e($user['email'] ?? '') ?></div>
      <div class="profile-since">Member since <?= date('M Y', strtotime($user['created_at'] ?? 'now')) ?></div>
    </div>

    <div class="profile-stats">
      <div class="profile-stat">
        <div class="profile-stat-val"><?= (int)$receiptsCreated->fetchColumn() ?></div>
        <div class="profile-stat-lbl">Receipts</div>
      </div>
      <div class="profile-stat">
        <div class="profile-stat-val"><?= (int)$deliveriesCreated->fetchColumn() ?></div>
        <div class="profile-stat-lbl">Deliveries</div>
      </div>
      <div class="profile-stat">
        <div class="profile-stat-val"><?= (int)$transfersCreated->fetchColumn() ?></div>
        <div class="profile-stat-lbl">Transfers</div>
      </div>
      <div class="profile-stat">
        <div class="profile-stat-val"><?= (int)$adjustCreated->fetchColumn() ?></div>
        <div class="profile-stat-lbl">Adjustments</div>
      </div>
    </div>
  </div>

  <!-- Right column: edit forms -->
  <div class="profile-forms">

    <!-- Update profile info -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i data-lucide="user" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px;"></i>Account Information</span>
      </div>
      <div class="card-body">
        <form method="POST" action="profile.php">
          <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="action"  value="update_profile">
          <div class="form-grid">
            <div class="form-group">
              <label>Full Name *</label>
              <input type="text" name="name" class="form-control"
                     value="<?= e($user['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label>Email Address *</label>
              <input type="email" name="email" class="form-control"
                     value="<?= e($user['email'] ?? '') ?>" required>
            </div>
          </div>
          <div style="display:flex;justify-content:flex-end;margin-top:4px;">
            <button type="submit" class="btn btn-primary">
              <i data-lucide="save"></i> Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Change password -->
    <div class="card" style="margin-top:20px;">
      <div class="card-header">
        <span class="card-title"><i data-lucide="lock" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px;"></i>Change Password</span>
      </div>
      <div class="card-body">
        <form method="POST" action="profile.php">
          <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="action"  value="change_password">
          <div class="form-group">
            <label>Current Password *</label>
            <input type="password" name="current_password" class="form-control"
                   autocomplete="current-password" placeholder="Enter current password" required>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label>New Password *</label>
              <input type="password" name="new_password" class="form-control"
                     autocomplete="new-password" placeholder="Min. 8 characters" required>
            </div>
            <div class="form-group">
              <label>Confirm New Password *</label>
              <input type="password" name="confirm_password" class="form-control"
                     autocomplete="new-password" placeholder="Repeat new password" required>
            </div>
          </div>
          <div style="display:flex;justify-content:flex-end;margin-top:4px;">
            <button type="submit" class="btn btn-primary">
              <i data-lucide="shield-check"></i> Update Password
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Danger zone -->
    <div class="card" style="margin-top:20px;border-color:var(--danger-light);">
      <div class="card-header" style="background:var(--danger-light);">
        <span class="card-title" style="color:var(--danger-text);">
          <i data-lucide="alert-triangle" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px;"></i>
          Session
        </span>
      </div>
      <div class="card-body">
        <p class="text-secondary" style="margin-bottom:16px;font-size:13.5px;">
          Sign out from all devices by logging out now.
        </p>
        <a href="logout.php" class="btn btn-danger">
          <i data-lucide="log-out"></i> Log Out
        </a>
      </div>
    </div>

  </div><!-- .profile-forms -->
</div><!-- .profile-layout -->

<style>
.profile-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 24px;
  align-items: start;
}
.profile-sidebar-card {
  background: var(--card);
  border-radius: var(--r-xl);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}
.profile-avatar-wrap {
  padding: 32px 24px 24px;
  text-align: center;
  background: linear-gradient(135deg,#eef2ff 0%,#f1f5f9 100%);
  border-bottom: 1px solid var(--border);
}
.profile-avatar-large {
  width: 72px; height: 72px;
  border-radius: 50%;
  background: var(--primary);
  color: white;
  font-size: 28px;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 14px;
  box-shadow: 0 4px 12px rgba(99,102,241,0.3);
}
.profile-name {
  font-size: 17px; font-weight: 700; color: var(--text); margin-bottom: 4px;
}
.profile-email { font-size: 13px; color: var(--text-secondary); margin-bottom: 8px; }
.profile-since { font-size: 12px; color: var(--text-muted); }
.profile-stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  text-align: center;
}
.profile-stat {
  padding: 18px 12px;
  border-right: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
}
.profile-stat:nth-child(even) { border-right: none; }
.profile-stat:nth-child(3), .profile-stat:nth-child(4) { border-bottom: none; }
.profile-stat-val { font-size: 22px; font-weight: 700; color: var(--text); }
.profile-stat-lbl { font-size: 11px; color: var(--text-secondary); margin-top: 2px; text-transform: uppercase; letter-spacing: 0.05em; }
@media(max-width:768px){
  .profile-layout { grid-template-columns: 1fr; }
}
</style>

<?php include 'includes/footer.php'; ?>
