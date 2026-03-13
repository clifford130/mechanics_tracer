<?php
session_start();
require_once __DIR__ . '/../forms/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ' . FORMS_URL . 'auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM drivers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();
if (!$driver) {
    header('Location: ' . FORMS_URL . 'profile/driver_profile.php');
    exit;
}
$driver_id = $driver['id'];

$success = $error = '';

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $stars = (int)($_POST['stars'] ?? 0);
    $review = trim($_POST['review'] ?? '');

    if ($stars < 1 || $stars > 5) {
        $error = 'Please select 1–5 stars.';
    } else {
        $stmt = $conn->prepare("SELECT b.id, b.mechanic_id FROM bookings b WHERE b.id = ? AND b.driver_id = ? AND b.booking_status = 'completed'");
        $stmt->bind_param("ii", $booking_id, $driver_id);
        $stmt->execute();
        $booking = $stmt->get_result()->fetch_assoc();

        if (!$booking) {
            $error = 'Invalid or already rated booking.';
        } else {
            $chk = $conn->prepare("SELECT id FROM ratings WHERE booking_id = ?");
            $chk->bind_param("i", $booking_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = 'You have already rated this booking.';
            } else {
                $ins = $conn->prepare("INSERT INTO ratings (booking_id, driver_id, mechanic_id, stars, review) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("iiiis", $booking_id, $driver_id, $booking['mechanic_id'], $stars, $review);
                if ($ins->execute()) {
                    $success = 'Thank you for your rating!';
                } else {
                    $error = 'Could not save rating.';
                }
            }
        }
    }
}

// Completed bookings not yet rated
$unrated = $conn->query("
    SELECT b.id, b.service_requested, b.created_at, m.garage_name, u.full_name AS mechanic_name
    FROM bookings b
    JOIN mechanics m ON b.mechanic_id = m.id
    JOIN users u ON m.user_id = u.id
    LEFT JOIN ratings r ON r.booking_id = b.id
    WHERE b.driver_id = {$driver_id} AND b.booking_status = 'completed' AND r.id IS NULL
    ORDER BY b.created_at DESC
");
$unrated_list = $unrated ? $unrated->fetch_all(MYSQLI_ASSOC) : [];

// Past ratings
$rated = $conn->query("
    SELECT r.id, r.stars, r.review, r.created_at, b.service_requested, m.garage_name, u.full_name AS mechanic_name
    FROM ratings r
    JOIN bookings b ON r.booking_id = b.id
    JOIN mechanics m ON r.mechanic_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE r.driver_id = {$driver_id}
    ORDER BY r.created_at DESC
");
$rated_list = $rated ? $rated->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Mechanic | MechanicTracer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f4f6f8; min-height: 100vh; padding: 24px; }
        .container { max-width: 700px; margin: 0 auto; }
        h1 { color: #0f172a; margin-bottom: 8px; }
        .sub { color: #64748b; margin-bottom: 24px; }
        .card { background: #fff; border-radius: 14px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .card h2 { font-size: 1.1rem; margin-bottom: 14px; color: #0f172a; }
        .empty { color: #64748b; padding: 16px 0; }
        .booking-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9; flex-wrap: wrap; gap: 10px; }
        .booking-row:last-child { border-bottom: none; }
        .stars { color: #fbbf24; font-size: 1.2rem; }
        .stars input { display: none; }
        .stars label { cursor: pointer; }
        .stars label:hover { opacity: 0.8; }
        .rating-form { display: none; padding: 12px 0; }
        .rating-form.show { display: block; }
        textarea { width: 100%; min-height: 80px; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.95rem; margin: 10px 0; resize: vertical; }
        .btn { padding: 8px 16px; border-radius: 8px; font-weight: 500; cursor: pointer; border: none; font-size: 0.9rem; }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 16px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        a.back { display: inline-flex; align-items: center; gap: 8px; color: #0f172a; text-decoration: none; font-weight: 500; margin-bottom: 20px; }
        a.back:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <a href="driver_dashboard.php" class="back"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
    <h1>Rate your mechanics</h1>
    <p class="sub">Leave a rating for completed jobs to help other drivers.</p>

    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card">
        <h2>Completed jobs to rate</h2>
        <?php if (empty($unrated_list)): ?>
            <p class="empty">No completed bookings to rate.</p>
        <?php else: ?>
            <?php foreach ($unrated_list as $u): ?>
                <div class="booking-row">
                    <div>
                        <strong><?php echo htmlspecialchars($u['garage_name']); ?></strong> — <?php echo htmlspecialchars($u['service_requested'] ?? 'Service'); ?>
                        <br><small style="color:#64748b;"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></small>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="toggleForm(<?php echo (int)$u['id']; ?>)">Rate</button>
                </div>
                <div class="rating-form" id="form-<?php echo (int)$u['id']; ?>">
                    <form method="POST">
                        <input type="hidden" name="booking_id" value="<?php echo (int)$u['id']; ?>">
                        <p style="margin-bottom:8px;">Stars (1–5):</p>
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <label><input type="radio" name="stars" value="<?php echo $i; ?>" required> <i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                        <label style="display:block;margin:12px 0 4px;font-weight:600;">Review (optional)</label>
                        <textarea name="review" placeholder="How was the service?"></textarea>
                        <button type="submit" class="btn btn-primary">Submit</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleForm(<?php echo (int)$u['id']; ?>)">Cancel</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Your past ratings</h2>
        <?php if (empty($rated_list)): ?>
            <p class="empty">You have not rated any mechanics yet.</p>
        <?php else: ?>
            <?php foreach ($rated_list as $r): ?>
                <div class="booking-row">
                    <div>
                        <strong><?php echo htmlspecialchars($r['garage_name']); ?></strong>
                        <span class="stars"><?php echo str_repeat('<i class="fas fa-star"></i>', (int)$r['stars']); ?></span>
                        <?php if ($r['review']): ?><br><em><?php echo htmlspecialchars($r['review']); ?></em><?php endif; ?>
                        <br><small style="color:#64748b;"><?php echo date('M j, Y', strtotime($r['created_at'])); ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<script>
function toggleForm(id) {
    var el = document.getElementById('form-' + id);
    el.classList.toggle('show');
}
</script>
</body>
</html>
