<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Adjustments';
$activeNav = 'adjustments';
$userId    = (int)$_SESSION['user_id'];
$db        = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $ref      = genRef('ADJ');
        $locId    = (int)($_POST['location_id'] ?? 0) ?: null;
        $notes    = trim($_POST['notes'] ?? '');
        $db->prepare("INSERT INTO adjustments (reference,location_id,notes,created_by) VALUES (?,?,?,?)")
           ->execute([$ref, $locId, $notes, $userId]);
        $id = $db->lastInsertId();
        flash('success', 'Adjustment ' . $ref . ' created.');
        redirect("adjustments.php?id=$id");
    }

    if ($action === 'update_header') {
        $id    = (int)($_POST['id']          ?? 0);
        $locId = (int)($_POST['location_id'] ?? 0) ?: null;
        $notes = trim($_POST['notes'] ?? '');
        $db->prepare("UPDATE adjustments SET location_id=?,notes=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status NOT IN ('Done','Canceled')")
           ->execute([$locId, $notes, $id]);
        redirect("adjustments.php?id=$id");
    }

    if ($action === 'add_item') {
        $id        = (int)($_POST['id']         ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $countedQty= (float)($_POST['counted_qty'] ?? 0);

        // Get location_id from parent adjustment
        $adj = $db->prepare("SELECT location_id FROM adjustments WHERE id=?");
        $adj->execute([$id]);
        $row = $adj->fetch();
        $locId = $row ? (int)($row['location_id'] ?? 0) : 0;

        if ($id && $productId) {
            $sysQty = $locId ? stockQty($productId, $locId) : stockQty($productId);
            $diff   = $countedQty - $sysQty;
            $db->prepare("INSERT INTO adjustment_items (adjustment_id,product_id,system_qty,counted_qty,difference)
                          VALUES (?,?,?,?,?)")
               ->execute([$id, $productId, $sysQty, $countedQty, $diff]);
        }
        redirect("adjustments.php?id=$id");
    }

    if ($action === 'save_items') {
        $id    = (int)($_POST['id'] ?? 0);
        $items = $_POST['items'] ?? [];
        // Get location
        $adj = $db->prepare("SELECT location_id FROM adjustments WHERE id=?");
        $adj->execute([$id]);
        $row   = $adj->fetch();
        $locId = $row ? (int)($row['location_id'] ?? 0) : 0;

        foreach ($items as $itemId => $vals) {
            $counted = (float)($vals['counted_qty'] ?? 0);
            $sys     = (float)($vals['system_qty']  ?? 0);
            $diff    = $counted - $sys;
            $db->prepare("UPDATE adjustment_items SET counted_qty=?,difference=? WHERE id=? AND adjustment_id=?")
               ->execute([$counted, $diff, (int)$itemId, $id]);
        }
        flash('success', 'Counts saved.');
        redirect("adjustments.php?id=$id");
    }

    if ($action === 'delete_item') {
        $id     = (int)($_POST['id']      ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        $db->prepare("DELETE FROM adjustment_items WHERE id=? AND adjustment_id=?")->execute([$itemId, $id]);
        redirect("adjustments.php?id=$id");
    }

    if ($action === 'validate') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM adjustments WHERE id=?");
        $stmt->execute([$id]);
        $adj = $stmt->fetch();
        if ($adj && !in_array($adj['status'], ['Done','Canceled']) && $adj['location_id']) {
            $items = $db->prepare("SELECT * FROM adjustment_items WHERE adjustment_id=?");
            $items->execute([$id]);
            foreach ($items->fetchAll() as $item) {
                $diff = (float)$item['difference'];
                if ($diff != 0) {
                    adjustStock(
                        (int)$item['product_id'],
                        (int)$adj['location_id'],
                        $diff,
                        'Adjustment',
                        $adj['reference'],
                        $userId,
                        'Stock count adjustment'
                    );
                }
            }
            $db->prepare("UPDATE adjustments SET status='Done',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
            flash('success', 'Adjustment validated. Stock updated.');
        } else {
            flash('error', 'Set a location before validating.');
        }
        redirect("adjustments.php?id=$id");
    }

    if ($action === 'cancel') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE adjustments SET status='Canceled',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status NOT IN ('Done','Canceled')")
           ->execute([$id]);
        redirect("adjustments.php?id=$id");
    }

    redirect('adjustments.php');
}

$detailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($detailId) {
    $stmt = $db->prepare("
        SELECT a.*, u.name creator, l.name loc_name, w.name wh_name
        FROM adjustments a
        LEFT JOIN users u ON u.id = a.created_by
        LEFT JOIN locations l ON l.id = a.location_id
        LEFT JOIN warehouses w ON w.id = l.warehouse_id
        WHERE a.id=?
    ");
    $stmt->execute([$detailId]);
    $adj = $stmt->fetch();
    if (!$adj) { flash('error','Adjustment not found.'); redirect('adjustments.php'); }

    $aItems = $db->prepare("
        SELECT ai.*, p.name prod_name, p.sku prod_sku, p.unit_of_measure
        FROM adjustment_items ai
        JOIN products p ON p.id = ai.product_id
        WHERE ai.adjustment_id = ?
        ORDER BY ai.id
    ");
    $aItems->execute([$detailId]);
    $items     = $aItems->fetchAll();
    $products  = allProducts();
    $locations = allLocations();
    $pageTitle = 'Adjustment ' . $adj['reference'];
}

$allAdj = $db->query("
    SELECT a.*, u.name creator, l.name loc_name, w.name wh_name,
           (SELECT COUNT(*) FROM adjustment_items WHERE adjustment_id=a.id) item_count
    FROM adjustments a
    LEFT JOIN users u ON u.id = a.created_by
    LEFT JOIN locations l ON l.id = a.location_id
    LEFT JOIN warehouses w ON w.id = l.warehouse_id
    ORDER BY a.created_at DESC
")->fetchAll();

include 'includes/header.php';
?>

<?php $s = getFlash('success'); if ($s): ?>
<div class="alert alert-success" data-auto><i data-lucide="check-circle"></i> <?= e($s) ?></div>
<?php endif; ?>
<?php $err = getFlash('error'); if ($err): ?>
<div class="alert alert-danger" data-auto><i data-lucide="alert-circle"></i> <?= e($err) ?></div>
<?php endif; ?>

<?php if ($detailId && isset($adj)): ?>
<!-- DETAIL -->
<div class="page-header">
  <div class="page-header-text">
    <h1>
      <a href="adjustments.php" style="color:var(--text-secondary);text-decoration:none;font-weight:400;font-size:17px;">Adjustments</a>
      <span style="color:var(--text-muted);margin:0 6px;">/</span>
      <?= e($adj['reference']) ?>
    </h1>
  </div>
</div>

<?php $editable = !in_array($adj['status'], ['Done','Canceled']); ?>

<div class="detail-header-card">
  <div class="detail-ref">Stock Adjustment</div>
  <div class="detail-title"><?= e($adj['reference']) ?> <?= statusBadge($adj['status']) ?></div>

  <?php if ($editable): ?>
  <form method="POST" action="adjustments.php" style="margin-bottom:16px;">
    <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="update_header">
    <input type="hidden" name="id"     value="<?= $adj['id'] ?>">
    <div class="form-grid" style="margin-bottom:12px;">
      <div class="form-group">
        <label>Location *</label>
        <select name="location_id" class="form-control" required>
          <option value="">— Select Location —</option>
          <?php foreach ($locations as $loc): ?>
          <option value="<?= $loc['id'] ?>" <?= $adj['location_id'] == $loc['id'] ? 'selected' : '' ?>>
            <?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <input type="text" name="notes" class="form-control" value="<?= e($adj['notes']) ?>" placeholder="Optional">
      </div>
    </div>
    <button type="submit" class="btn btn-secondary btn-sm"><i data-lucide="save"></i> Save Header</button>
  </form>
  <?php endif; ?>

  <div class="detail-meta">
    <div class="detail-meta-item">
      <label>Location</label>
      <div class="value"><?= $adj['wh_name'] ? e($adj['wh_name'] . ' › ' . $adj['loc_name']) : '—' ?></div>
    </div>
    <div class="detail-meta-item"><label>Status</label><div class="value"><?= statusBadge($adj['status']) ?></div></div>
    <div class="detail-meta-item"><label>Created By</label><div class="value"><?= e($adj['creator'] ?? '—') ?></div></div>
    <div class="detail-meta-item"><label>Date</label><div class="value"><?= date('d M Y, H:i', strtotime($adj['created_at'])) ?></div></div>
  </div>

  <?php if ($editable): ?>
  <div class="detail-actions" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
    <form method="POST" action="adjustments.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="validate">
      <input type="hidden" name="id"     value="<?= $adj['id'] ?>">
      <button type="submit" class="btn btn-success" onclick="return confirm('Apply stock adjustments?')">
        <i data-lucide="check-circle"></i> Validate Adjustment
      </button>
    </form>
    <form method="POST" action="adjustments.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="cancel">
      <input type="hidden" name="id"     value="<?= $adj['id'] ?>">
      <button type="submit" class="btn btn-secondary" onclick="return confirm('Cancel?')" style="color:var(--danger);">
        <i data-lucide="x-circle"></i> Cancel
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>

<!-- Items -->
<div class="items-section">
  <div class="items-section-header">
    <span class="items-section-title"><i data-lucide="list" style="width:15px;height:15px;vertical-align:-2px;margin-right:6px;"></i>Adjustment Lines</span>
    <?php if ($editable): ?>
    <button class="btn btn-primary btn-sm" onclick="openModal('modal-add-item')"><i data-lucide="plus"></i> Add Product</button>
    <?php endif; ?>
  </div>
  <?php if (empty($items)): ?>
  <div class="empty-state"><div class="empty-icon"><i data-lucide="sliders-horizontal"></i></div><h3>No products added</h3><p>Add products to count and adjust.</p></div>
  <?php else: ?>
  <form method="POST" action="adjustments.php">
    <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="save_items">
    <input type="hidden" name="id"     value="<?= $adj['id'] ?>">
    <table class="items-table">
      <thead>
        <tr><th>Product</th><th>SKU</th><th>UoM</th>
          <th style="text-align:right;">System Qty</th>
          <th style="text-align:right;">Counted Qty</th>
          <th style="text-align:right;">Difference</th>
          <?php if ($editable): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <?php $diff = (float)$item['counted_qty'] - (float)$item['system_qty']; ?>
        <tr>
          <td class="fw-600"><?= e($item['prod_name']) ?></td>
          <td class="font-mono text-secondary"><?= e($item['prod_sku']) ?></td>
          <td class="text-secondary text-sm"><?= e($item['unit_of_measure']) ?></td>
          <td style="text-align:right;" class="text-secondary">
            <input type="hidden" name="items[<?= $item['id'] ?>][system_qty]" value="<?= $item['system_qty'] ?>">
            <?= $item['system_qty'] ?>
          </td>
          <td style="text-align:right;">
            <?php if ($editable): ?>
            <input type="number" name="items[<?= $item['id'] ?>][counted_qty]"
                   class="form-control" style="width:100px;text-align:right;"
                   value="<?= $item['counted_qty'] ?>" min="0" step="0.01">
            <?php else: ?><strong><?= $item['counted_qty'] ?></strong><?php endif; ?>
          </td>
          <td style="text-align:right;" class="fw-600 <?= $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-secondary') ?>">
            <?= $diff > 0 ? '+' : '' ?><?= $item['difference'] ?>
          </td>
          <?php if ($editable): ?>
          <td>
            <form method="POST" action="adjustments.php" style="display:inline;">
              <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action"  value="delete_item">
              <input type="hidden" name="id"      value="<?= $adj['id'] ?>">
              <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
              <button type="submit" class="btn-row-remove"><i data-lucide="trash-2"></i></button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($editable): ?>
    <div class="add-row-area" style="text-align:right;">
      <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Counts</button>
    </div>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</div>

<?php if ($editable): ?>
<div class="modal-overlay" id="modal-add-item">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Product to Count</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-add-item')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="adjustments.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="add_item">
      <input type="hidden" name="id"     value="<?= $adj['id'] ?>">
      <div class="modal-body">
        <div class="form-group">
          <label>Product *</label>
          <select name="product_id" class="form-control" required>
            <option value="">— Select Product —</option>
            <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>) – System: <?= $adj['location_id'] ? stockQty((int)$p['id'], (int)$adj['location_id']) : $p['total_stock'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Counted Quantity *</label>
          <input type="number" name="counted_qty" class="form-control" placeholder="Physical count" min="0" step="0.01" required>
          <div class="form-hint">Enter what you physically counted</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-item')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="plus"></i> Add Line</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- LIST -->
<div class="page-header">
  <div class="page-header-text">
    <h1>Stock Adjustments</h1>
    <p>Reconcile physical counts with system stock</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" onclick="openModal('modal-create')"><i data-lucide="plus"></i> New Adjustment</button>
  </div>
</div>

<div class="filter-bar">
  <div class="search-wrap">
    <i data-lucide="search"></i>
    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search adjustments…">
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="adjTable">
      <thead>
        <tr><th>Reference</th><th>Location</th><th>Items</th><th>Status</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($allAdj)): ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <div class="empty-icon"><i data-lucide="sliders-horizontal"></i></div>
            <h3>No adjustments yet</h3>
            <p>Create an adjustment to reconcile physical stock counts.</p>
            <button class="btn btn-primary" onclick="openModal('modal-create')"><i data-lucide="plus"></i> New Adjustment</button>
          </div>
        </td></tr>
        <?php else: ?>
          <?php foreach ($allAdj as $a): ?>
          <tr>
            <td class="fw-600 font-mono"><?= e($a['reference']) ?></td>
            <td class="text-secondary text-sm"><?= $a['wh_name'] ? e($a['wh_name'] . ' › ' . $a['loc_name']) : '—' ?></td>
            <td><span class="badge badge-gray"><?= $a['item_count'] ?> lines</span></td>
            <td><?= statusBadge($a['status']) ?></td>
            <td class="text-secondary text-sm"><?= date('d M Y', strtotime($a['created_at'])) ?></td>
            <td><a href="adjustments.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm"><i data-lucide="eye"></i> View</a></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-create">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i data-lucide="sliders-horizontal" style="width:18px;height:18px;vertical-align:-3px;margin-right:8px;"></i>New Adjustment</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-create')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="adjustments.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-group">
          <label>Location</label>
          <select name="location_id" class="form-control">
            <option value="">— Select Location —</option>
            <?php foreach (allLocations() as $loc): ?>
            <option value="<?= $loc['id'] ?>"><?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" class="form-control" placeholder="Reason for adjustment…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="plus"></i> Create Adjustment</button>
      </div>
    </form>
  </div>
</div>

<script>bindSearch('searchInput', 'adjTable');</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
