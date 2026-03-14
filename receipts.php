<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Receipts';
$activeNav = 'receipts';
$userId    = (int)$_SESSION['user_id'];
$db        = getDB();

// ── POST actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Create new receipt
    if ($action === 'create') {
      $ref         = genRef('REC');
      $supplierId  = (int)($_POST['supplier_id'] ?? 0) ?: null;
      $supplierTxt = trim($_POST['supplier'] ?? '');
      $purpose     = trim($_POST['purpose'] ?? '');

      // Optional first product line from the create modal
      $firstProductId   = (int)($_POST['first_product_id']   ?? 0);
      $firstLocationId  = (int)($_POST['first_location_id']  ?? 0) ?: null;
      $firstQtyExpected = (float)($_POST['first_qty_expected'] ?? 0);

        if (!$supplierId && $supplierTxt === '') {
            flash('error', 'Please provide a supplier name or select one.');
            redirect('receipts.php');
        }

        if ($supplierId) {
            $st = $db->prepare("SELECT name FROM suppliers WHERE id=? AND active=1");
            $st->execute([$supplierId]);
            $nameFromMaster = (string)($st->fetchColumn() ?: '');
            if ($nameFromMaster === '') {
                flash('error', 'Selected supplier is invalid.');
                redirect('receipts.php');
            }
            $supplierTxt = $nameFromMaster;
        }

        // Resolve created_by safely to avoid foreign key issues
        $createdBy = $userId;
        try {
          $stUser = $db->prepare("SELECT id FROM users WHERE id=?");
          $stUser->execute([$createdBy]);
          if (!$stUser->fetchColumn()) {
            $createdBy = null;
          }
        } catch (Exception $e) {
          $createdBy = null;
        }

        $db->prepare("INSERT INTO receipts (reference,supplier_id,supplier,purpose,created_by) VALUES (?,?,?,?,?)")
           ->execute([$ref, $supplierId, $supplierTxt, $purpose, $createdBy]);
        $id = $db->lastInsertId();

        // If a first product line was provided, create it immediately
        if ($firstProductId && $firstLocationId && $firstQtyExpected > 0) {
          $db->prepare("INSERT INTO receipt_items (receipt_id,product_id,location_id,qty_expected,qty_received)
                        VALUES (?,?,?,?,?)")
             ->execute([$id, $firstProductId, $firstLocationId, $firstQtyExpected, $firstQtyExpected]);
        }
        flash('success', 'Receipt ' . $ref . ' created.');
        redirect("receipts.php?id=$id");
    }

    // Supplier create (shared from list/detail view)
    if ($action === 'create_supplier') {
      $name          = trim($_POST['name'] ?? '');
      $contactPerson = trim($_POST['contact_person'] ?? '');
      $phone         = trim($_POST['phone'] ?? '');
      $email         = trim($_POST['email'] ?? '');
      $address       = trim($_POST['address'] ?? '');
      $returnId      = (int)($_POST['return_receipt_id'] ?? 0);

      if ($name === '') {
        flash('error', 'Supplier name is required.');
        redirect($returnId ? "receipts.php?id=$returnId" : 'receipts.php');
      }

      $db->prepare("INSERT INTO suppliers (name,contact_person,phone,email,address) VALUES (?,?,?,?,?)")
         ->execute([$name, $contactPerson, $phone, $email, $address]);
      flash('success', 'Supplier created.');
      redirect($returnId ? "receipts.php?id=$returnId" : 'receipts.php');
    }

    // Supplier update (name and contact details)
    if ($action === 'update_supplier') {
      $supplierId    = (int)($_POST['supplier_id'] ?? 0);
      $name          = trim($_POST['name'] ?? '');
      $contactPerson = trim($_POST['contact_person'] ?? '');
      $phone         = trim($_POST['phone'] ?? '');
      $email         = trim($_POST['email'] ?? '');
      $address       = trim($_POST['address'] ?? '');
      $active        = isset($_POST['active']) ? (int)$_POST['active'] : 0;

      if ($supplierId > 0 && $name !== '') {
        $db->prepare("UPDATE suppliers SET name=?,contact_person=?,phone=?,email=?,address=?,active=? WHERE id=?")
           ->execute([$name, $contactPerson, $phone, $email, $address, $active, $supplierId]);
        flash('success', 'Supplier details updated.');
      } else {
        flash('error', 'Supplier name is required.');
      }
      redirect('receipts.php');
    }

    // Update header
    if ($action === 'update_header') {
        $id         = (int)($_POST['id'] ?? 0);
        $supplierId = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $supplier   = trim($_POST['supplier'] ?? '');
      $purpose    = trim($_POST['purpose'] ?? '');

        if ($id) {
            if ($supplierId) {
                $st = $db->prepare("SELECT name FROM suppliers WHERE id=?");
                $st->execute([$supplierId]);
                $nameFromMaster = (string)($st->fetchColumn() ?: '');
                if ($nameFromMaster !== '') {
                    $supplier = $nameFromMaster;
                }
            }

            $db->prepare("UPDATE receipts
                      SET supplier_id=?,supplier=?,purpose=?,updated_at=CURRENT_TIMESTAMP
                      WHERE id=? AND status NOT IN ('Done','Canceled')")
              ->execute([$supplierId, $supplier, $purpose, $id]);
        }
        redirect("receipts.php?id=$id");
    }

    // Add item line
    if ($action === 'add_item') {
        $id          = (int)($_POST['id']          ?? 0);
        $productId   = (int)($_POST['product_id']  ?? 0);
        $locationId  = (int)($_POST['location_id'] ?? 0) ?: null;
        $qtyExpected = (float)($_POST['qty_expected'] ?? 0);
        if ($id && $productId && $qtyExpected > 0) {
            $db->prepare("INSERT INTO receipt_items (receipt_id,product_id,location_id,qty_expected,qty_received)
                          VALUES (?,?,?,?,?)")
               ->execute([$id, $productId, $locationId, $qtyExpected, $qtyExpected]);
        }
        redirect("receipts.php?id=$id");
    }

    // Save received quantities
    if ($action === 'save_items') {
        $id    = (int)($_POST['id'] ?? 0);
        $items = $_POST['items'] ?? [];
        foreach ($items as $itemId => $vals) {
            $qtyRec = (float)($vals['qty_received'] ?? 0);
            $locId  = (int)($vals['location_id'] ?? 0) ?: null;
            $db->prepare("UPDATE receipt_items SET qty_received=?,location_id=? WHERE id=? AND receipt_id=?")
               ->execute([$qtyRec, $locId, (int)$itemId, $id]);
        }
        flash('success', 'Quantities saved.');
        redirect("receipts.php?id=$id");
    }

    // Delete item
    if ($action === 'delete_item') {
        $id     = (int)($_POST['id']      ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        $db->prepare("DELETE FROM receipt_items WHERE id=? AND receipt_id=?")->execute([$itemId, $id]);
        redirect("receipts.php?id=$id");
    }

    // Validate → Done
    if ($action === 'validate') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM receipts WHERE id=?");
        $stmt->execute([$id]);
        $receipt = $stmt->fetch();

        if ($receipt && !in_array($receipt['status'], ['Done','Canceled'])) {
            $items = $db->prepare("SELECT * FROM receipt_items WHERE receipt_id=?");
            $items->execute([$id]);
        $allItems = $items->fetchAll();

        if (empty($receipt['supplier_id'])) {
          flash('error', 'Select a supplier before validating this receipt.');
          redirect("receipts.php?id=$id");
        }
        if (empty($allItems)) {
          flash('error', 'Add at least one product line before validation.');
          redirect("receipts.php?id=$id");
        }

        foreach ($allItems as $item) {
          if ((float)$item['qty_received'] <= 0 || empty($item['location_id'])) {
            flash('error', 'Each line must have destination and received quantity > 0 before validation.');
            redirect("receipts.php?id=$id");
                }
            }

        foreach ($allItems as $item) {
          adjustStock(
            (int)$item['product_id'],
            (int)$item['location_id'],
            (float)$item['qty_received'],
            'Receipt',
            $receipt['reference'],
            $userId,
            'Validated receipt'
          );
        }

            $db->prepare("UPDATE receipts SET status='Done',updated_at=CURRENT_TIMESTAMP WHERE id=?")
               ->execute([$id]);
            flash('success', 'Receipt validated. Stock updated.');
        }
        redirect("receipts.php?id=$id");
    }

    // Cancel
    if ($action === 'cancel') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE receipts SET status='Canceled',updated_at=CURRENT_TIMESTAMP WHERE id=? AND status NOT IN ('Done','Canceled')")
           ->execute([$id]);
        redirect("receipts.php?id=$id");
    }

    redirect('receipts.php');
}

// ── Determine view mode ───────────────────────────────────────────────────────
$detailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($detailId) {
    // Detail view
    $stmt = $db->prepare("SELECT r.*, u.name creator,
                 s.name supplier_name,
                 s.contact_person supplier_contact,
                 s.phone supplier_phone,
                 s.email supplier_email,
                 s.address supplier_address
              FROM receipts r
              LEFT JOIN users u ON u.id=r.created_by
              LEFT JOIN suppliers s ON s.id=r.supplier_id
              WHERE r.id=?");
    $stmt->execute([$detailId]);
    $receipt = $stmt->fetch();
    if (!$receipt) { flash('error','Receipt not found.'); redirect('receipts.php'); }

    $receiptItems = $db->prepare("
      SELECT ri.*, p.name prod_name, p.sku prod_sku, p.unit_of_measure,
             l.name loc_name, w.name wh_name
      FROM receipt_items ri
      JOIN products p  ON p.id  = ri.product_id
      LEFT JOIN locations l ON l.id = ri.location_id
      LEFT JOIN warehouses w ON w.id = l.warehouse_id
      WHERE ri.receipt_id = ?
      ORDER BY ri.id
    ");
    $receiptItems->execute([$detailId]);
    $items = $receiptItems->fetchAll();

    $suppliers = allSuppliers(false);
    $pageTitle = 'Receipt ' . $receipt['reference'];
}

$products  = allProducts();
$locations = allLocations();

$allReceipts = $db->query("
    SELECT r.*, u.name creator,
         COALESCE(s.name, r.supplier) supplier_display,
           (SELECT COUNT(*) FROM receipt_items WHERE receipt_id=r.id) item_count
    FROM receipts r
    LEFT JOIN users u ON u.id=r.created_by
    LEFT JOIN suppliers s ON s.id=r.supplier_id
    ORDER BY r.created_at DESC
")->fetchAll();

  $allSuppliers = allSuppliers(false);
  $activeSuppliers = array_values(array_filter($allSuppliers, fn($s) => (int)$s['active'] === 1));

include 'includes/header.php';
?>

<?php $s = getFlash('success'); if ($s): ?>
<div class="alert alert-success" data-auto><i data-lucide="check-circle"></i> <?= e($s) ?></div>
<?php endif; ?>
<?php $err = getFlash('error'); if ($err): ?>
<div class="alert alert-danger" data-auto><i data-lucide="alert-circle"></i> <?= e($err) ?></div>
<?php endif; ?>

<?php if ($detailId && isset($receipt)): ?>
<!-- ══ DETAIL VIEW ══════════════════════════════════════════════════════════ -->

<div class="page-header">
  <div class="page-header-text">
    <h1>
      <a href="receipts.php" style="color:var(--text-secondary);text-decoration:none;font-weight:400;font-size:17px;">Receipts</a>
      <span style="color:var(--text-muted);margin:0 6px;">/</span>
      <?= e($receipt['reference']) ?>
    </h1>
  </div>
</div>

<!-- Header card -->
<div class="detail-header-card">
  <div class="detail-ref">Goods Receipt</div>
  <div class="detail-title">
    <?= e($receipt['reference']) ?>
    <?= statusBadge($receipt['status']) ?>
  </div>

  <?php $editable = !in_array($receipt['status'], ['Done','Canceled']); ?>

  <?php if ($editable): ?>
  <form method="POST" action="receipts.php" style="margin-bottom:16px;">
    <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action"  value="update_header">
    <input type="hidden" name="id"      value="<?= $receipt['id'] ?>">
    <div class="form-grid" style="margin-bottom:12px;">
      <div class="form-group">
        <label>Supplier *</label>
        <select name="supplier_id" class="form-control" required>
          <option value="">— Select Supplier —</option>
          <?php foreach ($suppliers as $s): ?>
          <option value="<?= $s['id'] ?>" <?= (int)$receipt['supplier_id'] === (int)$s['id'] ? 'selected' : '' ?>>
            <?= e($s['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div class="text-xs" style="margin-top:4px;">
          <a href="#" onclick="openModal('modal-manage-suppliers');return false;">Add / manage suppliers</a>
        </div>
      </div>
      <div class="form-group">
        <label>Purpose / Reference</label>
        <input type="text" name="purpose" class="form-control" value="<?= e($receipt['purpose']) ?>" placeholder="What is this receipt for?">
      </div>
    </div>
    <input type="hidden" name="supplier" value="<?= e($receipt['supplier']) ?>">
    <button type="submit" class="btn btn-secondary btn-sm"><i data-lucide="save"></i> Save Header</button>
  </form>
  <?php endif; ?>

  <div class="detail-meta">
    <div class="detail-meta-item">
      <label>Supplier</label>
      <div class="value"><?= e($receipt['supplier'] ?: '—') ?></div>
    </div>
    <div class="detail-meta-item">
      <label>Purpose</label>
      <div class="value"><?= e($receipt['purpose'] ?: '—') ?></div>
    </div>
    <div class="detail-meta-item">
      <label>Supplier Contact</label>
      <div class="value">
        <?php if ($receipt['supplier_contact'] || $receipt['supplier_phone'] || $receipt['supplier_email']): ?>
          <?= e($receipt['supplier_contact'] ?: '') ?>
          <?php if ($receipt['supplier_phone']): ?>
            <span class="text-secondary text-sm"> · <?= e($receipt['supplier_phone']) ?></span>
          <?php endif; ?>
          <?php if ($receipt['supplier_email']): ?>
            <div class="text-secondary text-sm"><?= e($receipt['supplier_email']) ?></div>
          <?php endif; ?>
        <?php else: ?>
          —
        <?php endif; ?>
      </div>
    </div>
    <div class="detail-meta-item">
      <label>Status</label>
      <div class="value"><?= statusBadge($receipt['status']) ?></div>
    </div>
    <div class="detail-meta-item">
      <label>Created By</label>
      <div class="value"><?= e($receipt['creator'] ?? '—') ?></div>
    </div>
    <div class="detail-meta-item">
      <label>Date</label>
      <div class="value"><?= date('d M Y, H:i', strtotime($receipt['created_at'])) ?></div>
    </div>
  </div>

  <?php if ($editable): ?>
  <div class="detail-actions" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
    <form method="POST" action="receipts.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="validate">
      <input type="hidden" name="id"     value="<?= $receipt['id'] ?>">
      <button type="submit" class="btn btn-success" onclick="return confirm('Validate receipt and update stock levels?')">
        <i data-lucide="check-circle"></i> Validate & Update Stock
      </button>
    </form>
    <form method="POST" action="receipts.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="cancel">
      <input type="hidden" name="id"     value="<?= $receipt['id'] ?>">
      <button type="submit" class="btn btn-secondary" onclick="return confirm('Cancel this receipt?')" style="color:var(--danger);">
        <i data-lucide="x-circle"></i> Cancel
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>

<!-- Items section -->
<div class="items-section">
  <div class="items-section-header">
    <span class="items-section-title"><i data-lucide="list" style="width:15px;height:15px;vertical-align:-2px;margin-right:6px;"></i>Receipt Lines</span>
    <?php if ($editable): ?>
    <button class="btn btn-primary btn-sm" onclick="openModal('modal-add-item')">
      <i data-lucide="plus"></i> Add Product
    </button>
    <?php endif; ?>
  </div>

  <?php if (empty($items)): ?>
  <div class="empty-state">
    <div class="empty-icon"><i data-lucide="package"></i></div>
    <h3>No products added</h3>
    <p>Add products to receive into stock.</p>
  </div>
  <?php else: ?>
  <form method="POST" action="receipts.php">
    <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="save_items">
    <input type="hidden" name="id"     value="<?= $receipt['id'] ?>">
    <table class="items-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>SKU</th>
          <th>Warehouse / Location</th>
          <th style="text-align:right;">Expected Qty</th>
          <th style="text-align:right;">Received Qty</th>
          <?php if ($editable): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
          <td class="fw-600"><?= e($item['prod_name']) ?></td>
          <td class="font-mono text-secondary"><?= e($item['prod_sku']) ?></td>
          <td>
            <?php if ($editable): ?>
            <select name="items[<?= $item['id'] ?>][location_id]" class="form-control" style="min-width:150px;">
              <option value="">— Select Location —</option>
              <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>" <?= $item['location_id'] == $loc['id'] ? 'selected' : '' ?>>
                <?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php else: ?>
              <?= $item['wh_name'] ? e($item['wh_name'] . ' › ' . $item['loc_name']) : e($item['loc_name'] ?: '—') ?>
            <?php endif; ?>
          </td>
          <td style="text-align:right;" class="text-secondary"><?= $item['qty_expected'] ?> <?= e($item['unit_of_measure']) ?></td>
          <td style="text-align:right;">
            <?php if ($editable): ?>
            <input type="number" name="items[<?= $item['id'] ?>][qty_received]"
                   class="form-control" style="width:100px;text-align:right;"
                   value="<?= $item['qty_received'] ?>" min="0" step="0.01">
            <?php else: ?>
              <strong><?= $item['qty_received'] ?></strong> <?= e($item['unit_of_measure']) ?>
            <?php endif; ?>
          </td>
          <?php if ($editable): ?>
          <td>
            <form method="POST" action="receipts.php" style="display:inline;">
              <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action"  value="delete_item">
              <input type="hidden" name="id"      value="<?= $receipt['id'] ?>">
              <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
              <button type="submit" class="btn-row-remove" title="Remove"><i data-lucide="trash-2"></i></button>
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

<!-- Add Item Modal -->
<?php if ($editable): ?>
<div class="modal-overlay" id="modal-add-item">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Product to Receipt</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-add-item')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="receipts.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="add_item">
      <input type="hidden" name="id"     value="<?= $receipt['id'] ?>">
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
          <label>Warehouse &amp; Location *</label>
          <select name="location_id" class="form-control" required>
            <option value="">— Select Location —</option>
            <?php foreach ($locations as $loc): ?>
            <option value="<?= $loc['id'] ?>"><?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Expected Quantity *</label>
          <input type="number" name="qty_expected" class="form-control" placeholder="0" min="0.01" step="0.01" required>
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
<!-- ══ LIST VIEW ════════════════════════════════════════════════════════════ -->

<div class="page-header">
  <div class="page-header-text">
    <h1>Receipts</h1>
    <p>Manage incoming goods from suppliers</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" onclick="openModal('modal-create')">
      <i data-lucide="plus"></i> New Receipt
    </button>
  </div>
</div>

<div class="filter-bar">
  <div class="search-wrap">
    <i data-lucide="search"></i>
    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search receipts…">
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="receiptsTable">
      <thead>
        <tr>
          <th>Reference</th>
          <th>Supplier</th>
          <th>Items</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($allReceipts)): ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <div class="empty-icon"><i data-lucide="inbox"></i></div>
            <h3>No receipts yet</h3>
            <p>Create a receipt when goods arrive from a supplier.</p>
            <button class="btn btn-primary" onclick="openModal('modal-create')"><i data-lucide="plus"></i> New Receipt</button>
          </div>
        </td></tr>
        <?php else: ?>
          <?php foreach ($allReceipts as $r): ?>
          <tr>
            <td class="fw-600 font-mono"><?= e($r['reference']) ?></td>
            <td><?= e($r['supplier_display'] ?: '—') ?></td>
            <td><span class="badge badge-gray"><?= $r['item_count'] ?> lines</span></td>
            <td><?= statusBadge($r['status']) ?></td>
            <td class="text-secondary text-sm"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            <td>
              <a href="receipts.php?id=<?= $r['id'] ?>" class="btn btn-secondary btn-sm">
                <i data-lucide="eye"></i> View
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create Modal -->
<div class="modal-overlay" id="modal-create">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i data-lucide="inbox" style="width:18px;height:18px;vertical-align:-3px;margin-right:8px;"></i>New Receipt</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-create')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="receipts.php">
      <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action"  value="create">
      <div class="modal-body">
        <div class="form-group">
          <label>Supplier *</label>
          <select name="supplier_id" class="form-control" required>
            <option value="">— Select Supplier —</option>
            <?php foreach ($activeSuppliers as $s): ?>
            <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="text-xs" style="margin-top:4px;">
            <a href="#" onclick="openModal('modal-manage-suppliers');return false;">Add / manage suppliers</a>
          </div>
        </div>
        <div class="form-group">
          <label>Purpose / Reference</label>
          <input type="text" name="purpose" class="form-control" placeholder="e.g. Purchase order number or reason">
        </div>

        <hr style="margin:16px 0;">
        <div class="text-sm text-secondary" style="margin-bottom:8px;">
          <strong>First Product Line (optional)</strong><br>
          Select which product is being received now, how much quantity, and into which warehouse & location.
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Product</label>
            <select name="first_product_id" class="form-control">
              <option value="">— Select Product —</option>
              <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Warehouse &amp; Location</label>
            <select name="first_location_id" class="form-control">
              <option value="">— Select Location —</option>
              <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>"><?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="first_qty_expected" class="form-control" placeholder="0" min="0.01" step="0.01">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="plus"></i> Create Receipt</button>
      </div>
    </form>
  </div>
</div>

<script>bindSearch('searchInput', 'receiptsTable');</script>

<?php endif; ?>

<!-- Supplier Management Modals (available on list and detail views) -->
<div class="modal-overlay" id="modal-manage-suppliers">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title"><i data-lucide="users" style="width:18px;height:18px;vertical-align:-3px;margin-right:8px;"></i>Suppliers</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-manage-suppliers')"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body">
      <div style="margin-bottom:12px;">
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-supplier')">
          <i data-lucide="plus"></i> New Supplier
        </button>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Contact</th>
              <th>Phone</th>
              <th>Email</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($allSuppliers)): ?>
            <tr><td colspan="6" class="text-secondary text-sm">No suppliers yet.</td></tr>
            <?php else: ?>
              <?php foreach ($allSuppliers as $s): ?>
              <tr>
                <td class="fw-600"><?= e($s['name']) ?></td>
                <td class="text-secondary text-sm"><?= e($s['contact_person'] ?: '—') ?></td>
                <td class="text-secondary text-sm"><?= e($s['phone'] ?: '—') ?></td>
                <td class="text-secondary text-sm"><?= e($s['email'] ?: '—') ?></td>
                <td>
                  <?= (int)$s['active'] === 1 ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Inactive</span>' ?>
                </td>
                <td>
                  <button class="btn btn-ghost btn-sm" onclick="fillModal('modal-edit-supplier', {
                    supplier_id: '<?= $s['id'] ?>',
                    name: <?= json_encode($s['name']) ?>,
                    contact_person: <?= json_encode($s['contact_person'] ?? '') ?>,
                    phone: <?= json_encode($s['phone'] ?? '') ?>,
                    email: <?= json_encode($s['email'] ?? '') ?>,
                    address: <?= json_encode($s['address'] ?? '') ?>,
                    active: '<?= (int)$s['active'] ?>'
                  }); return false;">Edit</button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-manage-suppliers')">Close</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-create-supplier">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">New Supplier</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-create-supplier')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="receipts.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="create_supplier">
      <input type="hidden" name="return_receipt_id" value="<?= isset($receipt) ? (int)$receipt['id'] : 0 ?>">
      <div class="modal-body">
        <div class="form-group">
          <label>Name *</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Contact Person</label>
            <input type="text" name="contact_person" class="form-control">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" class="form-control">
        </div>
        <div class="form-group">
          <label>Address</label>
          <textarea name="address" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-supplier')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Supplier</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-edit-supplier">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Supplier</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-edit-supplier')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="receipts.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="update_supplier">
      <input type="hidden" name="supplier_id" id="edit_supplier_id">
      <div class="modal-body">
        <div class="form-group">
          <label>Name *</label>
          <input type="text" name="name" id="edit_supplier_name" class="form-control" required>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Contact Person</label>
            <input type="text" name="contact_person" id="edit_supplier_contact_person" class="form-control">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" id="edit_supplier_phone" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" id="edit_supplier_email" class="form-control">
        </div>
        <div class="form-group">
          <label>Address</label>
          <textarea name="address" id="edit_supplier_address" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="active" id="edit_supplier_active" class="form-control">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-supplier')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Update Supplier</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
