<?php
require_once __DIR__ . '/../forms/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$msg = '';
$err = '';

// Add service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (csrf_verify()) {
        $cat = trim($_POST['category'] ?? '');
        $name = trim($_POST['service_name'] ?? '');
        if ($cat === '' || $name === '') {
            $err = 'Category and service name are required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO services (category, service_name) VALUES (?, ?)");
            $stmt->bind_param("ss", $cat, $name);
            if ($stmt->execute()) {
                header('Location: services.php?msg=added');
                exit;
            } else {
                $err = 'Could not add service. It may already exist.';
            }
        }
    }
}

// Edit service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    if (csrf_verify()) {
        $id = (int)($_POST['id'] ?? 0);
        $cat = trim($_POST['category'] ?? '');
        $name = trim($_POST['service_name'] ?? '');
        if ($id < 1 || $cat === '' || $name === '') {
            $err = 'Invalid data.';
        } else {
            $stmt = $conn->prepare("UPDATE services SET category = ?, service_name = ? WHERE id = ?");
            $stmt->bind_param("ssi", $cat, $name, $id);
            if ($stmt->execute()) {
                header('Location: services.php?msg=updated');
                exit;
            } else {
                $err = 'Could not update.';
            }
        }
    }
}

// Delete service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (csrf_verify()) {
        $id = (int)($_POST['id'] ?? 0);
        if ($id >= 1) {
            $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows) {
                header('Location: services.php?msg=deleted');
                exit;
            } else {
                $err = 'Could not delete (may be in use).';
            }
        }
    }
}

$msg = match($_GET['msg'] ?? '') {
    'added' => 'Service added.',
    'updated' => 'Service updated.',
    'deleted' => 'Service deleted.',
    default => ''
};

$page_title = 'Services';
$active_nav = 'services';

// Load services grouped by category
$res = $conn->query("SELECT id, category, service_name FROM services ORDER BY category, service_name");
$byCategory = [];
while ($row = $res->fetch_assoc()) {
    $c = $row['category'];
    if (!isset($byCategory[$c])) $byCategory[$c] = [];
    $byCategory[$c][] = $row;
}

// Distinct categories for add form
$categories = array_keys($byCategory);
sort($categories);

include __DIR__ . '/includes/header.php';
?>

<div class="top-bar">
    <div>
        <h1>Services</h1>
        <p>Manage service categories and individual services.</p>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>

<div class="card">
    <h2>Add service</h2>
    <form method="POST" style="max-width: 500px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <div class="form-group">
            <label>Category</label>
            <input type="text" name="category" list="categories" placeholder="e.g. Engine Services" required>
            <datalist id="categories">
                <?php foreach ($categories as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="form-group">
            <label>Service name</label>
            <input type="text" name="service_name" placeholder="e.g. Oil Change" required>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
    </form>
</div>

<div class="card">
    <h2>All services</h2>
    <?php if (empty($byCategory)): ?>
        <div class="empty-state">No services yet. Add one above.</div>
    <?php else: ?>
        <?php foreach ($byCategory as $cat => $items): ?>
            <div style="margin-bottom: 20px;">
                <h3 style="font-size: 1rem; color: #475569; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0;">
                    <?php echo htmlspecialchars($cat); ?>
                </h3>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Service</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $s): ?>
                            <tr>
                                <td><?php echo (int)$s['id']; ?></td>
                                <td><?php echo htmlspecialchars($s['service_name']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;" id="edit-form-<?php echo $s['id']; ?>">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($s['category']); ?>">
                                        <input type="hidden" name="service_name" value="<?php echo htmlspecialchars($s['service_name']); ?>">
                                    </form>
                                    <button type="button" class="btn btn-sm btn-secondary btn-edit" data-id="<?php echo (int)$s['id']; ?>" data-cat="<?php echo htmlspecialchars($s['category'], ENT_QUOTES); ?>" data-name="<?php echo htmlspecialchars($s['service_name'], ENT_QUOTES); ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this service?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Edit modal -->
<div id="editModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;" onclick="if(event.target===this)closeEdit()">
    <div style="background:#fff; padding:24px; border-radius:14px; max-width:400px; width:90%;" onclick="event.stopPropagation()">
        <h3 style="margin-bottom:16px;">Edit service</h3>
        <form method="POST" id="editForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="form-group">
                <label>Category</label>
                <input type="text" name="category" id="editCategory" required list="editCatList">
                <datalist id="editCatList">
                    <?php foreach ($categories as $c): ?><option value="<?php echo htmlspecialchars($c); ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group">
                <label>Service name</label>
                <input type="text" name="service_name" id="editServiceName" required>
            </div>
            <div class="flex mt-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-secondary" onclick="closeEdit()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.btn-edit').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('editId').value = this.dataset.id;
        document.getElementById('editCategory').value = this.dataset.cat || '';
        document.getElementById('editServiceName').value = this.dataset.name || '';
        document.getElementById('editModal').style.display = 'flex';
    });
});
function closeEdit() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
