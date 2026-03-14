<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Warehouses';
$activeNav = 'warehouses';
$userId    = (int)$_SESSION['user_id'];
$db        = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_warehouse') {
        $name    = trim($_POST['name']    ?? '');
        $code    = strtoupper(trim($_POST['code'] ?? ''));
        $address = trim($_POST['address'] ?? '');
        if ($name && $code) {
            try {
                $db->prepare("INSERT INTO warehouses (name,code,address) VALUES (?,?,?)")
                   ->execute([$name, $code, $address]);
                flash('success', 'Warehouse "' . htmlspecialchars($name, ENT_QUOTES) . '" created.');
            } catch (PDOException $e) {
                flash('error', 'Warehouse code already exists.');
            }
        }
        redirect('warehouses.php');
    }

    if ($action === 'update_warehouse') {
        $id      = (int)($_POST['id']   ?? 0);
        $name    = trim($_POST['name']  ?? '');
        $code    = strtoupper(trim($_POST['code']    ?? ''));
        $address = trim($_POST['address'] ?? '');
        if ($id && $name && $code) {
            try {
                $db->prepare("UPDATE warehouses SET name=?,code=?,address=? WHERE id=?")
                   ->execute([$name, $code, $address, $id]);
                flash('success', 'Warehouse updated.');
            } catch (PDOException $e) {
                flash('error', 'Code already in use.');
            }
        }
        redirect('warehouses.php');
    }

    if ($action === 'delete_warehouse') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE warehouses SET active=0 WHERE id=?")->execute([$id]);
            flash('success', 'Warehouse removed.');
        }
        redirect('warehouses.php');
    }

    if ($action === 'create_location') {
        $whId = (int)($_POST['warehouse_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($whId && $name && $code) {
            $db->prepare("INSERT INTO locations (warehouse_id,name,code) VALUES (?,?,?)")
               ->execute([$whId, $name, $code]);
            flash('success', 'Location "' . htmlspecialchars($name, ENT_QUOTES) . '" created.');
        }
        redirect('warehouses.php');
    }

    if ($action === 'update_location') {
        $id   = (int)($_POST['id']   ?? 0);
        $name = trim($_POST['name']  ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $whId = (int)($_POST['warehouse_id'] ?? 0);
        if ($id && $name && $code) {
            $db->prepare("UPDATE locations SET name=?,code=?,warehouse_id=? WHERE id=?")
               ->execute([$name, $code, $whId, $id]);
            flash('success', 'Location updated.');
        }
        redirect('warehouses.php');
    }

    if ($action === 'delete_location') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE locations SET active=0 WHERE id=?")->execute([$id]);
            flash('success', 'Location removed.');
        }
        redirect('warehouses.php');
    }

    redirect('warehouses.php');
}

$warehouses = $db->query("SELECT * FROM warehouses WHERE active=1 ORDER BY name")->fetchAll();
$locations  = $db->query("
  SELECT l.*, w.name wh_name FROM locations l
  JOIN warehouses w ON w.id = l.warehouse_id
  WHERE l.active=1 ORDER BY w.name, l.name
")->fetchAll();
// Pre-group location counts per warehouse for O(1) lookup
$locCountMap = [];
foreach ($locations as $l) {
  $locCountMap[(int)$l['warehouse_id']] = ($locCountMap[(int)$l['warehouse_id']] ?? 0) + 1;
}

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
    <h1>Warehouses</h1>
    <p>Manage warehouses and storage locations</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-secondary" onclick="openModal('modal-create-location')">
      <i data-lucide="map-pin"></i> Add Location
    </button>
    <button class="btn btn-primary" onclick="openModal('modal-create-warehouse')">
      <i data-lucide="plus"></i> New Warehouse
    </button>
  </div>
</div>

<!-- Warehouses table -->
<div class="card mb-6">
  <div class="card-header">
    <span class="card-title"><i data-lucide="warehouse" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px;"></i>Warehouses</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Name</th><th>Code</th><th>Address</th><th>Locations</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($warehouses)): ?>
        <tr><td colspan="5">
          <div class="empty-state" style="padding:32px;">
            <div class="empty-icon"><i data-lucide="warehouse"></i></div>
            <h3>No warehouses</h3>
            <p>Add a warehouse to get started.</p>
          </div>
        </td></tr>
        <?php else: ?>
          <?php foreach ($warehouses as $wh): ?>
          <?php $locCount = $locCountMap[(int)$wh['id']] ?? 0; ?>
          <tr>
            <td class="fw-600"><?= e($wh['name']) ?></td>
            <td class="font-mono text-secondary"><?= e($wh['code']) ?></td>
            <td class="text-secondary"><?= e($wh['address'] ?: '—') ?></td>
            <td><span class="badge badge-gray"><?= count(array_filter($locations, fn($l) => isset($l['warehouse_id']) && false)) ?></span></td>
                        <td><span class="badge badge-gray"><?= $locCount ?></span></td>
            <td>
              <div class="d-flex gap-2">
                <button class="btn btn-ghost btn-sm" title="Edit"
                  onclick="fillModal('modal-edit-warehouse', {
                    id: '<?= $wh['id'] ?>',
                    name: <?= json_encode($wh['name']) ?>,
                    code: <?= json_encode($wh['code']) ?>,
                    address: <?= json_encode($wh['address'] ?? '') ?>
                  })">
                  <i data-lucide="pencil"></i>
                </button>
                <form method="POST" style="display:inline;" onsubmit="return confirmDelete(this,'Delete warehouse?')">
                  <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
                  <input type="hidden" name="action"  value="delete_warehouse">
                  <input type="hidden" name="id"      value="<?= $wh['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);"><i data-lucide="trash-2"></i></button>
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

