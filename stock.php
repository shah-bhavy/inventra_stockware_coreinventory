<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Stock';
$activeNav = 'stock';
$db        = getDB();

$userId = (int)($_SESSION['user_id'] ?? 0);

// Handle manual stock adjustments from this page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verifyCsrf();
  $action = $_POST['action'] ?? '';

  if ($action === 'set_stock') {
    $productId  = (int)($_POST['product_id']  ?? 0);
    $locationId = (int)($_POST['location_id'] ?? 0);
    $newQty     = isset($_POST['qty']) ? (float)$_POST['qty'] : 0.0;
    $notes      = trim($_POST['notes'] ?? '');

    if ($productId <= 0 || $locationId <= 0) {
      flash('error', 'Product and location are required.');
    } elseif ($newQty < 0) {
      flash('error', 'Quantity cannot be negative.');
    } else {
      $current = stockQty($productId, $locationId);
      $delta   = $newQty - $current;

      if (abs($delta) > 0.000001) {
        $ref = 'STOCK/' . date('YmdHis');
        $op  = 'Manual-Adjust';
        $msg = $notes !== '' ? $notes : 'Manual stock update from Stock page';
        adjustStock($productId, $locationId, $delta, $op, $ref, $userId, $msg);
        flash('success', 'Stock updated.');
      } else {
        flash('success', 'No changes to stock quantity.');
      }
    }

    $qs = $_SERVER['QUERY_STRING'] ?? '';
    redirect('stock.php' . ($qs ? '?' . $qs : ''));
  }

  redirect('stock.php');
}

// Filters (GET)
$categoryId  = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$warehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
$locationId  = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;
$q           = trim($_GET['q'] ?? '');
$stockFilter = $_GET['stock_filter'] ?? '';

// Lookups for filters
$categories = allCategories(true);
$warehouses = allWarehouses();
$locations  = allLocations();

// Build stock query (product x location with qty)
$sql = "
SELECT
  sl.product_id,
  sl.location_id,
  sl.qty,
  p.name              AS product_name,
  p.sku               AS sku,
  p.unit_of_measure   AS uom,
  COALESCE(p.reorder_qty,0) AS reorder_qty,
  p.active            AS product_active,
  c.id                AS category_id,
  c.name              AS category_name,
  l.name              AS location_name,
  w.id                AS warehouse_id,
  w.name              AS warehouse_name
FROM stock_levels sl
JOIN products p          ON p.id = sl.product_id
LEFT JOIN product_categories c ON c.id = p.category_id
JOIN locations l         ON l.id = sl.location_id
JOIN warehouses w        ON w.id = l.warehouse_id
WHERE 1=1
";

$params = [];

if ($categoryId > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryId;
}

if ($warehouseId > 0) {
    $sql .= " AND w.id = ?";
    $params[] = $warehouseId;
}

if ($locationId > 0) {
    $sql .= " AND l.id = ?";
    $params[] = $locationId;
}

if ($q !== '') {
    $sql       .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $like       = '%' . $q . '%';
    $params[]   = $like;
    $params[]   = $like;
}

if ($stockFilter === 'zero') {
    $sql .= " AND sl.qty <= 0";
} elseif ($stockFilter === 'positive') {
    $sql .= " AND sl.qty > 0";
}

$sql .= "
ORDER BY
  COALESCE(c.name, ''),
  p.name,
  w.name,
  l.name
";

$st = $db->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

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
    <h1>Stock</h1>
    <p>Current stock across all warehouses and locations.</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" onclick="openModal('modal-new-stock')">
      <i data-lucide="plus"></i> Add Stock Entry
    </button>
  </div>
</div>

