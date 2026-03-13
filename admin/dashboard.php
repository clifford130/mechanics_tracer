<?php
require_once __DIR__ . '/../forms/config.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = 'Dashboard';
$active_nav = 'dashboard';

// Stats
$stats = [];
$stats['users'] = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$stats['drivers'] = $conn->query("SELECT COUNT(*) FROM drivers")->fetch_row()[0];
$stats['mechanics'] = $conn->query("SELECT COUNT(*) FROM mechanics")->fetch_row()[0];
$stats['bookings'] = $conn->query("SELECT COUNT(*) FROM bookings")->fetch_row()[0];
$stats['pending'] = $conn->query("SELECT COUNT(*) FROM bookings WHERE booking_status='pending'")->fetch_row()[0];
$stats['completed'] = $conn->query("SELECT COUNT(*) FROM bookings WHERE booking_status='completed'")->fetch_row()[0];
$rt = @$conn->query("SHOW TABLES LIKE 'ratings'");
$stats['ratings'] = ($rt && $rt->num_rows) ? $conn->query("SELECT COUNT(*) FROM ratings")->fetch_row()[0] : 0;

// Recent bookings
$recentBookings = $conn->query("
    SELECT b.id, b.service_requested, b.booking_status, b.created_at,
           u1.full_name AS driver_name, u2.full_name AS mechanic_name, m.garage_name
    FROM bookings b
    JOIN drivers d ON b.driver_id = d.id
    JOIN users u1 ON d.user_id = u1.id
    JOIN mechanics m ON b.mechanic_id = m.id
    JOIN users u2 ON m.user_id = u2.id
    ORDER BY b.created_at DESC
    LIMIT 10
");
$bookings = $recentBookings ? $recentBookings->fetch_all(MYSQLI_ASSOC) : [];

include __DIR__ . '/includes/header.php';
?>

<div class="top-bar">
    <div>
        <h1>Dashboard</h1>
        <p>Overview of your MechanicTracer platform.</p>
    </div>
    <a href="users.php" class="btn btn-secondary">View users</a>
</div>

<div class="stats-grid">
    <div class="stat-card"><h3>Users</h3><div class="num"><?php echo (int)$stats['users']; ?></div></div>
    <div class="stat-card"><h3>Drivers</h3><div class="num"><?php echo (int)$stats['drivers']; ?></div></div>
    <div class="stat-card"><h3>Mechanics</h3><div class="num"><?php echo (int)$stats['mechanics']; ?></div></div>
    <div class="stat-card"><h3>Bookings</h3><div class="num"><?php echo (int)$stats['bookings']; ?></div></div>
    <div class="stat-card"><h3>Pending</h3><div class="num"><?php echo (int)$stats['pending']; ?></div></div>
    <div class="stat-card"><h3>Completed</h3><div class="num"><?php echo (int)$stats['completed']; ?></div></div>
    <div class="stat-card"><h3>Ratings</h3><div class="num"><?php echo (int)$stats['ratings']; ?></div></div>
</div>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <h2>Recent bookings</h2>
        <a href="bookings.php" class="btn btn-sm btn-secondary">View all</a>
    </div>
    <?php if (empty($bookings)): ?>
        <p class="empty-state">No bookings yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>ID</th><th>Service</th><th>Driver</th><th>Mechanic</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td><a href="booking_view.php?id=<?php echo (int)$b['id']; ?>" style="color:#0f172a;font-weight:600;text-decoration:none;">#<?php echo (int)$b['id']; ?></a></td>
                        <td><?php echo htmlspecialchars($b['service_requested'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($b['driver_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($b['garage_name'] ?? '—'); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($b['booking_status']); ?>"><?php echo htmlspecialchars($b['booking_status']); ?></span></td>
                        <td><?php echo date('M j, Y H:i', strtotime($b['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
