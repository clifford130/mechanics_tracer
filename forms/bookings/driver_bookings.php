<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . "/mechanics_tracer/forms/config.php");

// Only drivers can access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver'){
    header("Location: /mechanics_tracer/forms/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get driver ID
$stmt = $conn->prepare("SELECT id FROM drivers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();

if(!$driver){
    die("Driver profile not found.");
}

$driver_id = $driver['id'];

$user_name = "Driver";
$us = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$us->bind_param("i", $user_id);
$us->execute();
if ($ur = $us->get_result()->fetch_assoc()) {
    $user_name = explode(" ", $ur['full_name'])[0];
}

// Fetch bookings
$stmt = $conn->prepare("
    SELECT b.*, m.garage_name, m.id AS mechanic_id
    FROM bookings b
    JOIN mechanics m ON b.mechanic_id = m.id
    WHERE b.driver_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);

// Separate into active and past; mark which completed are unrated
$active = [];
$past = [];
$rated_ids = [];
if (($r = $conn->query("SHOW TABLES LIKE 'ratings'")) && $r->num_rows) {
    $rq = $conn->prepare("SELECT booking_id, stars, review FROM ratings WHERE driver_id = ?");
    $rq->bind_param("i", $driver_id);
    $rq->execute();
    foreach ($rq->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $rated_ids[(int)$row['booking_id']] = $row;
    }
}
foreach($bookings as $b){
    if(in_array($b['booking_status'], ['pending','accepted'])){
        $active[] = $b;
    } else {
        $b['can_rate'] = ($b['booking_status'] === 'completed' && !isset($rated_ids[(int)$b['id']]));
        $b['rating_data'] = $rated_ids[(int)$b['id']] ?? null;
        $past[] = $b;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Bookings</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="/mechanics_tracer/assets/css/ux_enhancements.css">
<link rel="stylesheet" href="/mechanics_tracer/assets/css/chat.css">
<script src="/mechanics_tracer/assets/js/ux_enhancements.js"></script>
<script src="/mechanics_tracer/assets/js/chat.js"></script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* ===== RESET & GLOBAL ===== */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif;}
body{background:#f4f6f8;display:flex;flex-direction:column;min-height:100vh;overflow-x:hidden;}
.app-wrapper{display:flex;flex:1;}

/* ===== SIDEBAR ===== */
.sidebar{
    width:260px;
    background:#1e293b;
    color:#e2e8f0;
    display:flex;
    flex-direction:column;
    transition:transform 0.3s ease;
    position:sticky;
    top:0;
    height:100vh;
    overflow-y:auto;
}
.sidebar-header{padding:24px 20px;border-bottom:1px solid #334155;}
.sidebar-header h2{font-size:1.4rem;font-weight:600;color:white;margin-bottom:4px;}
.sidebar-header p{font-size:0.9rem;color:#94a3b8;}
.nav-links{flex:1;padding:20px 0;}
.nav-links a{
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px 20px;
    color:#cbd5e1;
    text-decoration:none;
    font-size:1rem;
    transition:0.2s;
}
.nav-links a i{width:24px;text-align:center;}
.nav-links a:hover,.nav-links a.active{background:#2d3a4f;color:white;border-left:4px solid #1890ff;}
.sidebar-footer{padding:20px;border-top:1px solid #334155;}
.sidebar-footer a{display:flex;align-items:center;gap:12px;color:#f87171;text-decoration:none;font-weight:500;}
.sidebar-footer a i{width:24px;}
.menu-toggle{display:none;}

/* ===== MAIN CONTENT ===== */
.main-content{
    flex:1;
    padding:24px 32px;
    overflow-y:auto;
    width:100%;
}

@media (max-width: 768px){
    .app-wrapper{flex-direction:column;}
    .sidebar{
        position:fixed;
        left:0;top:0;
        z-index:1000;
        transform:translateX(-100%);
        width:280px;
        box-shadow:4px 0 20px rgba(0,0,0,0.1);
    }
    .sidebar.open{transform:translateX(0);}
    .menu-toggle{display:inline-block; background:none; border:none; font-size:1.5rem; cursor:pointer; color:#1e293b;}
    .main-content{padding:16px;}
}

/* GENERAL STYLES */
.container { max-width: 950px; margin: 0 auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.1); }
h2.page-title { text-align:center; color:#2c3e50; margin-bottom:30px; display:flex; align-items:center; justify-content:space-between; }

/* SECTION HEADERS */
.section-title.active { margin-top:30px; margin-bottom:15px; font-size:20px; color:#fff; background:#1890ff; padding:8px 12px; border-radius:6px; }
.section-title.past { margin-top:30px; margin-bottom:15px; font-size:20px; color:#fff; background:#e74c3c; padding:8px 12px; border-radius:6px; }

/* BOOKING CARDS */
.booking { padding:15px; margin-bottom:15px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; background:#fff; border:1px solid #dfe3e6; box-shadow:0 2px 6px rgba(0,0,0,0.05); flex-wrap:wrap; }
.booking-info { flex:1 1 60%; min-width:200px; }
.booking-info p { margin:5px 0; color:#34495e; font-size:14px; word-wrap: break-word; }

.status { font-weight:bold; padding:4px 10px; border-radius:12px; color:#fff; font-size:13px; display:inline-block; }
.status.pending { background:#f39c12; } 
.status.accepted { background:#27ae60; } 
.status.completed { background:#3498db; } 
.status.cancelled { background:#e74c3c; }

/* ACTION BUTTONS */
.actions { text-align:right; display:flex; gap:5px; flex:1 1 30%; min-width:120px; justify-content:flex-end; margin-top:10px; }
button, .chat-icon { padding:6px 12px; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
button.cancel { background:#e74c3c; color:#fff; }
.chat-icon { background:#2980b9; color:#fff; font-weight:bold; }
button:hover, .chat-icon:hover { opacity:0.85; }

/* MODAL */
.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); }
.modal-content { background:#fff; margin:5% auto; padding:0; border-radius:10px; max-width:500px; box-shadow:0 6px 18px rgba(0,0,0,0.2); display:flex; flex-direction:column; height:500px; }
.modal-header { display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #ddd; padding:10px 15px; }
.close { color:#aaa; font-size:28px; font-weight:bold; cursor:pointer; }
.close:hover { color:#000; }

/* CHAT STYLES */
.chat-container { display:flex; flex-direction:column; height:100%; }
.chat-messages { flex:1; padding:10px; overflow-y:auto; background:#f9f9f9; }
.message { max-width:70%; margin-bottom:8px; padding:8px 12px; border-radius:20px; word-wrap:break-word; }
.message.driver { background:#1890ff; color:#fff; align-self:flex-end; }
.message.mechanic { background:#e0e0e0; color:#333; align-self:flex-start; }
.chat-input { display:flex; border-top:1px solid #ccc; }
.chat-input input { flex:1; padding:10px; border:none; border-radius:0; }
.chat-input button { background:#1890ff; color:#fff; border:none; padding:10px 15px; cursor:pointer; }

/* RESPONSIVENESS */
@media(max-width:600px){
    .booking { flex-direction:column; align-items:flex-start; }
    .actions { justify-content:flex-start; margin-top:10px; width:100%; gap:8px; }
}
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
      <a href="/mechanics_tracer/forms/bookings/driver_bookings.php" class="active"><i class="fas fa-calendar-check"></i> My Bookings</a>
      <a href="/mechanics_tracer/dashboard/rate_mechanic.php"><i class="fas fa-star"></i> Ratings</a>
    </nav>
    <div class="sidebar-footer">
      <a href="/mechanics_tracer/forms/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="container">
      <h2 class="page-title">
        <button class="menu-toggle" id="menuToggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
          <i class="fas fa-bars"></i>
        </button>
        My Bookings
        <div style="width:24px"></div>
      </h2>

<?php if(empty($bookings)): ?>
    <p>You have no bookings yet. <a href="/mechanics_tracer/forms/bookings/book_mechanic.php">Book a mechanic now</a>.</p>
<?php else: ?>

    <?php if(!empty($active)): ?>
        <div class="section-title active">Active Bookings</div>
        <?php foreach($active as $b): ?>
            <div class="booking">
                <div class="booking-info">
                    <p><strong>Mechanic:</strong> <?php echo htmlspecialchars($b['garage_name']); ?></p>
                    <p><strong>Service:</strong> <?php echo htmlspecialchars($b['service_requested']); ?></p>
                    <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($b['vehicle_type']); ?></p>
                    <p><strong>Booked On:</strong> <?php echo htmlspecialchars($b['created_at']); ?></p>
                    <p class="status <?php echo htmlspecialchars($b['booking_status']); ?>"><?php echo ucfirst($b['booking_status']); ?></p>
                </div>
                <div class="actions">
                    <?php if($b['booking_status']=='pending'): ?>
                        <form method="POST" action="/mechanics_tracer/forms/bookings/cancel_booking.php" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                            <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                            <button type="submit" class="cancel">Cancel</button>
                        </form>
                    <?php endif; ?>
                    <button class="chat-icon" onclick="MT_Chat.open(<?php echo $b['id']; ?>,'<?php echo addslashes($b['garage_name']); ?>')">💬 Chat</button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if(!empty($past)): ?>
        <div class="section-title past">Past / Cancelled Bookings</div>
        <?php foreach($past as $b): ?>
            <div class="booking">
                <div class="booking-info">
                    <p><strong>Mechanic:</strong> <?php echo htmlspecialchars($b['garage_name']); ?></p>
                    <p><strong>Service:</strong> <?php echo htmlspecialchars($b['service_requested']); ?></p>
                    <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($b['vehicle_type']); ?></p>
                    <p><strong>Booked On:</strong> <?php echo htmlspecialchars($b['created_at']); ?></p>
                    <p class="status <?php echo htmlspecialchars($b['booking_status']); ?>"><?php echo ucfirst($b['booking_status']); ?></p>
                </div>
                <div class="actions">
                    <?php if (!empty($b['can_rate'])): ?>
                        <button class="chat-icon" style="background:#f39c12;" onclick="openRateModal(<?php echo $b['id']; ?>)">⭐ Rate</button>
                    <?php elseif(!empty($b['rating_data'])): ?>
                        <div style="color:#fbbf24; margin-right: 10px; display:flex; align-items:center;" title="Rated: <?php echo htmlspecialchars($b['rating_data']['review']); ?>">
                            <?php echo str_repeat('<i class="fas fa-star"></i>', (int)$b['rating_data']['stars']); ?>
                        </div>
                    <?php endif; ?>
                    <button class="chat-icon" onclick="MT_Chat.open(<?php echo $b['id']; ?>,'<?php echo addslashes($b['garage_name']); ?>')">💬 Chat</button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>
    </div>
  </main>
</div>

<!-- Chat Modal and Rate Modal have different structures, MT_Chat handles chat -->

<!-- Rate Modal -->
<div id="rateModal" class="modal">
    <div class="modal-content" style="height: auto; min-height: 350px;">
        <div class="modal-header">
            <h3>Rate Booking</h3>
            <span class="close" onclick="closeRateModal()">&times;</span>
        </div>
        <div style="padding: 20px;">
            <form id="rateForm" method="POST" action="/mechanics_tracer/forms/bookings/submit_rating.php">
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
window.onclick = function(event){
    const rateModal = document.getElementById('rateModal');
    if(event.target==rateModal) closeRateModal();
}

// Initializing MT_Chat
MT_Chat.init(<?php echo $_SESSION['user_id']; ?>);

// Rate Modal functionality
function openRateModal(bookingId) {
    document.getElementById('rateBookingId').value = bookingId;
    document.getElementById('rateModal').style.display = 'block';
    
    // reset stars and form
    document.getElementById('selectedStars').value = '0';
    document.querySelector('textarea[name="review"]').value = '';
    highlightStars(0);
}

function closeRateModal() {
    document.getElementById('rateModal').style.display = 'none';
}

const stars = document.querySelectorAll('.star-rating .star');
const selectedStarsInput = document.getElementById('selectedStars');

stars.forEach(star => {
    star.addEventListener('mouseover', function() {
        highlightStars(this.getAttribute('data-value'));
    });
    star.addEventListener('mouseout', function() {
        highlightStars(selectedStarsInput.value);
    });
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

// Auto-refresh handled by MT_Chat.startPolling() inside open() method

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