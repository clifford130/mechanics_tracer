<?php
require_once __DIR__ . '/../forms/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    header('Location: bookings.php');
    exit;
}

// Cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && csrf_verify()) {
    if ($_POST['action'] === 'cancel') {
        $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'cancelled' WHERE id = ? AND booking_status IN ('pending','accepted')");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows) {
            header('Location: booking_view.php?id=' . $id . '&msg=cancelled');
            exit;
        }
    } elseif ($_POST['action'] === 'complete') {
        $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'completed' WHERE id = ? AND booking_status IN ('pending','accepted')");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows) {
            header('Location: booking_view.php?id=' . $id . '&msg=completed');
            exit;
        }
    }
}

$stmt = $conn->prepare("
    SELECT b.*, u1.full_name AS driver_name, u1.email AS driver_email, u1.phone AS driver_phone,
           u2.full_name AS mechanic_name, u2.phone AS mechanic_phone, m.garage_name, m.experience, m.services_offered,
           d.vehicle_type, d.vehicle_make, d.vehicle_model, d.vehicle_year
    FROM bookings b
    JOIN drivers d ON b.driver_id = d.id
    JOIN users u1 ON d.user_id = u1.id
    JOIN mechanics m ON b.mechanic_id = m.id
    JOIN users u2 ON m.user_id = u2.id
    WHERE b.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$b = $stmt->get_result()->fetch_assoc();

if (!$b) {
    header('Location: bookings.php');
    exit;
}

$rating = null;
$rt = @$conn->query("SHOW TABLES LIKE 'ratings'");
if ($rt && $rt->num_rows) {
    $rq = $conn->prepare("SELECT r.stars, r.review, r.created_at, u.full_name AS driver_name FROM ratings r JOIN drivers d ON r.driver_id = d.id JOIN users u ON d.user_id = u.id WHERE r.booking_id = ?");
    $rq->bind_param("i", $id);
    $rq->execute();
    $rating = $rq->get_result()->fetch_assoc();
}

$page_title = 'Booking #' . $id;
$active_nav = 'bookings';
$msg = $_GET['msg'] ?? '';
include __DIR__ . '/includes/header.php';
?>

<div class="top-bar">
    <div>
        <a href="bookings.php" class="btn btn-secondary" style="margin-bottom:8px;"><i class="fas fa-arrow-left"></i> Back</a>
        <h1>Booking #<?php echo (int)$id; ?></h1>
        <p><span class="badge badge-<?php echo htmlspecialchars($b['booking_status']); ?>"><?php echo htmlspecialchars($b['booking_status']); ?></span></p>
    </div>
</div>

<?php if ($msg === 'cancelled'): ?><div class="alert alert-success">Booking cancelled.</div><?php endif; ?>
<?php if ($msg === 'completed'): ?><div class="alert alert-success">Booking marked complete.</div><?php endif; ?>

<div class="card">
    <h2>Booking details</h2>
    <table>
        <tr><th>Service requested</th><td><?php echo htmlspecialchars($b['service_requested'] ?? '—'); ?></td></tr>
        <tr><th>Vehicle type</th><td><?php echo htmlspecialchars($b['vehicle_type'] ?? '—'); ?></td></tr>
        <tr><th>Status</th><td><span class="badge badge-<?php echo htmlspecialchars($b['booking_status']); ?>"><?php echo htmlspecialchars($b['booking_status']); ?></span></td></tr>
        <tr><th>Created</th><td><?php echo date('F j, Y H:i', strtotime($b['created_at'])); ?></td></tr>
        <tr><th>Updated</th><td><?php echo date('F j, Y H:i', strtotime($b['updated_at'])); ?></td></tr>
    </table>
    <?php if ($b['notes']): ?>
        <p style="margin-top:12px;"><strong>Notes:</strong><br><?php echo nl2br(htmlspecialchars($b['notes'])); ?></p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Driver</h2>
    <table>
        <tr><th>Name</th><td><?php echo htmlspecialchars($b['driver_name']); ?></td></tr>
        <tr><th>Email</th><td><?php echo htmlspecialchars($b['driver_email']); ?></td></tr>
        <tr><th>Phone</th><td><?php echo htmlspecialchars($b['driver_phone'] ?? '—'); ?></td></tr>
        <tr><th>Vehicle</th><td><?php echo htmlspecialchars($b['vehicle_make'] . ' ' . $b['vehicle_model'] . ' (' . $b['vehicle_year'] . ')'); ?></td></tr>
        <?php if ($b['driver_address']): ?>
        <tr><th>Address</th><td><?php echo htmlspecialchars($b['driver_address']); ?></td></tr>
        <?php endif; ?>
        <?php if ($b['driver_latitude'] && $b['driver_longitude']): ?>
        <tr><th>Location</th><td><?php echo htmlspecialchars($b['driver_latitude'] . ', ' . $b['driver_longitude']); ?></td></tr>
        <?php endif; ?>
    </table>
</div>

<div class="card">
    <h2>Mechanic</h2>
    <table>
        <tr><th>Garage</th><td><?php echo htmlspecialchars($b['garage_name']); ?></td></tr>
        <tr><th>Owner</th><td><?php echo htmlspecialchars($b['mechanic_name']); ?></td></tr>
        <tr><th>Phone</th><td><?php echo htmlspecialchars($b['mechanic_phone'] ?? '—'); ?></td></tr>
        <tr><th>Experience</th><td><?php echo (int)$b['experience']; ?> years</td></tr>
    </table>
</div>

<?php if ($rating): ?>
<div class="card">
    <h2>Rating</h2>
    <p><span style="color:#fbbf24;font-size:1.1rem;"><?php echo str_repeat('★', (int)$rating['stars']) . str_repeat('☆', 5 - (int)$rating['stars']); ?></span> by <?php echo htmlspecialchars($rating['driver_name']); ?> — <?php echo date('M j, Y', strtotime($rating['created_at'])); ?></p>
    <?php if ($rating['review']): ?><p style="margin-top:8px;"><?php echo nl2br(htmlspecialchars($rating['review'])); ?></p><?php endif; ?>
</div>
<?php endif; ?>

<?php if (in_array($b['booking_status'], ['pending', 'accepted'])): ?>
<div class="card">
    <div class="flex" style="gap:10px;">
        <form method="POST" onsubmit="return confirm('Mark this booking as completed?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="complete">
            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Mark complete</button>
        </form>
        <form method="POST" onsubmit="return confirm('Cancel this booking?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Cancel booking</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
