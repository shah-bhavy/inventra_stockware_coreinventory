<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Customers';
$activeNav = 'customers';
$db        = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_customer') {
        $name          = trim($_POST['name'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $address       = trim($_POST['address'] ?? '');

        if ($name === '') {
            flash('error', 'Customer name is required.');
            redirect('customers.php');
        }

        $db->prepare('INSERT INTO customers (name,contact_person,phone,email,address) VALUES (?,?,?,?,?)')
           ->execute([$name, $contactPerson, $phone, $email, $address]);
        flash('success', 'Customer created.');
        redirect('customers.php');
    }

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
            flash('success', 'Customer updated.');
        } else {
            flash('error', 'Customer name is required.');
        }
        redirect('customers.php');
    }

    if ($action === 'delete_customer') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        if ($customerId) {
            $db->prepare('UPDATE customers SET active=0 WHERE id=?')->execute([$customerId]);
            flash('success', 'Customer deactivated.');
        }
        redirect('customers.php');
    }

    redirect('customers.php');
}

$customers = allCustomers(false);

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
    <h1>Customers</h1>
    <p>Manage customer master data for outgoing deliveries</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" onclick="openModal('modal-create-customer')">
      <i data-lucide="plus"></i> New Customer
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
        <?php if (empty($customers)): ?>
        <tr><td colspan="7" class="text-secondary text-sm">No customers yet.</td></tr>
        <?php else: ?>
          <?php foreach ($customers as $c): ?>
          <tr>
            <td class="fw-600"><?= e($c['name']) ?></td>
            <td class="text-secondary text-sm"><?= e($c['contact_person'] ?: '—') ?></td>
            <td class="text-secondary text-sm"><?= e($c['phone'] ?: '—') ?></td>
            <td class="text-secondary text-sm"><?= e($c['email'] ?: '—') ?></td>
            <td class="text-secondary text-sm"><?= e($c['address'] ?: '—') ?></td>
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
              <?php if ((int)$c['active'] === 1): ?>
              <form method="POST" action="customers.php" style="display:inline;" onsubmit="return confirmDelete(this,'Deactivate customer?')">
                <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="delete_customer">
                <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
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

<!-- Create Customer Modal -->
<div class="modal-overlay" id="modal-create-customer">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">New Customer</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-create-customer')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="customers.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="create_customer">
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

<!-- Edit Customer Modal -->
<div class="modal-overlay" id="modal-edit-customer">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Customer</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-edit-customer')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="customers.php">
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
