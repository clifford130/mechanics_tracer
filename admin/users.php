<?php
require_once __DIR__ . '/../forms/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// Toggle mechanic availability (from list)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_avail'], $_POST['user_id']) && csrf_verify()) {
    $uid = (int)$_POST['user_id'];
    $stmt = $conn->prepare("UPDATE mechanics m JOIN users u ON m.user_id = u.id SET m.availability = 1 - m.availability WHERE u.id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    if ($stmt->affected_rows) {
        header('Location: users.php?' . http_build_query(array_merge($_GET, ['msg' => 'avail'])));
        exit;
    }
}

$page_title = 'Users';
$active_nav = 'users';

// Filters
$role_filter = $_GET['role'] ?? '';
$search = trim($_GET['search'] ?? '');
$profile_filter = $_GET['profile'] ?? '';      // '', 'yes', 'no'
$sort = $_GET['sort'] ?? 'created_desc';       // name_asc, name_desc, created_asc, created_desc, role_asc
$per_page = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];
$types = '';

if ($role_filter && in_array($role_filter, ['driver', 'mechanic', 'pending', 'admin'])) {
    $where[] = "u.role = ?";
    $params[] = $role_filter;
    $types .= 's';
}
if ($profile_filter === 'yes') {
    $where[] = "u.profile_completed = 1";
} elseif ($profile_filter === 'no') {
    $where[] = "(u.profile_completed = 0 OR u.profile_completed IS NULL)";
}
if ($search !== '') {
    $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$order = match($sort) {
    'name_asc'  => "u.full_name ASC",
    'name_desc' => "u.full_name DESC",
    'created_asc' => "u.created_at ASC",
    'role_asc'  => "u.role ASC, u.full_name ASC",
    default     => "u.created_at DESC"
};

// Count total
$sqlCount = "SELECT COUNT(*) FROM users u";
if (!empty($where)) {
    $sqlCount .= " WHERE " . implode(" AND ", $where);
}
$stmtCount = $conn->prepare($sqlCount);
if (!empty($params)) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$total = (int)$stmtCount->get_result()->fetch_row()[0];
$total_pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

// Main query – join mechanics when we need mechanic-specific data
$show_mechanic_cols = ($role_filter === 'mechanic' || $role_filter === '');
$sql = "SELECT u.id, u.full_name, u.email, u.phone, u.role, u.profile_completed, u.created_at";
if ($show_mechanic_cols) {
    $sql .= ", m.id AS mechanic_id, m.garage_name, m.availability AS mechanic_available";
}
$sql .= " FROM users u";
if ($show_mechanic_cols) {
    $sql .= " LEFT JOIN mechanics m ON m.user_id = u.id";
}
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY " . $order . " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$msg = ($_GET['msg'] ?? '') === 'avail' ? 'Availability updated.' : '';

include __DIR__ . '/includes/header.php';
?>

<div class="top-bar">
    <div>
        <h1>Users</h1>
        <p>Manage all platform users: drivers, mechanics, admins. Filter and search to find anyone.</p>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div class="card">
    <h2>Filters</h2>
    <form method="GET" class="filters" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;align-items:end;">
        <div class="form-group" style="margin:0;">
            <label>Search</label>
            <input type="text" name="search" placeholder="Name, email, phone" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Role</label>
            <select name="role">
                <option value="">All</option>
                <option value="driver" <?php echo $role_filter === 'driver' ? 'selected' : ''; ?>>Driver</option>
                <option value="mechanic" <?php echo $role_filter === 'mechanic' ? 'selected' : ''; ?>>Mechanic</option>
                <option value="pending" <?php echo $role_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Profile</label>
            <select name="profile">
                <option value="">Any</option>
                <option value="yes" <?php echo $profile_filter === 'yes' ? 'selected' : ''; ?>>Completed</option>
                <option value="no" <?php echo $profile_filter === 'no' ? 'selected' : ''; ?>>Incomplete</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Sort</label>
            <select name="sort">
                <option value="created_desc" <?php echo $sort === 'created_desc' ? 'selected' : ''; ?>>Newest first</option>
                <option value="created_asc" <?php echo $sort === 'created_asc' ? 'selected' : ''; ?>>Oldest first</option>
                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name A–Z</option>
                <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name Z–A</option>
                <option value="role_asc" <?php echo $sort === 'role_asc' ? 'selected' : ''; ?>>By role</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Per page</label>
            <select name="per_page">
                <?php foreach ([10, 25, 50, 100] as $n): ?>
                    <option value="<?php echo $n; ?>" <?php echo $per_page == $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="page" value="1">
        <div class="flex" style="gap:8px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
            <a href="users.php" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <h2>Results <span style="color:#64748b;font-weight:400;">(<?php echo $total; ?> user<?php echo $total === 1 ? '' : 's'; ?>)</span></h2>
    </div>

    <?php if (empty($users)): ?>
        <div class="empty-state">No users match your filters.</div>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Profile</th>
                    <?php if ($show_mechanic_cols): ?>
                    <th>Garage</th>
                    <th>Available</th>
                    <th>Actions</th>
                    <?php else: ?>
                    <th>Joined</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo (int)$u['id']; ?></td>
                        <td><a href="user_view.php?id=<?php echo (int)$u['id']; ?>" style="color:#0f172a;text-decoration:none;font-weight:600;"><?php echo htmlspecialchars($u['full_name']); ?></a></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($u['role']); ?>"><?php echo htmlspecialchars($u['role']); ?></span></td>
                        <td><?php echo ($u['profile_completed'] ?? 0) ? 'Yes' : 'No'; ?></td>
                        <?php if ($show_mechanic_cols): ?>
                        <td><?php echo isset($u['garage_name']) && $u['garage_name'] ? htmlspecialchars($u['garage_name']) : '—'; ?></td>
                        <td>
                            <?php if (isset($u['mechanic_id']) && $u['mechanic_id']): ?>
                                <span class="badge <?php echo $u['mechanic_available'] ? 'badge-completed' : 'badge-cancelled'; ?>">
                                    <?php echo $u['mechanic_available'] ? 'Yes' : 'No'; ?>
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($u['mechanic_id']) && $u['mechanic_id']): ?>
                                <form method="POST" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                    <input type="hidden" name="toggle_avail" value="1">
                                    <button type="submit" class="btn btn-sm btn-secondary" title="Toggle availability">Toggle</button>
                                </form>
                            <?php else: ?>
                                <a href="user_view.php?id=<?php echo (int)$u['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                            <?php endif; ?>
                        </td>
                        <?php else: ?>
                        <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="flex mt-2" style="margin-top:20px;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <span style="color:#64748b;font-size:0.9rem;">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
            <div class="flex" style="gap:6px;">
                <?php
                $qp = array_merge($_GET, ['page' => $page - 1]);
                $qn = array_merge($_GET, ['page' => $page + 1]);
                ?>
                <?php if ($page > 1): ?>
                    <a href="users.php?<?php echo http_build_query($qp); ?>" class="btn btn-sm btn-secondary"><i class="fas fa-chevron-left"></i> Prev</a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="users.php?<?php echo http_build_query($qn); ?>" class="btn btn-sm btn-secondary">Next <i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
