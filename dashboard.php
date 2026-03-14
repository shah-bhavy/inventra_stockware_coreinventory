<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

$db = getDB();

// KPIs
$totalProducts   = totalProducts();
$totalWarehouses = totalWarehouses();
$totalCustomers  = totalCustomers();
$lowStock        = lowStockCount();
$outOfStock      = outOfStockCount();
$pendingRec      = pendingCount('receipts');
$pendingDel      = pendingCount('deliveries');

// Recent receipts
$recentReceipts = $db->query("
    SELECT r.*, u.name user_name FROM receipts r
    LEFT JOIN users u ON u.id = r.created_by
    ORDER BY r.created_at DESC LIMIT 5
")->fetchAll();

// Recent deliveries
$recentDeliveries = $db->query("
    SELECT d.*, u.name user_name FROM deliveries d
    LEFT JOIN users u ON u.id = d.created_by
    ORDER BY d.created_at DESC LIMIT 5
")->fetchAll();

// Low stock products
$lowStockProducts = $db->query("
    SELECT p.name, p.sku, p.unit_of_measure, p.reorder_qty,
           COALESCE(SUM(s.qty),0) total_qty
    FROM products p
    LEFT JOIN stock_levels s ON s.product_id = p.id
    WHERE p.active = 1
    GROUP BY p.id
    HAVING total_qty <= p.reorder_qty AND p.reorder_qty > 0
    ORDER BY total_qty ASC
    LIMIT 6
")->fetchAll();

include 'includes/header.php';
?>

<!-- Flash -->
<?php $s = getFlash('success'); if ($s): ?>
<div class="alert alert-success" data-auto>
  <i data-lucide="check-circle"></i> <?= e($s) ?>
</div>
<?php endif; ?>

<!-- KPI Grid -->
<div class="kpi-grid">

  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">Warehouses</span>
      <div class="kpi-icon blue"><i data-lucide="warehouse"></i></div>
    </div>
    <div class="kpi-value"><?= $totalWarehouses ?></div>
    <div class="kpi-sub">Active warehouse sites</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">Customers</span>
      <div class="kpi-icon green"><i data-lucide="users"></i></div>
    </div>
    <div class="kpi-value"><?= $totalCustomers ?></div>
    <div class="kpi-sub">Active customers</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">Total Products</span>
      <div class="kpi-icon indigo"><i data-lucide="package"></i></div>
    </div>
    <div class="kpi-value"><?= $totalProducts ?></div>
    <div class="kpi-sub">Active SKUs in system</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">Low Stock</span>
      <div class="kpi-icon yellow"><i data-lucide="alert-triangle"></i></div>
    </div>
    <div class="kpi-value"><?= $lowStock ?></div>
    <div class="kpi-sub">At or below reorder level</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">Out of Stock</span>
      <div class="kpi-icon red"><i data-lucide="x-circle"></i></div>
    </div>
    <div class="kpi-value"><?= $outOfStock ?></div>
    <div class="kpi-sub">Zero quantity items</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">Pending Receipts</span>
      <div class="kpi-icon blue"><i data-lucide="inbox"></i></div>
    </div>
    <div class="kpi-value"><?= $pendingRec ?></div>
    <div class="kpi-sub">Awaiting validation</div>
  </div>

  <div class="kpi-card">
    <div class="kpi-header">
      <span class="kpi-label">Pending Deliveries</span>
      <div class="kpi-icon green"><i data-lucide="truck"></i></div>
    </div>
    <div class="kpi-value"><?= $pendingDel ?></div>
    <div class="kpi-sub">Awaiting dispatch</div>
  </div>

</div>
<!-- Row 2: Recent Receipts + Low Stock -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">

  <!-- Recent Receipts -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i data-lucide="inbox" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px;"></i>Recent Receipts</span>
      <a href="receipts.php" class="btn btn-secondary btn-sm">View all</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Reference</th>
            <th>Supplier</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentReceipts)): ?>
          <tr><td colspan="4">
            <div class="empty-state" style="padding:24px;">
              <div class="empty-icon"><i data-lucide="inbox"></i></div>
              <p>No receipts yet</p>
            </div>
          </td></tr>
          <?php else: ?>
            <?php foreach ($recentReceipts as $r): ?>
            <tr>
              <td><a href="receipts.php?id=<?= $r['id'] ?>" class="fw-600 font-mono" style="color:var(--primary);text-decoration:none;"><?= e($r['reference']) ?></a></td>
              <td><?= e($r['supplier'] ?: '—') ?></td>
              <td><?= statusBadge($r['status']) ?></td>
              <td class="text-secondary text-sm"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Deliveries -->
  <div class="card">
    <div class="card-header">
      <span class="card-title"><i data-lucide="truck" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px;"></i>Recent Deliveries</span>
      <a href="deliveries.php" class="btn btn-secondary btn-sm">View all</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Reference</th>
            <th>Customer</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentDeliveries)): ?>
          <tr><td colspan="4">
            <div class="empty-state" style="padding:24px;">
              <div class="empty-icon"><i data-lucide="truck"></i></div>
              <p>No deliveries yet</p>
            </div>
          </td></tr>
          <?php else: ?>
            <?php foreach ($recentDeliveries as $d): ?>
            <tr>
              <td><a href="deliveries.php?id=<?= $d['id'] ?>" class="fw-600 font-mono" style="color:var(--primary);text-decoration:none;"><?= e($d['reference']) ?></a></td>
              <td><?= e($d['customer'] ?: '—') ?></td>
              <td><?= statusBadge($d['status']) ?></td>
              <td class="text-secondary text-sm"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Low Stock Alert Table -->
<?php if (!empty($lowStockProducts)): ?>
<div class="card">
  <div class="card-header">
    <span class="card-title">
      <i data-lucide="alert-triangle" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px;color:var(--warning);"></i>
      Low Stock Alerts
    </span>
    <a href="products.php" class="btn btn-secondary btn-sm">View products</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Product</th>
          <th>SKU</th>
          <th>Current Stock</th>
          <th>Reorder At</th>
          <th>UoM</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lowStockProducts as $p): ?>
        <tr>
          <td class="fw-600"><?= e($p['name']) ?></td>
          <td class="font-mono text-secondary"><?= e($p['sku']) ?></td>
          <td>
            <?php if ($p['total_qty'] == 0): ?>
              <span class="stock-zero"><?= $p['total_qty'] ?></span>
            <?php else: ?>
              <span class="stock-low"><?= $p['total_qty'] ?></span>
            <?php endif; ?>
          </td>
          <td class="text-secondary"><?= $p['reorder_qty'] ?></td>
          <td class="text-secondary text-sm"><?= e($p['unit_of_measure']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
