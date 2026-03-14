<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Deliveries';
$activeNav = 'deliveries';
$userId    = (int)$_SESSION['user_id'];
$db        = getDB();

// 
// POST actions
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Create new delivery order
    if ($action === 'create') {
      $ref         = genRef('DEL');
      $customerId  = (int)($_POST['customer_id'] ?? 0) ?: null;
      $customerTxt = trim($_POST['customer'] ?? '');
      $orderRef    = trim($_POST['order_reference'] ?? '');

      // Optional first product line from the create modal
      $firstProductId  = (int)($_POST['first_product_id']  ?? 0);
      $firstLocationId = (int)($_POST['first_location_id'] ?? 0) ?: null;
      $firstQtyOrdered = (float)($_POST['first_qty_ordered'] ?? 0);

        if (!$customerId && $customerTxt === '') {
            flash('error', 'Please provide a customer name or select one.');
            redirect('deliveries.php');
        }

        if ($customerId) {
            $st = $db->prepare('SELECT name FROM customers WHERE id=? AND active=1');
            $st->execute([$customerId]);
            $nameFromMaster = (string)($st->fetchColumn() ?: '');
            if ($nameFromMaster === '') {
                flash('error', 'Selected customer is invalid.');
                redirect('deliveries.php');
            }
            $customerTxt = $nameFromMaster;
        }

        // Resolve created_by safely to avoid foreign key issues
        $createdBy = $userId;
        try {
          $stUser = $db->prepare('SELECT id FROM users WHERE id=?');
          $stUser->execute([$createdBy]);
          if (!$stUser->fetchColumn()) {
            $createdBy = null;
          }
        } catch (Exception $e) {
          $createdBy = null;
        }

        $db->prepare('INSERT INTO deliveries (reference,customer_id,customer,order_reference,created_by) VALUES (?,?,?,?,?)')
           ->execute([$ref, $customerId, $customerTxt, $orderRef, $createdBy]);
        $id = $db->lastInsertId();

        // If a first product line was provided, create it immediately
        if ($firstProductId && $firstLocationId && $firstQtyOrdered > 0) {
          $db->prepare('INSERT INTO delivery_items (delivery_id,product_id,location_id,qty_ordered,qty_delivered)
                        VALUES (?,?,?,?,?)')
             ->execute([$id, $firstProductId, $firstLocationId, $firstQtyOrdered, $firstQtyOrdered]);
        }
        flash('success', 'Delivery order ' . $ref . ' created.');
        redirect("deliveries.php?id=$id");
    }

    // Customer create
    if ($action === 'create_customer') {
        $name          = trim($_POST['name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $returnId      = (int)($_POST['return_delivery_id'] ?? 0);

        if ($name === '') {
            flash('error', 'Customer name is required.');
            redirect($returnId ? "deliveries.php?id=$returnId" : 'deliveries.php');
        }

        $db->prepare('INSERT INTO customers (name,contact_person,phone,email,address) VALUES (?,?,?,?,?)')
           ->execute([$name, $contactPerson, $phone, $email, $address]);
        flash('success', 'Customer created.');
        redirect($returnId ? "deliveries.php?id=$returnId" : 'deliveries.php');
    }

    // Customer update
    if ($action === 'update_customer') {
        $customerId    = (int)($_POST['customer_id'] ?? 0);
        $name          = trim($_POST['name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $active        = isset($_POST['active']) ? (int)$_POST['active'] : 0;

        if ($customerId > 0 && $name !== '') {
            $db->prepare('UPDATE customers SET name=?,contact_person=?,phone=?,email=?,address=?,active=? WHERE id=?')
               ->execute([$name, $contactPerson, $phone, $email, $address, $active, $customerId]);
            flash('success', 'Customer details updated.');
        } else {
            flash('error', 'Customer name is required.');
        }
        redirect('deliveries.php');
    }

    // Update header
    if ($action === 'update_header') {
        $id         = (int)($_POST['id'] ?? 0);
        $customerId = (int)($_POST['customer_id'] ?? 0) ?: null;
        $customer   = trim($_POST['customer'] ?? '');
      $orderRef   = trim($_POST['order_reference'] ?? '');

        if ($id) {
            if ($customerId) {
                $st = $db->prepare('SELECT name FROM customers WHERE id=?');
                $st->execute([$customerId]);
                $nameFromMaster = (string)($st->fetchColumn() ?: '');
                if ($nameFromMaster !== '') {
                    $customer = $nameFromMaster;
                }
            }

            $db->prepare('UPDATE deliveries
                      SET customer_id=?,customer=?,order_reference=?,updated_at=CURRENT_TIMESTAMP
                      WHERE id=? AND status NOT IN ("Done","Canceled")')
              ->execute([$customerId, $customer, $orderRef, $id]);
        }
        redirect("deliveries.php?id=$id");
    }

    // Add item line
    if ($action === 'add_item') {
        $id         = (int)($_POST['id']          ?? 0);
        $productId  = (int)($_POST['product_id']  ?? 0);
        $locationId = (int)($_POST['location_id'] ?? 0) ?: null;
        $qtyOrdered = (float)($_POST['qty_ordered'] ?? 0);
        if ($id && $productId && $qtyOrdered > 0) {
            $db->prepare('INSERT INTO delivery_items (delivery_id,product_id,location_id,qty_ordered,qty_delivered)
                          VALUES (?,?,?,?,?)')
               ->execute([$id, $productId, $locationId, $qtyOrdered, $qtyOrdered]);
        }
        redirect("deliveries.php?id=$id");
    }

    // Save item quantities / locations
    if ($action === 'save_items') {
        $id    = (int)($_POST['id'] ?? 0);
        $items = $_POST['items'] ?? [];
        foreach ($items as $itemId => $vals) {
            $qtyDel = (float)($vals['qty_delivered'] ?? 0);
            $locId  = (int)($vals['location_id'] ?? 0) ?: null;
            $db->prepare('UPDATE delivery_items SET qty_delivered=?,location_id=? WHERE id=? AND delivery_id=?')
               ->execute([$qtyDel, $locId, (int)$itemId, $id]);
        }
        flash('success', 'Quantities saved.');
        redirect("deliveries.php?id=$id");
    }

    // Delete item
    if ($action === 'delete_item') {
        $id     = (int)($_POST['id']      ?? 0);
        $itemId = (int)($_POST['item_id'] ?? 0);
        $db->prepare('DELETE FROM delivery_items WHERE id=? AND delivery_id=?')->execute([$itemId, $id]);
        redirect("deliveries.php?id=$id");
    }

    // Mark as picked
    if ($action === 'mark_picked') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('UPDATE deliveries SET status="Picked",updated_at=CURRENT_TIMESTAMP WHERE id=? AND status NOT IN ("Done","Canceled")')
           ->execute([$id]);
        redirect("deliveries.php?id=$id");
    }

    // Mark as packed
    if ($action === 'mark_packed') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('UPDATE deliveries SET status="Packed",updated_at=CURRENT_TIMESTAMP WHERE id=? AND status NOT IN ("Done","Canceled")')
           ->execute([$id]);
        redirect("deliveries.php?id=$id");
    }

    // Validate -> Done (deduct stock)
    if ($action === 'validate') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM deliveries WHERE id=?');
        $stmt->execute([$id]);
        $delivery = $stmt->fetch();

        if ($delivery && !in_array($delivery['status'], ['Done','Canceled'], true)) {
            if (empty($delivery['customer_id'])) {
                flash('error', 'Select a customer before validating this delivery.');
                redirect("deliveries.php?id=$id");
            }

            $itemsStmt = $db->prepare('SELECT * FROM delivery_items WHERE delivery_id=?');
            $itemsStmt->execute([$id]);
            $rows   = $itemsStmt->fetchAll();
            $errors = [];
            $hasQty = false;

            foreach ($rows as $item) {
                $qty = (float)$item['qty_delivered'];
                if ($qty > 0) {
                    $hasQty = true;
                    if (!(int)$item['location_id']) {
                        $errors[] = 'Each delivered line must have a source location.';
                        continue;
                    }
                    $avail = stockQty((int)$item['product_id'], (int)$item['location_id']);
                    if ($avail < $qty) {
                        $errors[] = 'Insufficient stock at selected location.';
                    }
                }
            }

            if (!$hasQty) {
                $errors[] = 'Add at least one line with delivered quantity > 0 before validation.';
            }

            if (empty($errors)) {
                foreach ($rows as $item) {
                    if ((float)$item['qty_delivered'] > 0 && $item['location_id']) {
                        adjustStock(
                            (int)$item['product_id'],
                            (int)$item['location_id'],
                            -(float)$item['qty_delivered'],
                            'Delivery',
                            $delivery['reference'],
                            $userId,
                            'Validated delivery'
                        );
                    }
                }
                $db->prepare('UPDATE deliveries SET status="Done",updated_at=CURRENT_TIMESTAMP WHERE id=?')
                   ->execute([$id]);
                flash('success', 'Delivery validated. Stock deducted.');
            } else {
                flash('error', implode(' ', $errors));
            }
        }
        redirect("deliveries.php?id=$id");
    }

    // Cancel
    if ($action === 'cancel') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('UPDATE deliveries SET status="Canceled",updated_at=CURRENT_TIMESTAMP WHERE id=? AND status NOT IN ("Done","Canceled")')
           ->execute([$id]);
        redirect("deliveries.php?id=$id");
    }

    redirect('deliveries.php');
}

// 
// View mode
// -------------------------------------------------------------
$detailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($detailId) {
    $stmt = $db->prepare('SELECT d.*, u.name creator,
                                 c.name customer_name,
                                 c.contact_person customer_contact,
                                 c.phone customer_phone,
                                 c.email customer_email,
                                 c.address customer_address
                          FROM deliveries d
                          LEFT JOIN users u ON u.id = d.created_by
                          LEFT JOIN customers c ON c.id = d.customer_id
                          WHERE d.id=?');
    $stmt->execute([$detailId]);
    $delivery = $stmt->fetch();
    if (!$delivery) {
        flash('error', 'Delivery not found.');
        redirect('deliveries.php');
    }

    $dItems = $db->prepare('SELECT di.*, p.name prod_name, p.sku prod_sku, p.unit_of_measure,
                                   l.name loc_name, w.name wh_name
                            FROM delivery_items di
                            JOIN products p  ON p.id  = di.product_id
                            LEFT JOIN locations l ON l.id = di.location_id
                            LEFT JOIN warehouses w ON w.id = l.warehouse_id
                            WHERE di.delivery_id = ?
                            ORDER BY di.id');
    $dItems->execute([$detailId]);
                $items     = $dItems->fetchAll();
                $customers = allCustomers(false);
                $pageTitle = 'Delivery ' . $delivery['reference'];
}

              $products  = allProducts();
              $locations = allLocations();

$allDeliveries = $db->query('SELECT d.*, u.name creator,
                                    COALESCE(c.name, d.customer) customer_display,
                                    (SELECT COUNT(*) FROM delivery_items WHERE delivery_id=d.id) item_count
                             FROM deliveries d
                             LEFT JOIN users u ON u.id = d.created_by
                             LEFT JOIN customers c ON c.id = d.customer_id
                             ORDER BY d.created_at DESC')->fetchAll();

$allCustomers   = allCustomers(false);
$activeCustomers = array_values(array_filter($allCustomers, fn($c) => (int)$c['active'] === 1));

include 'includes/header.php';
?>

<?php $s = getFlash('success'); if ($s): ?>
<div class="alert alert-success" data-auto><i data-lucide="check-circle"></i> <?= e($s) ?></div>
<?php endif; ?>
<?php $err = getFlash('error'); if ($err): ?>
<div class="alert alert-danger" data-auto><i data-lucide="alert-circle"></i> <?= e($err) ?></div>
<?php endif; ?>

<?php if ($detailId && isset($delivery)): ?>
<!-- DETAIL VIEW -->
<div class="page-header">
  <div class="page-header-text">
    <h1>
      <a href="deliveries.php" style="color:var(--text-secondary);text-decoration:none;font-weight:400;font-size:17px;">Deliveries</a>
      <span style="color:var(--text-muted);margin:0 6px;">/</span>
      <?= e($delivery['reference']) ?>
    </h1>
  </div>
</div>

<?php $editable = !in_array($delivery['status'], ['Done','Canceled'], true); ?>

<div class="detail-header-card">
  <div class="detail-ref">Delivery Order</div>
  <div class="detail-title"><?= e($delivery['reference']) ?> <?= statusBadge($delivery['status']) ?></div>

  <?php if ($editable): ?>
  <form method="POST" action="deliveries.php" style="margin-bottom:16px;">
    <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="update_header">
    <input type="hidden" name="id"     value="<?= $delivery['id'] ?>">
    <div class="form-grid" style="margin-bottom:12px;">
      <div class="form-group">
        <label>Customer *</label>
        <select name="customer_id" class="form-control" required>
          <option value="">— Select Customer —</option>
          <?php foreach ($customers as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (int)$delivery['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div class="text-xs" style="margin-top:4px;">
          <a href="#" onclick="openModal('modal-manage-customers');return false;">Add / manage customers</a>
        </div>
      </div>
      <div class="form-group">
        <label>Order Reference</label>
        <input type="text" name="order_reference" class="form-control" value="<?= e($delivery['order_reference']) ?>" placeholder="Sales order / reference">
      </div>
    </div>
    <input type="hidden" name="customer" value="<?= e($delivery['customer']) ?>">
    <button type="submit" class="btn btn-secondary btn-sm"><i data-lucide="save"></i> Save Header</button>
  </form>
  <?php endif; ?>

  <div class="detail-meta">
    <div class="detail-meta-item"><label>Customer</label><div class="value"><?= e($delivery['customer'] ?: '—') ?></div></div>
    <div class="detail-meta-item"><label>Order Ref</label><div class="value"><?= e($delivery['order_reference'] ?: '—') ?></div></div>
    <div class="detail-meta-item">
      <label>Customer Contact</label>
      <div class="value">
        <?php if ($delivery['customer_contact'] || $delivery['customer_phone'] || $delivery['customer_email']): ?>
          <?= e($delivery['customer_contact'] ?: '') ?>
          <?php if ($delivery['customer_phone']): ?>
            <span class="text-secondary text-sm"> · <?= e($delivery['customer_phone']) ?></span>
          <?php endif; ?>
          <?php if ($delivery['customer_email']): ?>
            <div class="text-secondary text-sm"><?= e($delivery['customer_email']) ?></div>
          <?php endif; ?>
        <?php else: ?>
          —
        <?php endif; ?>
      </div>
    </div>
    <div class="detail-meta-item"><label>Status</label><div class="value"><?= statusBadge($delivery['status']) ?></div></div>
    <div class="detail-meta-item"><label>Created By</label><div class="value"><?= e($delivery['creator'] ?? '—') ?></div></div>
    <div class="detail-meta-item"><label>Date</label><div class="value"><?= date('d M Y, H:i', strtotime($delivery['created_at'])) ?></div></div>
  </div>

  <?php if ($editable): ?>
  <div class="detail-actions" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
    <form method="POST" action="deliveries.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="mark_picked">
      <input type="hidden" name="id"     value="<?= $delivery['id'] ?>">
      <button type="submit" class="btn btn-secondary">
        <i data-lucide="check-square"></i> Mark as Picked
      </button>
    </form>
    <form method="POST" action="deliveries.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="mark_packed">
      <input type="hidden" name="id"     value="<?= $delivery['id'] ?>">
      <button type="submit" class="btn btn-secondary">
        <i data-lucide="package"></i> Mark as Packed
      </button>
    </form>
    <form method="POST" action="deliveries.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="validate">
      <input type="hidden" name="id"     value="<?= $delivery['id'] ?>">
      <button type="submit" class="btn btn-success" onclick="return confirm('Validate and deduct stock?');">
        <i data-lucide="check-circle"></i> Validate & Deduct Stock
      </button>
    </form>
    <form method="POST" action="deliveries.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="cancel">
      <input type="hidden" name="id"     value="<?= $delivery['id'] ?>">
      <button type="submit" class="btn btn-secondary" onclick="return confirm('Cancel this delivery?');" style="color:var(--danger);">
        <i data-lucide="x-circle"></i> Cancel
      </button>
    </form>
  </div>
  <?php endif; ?>
</div>

<!-- Items -->
<div class="items-section">
  <div class="items-section-header">
    <span class="items-section-title"><i data-lucide="list" style="width:15px;height:15px;vertical-align:-2px;margin-right:6px;"></i>Delivery Lines</span>
    <?php if ($editable): ?>
    <button class="btn btn-primary btn-sm" onclick="openModal('modal-add-item')"><i data-lucide="plus"></i> Add Product</button>
    <?php endif; ?>
  </div>

  <?php if (empty($items)): ?>
  <div class="empty-state"><div class="empty-icon"><i data-lucide="package"></i></div><h3>No products added</h3><p>Add products to deliver.</p></div>
  <?php else: ?>
  <form method="POST" action="deliveries.php">
    <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="save_items">
    <input type="hidden" name="id"     value="<?= $delivery['id'] ?>">
    <table class="items-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>SKU</th>
          <th>Source Location</th>
          <th style="text-align:right;">Ordered Qty</th>
          <th style="text-align:right;">Delivered Qty</th>
          <th style="text-align:right;">Available</th>
          <?php if ($editable): ?><th></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <?php $avail = $item['location_id'] ? stockQty((int)$item['product_id'], (int)$item['location_id']) : stockQty((int)$item['product_id']); ?>
        <tr>
          <td class="fw-600"><?= e($item['prod_name']) ?></td>
          <td class="font-mono text-secondary"><?= e($item['prod_sku']) ?></td>
          <td>
            <?php if ($editable): ?>
            <select name="items[<?= $item['id'] ?>][location_id]" class="form-control" style="min-width:150px;">
              <option value="">— Location —</option>
              <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>" <?= $item['location_id'] == $loc['id'] ? 'selected' : '' ?>>
                <?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php else: ?>
              <?= $item['wh_name'] ? e($item['wh_name'] . ' › ' . $item['loc_name']) : '—' ?>
            <?php endif; ?>
          </td>
          <td style="text-align:right;" class="text-secondary"><?= $item['qty_ordered'] ?></td>
          <td style="text-align:right;">
            <?php if ($editable): ?>
            <input type="number" name="items[<?= $item['id'] ?>][qty_delivered]"
                   class="form-control" style="width:100px;text-align:right;"
                   value="<?= $item['qty_delivered'] ?>" min="0" step="0.01">
            <?php else: ?><strong><?= $item['qty_delivered'] ?></strong><?php endif; ?>
          </td>
          <td style="text-align:right;">
            <?php if ($avail <= 0): ?>
              <span class="stock-zero"><?= $avail ?></span>
            <?php else: ?>
              <span class="stock-ok"><?= $avail ?></span>
            <?php endif; ?>
            <span class="text-muted text-xs"> <?= e($item['unit_of_measure']) ?></span>
          </td>
          <?php if ($editable): ?>
          <td>
            <form method="POST" action="deliveries.php" style="display:inline;">
              <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="action"  value="delete_item">
              <input type="hidden" name="id"      value="<?= $delivery['id'] ?>">
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

<!-- Add Item Modal -->
<?php if ($editable): ?>
<div class="modal-overlay" id="modal-add-item">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Product to Delivery</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-add-item')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="deliveries.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="add_item">
      <input type="hidden" name="id"     value="<?= $delivery['id'] ?>">
      <div class="modal-body">
        <div class="form-group">
          <label>Product *</label>
          <select name="product_id" class="form-control" required>
            <option value="">— Select Product —</option>
            <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>) – Stock: <?= $p['total_stock'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Source Location *</label>
          <select name="location_id" class="form-control" required>
            <option value="">— Select Location —</option>
            <?php foreach ($locations as $loc): ?>
            <option value="<?= $loc['id'] ?>"><?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Quantity *</label>
          <input type="number" name="qty_ordered" class="form-control" placeholder="0" min="0.01" step="0.01" required>
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
<!-- LIST VIEW -->
<div class="page-header">
  <div class="page-header-text">
    <h1>Deliveries</h1>
    <p>Manage outgoing stock to customers</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" onclick="openModal('modal-create')"><i data-lucide="plus"></i> New Delivery</button>
  </div>
</div>

<div class="filter-bar">
  <div class="search-wrap">
    <i data-lucide="search"></i>
    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search deliveries…">
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="deliveriesTable">
      <thead>
        <tr><th>Reference</th><th>Customer</th><th>Items</th><th>Status</th><th>Created</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($allDeliveries)): ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <div class="empty-icon"><i data-lucide="truck"></i></div>
            <h3>No deliveries yet</h3>
            <p>Create a delivery order when stock leaves the warehouse.</p>
            <button class="btn btn-primary" onclick="openModal('modal-create')"><i data-lucide="plus"></i> New Delivery</button>
          </div>
        </td></tr>
        <?php else: ?>
          <?php foreach ($allDeliveries as $d): ?>
          <tr>
            <td class="fw-600 font-mono"><?= e($d['reference']) ?></td>
            <td><?= e($d['customer_display'] ?: '—') ?></td>
            <td><span class="badge badge-gray"><?= $d['item_count'] ?> lines</span></td>
            <td><?= statusBadge($d['status']) ?></td>
            <td class="text-secondary text-sm"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
            <td><a href="deliveries.php?id=<?= $d['id'] ?>" class="btn btn-secondary btn-sm"><i data-lucide="eye"></i> View</a></td>
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
      <span class="modal-title"><i data-lucide="truck" style="width:18px;height:18px;vertical-align:-3px;margin-right:8px;"></i>New Delivery Order</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-create')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="deliveries.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-group">
          <label>Customer *</label>
          <select name="customer_id" class="form-control" required>
            <option value="">— Select Customer —</option>
            <?php foreach ($activeCustomers as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="text-xs" style="margin-top:4px;">
            <a href="#" onclick="openModal('modal-manage-customers');return false;">Add / manage customers</a>
          </div>
        </div>
        <div class="form-group">
          <label>Order Reference</label>
          <input type="text" name="order_reference" class="form-control" placeholder="Sales order / reference">
        </div>

        <hr style="margin:16px 0;">
        <div class="text-sm text-secondary" style="margin-bottom:8px;">
          <strong>First Product Line (optional)</strong><br>
          Select which product to deliver now, how much quantity, and from which warehouse & location.
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Product</label>
            <select name="first_product_id" class="form-control">
              <option value="">— Select Product —</option>
              <?php foreach ($products as $p): ?>
              <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>) – Stock: <?= $p['total_stock'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Source Location</label>
            <select name="first_location_id" class="form-control">
              <option value="">— Select Location —</option>
              <?php foreach ($locations as $loc): ?>
              <option value="<?= $loc['id'] ?>"><?= e($loc['wh_name']) ?> › <?= e($loc['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="first_qty_ordered" class="form-control" placeholder="0" min="0.01" step="0.01">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="plus"></i> Create Delivery</button>
      </div>
    </form>
  </div>
</div>

<script>bindSearch('searchInput', 'deliveriesTable');</script>
<?php endif; ?>

<!-- Customer Management Modals -->
<div class="modal-overlay" id="modal-manage-customers">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title"><i data-lucide="users" style="width:18px;height:18px;vertical-align:-3px;margin-right:8px;"></i>Customers</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-manage-customers')"><i data-lucide="x"></i></button>
    </div>
    <div class="modal-body">
      <div style="margin-bottom:12px;">
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-customer')">
          <i data-lucide="plus"></i> New Customer
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
            <?php if (empty($allCustomers)): ?>
            <tr><td colspan="6" class="text-secondary text-sm">No customers yet.</td></tr>
            <?php else: ?>
              <?php foreach ($allCustomers as $c): ?>
              <tr>
                <td class="fw-600"><?= e($c['name']) ?></td>
                <td class="text-secondary text-sm"><?= e($c['contact_person'] ?: '—') ?></td>
                <td class="text-secondary text-sm"><?= e($c['phone'] ?: '—') ?></td>
                <td class="text-secondary text-sm"><?= e($c['email'] ?: '—') ?></td>
                <td>
                  <?= (int)$c['active'] === 1 ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Inactive</span>' ?>
                </td>
                <td>
                  <button class="btn btn-ghost btn-sm" onclick="fillModal('modal-edit-customer', {
                    customer_id: '<?= $c['id'] ?>',
                    name: <?= json_encode($c['name']) ?>,
                    contact_person: <?= json_encode($c['contact_person'] ?? '') ?>,
                    phone: <?= json_encode($c['phone'] ?? '') ?>,
                    email: <?= json_encode($c['email'] ?? '') ?>,
                    address: <?= json_encode($c['address'] ?? '') ?>,
                    active: '<?= (int)$c['active'] ?>'
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
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-manage-customers')">Close</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-create-customer">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">New Customer</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-create-customer')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="deliveries.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="create_customer">
      <input type="hidden" name="return_delivery_id" value="<?= isset($delivery) ? (int)$delivery['id'] : 0 ?>">
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
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-customer')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Customer</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-edit-customer">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Customer</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-edit-customer')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="deliveries.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="update_customer">
      <input type="hidden" name="customer_id" id="edit_customer_id">
      <div class="modal-body">
        <div class="form-group">
          <label>Name *</label>
          <input type="text" name="name" id="edit_customer_name" class="form-control" required>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Contact Person</label>
            <input type="text" name="contact_person" id="edit_customer_contact_person" class="form-control">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" id="edit_customer_phone" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" id="edit_customer_email" class="form-control">
        </div>
        <div class="form-group">
          <label>Address</label>
          <textarea name="address" id="edit_customer_address" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="active" id="edit_customer_active" class="form-control">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-customer')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Update Customer</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