<!-- Locations table -->
<div class="card">
  <div class="card-header">
    <span class="card-title"><i data-lucide="map-pin" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px;"></i>Locations</span>
    <button class="btn btn-primary btn-sm" onclick="openModal('modal-create-location')">
      <i data-lucide="plus"></i> Add Location
    </button>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Warehouse</th><th>Location Name</th><th>Code</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($locations)): ?>
        <tr><td colspan="4">
          <div class="empty-state" style="padding:32px;">
            <div class="empty-icon"><i data-lucide="map-pin"></i></div>
            <h3>No locations</h3>
            <p>Add locations (racks, zones, shelves) within a warehouse.</p>
          </div>
        </td></tr>
        <?php else: ?>
          <?php foreach ($locations as $loc): ?>
          <tr>
            <td class="text-secondary"><?= e($loc['wh_name']) ?></td>
            <td class="fw-600"><?= e($loc['name']) ?></td>
            <td class="font-mono text-secondary"><?= e($loc['code']) ?></td>
            <td>
              <div class="d-flex gap-2">
                <button class="btn btn-ghost btn-sm" title="Edit"
                  onclick="fillModal('modal-edit-location', {
                    id: '<?= $loc['id'] ?>',
                    warehouse_id: '<?= $loc['warehouse_id'] ?>',
                    name: <?= json_encode($loc['name']) ?>,
                    code: <?= json_encode($loc['code']) ?>
                  })">
                  <i data-lucide="pencil"></i>
                </button>
                <form method="POST" style="display:inline;" onsubmit="return confirmDelete(this,'Delete location?')">
                  <input type="hidden" name="_csrf"   value="<?= e(csrfToken()) ?>">
                  <input type="hidden" name="action"  value="delete_location">
                  <input type="hidden" name="id"      value="<?= $loc['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);"><i data-lucide="trash-2"></i></button>
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

<!-- Create Warehouse Modal -->
<div class="modal-overlay" id="modal-create-warehouse">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i data-lucide="warehouse" style="width:18px;height:18px;vertical-align:-3px;margin-right:8px;"></i>New Warehouse</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-create-warehouse')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="warehouses.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="create_warehouse">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Warehouse Name *</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Main Warehouse" required>
          </div>
          <div class="form-group">
            <label>Code *</label>
            <input type="text" name="code" class="form-control" placeholder="e.g. WH02" required style="text-transform:uppercase;">
          </div>
        </div>
        <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" class="form-control" placeholder="Physical address">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-warehouse')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Warehouse Modal -->
<div class="modal-overlay" id="modal-edit-warehouse">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Warehouse</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-edit-warehouse')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="warehouses.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="update_warehouse">
      <input type="hidden" name="id"     id="edit_wh_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Name *</label>
            <input type="text" name="name" id="edit_wh_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Code *</label>
            <input type="text" name="code" id="edit_wh_code" class="form-control" required style="text-transform:uppercase;">
          </div>
        </div>
        <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" id="edit_wh_address" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-warehouse')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Create Location Modal -->
<div class="modal-overlay" id="modal-create-location">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title"><i data-lucide="map-pin" style="width:18px;height:18px;vertical-align:-3px;margin-right:8px;"></i>New Location</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-create-location')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="warehouses.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="create_location">
      <div class="modal-body">
        <div class="form-group">
          <label>Warehouse *</label>
          <select name="warehouse_id" class="form-control" required>
            <option value="">— Select Warehouse —</option>
            <?php foreach ($warehouses as $wh): ?>
            <option value="<?= $wh['id'] ?>"><?= e($wh['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Location Name *</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Rack A" required>
          </div>
          <div class="form-group">
            <label>Code *</label>
            <input type="text" name="code" class="form-control" placeholder="e.g. WH01-RACK-A" required style="text-transform:uppercase;">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-location')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Location Modal -->
<div class="modal-overlay" id="modal-edit-location">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Location</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-edit-location')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="warehouses.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="update_location">
      <input type="hidden" name="id"     id="edit_loc_id">
      <div class="modal-body">
        <div class="form-group">
          <label>Warehouse *</label>
          <select name="warehouse_id" id="edit_loc_warehouse_id" class="form-control" required>
            <?php foreach ($warehouses as $wh): ?>
            <option value="<?= $wh['id'] ?>"><?= e($wh['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Name *</label>
            <input type="text" name="name" id="edit_loc_name" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Code *</label>
            <input type="text" name="code" id="edit_loc_code" class="form-control" required style="text-transform:uppercase;">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-location')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<style>.mb-6{margin-bottom:20px;}</style>

<?php include 'includes/footer.php'; ?>
