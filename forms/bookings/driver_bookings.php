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

// Fetch all bookings for this driver
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Bookings</title>
<style>
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; margin:0; padding:0; }
.container { max-width: 900px; margin: 40px auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 8px 20px rgba(0,0,0,0.1); }
h2 { text-align:center; color:#333; margin-bottom:30px; }
.booking { border-left:5px solid #1890ff; padding:15px; margin-bottom:20px; border-radius:6px; background:#e6f7ff; display:flex; justify-content:space-between; align-items:center; }
.booking-info { flex:1; }
.booking-info p { margin:4px 0; }
.status { font-weight:bold; padding:3px 8px; border-radius:4px; color:#fff; }
.status.pending { background:#ffc107; }
.status.accepted { background:#28a745; }
.status.completed { background:#6c757d; }
.status.cancelled { background:#dc3545; }
.actions { text-align:right; }
.actions form { display:inline; }
button, .chat-icon { padding:6px 12px; border:none; border-radius:6px; cursor:pointer; margin-left:5px; }
button.cancel { background:#dc3545; color:#fff; }
.chat-icon { background:#1890ff; color:#fff; font-weight:bold; }
button:hover, .chat-icon:hover { opacity:0.85; }

/* Modal styling */
.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); }
.modal-content { background:#fff; margin:10% auto; padding:20px; border-radius:8px; max-width:500px; }
.modal-header { display:flex; justify-content:space-between; align-items:center; }
.close { color:#aaa; font-size:28px; font-weight:bold; cursor:pointer; }
.close:hover { color:#000; }
</style>
</head>
<body>

<div class="container">
<h2>My Bookings</h2>

<?php if(empty($bookings)): ?>
    <p>You have no bookings yet. <a href="/mechanics_tracer/forms/bookings/book_mechanic.php">Book a mechanic now</a>.</p>
<?php else: ?>
    <?php foreach($bookings as $b): ?>
        <div class="booking">
            <div class="booking-info">
                <p><strong>Mechanic:</strong> <?php echo htmlspecialchars($b['garage_name']); ?></p>
                <p><strong>Service:</strong> <?php echo htmlspecialchars($b['service_requested']); ?></p>
                <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($b['vehicle_type']); ?></p>
                <p><strong>Booked On:</strong> <?php echo htmlspecialchars($b['created_at']); ?></p>
                <p class="status <?php echo htmlspecialchars($b['booking_status']); ?>"><?php echo ucfirst($b['booking_status']); ?></p>
            </div>
            <div class="actions">
                <?php if($b['booking_status'] == 'pending'): ?>
                    <form method="POST" action="/mechanics_tracer/forms/bookings/cancel_booking.php" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                        <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                        <button type="submit" class="cancel">Cancel</button>
                    </form>
                <?php endif; ?>
                <button class="chat-icon" onclick="openChatModal(<?php echo $b['mechanic_id']; ?>)">💬 Chat</button>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- Chat Modal -->
<div id="chatModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Chat with Mechanic</h3>
            <span class="close" onclick="closeChatModal()">&times;</span>
        </div>
        <iframe id="chatFrame" src="" style="width:100%; height:300px; border:none;"></iframe>
    </div>
</div>

<script>
function openChatModal(mechanicId){
    const chatModal = document.getElementById('chatModal');
    const chatFrame = document.getElementById('chatFrame');
    // Load chat page with booking_id or mechanic_id
    chatFrame.src = "/mechanics_tracer/forms/chat.php?mechanic_id=" + mechanicId;
    chatModal.style.display = "block";
}

function closeChatModal(){
    const chatModal = document.getElementById('chatModal');
    const chatFrame = document.getElementById('chatFrame');
    chatFrame.src = "";
    chatModal.style.display = "none";
}

// Close modal on outside click
window.onclick = function(event) {
    const chatModal = document.getElementById('chatModal');
    if(event.target == chatModal){
        closeChatModal();
    }
}
</script>

</body>
</html>