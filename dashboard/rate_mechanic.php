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

$user_name = "Driver";
$us = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$us->bind_param("i", $user_id);
$us->execute();
if ($ur = $us->get_result()->fetch_assoc()) {
    $user_name = explode(" ", $ur['full_name'])[0];
}

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
        /* ===== RESET & GLOBAL ===== */
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif;}
        body{background:#f4f6f8;display:flex;flex-direction:column;min-height:100vh;overflow-x:hidden;}
        .app-wrapper{display:flex;flex:1;}

        /* ===== SIDEBAR ===== */
        .sidebar{ width:260px; background:#1e293b; color:#e2e8f0; display:flex; flex-direction:column; transition:transform 0.3s ease; position:sticky; top:0; height:100vh; overflow-y:auto; }
        .sidebar-header{padding:24px 20px;border-bottom:1px solid #334155;}
        .sidebar-header h2{font-size:1.4rem;font-weight:600;color:white;margin-bottom:4px;}
        .sidebar-header p{font-size:0.9rem;color:#94a3b8;}
        .nav-links{flex:1;padding:20px 0;}
        .nav-links a{ display:flex; align-items:center; gap:12px; padding:12px 20px; color:#cbd5e1; text-decoration:none; font-size:1rem; transition:0.2s; }
        .nav-links a i{width:24px;text-align:center;}
        .nav-links a:hover,.nav-links a.active{background:#2d3a4f;color:white;border-left:4px solid #1890ff;}
        .sidebar-footer{padding:20px;border-top:1px solid #334155;}
        .sidebar-footer a{display:flex;align-items:center;gap:12px;color:#f87171;text-decoration:none;font-weight:500;}
        .sidebar-footer a i{width:24px;}
        .menu-toggle{display:none;}
        .main-content{ flex:1; padding:24px 32px; overflow-y:auto; width:100%; }

        @media (max-width: 768px){
            .app-wrapper{flex-direction:column;}
            .sidebar{ position:fixed; left:0;top:0; z-index:1000; transform:translateX(-100%); width:280px; box-shadow:4px 0 20px rgba(0,0,0,0.1); }
            .sidebar.open{transform:translateX(0);}
            .menu-toggle{display:inline-block; background:none; border:none; font-size:1.5rem; cursor:pointer; color:#1e293b; margin-right: 15px;}
            .main-content{padding:16px;}
        }

        /* MODAL */
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); }
        .modal-content { background:#fff; margin:5% auto; padding:0; border-radius:10px; max-width:500px; box-shadow:0 6px 18px rgba(0,0,0,0.2); display:flex; flex-direction:column; }
        .modal-header { display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #ddd; padding:10px 15px; }
        .close { color:#aaa; font-size:28px; font-weight:bold; cursor:pointer; }
        .close:hover { color:#000; }

        /* ORIGINAL RATE MECHANIC STYLES */
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
<div class="app-wrapper">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <h2><i class="fas fa-car" style="margin-right: 8px;"></i><?php echo htmlspecialchars($user_name); ?></h2>
      <p>Driver Profile</p>
    </div>
    <nav class="nav-links">
      <a href="/mechanics_tracer/dashboard/driver_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="/mechanics_tracer/forms/profile/driver_profile.php"><i class="fas fa-user"></i> My Profile</a>
      <a href="/mechanics_tracer/forms/bookings/driver_bookings.php"><i class="fas fa-calendar-check"></i> My Bookings</a>
      <a href="/mechanics_tracer/dashboard/rate_mechanic.php" class="active"><i class="fas fa-star"></i> Ratings</a>
    </nav>
    <div class="sidebar-footer">
      <a href="/mechanics_tracer/forms/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="container">
        <h1 style="display:flex; align-items:center; margin-bottom: 8px;">
            <button class="menu-toggle" id="menuToggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
            Rate your mechanics
        </h1>
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
                    <button class="btn btn-primary" onclick="openRateModal(<?php echo (int)$u['id']; ?>)">⭐ Rate Now</button>
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
  </main>
</div>

<!-- Rate Modal -->
<div id="rateModal" class="modal">
    <div class="modal-content" style="height: auto; min-height: 350px;">
        <div class="modal-header">
            <h3>Rate Booking</h3>
            <span class="close" onclick="closeRateModal()">&times;</span>
        </div>
        <div style="padding: 20px;">
            <form id="rateForm" method="POST">
                <input type="hidden" name="booking_id" id="rateBookingId">
                <p style="margin-bottom: 10px; font-weight: bold;">Rating (1-5 stars)</p>
                <div class="star-rating" style="font-size: 24px; cursor: pointer; color: #ccc; margin-bottom: 15px;">
                    <span class="star" data-value="1"><i class="far fa-star"></i></span>
                    <span class="star" data-value="2"><i class="far fa-star"></i></span>
                    <span class="star" data-value="3"><i class="far fa-star"></i></span>
                    <span class="star" data-value="4"><i class="far fa-star"></i></span>
                    <span class="star" data-value="5"><i class="far fa-star"></i></span>
                </div>
                <input type="hidden" name="stars" id="selectedStars" value="0" required>
                
                <p style="margin: 10px 0 5px; font-weight: bold;">Review (Optional)</p>
                <textarea name="review" style="width: 100%; height: 80px; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-family: inherit; font-size: 14px;" placeholder="How was the service?"></textarea>
                
                <button type="submit" style="background: #1890ff; color: white; border: none; padding: 10px 20px; border-radius: 6px; margin-top: 15px; width: 100%; font-size: 16px; cursor: pointer;">Submit Rating</button>
            </form>
        </div>
    </div>
</div>

<script>
function openRateModal(bookingId) {
    document.getElementById('rateBookingId').value = bookingId;
    document.getElementById('rateModal').style.display = 'block';
    document.getElementById('selectedStars').value = '0';
    document.querySelector('textarea[name="review"]').value = '';
    highlightStars(0);
}

function closeRateModal() {
    document.getElementById('rateModal').style.display = 'none';
}

window.onclick = function(event){
    const rateModal = document.getElementById('rateModal');
    if(event.target==rateModal) closeRateModal();
}

const stars = document.querySelectorAll('.star-rating .star');
const selectedStarsInput = document.getElementById('selectedStars');

stars.forEach(star => {
    star.addEventListener('mouseover', function() { highlightStars(this.getAttribute('data-value')); });
    star.addEventListener('mouseout', function() { highlightStars(selectedStarsInput.value); });
    star.addEventListener('click', function() {
        selectedStarsInput.value = this.getAttribute('data-value');
        highlightStars(this.getAttribute('data-value'));
    });
});

function highlightStars(val) {
    stars.forEach(s => {
        const v = s.getAttribute('data-value');
        const icon = s.querySelector('i');
        if (v <= val) {
            icon.classList.remove('far');
            icon.classList.add('fas');
            s.style.color = '#fbbf24';
        } else {
            icon.classList.remove('fas');
            icon.classList.add('far');
            s.style.color = '#ccc';
        }
    });
}

document.getElementById('rateForm').addEventListener('submit', function(e) {
    if (document.getElementById('selectedStars').value === '0') {
        e.preventDefault();
        alert('Please select a star rating.');
    }
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('menuToggle');
    if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
        sidebar.classList.remove('open');
    }
});
</script>
</body>
</html>
