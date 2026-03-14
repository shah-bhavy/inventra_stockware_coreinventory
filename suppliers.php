<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Suppliers';
$activeNav = 'suppliers';
$db        = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_supplier') {
        $name          = trim($_POST['name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $address       = trim($_POST['address'] ?? '');

        if ($name === '') {
            flash('error', 'Supplier name is required.');
            redirect('suppliers.php');
        }

        $db->prepare('INSERT INTO suppliers (name,contact_person,phone,email,address) VALUES (?,?,?,?,?)')
           ->execute([$name, $contactPerson, $phone, $email, $address]);
        flash('success', 'Supplier created.');
        redirect('suppliers.php');
    }

    if ($action === 'update_supplier') {
        $supplierId    = (int)($_POST['supplier_id'] ?? 0);
        $name          = trim($_POST['name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $address       = trim($_POST['address'] ?? '');
        $active        = isset($_POST['active']) ? (int)$_POST['active'] : 0;

        if ($supplierId > 0 && $name !== '') {
            $db->prepare('UPDATE suppliers SET name=?,contact_person=?,phone=?,email=?,address=?,active=? WHERE id=?')
               ->execute([$name, $contactPerson, $phone, $email, $address, $active, $supplierId]);
            flash('success', 'Supplier updated.');
        } else {
            flash('error', 'Supplier name is required.');
        }
        redirect('suppliers.php');
    }

    if ($action === 'delete_supplier') {
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        if ($supplierId) {
            $db->prepare('UPDATE suppliers SET active=0 WHERE id=?')->execute([$supplierId]);
            flash('success', 'Supplier deactivated.');
        }
        redirect('suppliers.php');
    }

    redirect('suppliers.php');
}

$suppliers = allSuppliers(false);

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
    <h1>Suppliers</h1>
    <p>Manage supplier master data for incoming goods</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" onclick="openModal('modal-create-supplier')">
      <i data-lucide="plus"></i> New Supplier
    </button>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Contact</th>
          <th>Phone</th>
          <th>Email</th>
          <th>Address</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($suppliers)): ?>
        <tr><td colspan="7" class="text-secondary text-sm">No suppliers yet.</td></tr>
        <?php else: ?>
          <?php foreach ($suppliers as $s): ?>
          <tr>
            <td class="fw-600"><?= e($s['name']) ?></td>
            <td class="text-secondary text-sm"><?= e($s['contact_person'] ?: '—') ?></td>
            <td class="text-secondary text-sm"><?= e($s['phone'] ?: '—') ?></td>
            <td class="text-secondary text-sm"><?= e($s['email'] ?: '—') ?></td>
            <td class="text-secondary text-sm"><?= e($s['address'] ?: '—') ?></td>
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
              <?php if ((int)$s['active'] === 1): ?>
              <form method="POST" action="suppliers.php" style="display:inline;" onsubmit="return confirmDelete(this,'Deactivate supplier?')">
                <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="delete_supplier">
                <input type="hidden" name="supplier_id" value="<?= $s['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);"><i data-lucide="trash-2"></i></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create Supplier Modal -->
<div class="modal-overlay" id="modal-create-supplier">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">New Supplier</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-create-supplier')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="suppliers.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="create_supplier">
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

<!-- Edit Supplier Modal -->
<div class="modal-overlay" id="modal-edit-supplier">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Supplier</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-edit-supplier')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="suppliers.php">
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
