<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Move History';
$activeNav = 'history';
$db        = getDB();

// Filters
$filterProduct  = (int)($_GET['product_id']  ?? 0);
$filterType     = trim($_GET['type']         ?? '');
$filterLocation = (int)($_GET['location_id'] ?? 0);

$where  = [];
$params = [];

if ($filterProduct) {
    $where[]  = 'sl.product_id = ?';
    $params[] = $filterProduct;
}
if ($filterType) {
    $where[]  = 'sl.operation_type = ?';
    $params[] = $filterType;
}
if ($filterLocation) {
    $where[]  = 'sl.location_id = ?';
    $params[] = $filterLocation;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT sl.*,
           p.name prod_name, p.sku prod_sku, p.unit_of_measure,
           l.name loc_name, w.name wh_name,
           u.name user_name
    FROM stock_ledger sl
    JOIN products p    ON p.id  = sl.product_id
    LEFT JOIN locations l  ON l.id  = sl.location_id
    LEFT JOIN warehouses w ON w.id  = l.warehouse_id
    LEFT JOIN users u      ON u.id  = sl.created_by
    $whereSql
    ORDER BY sl.created_at DESC
    LIMIT 500
");
$stmt->execute($params);
$ledger = $stmt->fetchAll();

$products  = allProducts();
$locations = allLocations();
$types = $db->query("SELECT DISTINCT operation_type FROM stock_ledger ORDER BY operation_type")->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<div class="page-header">
  <div class="page-header-text">
    <h1>Move History</h1>
    <p>Complete stock ledger — all movements tracked</p>
  </div>
</div>

<!-- Filters -->
<form method="GET" action="history.php">
<div class="filter-bar" style="margin-bottom:20px;">
  <div class="search-wrap">
    <i data-lucide="search"></i>
    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search…">
  </div>
  <select name="product_id" class="form-control" style="width:auto;min-width:180px;" onchange="this.form.submit()">
    <option value="">All Products</option>
    <?php foreach ($products as $p): ?>
    <option value="<?= $p['id'] ?>" <?= $filterProduct == $p['id'] ? 'selected' : '' ?>>
      <?= e($p['name']) ?>
    </option>
    <?php endforeach; ?>
  </select>
  <select name="type" class="form-control" style="width:auto;min-width:150px;" onchange="this.form.submit()">
    <option value="">All Types</option>
    <?php foreach ($types as $t): ?>
    <option value="<?= e($t) ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= e($t) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="location_id" class="form-control" style="width:auto;min-width:180px;" onchange="this.form.submit()">
    <option value="">All Locations</option>
    <?php foreach ($locations as $loc): ?>
    <option value="<?= $loc['id'] ?>" <?= $filterLocation == $loc['id'] ? 'selected' : '' ?>>
      <?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?>
    </option>
    <?php endforeach; ?>
  </select>
  <?php if ($filterProduct || $filterType || $filterLocation): ?>
  <a href="history.php" class="btn btn-secondary btn-sm"><i data-lucide="x"></i> Clear</a>
  <?php endif; ?>
</div>
</form>

<div class="card">
  <div class="table-wrap">
    <table id="ledgerTable">
      <thead>
        <tr>
          <th>Date & Time</th>
          <th>Type</th>
          <th>Reference</th>
          <th>Product</th>
          <th>Location</th>
          <th style="text-align:right;">Change</th>
          <th style="text-align:right;">Balance After</th>
          <th>By</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($ledger)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <div class="empty-icon"><i data-lucide="clock"></i></div>
            <h3>No movements recorded</h3>
            <p>Stock movements are logged here after validating receipts, deliveries, transfers and adjustments.</p>
          </div>
        </td></tr>
        <?php else: ?>
          <?php foreach ($ledger as $row): ?>
          <?php $change = (float)$row['qty_change']; ?>
          <tr>
            <td class="text-secondary text-sm" style="white-space:nowrap;"><?= date('d M Y H:i', strtotime($row['created_at'])) ?></td>
            <td>
              <?php
              $typeColors = [
                  'Receipt'     => 'badge-green',
                  'Delivery'    => 'badge-red',
                  'Transfer-In' => 'badge-blue',
                  'Transfer-Out'=> 'badge-yellow',
                  'Adjustment'  => 'badge-indigo',
              ];
              $cls = $typeColors[$row['operation_type']] ?? 'badge-gray';
              ?>
              <span class="badge <?= $cls ?>"><?= e($row['operation_type']) ?></span>
            </td>
            <td class="font-mono text-sm"><?= e($row['reference'] ?? '—') ?></td>
            <td>
              <div class="fw-600"><?= e($row['prod_name']) ?></div>
              <div class="text-muted text-xs"><?= e($row['prod_sku']) ?></div>
            </td>
            <td class="text-secondary text-sm">
              <?= $row['wh_name'] ? e($row['wh_name'] . ' › ' . $row['loc_name']) : '—' ?>
            </td>
            <td style="text-align:right;" class="fw-600 <?= $change >= 0 ? 'text-success' : 'text-danger' ?>">
              <?= $change >= 0 ? '+' : '' ?><?= $change ?> <?= e($row['unit_of_measure']) ?>
            </td>
            <td style="text-align:right;" class="fw-600">
              <?= $row['qty_after'] ?> <span class="text-muted text-xs"><?= e($row['unit_of_measure']) ?></span>
            </td>
            <td class="text-secondary text-sm"><?= e($row['user_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($ledger)): ?>
  <div style="padding:12px 20px;border-top:1px solid var(--border);font-size:12px;color:var(--text-secondary);">
    Showing <?= count($ledger) ?> most recent movements
  </div>
  <?php endif; ?>
</div>

<script>bindSearch('searchInput', 'ledgerTable');</script>

<?php include 'includes/footer.php'; ?>