<div class="card" style="margin-bottom:16px;">
  <form method="GET" action="stock.php">
    <div class="form-grid">
      <div class="form-group">
        <label>Search Product / SKU</label>
        <input type="text" name="q" class="form-control" placeholder="Type name or SKU" value="<?= e($q) ?>">
      </div>
      <div class="form-group">
        <label>Category</label>
        <select name="category_id" class="form-control">
          <option value="">All categories</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
            <?= e($cat['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Warehouse</label>
        <select name="warehouse_id" class="form-control">
          <option value="">All warehouses</option>
          <?php foreach ($warehouses as $wh): ?>
          <option value="<?= $wh['id'] ?>" <?= $warehouseId === (int)$wh['id'] ? 'selected' : '' ?>>
            <?= e($wh['name']) ?> (<?= e($wh['code']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Location</label>
        <select name="location_id" class="form-control">
          <option value="">All locations</option>
          <?php foreach ($locations as $loc): ?>
          <option value="<?= $loc['id'] ?>" <?= $locationId === (int)$loc['id'] ? 'selected' : '' ?>>
            <?= e($loc['wh_name']) ?> "> <?= e($loc['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Stock Filter</label>
        <select name="stock_filter" class="form-control">
          <option value="" <?= $stockFilter === '' ? 'selected' : '' ?>>All</option>
          <option value="positive" <?= $stockFilter === 'positive' ? 'selected' : '' ?>>Only with stock</option>
          <option value="zero" <?= $stockFilter === 'zero' ? 'selected' : '' ?>>Only out of stock</option>
        </select>
      </div>
    </div>
    <div style="margin-top:12px; display:flex; gap:8px; justify-content:flex-end;">
      <a href="stock.php" class="btn btn-secondary">Reset</a>
      <button type="submit" class="btn btn-primary"><i data-lucide="filter"></i> Apply Filters</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Product</th>
          <th>SKU</th>
          <th>Category</th>
          <th>Warehouse</th>
          <th>Location</th>
          <th style="text-align:right;">Quantity</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr>
          <td colspan="6" class="text-secondary text-sm" style="text-align:center;padding:20px;">
            No stock records match your filters.
          </td>
        </tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td class="fw-600">
              <?= e($r['product_name']) ?>
              <?php if ((int)$r['product_active'] !== 1): ?>
                <span class="badge badge-gray" style="margin-left:4px;">Inactive</span>
              <?php endif; ?>
            </td>
            <td class="font-mono text-secondary"><?= e($r['sku']) ?></td>
            <td class="text-secondary text-sm"><?= e($r['category_name'] ?? '—') ?></td>
            <td><?= e($r['warehouse_name']) ?></td>
            <td><?= e($r['location_name']) ?></td>
            <td style="text-align:right;">
              <?php if ($r['qty'] <= 0): ?>
                <span class="stock-zero"><?= $r['qty'] ?></span>
              <?php else: ?>
                <span class="stock-ok"><?= $r['qty'] ?></span>
              <?php endif; ?>
              <span class="text-muted text-xs"> <?= e($r['uom']) ?></span>
            </td>
            <td>
              <button type="button" class="btn btn-secondary btn-sm" onclick='fillModal("modal-adjust-stock", {
                "product_id": "<?= $r['product_id'] ?>",
                "location_id": "<?= $r['location_id'] ?>",
                "product_label": <?= json_encode($r['product_name'] . ' (' . $r['sku'] . ')') ?>,
                "location_label": <?= json_encode($r['warehouse_name'] . ' › ' . $r['location_name']) ?>,
                "qty": "<?= $r['qty'] ?>"
              }); return false;'>
                <i data-lucide="edit"></i> Adjust
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- New Stock Entry Modal -->
<div class="modal-overlay" id="modal-new-stock">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Stock Entry</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-new-stock')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="stock.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="set_stock">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Product *</label>
            <select name="product_id" class="form-control" required>
              <option value="">— Select Product —</option>
              <?php foreach (allProducts() as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Warehouse &amp; Location *</label>
            <select name="location_id" class="form-control" required>
              <option value="">— Select Location —</option>
              <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>"><?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Quantity *</label>
          <input type="number" name="qty" class="form-control" placeholder="0" min="0" step="0.01" required>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <input type="text" name="notes" class="form-control" placeholder="Optional notes for this change">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-new-stock')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Adjust Existing Stock Modal -->
<div class="modal-overlay" id="modal-adjust-stock">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Adjust Stock</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-adjust-stock')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="stock.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="set_stock">
      <input type="hidden" name="product_id">
      <input type="hidden" name="location_id">
      <div class="modal-body">
        <div class="form-group">
          <label>Product</label>
          <input type="text" name="product_label" class="form-control" readonly>
        </div>
        <div class="form-group">
          <label>Warehouse &amp; Location</label>
          <input type="text" name="location_label" class="form-control" readonly>
        </div>
        <div class="form-group">
          <label>New Quantity *</label>
          <input type="number" name="qty" class="form-control" min="0" step="0.01" required>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <input type="text" name="notes" class="form-control" placeholder="Optional notes for this change">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-adjust-stock')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
