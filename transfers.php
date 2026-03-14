<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Transfers';
$activeNav = 'transfers';
$userId    = (int)$_SESSION['user_id'];
$db        = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
      $ref         = genRef('TRF');
      $fromId      = (int)($_POST['from_location_id'] ?? 0) ?: null;
      $toId        = (int)($_POST['to_location_id']   ?? 0) ?: null;
      $notes       = trim($_POST['notes'] ?? '');
      $responsible = trim($_POST['responsible'] ?? '');
      $db->prepare("INSERT INTO transfers (reference,from_location_id,to_location_id,notes,responsible,created_by) VALUES (?,?,?,?,?,?)")
        ->execute([$ref, $fromId, $toId, $notes, $responsible, $userId]);
      $id = $db->lastInsertId();

      // Optional first product line on creation
      $firstProductId = (int)($_POST['first_product_id'] ?? 0);
      $firstQty       = (float)($_POST['first_qty'] ?? 0);
      if ($firstProductId && $firstQty > 0) {
        $db->prepare("INSERT INTO transfer_items (transfer_id,product_id,qty) VALUES (?,?,?)")
          ->execute([$id, $firstProductId, $firstQty]);
      }

        flash('success', 'Transfer ' . $ref . ' created.');
        redirect("transfers.php?id=$id");
    }

    if ($action === 'update_header') {
      $id          = (int)($_POST['id'] ?? 0);
      $fromId      = (int)($_POST['from_location_id'] ?? 0) ?: null;
      $toId        = (int)($_POST['to_location_id']   ?? 0) ?: null;
      $notes       = trim($_POST['notes'] ?? '');
      $responsible = trim($_POST['responsible'] ?? '');
      $db->prepare("UPDATE transfers SET from_location_id=?,to_location_id=?,notes=?,responsible=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status NOT IN ('Done','Canceled')")
        ->execute([$fromId, $toId, $notes, $responsible, $id]);
        redirect("transfers.php?id=$id");
    }

    if ($action === 'add_item') {
        $id        = (int)($_POST['id']         ?? 0);
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty       = (float)($_POST['qty']      ?? 0);
        if ($id && $productId && $qty > 0) {
            $db->prepare("INSERT INTO transfer_items (transfer_id,product_id,qty) VALUES (?,?,?)")
               ->execute([$id, $productId, $qty]);
        }
        redirect("transfers.php?id=$id");
    }

    if ($action === 'save_items') {
        $id    = (int)($_POST['id'] ?? 0);
        $items = $_POST['items'] ?? [];
        foreach ($items as $itemId => $vals) {
            $qty = (float)($vals['qty'] ?? 0);
            $db->prepare("UPDATE transfer_items SET qty=? WHERE id=? AND transfer_id=?")
               ->execute([$qty, (int)$itemId, $id]);
        }
        flash('success', 'Quantities saved.');
        redirect("transfers.php?id=$id");
    }

    if ($action === 'delete_item') {
        $id     = (int)($_POST['id']      ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        $db->prepare("DELETE FROM transfer_items WHERE id=? AND transfer_id=?")->execute([$itemId, $id]);
        redirect("transfers.php?id=$id");
    }

    if ($action === 'validate') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM transfers WHERE id=?");
        $stmt->execute([$id]);
        $transfer = $stmt->fetch();
        if ($transfer && !in_array($transfer['status'], ['Done','Canceled'])
            && $transfer['from_location_id'] && $transfer['to_location_id']) {
            $items = $db->prepare("SELECT * FROM transfer_items WHERE transfer_id=?");
            $items->execute([$id]);
            $rows   = $items->fetchAll();
            $errors = [];
            foreach ($rows as $item) {
                $avail = stockQty((int)$item['product_id'], (int)$transfer['from_location_id']);
                if ($avail < (float)$item['qty']) {
                    $errors[] = 'Insufficient stock for product ID ' . $item['product_id'] . '.';
                }
            }
            if (empty($errors)) {
                foreach ($rows as $item) {
                    // Deduct from source
                    adjustStock(
                        (int)$item['product_id'],
                        (int)$transfer['from_location_id'],
                        -(float)$item['qty'],
                        'Transfer-Out',
                        $transfer['reference'],
                        $userId,
                        'Transfer out'
                    );
                    // Add to destination
                    adjustStock(
                        (int)$item['product_id'],
                        (int)$transfer['to_location_id'],
                        (float)$item['qty'],
                        'Transfer-In',
                        $transfer['reference'],
                        $userId,
                        'Transfer in'
                    );
                }
                $db->prepare("UPDATE transfers SET status='Done',updated_at=CURRENT_TIMESTAMP WHERE id=?")
                   ->execute([$id]);
                flash('success', 'Transfer validated. Stock moved.');
            } else {
                flash('error', implode(' ', $errors));
            }
        } else {
            flash('error', 'Set From and To locations before validating.');
        }
        redirect("transfers.php?id=$id");
    }

    if ($action === 'cancel') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE transfers SET status='Canceled',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status NOT IN ('Done','Canceled')")
           ->execute([$id]);
        redirect("transfers.php?id=$id");
    }

    redirect('transfers.php');
}

$detailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($detailId) {
    $stmt = $db->prepare("
        SELECT t.*, u.name creator,
               fl.name from_loc_name, fl.warehouse_id from_wh_id,
               tl.name to_loc_name,   tl.warehouse_id to_wh_id,
               fw.name from_wh_name, tw.name to_wh_name
        FROM transfers t
        LEFT JOIN users u      ON u.id  = t.created_by
        LEFT JOIN locations fl ON fl.id = t.from_location_id
        LEFT JOIN locations tl ON tl.id = t.to_location_id
        LEFT JOIN warehouses fw ON fw.id = fl.warehouse_id
        LEFT JOIN warehouses tw ON tw.id = tl.warehouse_id
        WHERE t.id=?
    ");
    $stmt->execute([$detailId]);
    $transfer = $stmt->fetch();
    if (!$transfer) { flash('error','Transfer not found.'); redirect('transfers.php'); }

    $tItems = $db->prepare("
        SELECT ti.*, p.name prod_name, p.sku prod_sku, p.unit_of_measure
        FROM transfer_items ti
        JOIN products p ON p.id = ti.product_id
        WHERE ti.transfer_id = ?
        ORDER BY ti.id
    ");
    $tItems->execute([$detailId]);
    $items     = $tItems->fetchAll();
    $products  = allProducts();
    $locations = allLocations();
    $pageTitle = 'Transfer ' . $transfer['reference'];
}

$allTransfers = $db->query("
    SELECT t.*, u.name creator,
           fl.name from_loc, fw.name from_wh,
           tl.name to_loc, tw.name to_wh,
           (SELECT COUNT(*) FROM transfer_items WHERE transfer_id=t.id) item_count
    FROM transfers t
    LEFT JOIN users u      ON u.id  = t.created_by
    LEFT JOIN locations fl ON fl.id = t.from_location_id
    LEFT JOIN locations tl ON tl.id = t.to_location_id
    LEFT JOIN warehouses fw ON fw.id = fl.warehouse_id
    LEFT JOIN warehouses tw ON tw.id = tl.warehouse_id
    ORDER BY t.created_at DESC
")->fetchAll();

include 'includes/header.php';
?>

<?php $s = getFlash('success'); if ($s): ?>
<div class="alert alert-success" data-auto><i data-lucide="check-circle"></i> <?= e($s) ?></div>
<?php endif; ?>
<?php $err = getFlash('error'); if ($err): ?>
<div class="alert alert-danger" data-auto><i data-lucide="alert-circle"></i> <?= e($err) ?></div>
<?php endif; ?>

<?php if ($detailId && isset($transfer)): ?>
<!-- DETAIL -->
<div class="page-header">
  <div class="page-header-text">
    <h1>
      <a href="transfers.php" style="color:var(--text-secondary);text-decoration:none;font-weight:400;font-size:17px;">Transfers</a>
      <span style="color:var(--text-muted);margin:0 6px;">/</span>
      <?= e($transfer['reference']) ?>
    </h1>
  </div>
</div>

<?php $editable = !in_array($transfer['status'], ['Done','Canceled']); ?>

<div class="detail-header-card">
  <div class="detail-ref">Internal Transfer</div>
  <div class="detail-title"><?= e($transfer['reference']) ?> <?= statusBadge($transfer['status']) ?></div>

  <?php if ($editable): ?>
  <form method="POST" action="transfers.php" style="margin-bottom:16px;">
    <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="update_header">
    <input type="hidden" name="id"     value="<?= $transfer['id'] ?>">
    <div class="form-grid-3" style="margin-bottom:12px;">
      <div class="form-group">
        <label>From Location *</label>
        <select name="from_location_id" class="form-control" required>
          <option value="">— Select —</option>
          <?php foreach ($locations as $loc): ?>
          <option value="<?= $loc['id'] ?>" <?= $transfer['from_location_id'] == $loc['id'] ? 'selected' : '' ?>>
            <?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>To Location *</label>
        <select name="to_location_id" class="form-control" required>
          <option value="">— Select —</option>
          <?php foreach ($locations as $loc): ?>
          <option value="<?= $loc['id'] ?>" <?= $transfer['to_location_id'] == $loc['id'] ? 'selected' : '' ?>>
            <?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Responsible</label>
        <input type="text" name="responsible" class="form-control" value="<?= e($transfer['responsible'] ?? '') ?>" placeholder="Person responsible">
      </div>
    </div>
    <div class="form-group" style="margin-bottom:12px;">
      <label>Notes</label>
      <input type="text" name="notes" class="form-control" value="<?= e($transfer['notes']) ?>" placeholder="Optional">
    </div>
    <button type="submit" class="btn btn-secondary btn-sm"><i data-lucide="save"></i> Save Header</button>
  </form>
  <?php endif; ?>

  <div class="detail-meta">
    <div class="detail-meta-item">
      <label>From</label>
      <div class="value"><?= $transfer['from_wh_name'] ? e($transfer['from_wh_name'] . ' › ' . $transfer['from_loc_name']) : '—' ?></div>
    </div>
    <div class="detail-meta-item">
      <label>To</label>
      <div class="value"><?= $transfer['to_wh_name'] ? e($transfer['to_wh_name'] . ' › ' . $transfer['to_loc_name']) : '—' ?></div>
    </div>
    <div class="detail-meta-item"><label>Status</label><div class="value"><?= statusBadge($transfer['status']) ?></div></div>
    <div class="detail-meta-item"><label>Responsible</label><div class="value"><?= e($transfer['responsible'] ?? '—') ?></div></div>
    <div class="detail-meta-item"><label>Created By</label><div class="value"><?= e($transfer['creator'] ?? '—') ?></div></div>
    <div class="detail-meta-item"><label>Date</label><div class="value"><?= date('d M Y, H:i', strtotime($transfer['created_at'])) ?></div></div>
  </div>

  <?php if ($editable): ?>
  <div class="detail-actions" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
    <form method="POST" action="transfers.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="validate">
      <input type="hidden" name="id"     value="<?= $transfer['id'] ?>">
      <button type="submit" class="btn btn-success" onclick="return confirm('Validate and move stock?')">
        <i data-lucide="check-circle"></i> Validate Transfer
      </button>
    </form>
    <form method="POST" action="transfers.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="cancel">
      <input type="hidden" name="id"     value="<?= $transfer['id'] ?>">
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
    <span class="items-section-title"><i data-lucide="list" style="width:15px;height:15px;vertical-align:-2px;margin-right:6px;"></i>Transfer Lines</span>
    <?php if ($editable): ?>
    <button class="btn btn-primary btn-sm" onclick="openModal('modal-add-item')"><i data-lucide="plus"></i> Add Product</button>
    <?php endif; ?>
  </div>
  <?php if (empty($items)): ?>
  <div class="empty-state"><div class="empty-icon"><i data-lucide="package"></i></div><h3>No products added</h3><p>Add products to transfer.</p></div>
  <?php else: ?>
  <form method="POST" action="transfers.php">
    <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="save_items">
    <input type="hidden" name="id"     value="<?= $transfer['id'] ?>">
    <table class="items-table">
      <thead>
        <tr><th>Product</th><th>SKU</th><th>UoM</th>
          <th style="text-align:right;">Qty to Transfer</th>
          <th style="text-align:right;">Available</th>
          <?php if ($editable): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <?php $avail = $transfer['from_location_id'] ? stockQty((int)$item['product_id'], (int)$transfer['from_location_id']) : 0; ?>
        <tr>
          <td class="fw-600"><?= e($item['prod_name']) ?></td>
          <td class="font-mono text-secondary"><?= e($item['prod_sku']) ?></td>
          <td class="text-secondary text-sm"><?= e($item['unit_of_measure']) ?></td>
          <td style="text-align:right;">
            <?php if ($editable): ?>
            <input type="number" name="items[<?= $item['id'] ?>][qty]"
                   class="form-control" style="width:100px;text-align:right;"
                   value="<?= $item['qty'] ?>" min="0.01" step="0.01">
            <?php else: ?><strong><?= $item['qty'] ?></strong><?php endif; ?>
          </td>
          <td style="text-align:right;" class="<?= $avail <= 0 ? 'stock-zero' : 'stock-ok' ?>"><?= $avail ?></td>
          <?php if ($editable): ?>
          <td>
            <form method="POST" action="transfers.php" style="display:inline;">
              <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action"  value="delete_item">
              <input type="hidden" name="id"      value="<?= $transfer['id'] ?>">
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
      <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Quantities</button>
    </div>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</div>

<?php if ($editable): ?>
<div class="modal-overlay" id="modal-add-item">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Product to Transfer</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-add-item')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="transfers.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="add_item">
      <input type="hidden" name="id"     value="<?= $transfer['id'] ?>">
      <div class="modal-body">
        <div class="form-group">
          <label>Product *</label>
          <select name="product_id" class="form-control" required>
            <option value="">— Select Product —</option>
            <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Quantity *</label>
          <input type="number" name="qty" class="form-control" placeholder="0" min="0.01" step="0.01" required>
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
    <h1>Internal Transfers</h1>
    <p>Move stock between warehouses and locations</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" onclick="openModal('modal-create')"><i data-lucide="plus"></i> New Transfer</button>
  </div>
</div>

<div class="filter-bar">
  <div class="search-wrap">
    <i data-lucide="search"></i>
    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search transfers…">
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="transfersTable">
      <thead>
        <tr><th>Reference</th><th>From</th><th>To</th><th>Items</th><th>Responsible</th><th>Status</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($allTransfers)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <div class="empty-icon"><i data-lucide="arrow-left-right"></i></div>
            <h3>No transfers yet</h3>
            <p>Create a transfer to move stock between locations.</p>
            <button class="btn btn-primary" onclick="openModal('modal-create')"><i data-lucide="plus"></i> New Transfer</button>
          </div>
        </td></tr>
        <?php else: ?>
          <?php foreach ($allTransfers as $t): ?>
          <tr>
            <td class="fw-600 font-mono"><?= e($t['reference']) ?></td>
            <td class="text-secondary text-sm"><?= $t['from_wh'] ? e($t['from_wh'] . ' › ' . $t['from_loc']) : '—' ?></td>
            <td class="text-secondary text-sm"><?= $t['to_wh']   ? e($t['to_wh']   . ' › ' . $t['to_loc'])   : '—' ?></td>
            <td><span class="badge badge-gray"><?= $t['item_count'] ?> lines</span></td>
            <td class="text-secondary text-sm"><?= e($t['responsible'] ?? '—') ?></td>
            <td><?= statusBadge($t['status']) ?></td>
            <td class="text-secondary text-sm"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
            <td><a href="transfers.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm"><i data-lucide="eye"></i> View</a></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modal-create">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title"><i data-lucide="arrow-left-right" style="width:18px;height:18px;vertical-align:-3px;margin-right:8px;"></i>New Internal Transfer</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-create')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="transfers.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>From Location *</label>
            <select name="from_location_id" class="form-control" required>
              <option value="">— Select —</option>
              <?php foreach (allLocations() as $loc): ?>
              <option value="<?= $loc['id'] ?>"><?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>To Location *</label>
            <select name="to_location_id" class="form-control" required>
              <option value="">— Select —</option>
              <?php foreach (allLocations() as $loc): ?>
              <option value="<?= $loc['id'] ?>"><?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Responsible</label>
          <input type="text" name="responsible" class="form-control" placeholder="Person responsible for this transfer">
        </div>

        <hr style="margin:16px 0;">
        <div class="text-sm text-secondary" style="margin-bottom:8px;">
          <strong>First Product Line (optional)</strong><br>
          Select which product to transfer now and the quantity.
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Product</label>
            <select name="first_product_id" class="form-control">
              <option value="">— Select Product —</option>
              <?php foreach (allProducts() as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="first_qty" class="form-control" placeholder="0" min="0.01" step="0.01">
          </div>
        </div>

        <div class="form-group" style="margin-top:12px;">
          <label>Notes</label>
          <textarea name="notes" class="form-control" placeholder="Optional notes…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="plus"></i> Create Transfer</button>
      </div>
    </form>
  </div>
</div>

<script>bindSearch('searchInput', 'transfersTable');</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
