<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Products';
$activeNav = 'products';
$userId    = (int)$_SESSION['user_id'];
$db        = getDB();

// ── POST actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = trim($_POST['name']  ?? '');
        $sku   = strtoupper(trim($_POST['sku'] ?? ''));
        $catId = (int)($_POST['category_id'] ?? 0) ?: null;
        $uom   = trim($_POST['unit_of_measure'] ?? 'Units');
        $initialStock = max(0, (float)($_POST['initial_stock'] ?? 0));
        $desc  = trim($_POST['description'] ?? '');

        if ($name && $sku) {
            try {
                $db->prepare("INSERT INTO products (name,sku,category_id,unit_of_measure,reorder_qty,description)
                              VALUES (?,?,?,?,?,?)")
                   ->execute([$name, $sku, $catId, $uom, 0, $desc]);

                $productId = (int)$db->lastInsertId();
                if ($initialStock > 0) {
                    $defaultLocationId = (int)$db->query("SELECT id FROM locations WHERE active=1 ORDER BY id LIMIT 1")->fetchColumn();
                    if ($defaultLocationId > 0) {
                        adjustStock(
                            $productId,
                            $defaultLocationId,
                            $initialStock,
                            'Opening',
                            'PRODUCT/' . $sku,
                            $userId,
                            'Initial stock set during product creation'
                        );
                    }
                }
                flash('success', 'Product "' . htmlspecialchars($name, ENT_QUOTES) . '" created.');
            } catch (PDOException $e) {
                flash('error', 'SKU already exists or invalid data.');
            }
        } else {
            flash('error', 'Product name and SKU are required.');
        }
        redirect('products.php');
    }

    if ($action === 'update') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name']  ?? '');
        $sku   = strtoupper(trim($_POST['sku'] ?? ''));
        $catId = (int)($_POST['category_id'] ?? 0) ?: null;
        $uom   = trim($_POST['unit_of_measure'] ?? 'Units');
        $rqty  = (float)($_POST['reorder_qty'] ?? 0);
        $desc  = trim($_POST['description'] ?? '');

        if ($id && $name && $sku) {
            try {
                $db->prepare("UPDATE products SET name=?,sku=?,category_id=?,unit_of_measure=?,reorder_qty=?,description=? WHERE id=?")
                   ->execute([$name, $sku, $catId, $uom, $rqty, $desc, $id]);
                flash('success', 'Product updated.');
            } catch (PDOException $e) {
                flash('error', 'SKU already in use.');
            }
        }
        redirect('products.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);
            flash('success', 'Product removed.');
        }
        redirect('products.php');
    }

    if ($action === 'create_category') {
        $name = trim($_POST['cat_name'] ?? '');
        if ($name) {
            $db->prepare("INSERT INTO product_categories (name) VALUES (?)")->execute([$name]);
            flash('success', 'Category "' . htmlspecialchars($name, ENT_QUOTES) . '" created.');
        }
        redirect('products.php');
    }
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$products   = allProducts();
$categories = allCategories(true);
$globalLowStockThreshold = (float)(getSetting('global_low_stock_threshold', '0') ?? '0');

include 'includes/header.php';
?>

<!-- Flash -->
<?php $s = getFlash('success'); if ($s): ?>
<div class="alert alert-success" data-auto><i data-lucide="check-circle"></i> <?= e($s) ?></div>
<?php endif; ?>
<?php $err = getFlash('error'); if ($err): ?>
<div class="alert alert-danger" data-auto><i data-lucide="alert-circle"></i> <?= e($err) ?></div>
<?php endif; ?>

<div class="page-header">
  <div class="page-header-text">
    <h1>Products</h1>
    <p>Manage your product catalog and stock reorder rules</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-secondary" onclick="openModal('modal-category')">
      <i data-lucide="tag"></i> Add Category
    </button>
    <button class="btn btn-primary" onclick="openModal('modal-create-product')">
      <i data-lucide="plus"></i> New Product
    </button>
  </div>
</div>

<!-- Filter bar -->
<div class="filter-bar">
  <div class="search-wrap">
    <i data-lucide="search"></i>
    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search products…">
  </div>
  <select id="catFilter" class="form-control" style="width:auto;min-width:160px;">
    <option value="">All Categories</option>
    <?php foreach ($categories as $c): ?>
    <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<!-- Products table -->
