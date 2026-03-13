<?php
require_once __DIR__ . '/../forms/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$rt = @$conn->query("SHOW TABLES LIKE 'ratings'");
$ratings_table_exists = $rt && $rt->num_rows;

$ratings = [];
$mechanic_filter = (int)($_GET['mechanic_id'] ?? 0);
$stars_filter = (int)($_GET['stars'] ?? 0);
$mechanics = $conn->query("SELECT m.id, m.garage_name, u.full_name FROM mechanics m JOIN users u ON m.user_id = u.id ORDER BY m.garage_name")->fetch_all(MYSQLI_ASSOC);

if ($ratings_table_exists) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && csrf_verify()) {
        $rid = (int)$_POST['delete_id'];
        $stmt = $conn->prepare("DELETE FROM ratings WHERE id = ?");
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        if ($stmt->affected_rows) {
            header('Location: ratings.php?msg=deleted');
            exit;
        }
    }

    $where = [];
    $params = [];
    $types = '';

    if ($mechanic_filter > 0) {
        $where[] = "r.mechanic_id = ?";
        $params[] = $mechanic_filter;
        $types .= 'i';
    }
    if ($stars_filter >= 1 && $stars_filter <= 5) {
        $where[] = "r.stars = ?";
        $params[] = $stars_filter;
        $types .= 'i';
    }

    $sql = "SELECT r.id, r.stars, r.review, r.created_at,
            b.service_requested, b.id AS booking_id,
            u1.full_name AS driver_name, u2.full_name AS mechanic_name, m.garage_name
            FROM ratings r
            JOIN bookings b ON r.booking_id = b.id
            JOIN drivers d ON r.driver_id = d.id
            JOIN users u1 ON d.user_id = u1.id
            JOIN mechanics m ON r.mechanic_id = m.id
            JOIN users u2 ON m.user_id = u2.id";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY r.created_at DESC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $ratings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$page_title = 'Ratings';
$active_nav = 'ratings';

$msg = ($_GET['msg'] ?? '') === 'deleted' ? 'Rating deleted.' : '';

include __DIR__ . '/includes/header.php';
?>

<div class="top-bar">
    <div>
        <h1>Ratings</h1>
        <p>All driver ratings of mechanics. Delete inappropriate reviews.</p>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div class="card">
    <div class="filters">
        <form method="GET" class="flex" style="flex-wrap: wrap; gap: 10px; align-items: end;">
            <div class="form-group" style="margin:0;">
                <label>Mechanic</label>
                <select name="mechanic_id">
                    <option value="">All</option>
                    <?php foreach ($mechanics as $m): ?>
                        <option value="<?php echo (int)$m['id']; ?>" <?php echo $mechanic_filter == $m['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['garage_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Stars</label>
                <select name="stars">
                    <option value="">All</option>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php echo $stars_filter == $i ? 'selected' : ''; ?>>⭐ <?php echo $i; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="ratings.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <?php if (!$ratings_table_exists): ?>
        <div class="empty-state">Run <code>admin/sql/create_ratings.sql</code> to enable ratings.</div>
    <?php elseif (empty($ratings)): ?>
        <div class="empty-state">No ratings found.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Driver</th>
                    <th>Mechanic</th>
                    <th>Stars</th>
                    <th>Review</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ratings as $r): ?>
                    <tr>
                        <td><a href="booking_view.php?id=<?php echo (int)$r['booking_id']; ?>" style="color:#0f172a;">#<?php echo (int)$r['booking_id']; ?></a></td>
                        <td><?php echo htmlspecialchars($r['driver_name']); ?></td>
                        <td><?php echo htmlspecialchars($r['garage_name']); ?></td>
                        <td><span style="color:#fbbf24;"><?php echo str_repeat('★', (int)$r['stars']) . str_repeat('☆', 5 - (int)$r['stars']); ?></span></td>
                        <td><?php echo $r['review'] ? htmlspecialchars(mb_strimwidth($r['review'], 0, 80, '…')) : '—'; ?></td>
                        <td><?php echo date('M j, Y', strtotime($r['created_at'])); ?></td>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this rating?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="delete_id" value="<?php echo (int)$r['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
