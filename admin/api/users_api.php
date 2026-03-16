<?php
/**
 * Admin Users AJAX API
 * Handles both listing (GET) and actions (POST).
 */
require_once __DIR__ . '/../../forms/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');

/* ===================== POST: perform an action ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid security token. Refresh and try again.']);
        exit;
    }

    $uid    = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($uid < 1) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid user ID.']);
        exit;
    }

    $map = [
        'suspend_user'     => ["UPDATE users SET status = 'suspended' WHERE id = ?",                 'User suspended.'],
        'activate_user'    => ["UPDATE users SET status = 'active'    WHERE id = ?",                 'User activated.'],
        'delete_user'      => ["UPDATE users SET status = 'deleted'   WHERE id = ?",                 'User soft-deleted. Data preserved for audit.'],
        'restore_user'     => ["UPDATE users SET status = 'suspended' WHERE id = ?",                 'Account restored (now suspended – activate if needed).'],
        'mark_available'   => ["UPDATE mechanics SET availability = 1 WHERE user_id = ?",             'Mechanic set online.'],
        'mark_unavailable' => ["UPDATE mechanics SET availability = 0 WHERE user_id = ?",             'Mechanic set offline.'],
    ];

    if (!array_key_exists($action, $map)) {
        echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
        exit;
    }

    [$sql, $successMsg] = $map[$action];
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $uid);
    $ok = $stmt->execute();

    echo json_encode(['ok' => $ok, 'msg' => $ok ? $successMsg : 'Database error: ' . $conn->error]);
    exit;
}

/* ===================== GET: return filtered users list ===================== */
$role_filter   = $_GET['role']     ?? '';
$search        = trim($_GET['search']  ?? '');
$profile_filter = $_GET['profile'] ?? '';
$status_filter = $_GET['status']   ?? 'active';
$sort          = $_GET['sort']     ?? 'created_desc';
$per_page      = max(10, min(100, (int)($_GET['per_page'] ?? 25)));
$page          = max(1, (int)($_GET['page'] ?? 1));

$where  = [];
$params = [];
$types  = '';

if ($role_filter && in_array($role_filter, ['driver', 'mechanic', 'pending', 'admin'])) {
    $where[] = 'u.role = ?';
    $params[] = $role_filter;
    $types   .= 's';
}
if ($profile_filter === 'yes') {
    $where[] = 'u.profile_completed = 1';
} elseif ($profile_filter === 'no') {
    $where[] = '(u.profile_completed = 0 OR u.profile_completed IS NULL)';
}

if ($status_filter === 'all') {
    // no filter
} elseif (in_array($status_filter, ['active', 'suspended', 'deleted'])) {
    $where[] = 'u.status = ?';
    $params[] = $status_filter;
    $types   .= 's';
} else {
    $where[] = "u.status != 'deleted'";
}

if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}

$order = match($sort) {
    'name_asc'    => 'u.full_name ASC',
    'name_desc'   => 'u.full_name DESC',
    'created_asc' => 'u.created_at ASC',
    'role_asc'    => 'u.role ASC, u.full_name ASC',
    default       => 'u.created_at DESC',
};

// Count
$sqlCount = 'SELECT COUNT(*) FROM users u' . (!empty($where) ? ' WHERE ' . implode(' AND ', $where) : '');
$stmtCount = $conn->prepare($sqlCount);
if (!empty($params)) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$total       = (int)$stmtCount->get_result()->fetch_row()[0];
$total_pages = max(1, (int)ceil($total / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

// Data
$sql  = 'SELECT u.id, u.full_name, u.email, u.phone, u.role, u.profile_completed, u.created_at, u.status,
                m.id AS mechanic_id, m.garage_name, m.availability AS mechanic_available
         FROM users u LEFT JOIN mechanics m ON m.user_id = u.id';
if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= " ORDER BY {$order} LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types   .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'ok'          => true,
    'total'       => $total,
    'page'        => $page,
    'total_pages' => $total_pages,
    'users'       => $users,
    'csrf'        => csrf_token(),
]);