<div class="card">
  <div class="table-wrap">
    <table id="productsTable">
      <thead>
        <tr>
          <th>SKU</th>
          <th>Name</th>
          <th>Category</th>
          <th>UoM</th>
          <th style="text-align:right;">Total Stock</th>
          <th style="text-align:right;">Reorder At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <div class="empty-icon"><i data-lucide="package"></i></div>
            <h3>No products yet</h3>
            <p>Create your first product to get started.</p>
            <button class="btn btn-primary" onclick="openModal('modal-create-product')">
              <i data-lucide="plus"></i> New Product
            </button>
          </div>
        </td></tr>
        <?php else: ?>
          <?php foreach ($products as $p): ?>
          <tr data-cat="<?= e($p['category_id'] ?? '') ?>">
            <td class="font-mono text-secondary"><?= e($p['sku']) ?></td>
            <td class="fw-600"><?= e($p['name']) ?></td>
            <td>
              <?php if ($p['cat_name']): ?>
                <span class="badge badge-indigo"><?= e($p['cat_name']) ?></span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="text-secondary text-sm"><?= e($p['unit_of_measure']) ?></td>
            <td style="text-align:right;">
              <?php
                $qty = (float)$p['total_stock'];
                $effectiveThreshold = $globalLowStockThreshold > 0
                  ? $globalLowStockThreshold
                  : (float)$p['reorder_qty'];
              ?>
              <?php if ($qty == 0): ?>
                <span class="stock-zero stock-number">0</span>
              <?php elseif ($effectiveThreshold > 0 && $qty <= $effectiveThreshold): ?>
                <span class="stock-low stock-number"><?= $qty ?></span>
              <?php else: ?>
                <span class="stock-ok stock-number"><?= $qty ?></span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;" class="text-secondary"><?php
              if ($effectiveThreshold > 0) {
                  echo $effectiveThreshold;
              } else {
                  echo '—';
              }
            ?></td>
            <td>
              <div class="d-flex gap-2">
                <button class="btn btn-ghost btn-sm" title="Edit"
                  onclick="fillModal('modal-edit-product', {
                    id: '<?= $p['id'] ?>',
                    name: <?= json_encode($p['name']) ?>,
                    sku: <?= json_encode($p['sku']) ?>,
                    category_id: '<?= $p['category_id'] ?? '' ?>',
                    unit_of_measure: <?= json_encode($p['unit_of_measure']) ?>,
                    reorder_qty: '<?= $p['reorder_qty'] ?>',
                    description: <?= json_encode($p['description'] ?? '') ?>
                  })">
                  <i data-lucide="pencil"></i>
                </button>
                <form method="POST" style="display:inline;" onsubmit="return confirmDelete(this,'Delete product?')">
                  <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
                  <input type="hidden" name="action"  value="delete">
                  <input type="hidden" name="id"      value="<?= $p['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" title="Delete" style="color:var(--danger);">
                    <i data-lucide="trash-2"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Create Product Modal ────────────────────── -->
<div class="modal-overlay" id="modal-create-product">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title"><i data-lucide="package" style="width:18px;height:18px;vertical-align:-3px;margin-right:8px;"></i>New Product</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-create-product')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="products.php">
      <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action"  value="create">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Product Name *</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Steel Rods" required>
          </div>
          <div class="form-group">
            <label>SKU / Code *</label>
            <input type="text" name="sku" class="form-control" placeholder="e.g. STL-ROD-001" required style="text-transform:uppercase;">
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Category</label>
            <select name="category_id" class="form-control">
              <option value="">— None —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Unit of Measure</label>
            <select name="unit_of_measure" class="form-control">
              <option>Units</option><option>Kg</option><option>Grams</option>
              <option>Litres</option><option>Meters</option><option>Boxes</option>
              <option>Packs</option><option>Pieces</option><option>Tons</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Initial Stock <span class="text-muted text-sm">(optional)</span></label>
          <input type="number" name="initial_stock" class="form-control" placeholder="0" min="0" step="0.01">
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" class="form-control" placeholder="Optional notes…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-product')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Product</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Product Modal ──────────────────────── -->
<div class="modal-overlay" id="modal-edit-product">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title"><i data-lucide="pencil" style="width:18px;height:18px;vertical-align:-3px;margin-right:8px;"></i>Edit Product</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-edit-product')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="products.php">
      <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action"  value="update">
      <input type="hidden" name="id"      id="edit_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Product Name *</label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>SKU / Code *</label>
            <input type="text" name="sku" id="edit_sku" class="form-control" required style="text-transform:uppercase;">
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Category</label>
            <select name="category_id" id="edit_category_id" class="form-control">
              <option value="">— None —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Unit of Measure</label>
            <select name="unit_of_measure" id="edit_unit_of_measure" class="form-control">
              <option>Units</option><option>Kg</option><option>Grams</option>
              <option>Litres</option><option>Meters</option><option>Boxes</option>
              <option>Packs</option><option>Pieces</option><option>Tons</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Reorder Quantity</label>
          <input type="number" name="reorder_qty" id="edit_reorder_qty" class="form-control" min="0" step="0.01">
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="description" id="edit_description" class="form-control"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-product')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Update Product</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Add Category Modal ─────────────────────── -->
<div class="modal-overlay" id="modal-category">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i data-lucide="tag" style="width:18px;height:18px;vertical-align:-3px;margin-right:8px;"></i>New Category</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-category')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="products.php">
      <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action"  value="create_category">
      <div class="modal-body">
        <div class="form-group">
          <label>Category Name *</label>
          <input type="text" name="cat_name" class="form-control" placeholder="e.g. Raw Materials" required autofocus>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-category')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Create</button>
      </div>
    </form>
  </div>
</div>

<script>
bindSearch('searchInput', 'productsTable');

// Category filter
document.getElementById('catFilter').addEventListener('change', function () {
    const val = this.value;
    document.querySelectorAll('#productsTable tbody tr[data-cat]').forEach(function (tr) {
        tr.style.display = (!val || tr.dataset.cat == val) ? '' : 'none';
    });
});
</script>

<?php include 'includes/footer.php'; ?>
