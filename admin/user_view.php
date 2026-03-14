<?php
require_once __DIR__ . '/../forms/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
    header('Location: users.php');
    exit;
}

// Handle high-level user actions (delete user, remove profiles, toggle availability)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    if (isset($_POST['toggle_avail'])) {
        $stmt = $conn->prepare("UPDATE mechanics SET availability = 1 - availability WHERE user_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows) {
            header('Location: user_view.php?id=' . $id . '&msg=avail');
            exit;
        }
    } elseif (isset($_POST['user_action'])) {
        $action = $_POST['user_action'];

        if ($action === 'delete_user') {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            header('Location: users.php?msg=deleted_user');
            exit;
        }

        if ($action === 'remove_driver') {
            $stmt = $conn->prepare("DELETE FROM drivers WHERE user_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            header('Location: user_view.php?id=' . $id . '&msg=driver_removed');
            exit;
        }

        if ($action === 'remove_mechanic') {
            $stmt = $conn->prepare("DELETE FROM mechanics WHERE user_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            header('Location: user_view.php?id=' . $id . '&msg=mechanic_removed');
            exit;
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: users.php');
    exit;
}

$driver = $mechanic = null;
// Load profiles if they still exist
$st = $conn->prepare("SELECT * FROM drivers WHERE user_id = ?");
$st->bind_param("i", $id);
$st->execute();
$driver = $st->get_result()->fetch_assoc();

$st = $conn->prepare("SELECT * FROM mechanics WHERE user_id = ?");
$st->bind_param("i", $id);
$st->execute();
$mechanic = $st->get_result()->fetch_assoc();

// Bookings (as driver or mechanic)
$user_bookings = [];
$user_ratings_given = [];
$user_ratings_received = [];
$mechanic_avg_rating = null;

$rt = @$conn->query("SHOW TABLES LIKE 'ratings'");
$ratings_table_exists = $rt && $rt->num_rows > 0;

if ($driver) {
    $bq = $conn->prepare("SELECT b.id, b.service_requested, b.booking_status, b.created_at, m.garage_name FROM bookings b JOIN mechanics m ON b.mechanic_id = m.id WHERE b.driver_id = ? ORDER BY b.created_at DESC LIMIT 15");
    $bq->bind_param("i", $driver['id']);
    $bq->execute();
    $user_bookings = $bq->get_result()->fetch_all(MYSQLI_ASSOC);
    if ($ratings_table_exists) {
        $rq = $conn->prepare("SELECT r.id, r.stars, r.review, r.created_at, m.garage_name FROM ratings r JOIN mechanics m ON r.mechanic_id = m.id WHERE r.driver_id = ? ORDER BY r.created_at DESC LIMIT 10");
        $rq->bind_param("i", $driver['id']);
        $rq->execute();
        $user_ratings_given = $rq->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
if ($mechanic) {
    $bq = $conn->prepare("SELECT b.id, b.service_requested, b.booking_status, b.created_at, u.full_name AS driver_name FROM bookings b JOIN drivers d ON b.driver_id = d.id JOIN users u ON d.user_id = u.id WHERE b.mechanic_id = ? ORDER BY b.created_at DESC LIMIT 15");
    $bq->bind_param("i", $mechanic['id']);
    $bq->execute();
    $user_bookings = $bq->get_result()->fetch_all(MYSQLI_ASSOC);
    if ($ratings_table_exists) {
        $avg = $conn->query("SELECT AVG(stars) as avg_stars, COUNT(*) as cnt FROM ratings WHERE mechanic_id = " . (int)$mechanic['id'])->fetch_assoc();
        $mechanic_avg_rating = $avg;
        $rq = $conn->prepare("SELECT r.id, r.stars, r.review, r.created_at, u.full_name AS driver_name FROM ratings r JOIN drivers d ON r.driver_id = d.id JOIN users u ON d.user_id = u.id WHERE r.mechanic_id = ? ORDER BY r.created_at DESC LIMIT 10");
        $rq->bind_param("i", $mechanic['id']);
        $rq->execute();
        $user_ratings_received = $rq->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$page_title = 'User: ' . $user['full_name'];
$active_nav = 'users';
include __DIR__ . '/includes/header.php';
?>

<?php
$msgKey = $_GET['msg'] ?? '';
if ($msgKey === 'avail') {
    $msg = 'Availability updated.';
} elseif ($msgKey === 'driver_removed') {
    $msg = 'Driver profile removed.';
} elseif ($msgKey === 'mechanic_removed') {
    $msg = 'Mechanic profile removed.';
} elseif ($msgKey === 'deleted_user') {
    $msg = 'User deleted.';
} else {
    $msg = '';
}
?>

<div class="top-bar">
    <div>
        <a href="users.php" class="btn btn-secondary" style="margin-bottom:8px;"><i class="fas fa-arrow-left"></i> Back</a>
        <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
        <p><span class="badge badge-<?php echo htmlspecialchars($user['role']); ?>"><?php echo htmlspecialchars($user['role']); ?></span></p>
    </div>
    <div class="flex" style="gap:8px;">
        <?php if ($mechanic): ?>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="toggle_avail" value="1">
            <button type="submit" class="btn btn-secondary"><i class="fas fa-exchange-alt"></i> Toggle availability</button>
        </form>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('Permanently delete this user and all related data?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="user_action" value="delete_user">
            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i> Delete user</button>
        </form>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div class="card">
    <h2>Account info</h2>
    <table>
        <tr><th>ID</th><td><?php echo (int)$user['id']; ?></td></tr>
        <tr><th>Full name</th><td><?php echo htmlspecialchars($user['full_name']); ?></td></tr>
        <tr><th>Email</th><td><?php echo htmlspecialchars($user['email']); ?></td></tr>
        <tr><th>Phone</th><td><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></td></tr>
        <tr><th>Role</th><td><span class="badge badge-<?php echo htmlspecialchars($user['role']); ?>"><?php echo htmlspecialchars($user['role']); ?></span></td></tr>
        <tr><th>Profile completed</th><td><?php echo ($user['profile_completed'] ?? 0) ? 'Yes' : 'No'; ?></td></tr>
        <tr><th>Joined</th><td><?php echo date('F j, Y H:i', strtotime($user['created_at'])); ?></td></tr>
    </table>
</div>

<?php if ($driver): ?>
<div class="card">
    <h2>Driver profile</h2>
    <table>
        <tr><th>Vehicle type</th><td><?php echo htmlspecialchars($driver['vehicle_type']); ?></td></tr>
        <tr><th>Make / Model</th><td><?php echo htmlspecialchars($driver['vehicle_make'] . ' ' . $driver['vehicle_model']); ?></td></tr>
        <tr><th>Year</th><td><?php echo htmlspecialchars($driver['vehicle_year']); ?></td></tr>
        <tr><th>Service preferences</th><td><?php echo htmlspecialchars($driver['service_preferences'] ?? '—'); ?></td></tr>
    </table>
    <form method="POST" style="margin-top:10px;" onsubmit="return confirm('Remove driver profile? This will revert this user back to pending.');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="user_action" value="remove_driver">
        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-user-slash"></i> Remove driver profile</button>
    </form>
</div>
<?php endif; ?>

<?php if ($mechanic): ?>
<div class="card">
    <h2>Mechanic profile <span class="badge <?php echo $mechanic['availability'] ? 'badge-completed' : 'badge-cancelled'; ?>" style="margin-left:8px;"><?php echo $mechanic['availability'] ? 'Available' : 'Unavailable'; ?></span></h2>
    <table>
        <tr><th>Garage</th><td><?php echo htmlspecialchars($mechanic['garage_name']); ?></td></tr>
        <tr><th>Experience</th><td><?php echo (int)$mechanic['experience']; ?> years</td></tr>
        <tr><th>Certifications</th><td><?php echo htmlspecialchars($mechanic['certifications'] ?? '—'); ?></td></tr>
        <tr><th>Vehicle types</th><td><?php echo htmlspecialchars($mechanic['vehicle_types']); ?></td></tr>
        <tr><th>Services offered</th><td><?php echo nl2br(htmlspecialchars(str_replace(',', ', ', $mechanic['services_offered']))); ?></td></tr>
        <tr><th>Location</th><td><?php echo htmlspecialchars($mechanic['latitude'] . ', ' . $mechanic['longitude']); ?></td></tr>
        <tr><th>Available</th><td><?php echo $mechanic['availability'] ? 'Yes' : 'No'; ?></td></tr>
    </table>
    <form method="POST" style="margin-top:10px;" onsubmit="return confirm('Remove mechanic profile? This will revert this user back to pending.');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="user_action" value="remove_mechanic">
        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-wrench"></i> Remove mechanic profile</button>
    </form>
</div>
<?php endif; ?>

<?php if ($mechanic && $mechanic_avg_rating && ($mechanic_avg_rating['cnt'] ?? 0) > 0): ?>
<div class="card">
    <h2>Average rating</h2>
    <p><span style="color:#fbbf24;font-size:1.2rem;"><?php echo str_repeat('★', (int)round($mechanic_avg_rating['avg_stars'])); ?></span> <?php echo number_format((float)$mechanic_avg_rating['avg_stars'], 1); ?> (<?php echo (int)$mechanic_avg_rating['cnt']; ?> ratings)</p>
</div>
<?php endif; ?>

<?php if (!empty($user_bookings)): ?>
<div class="card">
    <h2><?php echo $driver ? 'Bookings as driver' : 'Bookings as mechanic'; ?></h2>
    <table>
        <thead><tr><th>ID</th><th>Service</th><th><?php echo $driver ? 'Mechanic' : 'Driver'; ?></th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
            <?php foreach ($user_bookings as $ub): ?>
                <tr>
                    <td><a href="booking_view.php?id=<?php echo (int)$ub['id']; ?>">#<?php echo (int)$ub['id']; ?></a></td>
                    <td><?php echo htmlspecialchars($ub['service_requested'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($ub['garage_name'] ?? $ub['driver_name'] ?? '—'); ?></td>
                    <td><span class="badge badge-<?php echo htmlspecialchars($ub['booking_status']); ?>"><?php echo htmlspecialchars($ub['booking_status']); ?></span></td>
                    <td><?php echo date('M j, Y', strtotime($ub['created_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($user_ratings_given)): ?>
<div class="card">
    <h2>Ratings given (as driver)</h2>
    <table>
        <thead><tr><th>Mechanic</th><th>Stars</th><th>Review</th><th>Date</th></tr></thead>
        <tbody>
            <?php foreach ($user_ratings_given as $rg): ?>
                <tr>
                    <td><?php echo htmlspecialchars($rg['garage_name']); ?></td>
                    <td><span style="color:#fbbf24;"><?php echo str_repeat('★', (int)$rg['stars']); ?></span></td>
                    <td><?php echo $rg['review'] ? htmlspecialchars(mb_strimwidth($rg['review'], 0, 60, '…')) : '—'; ?></td>
                    <td><?php echo date('M j, Y', strtotime($rg['created_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($user_ratings_received)): ?>
<div class="card">
    <h2>Ratings received (as mechanic)</h2>
    <table>
        <thead><tr><th>Driver</th><th>Stars</th><th>Review</th><th>Date</th></tr></thead>
        <tbody>
            <?php foreach ($user_ratings_received as $rr): ?>
                <tr>
                    <td><?php echo htmlspecialchars($rr['driver_name']); ?></td>
                    <td><span style="color:#fbbf24;"><?php echo str_repeat('★', (int)$rr['stars']); ?></span></td>
                    <td><?php echo $rr['review'] ? htmlspecialchars(mb_strimwidth($rr['review'], 0, 60, '…')) : '—'; ?></td>
                    <td><?php echo date('M j, Y', strtotime($rr['created_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
