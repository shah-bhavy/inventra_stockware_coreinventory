<?php
require_once 'includes/auth_check.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = 'Categories';
$activeNav = 'categories';
$db        = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('error', 'Category name is required.');
            redirect('categories.php');
        }
        $db->prepare('INSERT INTO product_categories (name) VALUES (?)')->execute([$name]);
        flash('success', 'Category created.');
        redirect('categories.php');
    }

    if ($action === 'update_category') {
        $id   = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name !== '') {
            $db->prepare('UPDATE product_categories SET name=? WHERE id=?')->execute([$name, $id]);
            flash('success', 'Category updated.');
        } else {
            flash('error', 'Category name is required.');
        }
        redirect('categories.php');
    }

    if ($action === 'delete_category') {
        $id = (int)($_POST['category_id'] ?? 0);
        if ($id) {
            $db->prepare('UPDATE product_categories SET active=0 WHERE id=?')->execute([$id]);
            flash('success', 'Category deactivated.');
        }
        redirect('categories.php');
    }

    redirect('categories.php');
}

$categories = allCategories(false);

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
    <h1>Categories</h1>
    <p>Manage product categories used across your catalog</p>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-primary" onclick="openModal('modal-create-category')">
      <i data-lucide="plus"></i> New Category
    </button>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($categories)): ?>
        <tr><td colspan="3" class="text-secondary text-sm">No categories yet.</td></tr>
        <?php else: ?>
          <?php foreach ($categories as $c): ?>
          <tr>
            <td class="fw-600"><?= e($c['name']) ?></td>
            <td>
              <?= (int)($c['active'] ?? 1) === 1 ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Inactive</span>' ?>
            </td>
            <td>
              <button class="btn btn-ghost btn-sm" onclick="fillModal('modal-edit-category', {
                category_id: '<?= $c['id'] ?>',
                name: <?= json_encode($c['name']) ?>
              }); return false;">Edit</button>
              <?php if ((int)($c['active'] ?? 1) === 1): ?>
              <form method="POST" action="categories.php" style="display:inline;" onsubmit="return confirmDelete(this,'Deactivate category?')">
                <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
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

<!-- Create Category Modal -->
<div class="modal-overlay" id="modal-create-category">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">New Category</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-create-category')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="categories.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="create_category">
      <div class="modal-body">
        <div class="form-group">
          <label>Name *</label>
          <input type="text" name="name" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-category')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Category</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Category Modal -->
<div class="modal-overlay" id="modal-edit-category">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Category</span>
      <button class="btn btn-ghost btn-icon" onclick="closeModal('modal-edit-category')"><i data-lucide="x"></i></button>
    </div>
    <form method="POST" action="categories.php">
      <input type="hidden" name="_csrf"  value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="action" value="update_category">
      <input type="hidden" name="category_id" id="edit_category_id">
      <div class="modal-body">
        <div class="form-group">
          <label>Name *</label>
          <input type="text" name="name" id="edit_category_name" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-category')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Update Category</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
