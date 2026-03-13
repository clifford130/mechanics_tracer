<?php
require_once __DIR__ . '/../forms/config.php';
require_once __DIR__ . '/includes/auth.php';

// Admin cancel booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    require_once __DIR__ . '/includes/csrf.php';
    if (csrf_verify()) {
        $bid = (int)$_POST['cancel_id'];
        $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled' WHERE id = ? AND booking_status IN ('pending','accepted')");
        $stmt->bind_param("i", $bid);
        $stmt->execute();
        if ($stmt->affected_rows) {
            header('Location: bookings.php?msg=cancelled');
            exit;
        }
    }
}
$msg = ($_GET['msg'] ?? '') === 'cancelled' ? 'Booking cancelled.' : '';

$page_title = 'Bookings';
$active_nav = 'bookings';

$status_filter = $_GET['status'] ?? '';
$where = [];
$params = [];
$types = '';

if ($status_filter && in_array($status_filter, ['pending', 'accepted', 'completed', 'cancelled'])) {
    $where[] = "b.booking_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$sql = "SELECT b.id, b.service_requested, b.vehicle_type, b.booking_status, b.notes, b.created_at, b.updated_at,
        u1.full_name AS driver_name, u1.email AS driver_email, u1.phone AS driver_phone,
        u2.full_name AS mechanic_name, m.garage_name, m.experience
        FROM bookings b
        JOIN drivers d ON b.driver_id = d.id
        JOIN users u1 ON d.user_id = u1.id
        JOIN mechanics m ON b.mechanic_id = m.id
        JOIN users u2 ON m.user_id = u2.id";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/csrf.php';
include __DIR__ . '/includes/header.php';
?>

<div class="top-bar">
    <div>
        <h1>Bookings</h1>
        <p>View and manage all platform bookings.</p>
    </div>
</div>

<?php if (!empty($msg)): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div class="card">
    <div class="filters">
        <form method="GET" class="flex" style="flex-wrap: wrap; gap: 10px;">
            <select name="status">
                <option value="">All statuses</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="bookings.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="empty-state">No bookings found.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Service</th>
                    <th>Driver</th>
                    <th>Mechanic</th>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><a href="booking_view.php?id=<?php echo (int)$b['id']; ?>" style="color:#0f172a;text-decoration:none;font-weight:600;">#<?php echo (int)$b['id']; ?></a></td>
                        <td><?php echo htmlspecialchars($b['service_requested'] ?? '—'); ?></td>
                        <td>
                            <?php echo htmlspecialchars($b['driver_name']); ?><br>
                            <small style="color:#64748b;"><?php echo htmlspecialchars($b['driver_phone'] ?? ''); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($b['garage_name']); ?></td>
                        <td><?php echo htmlspecialchars($b['vehicle_type'] ?? '—'); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($b['booking_status']); ?>"><?php echo htmlspecialchars($b['booking_status']); ?></span></td>
                        <td><?php echo date('M j, Y H:i', strtotime($b['created_at'])); ?></td>
                        <td>
                            <?php if (in_array($b['booking_status'], ['pending', 'accepted'])): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this booking?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="cancel_id" value="<?php echo (int)$b['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> Cancel</button>
                                </form>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
