<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . "/mechanics_tracer/forms/config.php");

// Only mechanics can access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mechanic'){
    header("Location: /mechanics_tracer/forms/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch mechanic details
$stmt = $conn->prepare("SELECT * FROM mechanics WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$mechanic = $result->fetch_assoc();

if(!$mechanic){
    die("Mechanic profile not found. Please complete your profile.");
}

$mechanic_id = $mechanic['id'];

// Fetch bookings related to this mechanic
$stmt = $conn->prepare("
    SELECT b.*, d.vehicle_type, d.vehicle_make, d.vehicle_model, d.vehicle_year, u.full_name AS driver_name, u.phone AS driver_phone
    FROM bookings b
    JOIN drivers d ON b.driver_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE b.mechanic_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $mechanic_id);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);

// Separate bookings by status
$pending = [];
$active = [];
$completed = [];
$cancelled = [];

foreach($bookings as $b){
    switch($b['booking_status']){
        case 'pending':
            $pending[] = $b; break;
        case 'accepted':
            $active[] = $b; break;
        case 'completed':
            $completed[] = $b; break;
        case 'cancelled':
            $cancelled[] = $b; break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Mechanic Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f4f6f8; margin:0; padding:0; }
header { background:#1890ff; color:#fff; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; }
header h1 { margin:0; font-size:22px; }
header a { color:#fff; text-decoration:none; font-weight:500; }
.container { max-width:1000px; margin:20px auto; padding:20px; }
.tabs { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px; }
.tab { padding:10px 20px; background:#ddd; border-radius:6px; cursor:pointer; transition:0.3s; }
.tab.active { background:#1890ff; color:#fff; }
.booking { padding:15px; margin-bottom:15px; border-radius:8px; display:flex; justify-content:space-between; align-items:center; background:#fff; border:1px solid #dfe3e6; box-shadow:0 2px 6px rgba(0,0,0,0.05); flex-wrap:wrap; }
.booking-info { flex:1 1 60%; min-width:200px; }
.booking-info p { margin:5px 0; color:#34495e; font-size:14px; }
.status { font-weight:bold; padding:4px 10px; border-radius:12px; color:#fff; font-size:13px; display:inline-block; }
.status.pending { background:#f39c12; } 
.status.accepted { background:#27ae60; } 
.status.completed { background:#3498db; } 
.status.cancelled { background:#e74c3c; }
.actions { text-align:right; display:flex; gap:5px; flex:1 1 30%; min-width:120px; justify-content:flex-end; margin-top:10px; }
button, .chat-icon, .view-location { padding:6px 12px; border:none; border-radius:6px; cursor:pointer; font-size:14px; }
button.accept { background:#27ae60; color:#fff; }
button.complete { background:#3498db; color:#fff; }
button.cancel { background:#e74c3c; color:#fff; }
.chat-icon { background:#2980b9; color:#fff; font-weight:bold; }
.view-location { background:#f1c40f; color:#fff; font-weight:bold; }
button:hover, .chat-icon:hover, .view-location:hover { opacity:0.85; }

/* Modal */
.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); }
.modal-content { background:#fff; margin:10% auto; padding:20px; border-radius:10px; max-width:500px; box-shadow:0 6px 18px rgba(0,0,0,0.2); }
.modal-header { display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #ddd; padding-bottom:10px; }
.close { color:#aaa; font-size:28px; font-weight:bold; cursor:pointer; }
.close:hover { color:#000; }
#map { width:100%; height:300px; border-radius:8px; }

/* Responsive */
@media(max-width:600px){
    .booking { flex-direction:column; align-items:flex-start; }
    .actions { justify-content:flex-start; margin-top:10px; width:100%; gap:8px; }
    .tabs { flex-direction:column; }
}
</style>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
</head>
<body>

<header>
    <h1><?php echo htmlspecialchars($mechanic['garage_name']); ?> Dashboard</h1>
    <a href="/mechanics_tracer/forms/auth/logout.php">Logout</a>
</header>

<div class="container">
    <div class="tabs">
        <div class="tab active" onclick="showTab('pending')">Pending (<?php echo count($pending); ?>)</div>
        <div class="tab" onclick="showTab('active')">Active (<?php echo count($active); ?>)</div>
        <div class="tab" onclick="showTab('completed')">Completed (<?php echo count($completed); ?>)</div>
        <div class="tab" onclick="showTab('cancelled')">Cancelled (<?php echo count($cancelled); ?>)</div>
    </div>

    <!-- Pending Bookings -->
    <div id="pending" class="booking-section">
        <?php foreach($pending as $b): ?>
            <div class="booking">
                <div class="booking-info">
                    <p><strong>Driver:</strong> <?php echo htmlspecialchars($b['driver_name']); ?></p>
                    <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($b['vehicle_type'] . ' - ' . $b['vehicle_make'] . ' ' . $b['vehicle_model']); ?></p>
                    <p><strong>Service:</strong> <?php echo htmlspecialchars($b['service_requested']); ?></p>
                    <p><strong>Booked On:</strong> <?php echo htmlspecialchars($b['created_at']); ?></p>
                    <p class="status pending">Pending</p>
                </div>
                <div class="actions">
                    <form method="POST" action="/mechanics_tracer/forms/bookings/accept_booking.php">
                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                        <button type="submit" class="accept">Accept</button>
                    </form>
                    <form method="POST" action="/mechanics_tracer/forms/bookings/cancel_booking.php" onsubmit="return confirm('Cancel this booking?');">
                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                        <button type="submit" class="cancel">Cancel</button>
                    </form>
                    <button class="chat-icon">💬 Chat</button>
                    <button class="view-location" onclick="openMapModal(<?php echo $b['driver_latitude']; ?>,<?php echo $b['driver_longitude']; ?>,'<?php echo htmlspecialchars($b['driver_name']); ?>')">📍 Driver Location</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Active Bookings -->
    <div id="active" class="booking-section" style="display:none;">
        <?php foreach($active as $b): ?>
            <div class="booking">
                <div class="booking-info">
                    <p><strong>Driver:</strong> <?php echo htmlspecialchars($b['driver_name']); ?></p>
                    <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($b['vehicle_type'] . ' - ' . $b['vehicle_make'] . ' ' . $b['vehicle_model']); ?></p>
                    <p><strong>Service:</strong> <?php echo htmlspecialchars($b['service_requested']); ?></p>
                    <p><strong>Booked On:</strong> <?php echo htmlspecialchars($b['created_at']); ?></p>
                    <p class="status accepted">Accepted</p>
                </div>
                <div class="actions">
                    <form method="POST" action="/mechanics_tracer/forms/bookings/complete_booking.php">
                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                        <button type="submit" class="complete">Complete</button>
                    </form>
                    <button class="chat-icon">💬 Chat</button>
                    <button class="view-location" onclick="openMapModal(<?php echo $b['driver_latitude']; ?>,<?php echo $b['driver_longitude']; ?>,'<?php echo htmlspecialchars($b['driver_name']); ?>')">📍 Driver Location</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Completed Bookings -->
    <div id="completed" class="booking-section" style="display:none;">
        <?php foreach($completed as $b): ?>
            <div class="booking">
                <div class="booking-info">
                    <p><strong>Driver:</strong> <?php echo htmlspecialchars($b['driver_name']); ?></p>
                    <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($b['vehicle_type'] . ' - ' . $b['vehicle_make'] . ' ' . $b['vehicle_model']); ?></p>
                    <p><strong>Service:</strong> <?php echo htmlspecialchars($b['service_requested']); ?></p>
                    <p><strong>Booked On:</strong> <?php echo htmlspecialchars($b['created_at']); ?></p>
                    <p class="status completed">Completed</p>
                </div>
                <div class="actions">
                    <button class="chat-icon">💬 Chat</button>
                    <button class="view-location" onclick="openMapModal(<?php echo $b['driver_latitude']; ?>,<?php echo $b['driver_longitude']; ?>,'<?php echo htmlspecialchars($b['driver_name']); ?>')">📍 Driver Location</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Cancelled Bookings -->
    <div id="cancelled" class="booking-section" style="display:none;">
        <?php foreach($cancelled as $b): ?>
            <div class="booking">
                <div class="booking-info">
                    <p><strong>Driver:</strong> <?php echo htmlspecialchars($b['driver_name']); ?></p>
                    <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($b['vehicle_type'] . ' - ' . $b['vehicle_make'] . ' ' . $b['vehicle_model']); ?></p>
                    <p><strong>Service:</strong> <?php echo htmlspecialchars($b['service_requested']); ?></p>
                    <p><strong>Booked On:</strong> <?php echo htmlspecialchars($b['created_at']); ?></p>
                    <p class="status cancelled">Cancelled</p>
                </div>
                <div class="actions">
                    <button class="chat-icon">💬 Chat</button>
                    <button class="view-location" onclick="openMapModal(<?php echo $b['driver_latitude']; ?>,<?php echo $b['driver_longitude']; ?>,'<?php echo htmlspecialchars($b['driver_name']); ?>')">📍 Driver Location</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Map Modal -->
<div id="mapModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="mapTitle">Driver Location</h3>
            <span class="close" onclick="closeMapModal()">&times;</span>
        </div>
        <div id="map"></div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
function showTab(tabId){
    document.querySelectorAll('.booking-section').forEach(sec=>sec.style.display='none');
    document.getElementById(tabId).style.display='block';
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    document.querySelector('[onclick="showTab(\''+tabId+'\')"]').classList.add('active');
}

// Map modal functions
let map;
function openMapModal(lat, lng, driverName){
    document.getElementById('mapTitle').textContent = driverName + "'s Location";
    document.getElementById('mapModal').style.display = 'block';

    setTimeout(()=>{
        if(map) map.remove(); // remove previous map
        map = L.map('map').setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
        L.marker([lat, lng]).addTo(map).bindPopup(driverName).openPopup();
    },100);
}

function closeMapModal(){
    document.getElementById('mapModal').style.display='none';
    if(map) map.remove();
}

window.onclick = function(event){
    if(event.target == document.getElementById('mapModal')) closeMapModal();
}
</script>

</body>
</html>