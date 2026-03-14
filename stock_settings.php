<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Stock Settings';
$activeNav = 'stock_settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $rateRaw = trim($_POST['global_low_stock_threshold'] ?? '');
    $rate    = (float)$rateRaw;
    if ($rate < 0) {
        $rate = 0;
    }
    setSetting('global_low_stock_threshold', (string)$rate);
    flash('success', 'Low stock threshold updated.');
    redirect('stock_settings.php');
}

$currentThreshold = (float)(getSetting('global_low_stock_threshold', '0') ?? '0');

include 'includes/header.php';
?>

<?php $s = getFlash('success'); if ($s): ?>
<div class="alert alert-success" data-auto><i data-lucide="check-circle"></i> <?= e($s) ?></div>
<?php endif; ?>
<?php $err = getFlash('error'); if ($err): ?>
<div class="alert alert-danger" data-auto><i data-lucide="alert-circle"></i> <?= e($err) ?></div>
<?php endif; ?>

<div class="page-header">
  <div class="page-header-text">
    <h1>Stock Settings</h1>
    <p>Control how low-stock alerts are calculated</p>
  </div>
</div>

<div class="card" style="max-width:520px;">
  <div class="card-body">
    <form method="POST" action="stock_settings.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <div class="form-group">
        <label>Global low-stock threshold</label>
        <input type="number" name="global_low_stock_threshold" class="form-control" min="0" step="0.01" value="<?= $currentThreshold ?>">
        <p class="text-secondary text-sm" style="margin-top:6px;">
          If set greater than 0, a product is considered <strong>low stock</strong> when its total quantity is less than or equal to this value.
          If set to 0, the system will instead use each product's individual <em>Reorder Quantity</em> as configured on the Products page.
        </p>
      </div>
      <div class="form-actions" style="margin-top:12px;">
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Settings</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
