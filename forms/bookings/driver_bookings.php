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
    $rq = $conn->prepare("SELECT booking_id FROM ratings WHERE driver_id = ?");
    $rq->bind_param("i", $driver_id);
    $rq->execute();
    foreach ($rq->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $rated_ids[(int)$row['booking_id']] = true;
    }
}
foreach($bookings as $b){
    if(in_array($b['booking_status'], ['pending','accepted'])){
        $active[] = $b;
    } else {
        $b['can_rate'] = ($b['booking_status'] === 'completed' && !isset($rated_ids[(int)$b['id']]));
        $past[] = $b;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Bookings</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* GENERAL STYLES */
body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f4f6f8; margin:0; padding:0; }
.container { max-width: 950px; margin: 40px auto; background:#fff; padding:30px; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.1); }
h2 { text-align:center; color:#2c3e50; margin-bottom:30px; }

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

<div class="container">
<h2>My Bookings</h2>

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
                    <button class="chat-icon" onclick="openChatModal(<?php echo $b['mechanic_id']; ?>,'<?php echo htmlspecialchars($b['garage_name']); ?>')">💬 Chat</button>
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
                        <a href="/mechanics_tracer/dashboard/rate_mechanic.php" class="chat-icon" style="text-decoration:none;">⭐ Rate</a>
                    <?php endif; ?>
                    <button class="chat-icon" onclick="openChatModal(<?php echo $b['mechanic_id']; ?>,'<?php echo addslashes(htmlspecialchars($b['garage_name'])); ?>')">💬 Chat</button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>
</div>

<!-- Chat Modal -->
<div id="chatModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="chatTitle">Chat with Mechanic</h3>
            <span class="close" onclick="closeChatModal()">&times;</span>
        </div>
        <div class="chat-container">
            <div class="chat-messages" id="chatMessages"></div>
            <div class="chat-input">
                <input type="text" id="chatText" placeholder="Type a message...">
                <button onclick="sendMessage()">Send</button>
            </div>
        </div>
    </div>
</div>

<script>
// Open chat modal and store mechanic in session
function openChatModal(mechanicId, garageName){
    document.getElementById('chatTitle').textContent = 'Chat with ' + garageName;
    sessionStorage.setItem('chat_mechanic_id', mechanicId);
    document.getElementById('chatModal').style.display = 'block';
    loadMessages();
}

function closeChatModal(){
    const chatModal = document.getElementById('chatModal');
    document.getElementById('chatText').value = '';
    document.getElementById('chatMessages').innerHTML = '';
    chatModal.style.display = "none";
}

window.onclick = function(event){
    const chatModal = document.getElementById('chatModal');
    if(event.target==chatModal) closeChatModal();
}

// Load messages via AJAX
function loadMessages(){
    const mechanicId = sessionStorage.getItem('chat_mechanic_id');
    if(!mechanicId) return;
    fetch('/mechanics_tracer/forms/fetch_messages.php?mechanic_id=' + mechanicId)
    .then(res => res.json())
    .then(data => {
        const chat = document.getElementById('chatMessages');
        chat.innerHTML = '';
        data.forEach(msg => {
            const div = document.createElement('div');
            div.className = 'message ' + (msg.sender=='driver' ? 'driver' : 'mechanic');
            div.textContent = msg.message;
            chat.appendChild(div);
        });
        chat.scrollTop = chat.scrollHeight;
    });
}

// Send message
function sendMessage(){
    const input = document.getElementById('chatText');
    const msg = input.value.trim();
    if(msg=='') return;
    const mechanicId = sessionStorage.getItem('chat_mechanic_id');

    fetch('/mechanics_tracer/forms/send_message.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'mechanic_id=' + mechanicId + '&message=' + encodeURIComponent(msg)
    }).then(res=>res.json())
      .then(data=>{
          input.value='';
          loadMessages();
      });
}

// Auto-refresh every 2s
setInterval(loadMessages, 2000);
</script>

</body>
</html>