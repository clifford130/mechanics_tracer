<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . "/mechanics_tracer/forms/config.php");

// Only mechanics can access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mechanic'){
    header("Location: /mechanics_tracer/forms/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch mechanic details with user's full name
$stmt = $conn->prepare("
    SELECT m.*, u.full_name AS mechanic_name 
    FROM mechanics m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$mechanic = $result->fetch_assoc();

if(!$mechanic){
    die("Mechanic profile not found. Please complete your profile.");
}

$mechanic_id = $mechanic['id'];
$mechanic_lat = $mechanic['latitude'];
$mechanic_lng = $mechanic['longitude'];
$mechanic_name = $mechanic['mechanic_name'] ?? $mechanic['garage_name'];

// Rating summary (if ratings table exists)
$ratingSummary = null;
$recentRatings = [];
$rt = @$conn->query("SHOW TABLES LIKE 'ratings'");
if ($rt && $rt->num_rows) {
    $row = $conn->query("SELECT AVG(stars) AS avg_stars, COUNT(*) AS cnt FROM ratings WHERE mechanic_id = " . (int)$mechanic_id)->fetch_assoc();
    if ($row && (int)$row['cnt'] > 0) {
        $ratingSummary = $row;
        $rq = $conn->prepare("
            SELECT r.stars, r.review, r.created_at, u.full_name AS driver_name
            FROM ratings r
            JOIN drivers d ON r.driver_id = d.id
            JOIN users u ON d.user_id = u.id
            WHERE r.mechanic_id = ?
            ORDER BY r.created_at DESC
            LIMIT 3
        ");
        $rq->bind_param("i", $mechanic_id);
        $rq->execute();
        $recentRatings = $rq->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Fetch bookings related to this mechanic
$stmt = $conn->prepare("
    SELECT b.*, d.vehicle_type AS driver_vehicle_type, d.vehicle_make AS driver_vehicle_make, d.vehicle_model AS driver_vehicle_model, d.vehicle_year AS driver_vehicle_year, u.full_name AS driver_name
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
$pending = $active = $completed = $cancelled = [];
foreach($bookings as $b){
    switch($b['booking_status']){
        case 'pending':    $pending[] = $b; break;
        case 'accepted':   $active[] = $b; break;
        case 'completed':  $completed[] = $b; break;
        case 'cancelled':  $cancelled[] = $b; break;
    }
}

// Counts for cards
$pendingCount = count($pending);
$activeCount = count($active);
$completedCount = count($completed);
$cancelledCount = count($cancelled);

// Time-based greeting
$hour = date('H');
if($hour < 12) $greeting = "Good morning";
elseif($hour < 18) $greeting = "Good afternoon";
else $greeting = "Good evening";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mechanic Dashboard | MechanicTracer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="/mechanics_tracer/assets/css/ux_enhancements.css">
    <link rel="stylesheet" href="/mechanics_tracer/assets/css/chat.css">
    <script>window.LOADER_MANUAL_INIT = true;</script>
    <script src="/mechanics_tracer/assets/js/ux_enhancements.js"></script>
    <script src="/mechanics_tracer/assets/js/chat.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #f4f6f8; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* Layout */
        .app-wrapper { display: flex; flex: 1; }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: #1e293b;
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid #334155;
        }
        .sidebar-header h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: white;
            margin-bottom: 4px;
        }
        .sidebar-header p {
            font-size: 0.9rem;
            color: #94a3b8;
        }
        .nav-links {
            flex: 1;
            padding: 20px 0;
        }
        .nav-links a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 1rem;
            transition: 0.2s;
        }
        .nav-links a i { width: 24px; text-align: center; }
        .nav-links a:hover, .nav-links a.active {
            background: #2d3a4f;
            color: white;
            border-left: 4px solid #1890ff;
        }
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid #334155;
        }
        .sidebar-footer a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #f87171;
            text-decoration: none;
            font-weight: 500;
        }
        .sidebar-footer a i { width: 24px; }
        
        /* Main content */
        .main-content {
            flex: 1;
            padding: 24px 32px;
            overflow-y: auto;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }
        
        /* Top bar */
        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .greeting h1 {
            font-size: 1.8rem;
            color: #0f172a;
            font-weight: 600;
        }
        .greeting p {
            color: #475569;
            margin-top: 4px;
            font-size: 1rem;
        }
        .menu-toggle {
            background: none;
            border: none;
            font-size: 1.8rem;
            color: #1e293b;
            cursor: pointer;
            display: none;
        }
        
        /* Stats Cards (now clickable) */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 20px 18px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }
        .card.active {
            border-color: #1890ff;
            background: #f0f7ff;
        }
        .card-left h3 {
            font-size: 1.9rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }
        .card-left p {
            color: #64748b;
            font-weight: 500;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .card-right {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .card.pending .card-right { background: #fef3c7; color: #b45309; }
        .card.active-status .card-right { background: #d1fae5; color: #065f46; }
        .card.completed .card-right { background: #dbeafe; color: #1e40af; }
        .card.cancelled .card-right { background: #fee2e2; color: #b91c1c; }
        
        /* Booking section grid */
        .booking-section {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            padding: 10px 0;
        }

        /* Booking cards (Redesigned) */
        .booking {
            display: flex;
            flex-direction: column;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e1e7ef;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            height: 100%;
        }
        .booking:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
        }
        .booking-info {
            padding: 24px;
            flex-grow: 1;
        }
        .booking-info p {
            margin: 10px 0;
            color: #475569;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .booking-info p i { width: 16px; color: #1890ff; opacity: 0.7; }
        .booking-info p strong {
            color: #0f172a;
            min-width: 80px;
        }
        .booking-info .status-badge {
            margin-top: 15px;
            display: inline-flex;
        }
        
        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            background: #e1e7ef;
            border-top: 1px solid #e1e7ef;
        }
        .actions button {
            border: none !important;
            border-radius: 0 !important;
            padding: 14px !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            background: #fff !important;
            color: #475569 !important;
            width: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            transition: background 0.2s !important;
            cursor: pointer;
        }
        .actions button:hover {
            background: #f8fafc !important;
            color: #0f172a !important;
        }
        .actions button.accept { color: #10b981 !important; }
        .actions button.complete { color: #3b82f6 !important; }
        .actions button.cancel { color: #ef4444 !important; }
        .actions button.chat-icon { color: #6366f1 !important; }
        .actions button.view-location { 
            grid-column: span 2;
            background: #f8fafc !important;
            color: #0f172a !important;
        }

        .no-bookings {
            grid-column: 1 / -1;
            padding: 40px;
            text-align: center;
            background: #f8fafc;
            border-radius: 16px;
            border: 2px dashed #e2e8f0;
            color: #64748b;
        }

        .status {
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 30px;
            color: white;
            font-size: 0.8rem;
            display: inline-block;
            text-transform: capitalize;
        }
        .status.pending { background: #f59e0b; }
        .status.accepted { background: #10b981; }
        .status.completed { background: #3b82f6; }
        .status.cancelled { background: #ef4444; }
        
        /* Map Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(3px);
        }
        .modal-content {
            background: white;
            margin: 4% auto;
            padding: 0;
            border-radius: 24px;
            max-width: 1000px;
            width: 90%;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .modal-header h3 {
            font-size: 1.3rem;
            color: #0f172a;
            font-weight: 600;
        }
        .close {
            color: #64748b;
            font-size: 28px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            line-height: 1;
        }
        .close:hover { color: #0f172a; }
        #map { width: 100%; height: 500px; }
        
        /* Notification popup */
        #popup {
            position: fixed;
            top: 24px;
            right: 24px;
            background: #10b981;
            color: white;
            padding: 14px 24px;
            border-radius: 40px;
            display: none;
            z-index: 3000;
            font-weight: 600;
            box-shadow: 0 12px 24px -8px rgba(0,0,0,0.2);
        }
        
        /* Responsive */
        @media (max-width: 900px) {
            .stats-cards { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .app-wrapper { flex-direction: column; }
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                z-index: 1000;
                transform: translateX(-100%);
                width: 280px;
                box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            }
            .sidebar.open { transform: translateX(0); }
            .menu-toggle { display: block; }
            .main-content { padding: 20px; }
            .top-bar { margin-bottom: 20px; }
            .greeting h1 { font-size: 1.5rem; }
        }
        @media (max-width: 480px) {
            .stats-cards { grid-template-columns: 1fr; }
            .booking { flex-direction: column; align-items: flex-start; }
            .actions { justify-content: flex-start; width: 100%; }
        }

        /* Toggle Switch Style */
        .status-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            padding: 8px 16px;
            border-radius: 99px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1;
            transition: .3s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px; width: 18px;
            left: 3px; bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: #10b981; }
        input:checked + .slider:before { transform: translateX(20px); }
        .status-label { font-size: 0.9rem; font-weight: 600; color: #475569; }
        .status-online { color: #10b981; }
        .status-offline { color: #ef4444; }
    </style>
</head>
<body>

<div class="app-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-wrench" style="margin-right: 8px;"></i><?php echo htmlspecialchars($mechanic['garage_name']); ?></h2>
            <p><?php echo htmlspecialchars($greeting . ', ' . explode(' ', $mechanic_name)[0]); ?></p>
        </div>
        <nav class="nav-links">
            <a href="/mechanics_tracer/dashboard/mechanic_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="/mechanics_tracer/dashboard/mechanic_dashboard.php#bookings"><i class="fas fa-calendar-check"></i> Bookings</a>
            <a href="/mechanics_tracer/dashboard/mechanic_ratings.php"><i class="fas fa-star"></i> My Ratings</a>
            <a href="/mechanics_tracer/forms/profile/mechanic_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
        </nav>
        <div class="sidebar-footer">
            <a href="/mechanics_tracer/forms/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top bar with greeting and mobile menu button -->
        <div class="top-bar">
            <div class="greeting">
                <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($mechanic_name); ?> 👋</h1>
                <p>Welcome back! Set your status to appear on driver's maps.</p>
            </div>
            
            <div class="flex" style="gap:15px; align-items:center;">
                <!-- Refresh Button -->
                <button class="btn btn-secondary no-loader" onclick="reloadBookings()" title="Refresh Bookings" style="padding: 8px 12px; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-sync-alt"></i>
                </button>

                <!-- Availability Toggle -->
                <div class="status-toggle">
                    <span id="statusText" class="status-label <?php echo $mechanic['availability'] ? 'status-online' : 'status-offline'; ?>">
                        <?php echo $mechanic['availability'] ? 'ONLINE' : 'OFFLINE'; ?>
                    </span>
                    <label class="switch">
                        <input type="checkbox" id="availabilityToggle" <?php echo $mechanic['availability'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <button class="menu-toggle" id="menuToggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <!-- Clickable Stats Cards (now also navigation) -->
        <div class="stats-cards">
            <div class="card pending <?php echo ($pendingCount > 0 ? 'active' : ''); ?>" id="card-pending" onclick="showCardTab('pending')">
                <div class="card-left">
                    <h3><?php echo $pendingCount; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="card-right"><i class="fas fa-hourglass-half"></i></div>
            </div>
            <div class="card active-status" id="card-active" onclick="showCardTab('active')">
                <div class="card-left">
                    <h3><?php echo $activeCount; ?></h3>
                    <p>Active</p>
                </div>
                <div class="card-right"><i class="fas fa-bolt"></i></div>
            </div>
            <div class="card completed" id="card-completed" onclick="showCardTab('completed')">
                <div class="card-left">
                    <h3><?php echo $completedCount; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="card-right"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="card cancelled" id="card-cancelled" onclick="showCardTab('cancelled')">
                <div class="card-left">
                    <h3><?php echo $cancelledCount; ?></h3>
                    <p>Cancelled</p>
                </div>
                <div class="card-right"><i class="fas fa-times-circle"></i></div>
            </div>
        </div>

        <!-- Notification popup -->
        <div id="popup">Message</div>

        <div class="card" id="bookings-card" style="padding: 0; background: transparent; border: none; box-shadow: none;">
            <!-- Booking sections (bookings FIRST) -->
            <div id="bookings-section-wrapper" class="loading-container" style="min-height: 200px;">
                <div id="bookings" style="scroll-margin-top: 20px;">
                    <div id="pending" class="booking-section">
                        <?php
                        if(empty($pending)) echo "<div class='no-bookings'><i class='fas fa-inbox' style='font-size:2rem;margin-bottom:10px;display:block;'></i>No pending bookings.</div>";
                        else renderBooking($pending, $mechanic_lat, $mechanic_lng);
                        ?>
                    </div>
                    <div id="active" class="booking-section" style="display:none;">
                        <?php
                        if(empty($active)) echo "<div class='no-bookings'><i class='fas fa-inbox' style='font-size:2rem;margin-bottom:10px;display:block;'></i>No active bookings.</div>";
                        else renderBooking($active, $mechanic_lat, $mechanic_lng);
                        ?>
                    </div>
                    <div id="completed" class="booking-section" style="display:none;">
                        <?php
                        if(empty($completed)) echo "<div class='no-bookings'><i class='fas fa-inbox' style='font-size:2rem;margin-bottom:10px;display:block;'></i>No completed bookings.</div>";
                        else renderBooking($completed, $mechanic_lat, $mechanic_lng);
                        ?>
                    </div>
                    <div id="cancelled" class="booking-section" style="display:none;">
                        <?php
                        if(empty($cancelled)) echo "<div class='no-bookings'><i class='fas fa-inbox' style='font-size:2rem;margin-bottom:10px;display:block;'></i>No cancelled bookings.</div>";
                        else renderBooking($cancelled, $mechanic_lat, $mechanic_lng);
                        ?>
                    </div>
                </div><!-- /#bookings -->
            </div><!-- /#bookings-section-wrapper -->
        </div>

        <!-- Ratings summary (BELOW bookings) -->
        <?php if ($ratingSummary && (int)$ratingSummary['cnt'] > 0): ?>
        <div class="card" id="ratings" style="margin-top:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <h2 style="font-size:1.1rem;color:#0f172a;margin:0;">Your Ratings</h2>
                <a href="/mechanics_tracer/dashboard/mechanic_ratings.php" style="font-size:0.85rem;color:#3b82f6;text-decoration:none;">View all &rarr;</a>
            </div>
            <p style="margin-bottom:6px;">
                <span style="color:#fbbf24;font-size:1.1rem;">
                    <?php
                    $rounded = (int)round($ratingSummary['avg_stars']);
                    echo str_repeat('★', $rounded) . str_repeat('☆', 5 - $rounded);
                    ?>
                </span>
                <?php echo number_format((float)$ratingSummary['avg_stars'], 1); ?>
                <span style="color:#64748b;">(<?php echo (int)$ratingSummary['cnt']; ?> ratings)</span>
            </p>
            <?php if (!empty($recentRatings)): ?>
                <ul style="margin-top:8px;list-style:none;padding-left:0;">
                    <?php foreach ($recentRatings as $r): ?>
                        <li style="margin-bottom:6px;">
                            <strong><?php echo htmlspecialchars($r['driver_name']); ?></strong>
                            <span style="color:#fbbf24;margin-left:4px;"><?php echo str_repeat('★', (int)$r['stars']); ?></span>
                            <?php if (!empty($r['review'])): ?>
                                <br><span style="color:#4b5563;"><?php echo htmlspecialchars(mb_strimwidth($r['review'], 0, 80, '…')); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="card" id="ratings" style="margin-top:24px;padding:20px;text-align:center;color:#64748b;">
            <i class="fas fa-star" style="font-size:2rem;color:#e2e8f0;margin-bottom:10px;"></i>
            <p style="margin:0;">No ratings yet. Completed jobs will show up here.</p>
        </div>
        <?php endif; ?>
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

<!-- Leaflet & JavaScript -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
    // ---------- Availability Toggle ----------
    document.getElementById('availabilityToggle').addEventListener('change', function(e) {
        const isOnline = e.target.checked;
        const statusText = document.getElementById('statusText');
        
        statusText.textContent = isOnline ? 'ONLINE' : 'OFFLINE';
        statusText.className = 'status-label ' + (isOnline ? 'status-online' : 'status-offline');
        
        const formData = new FormData();
        formData.append('availability', isOnline ? 1 : 0);
        
        fetch('/mechanics_tracer/dashboard/api/mechanic_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Optional: show a small toast
                console.log('Status updated to ' + (isOnline ? 'online' : 'offline'));
            } else {
                alert('Failed to update status. Please try again.');
                // revert toggle
                e.target.checked = !isOnline;
                statusText.textContent = (!isOnline) ? 'ONLINE' : 'OFFLINE';
                statusText.className = 'status-label ' + ((!isOnline) ? 'status-online' : 'status-offline');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('A connection error occurred.');
            e.target.checked = !isOnline;
        });
    });

    // Initialize Chat
    MT_Chat.init(<?php echo $_SESSION['user_id']; ?>);



    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('menuToggle');
        if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('open');
        }
    });

    // ---------- Card-based navigation ----------
    function showCardTab(status) {
        // Hide all booking sections
        document.querySelectorAll('.booking-section').forEach(sec => sec.style.display = 'none');
        // Show selected section
        document.getElementById(status).style.display = 'block';
        
        // Remove active class from all cards
        document.querySelectorAll('.card').forEach(card => card.classList.remove('active'));
        // Add active class to the clicked card
        document.getElementById('card-' + status).classList.add('active');
    }

    // Initialize: if no card is active, activate the first one with content or default to pending
    window.addEventListener('load', function() {
        // Check if any card is already active (set by PHP if pending >0)
        let activeCard = document.querySelector('.card.active');
        if (!activeCard) {
            // Default to pending card if exists, else first card
            const pendingCard = document.getElementById('card-pending');
            if (pendingCard) {
                pendingCard.classList.add('active');
                showCardTab('pending');
            } else {
                // Fallback to first card
                const firstCard = document.querySelector('.card');
                if (firstCard) {
                    const firstStatus = firstCard.id.replace('card-', '');
                    firstCard.classList.add('active');
                    showCardTab(firstStatus);
                }
            }
        } else {
            // Ensure the correct section is shown based on the active card
            const activeId = activeCard.id;
            const status = activeId.replace('card-', '');
            showCardTab(status);
        }
    });

    // ---------- Map with fix (reuse instance) ----------
    let map = null;
    let mapInitialized = false;
    let mechMarker = null, driverMarker = null, routeLayer = null, currentInfoControl = null;

    // Open modal and show map with mechanic/driver markers
    async function openMapModal(mechLat, mechLng, driverLat, driverLng, driverName, vehicleInfo, serviceRequested, notes) {
        document.getElementById('mapTitle').textContent = driverName ? driverName + "'s Location" : "Driver Location";
        document.getElementById('mapModal').style.display = 'block';

        const validCoord = (v) => v !== null && v !== undefined && v !== '' && !isNaN(parseFloat(v)) && parseFloat(v) !== 0;
        if (!validCoord(driverLat) || !validCoord(driverLng)) {
            alert("Driver location not available.");
            return;
        }

        // If map not yet created, create it after modal is visible
        if (!mapInitialized) {
            setTimeout(() => {
                map = L.map('map').setView([(mechLat + driverLat) / 2, (mechLng + driverLng) / 2], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
                mapInitialized = true;
                // Now add markers and route
                updateMapContent(mechLat, mechLng, driverLat, driverLng, driverName, vehicleInfo, serviceRequested, notes);
            }, 200);
        } else {
            // Map already exists, just update content and ensure correct size
            setTimeout(() => {
                map.invalidateSize(); // critical after showing hidden container
                updateMapContent(mechLat, mechLng, driverLat, driverLng, driverName, vehicleInfo, serviceRequested, notes);
            }, 200);
        }
    }

    // Update markers and route on existing map
    async function updateMapContent(mechLat, mechLng, driverLat, driverLng, driverName, vehicleInfo, serviceRequested, notes) {
        // Clear previous layers
        if (mechMarker) map.removeLayer(mechMarker);
        if (driverMarker) map.removeLayer(driverMarker);
        if (routeLayer) map.removeLayer(routeLayer);
        if (currentInfoControl) map.removeControl(currentInfoControl);

        // Icons
        const mechanicIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        });
        const driverIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        });

        mechMarker = L.marker([mechLat, mechLng], { icon: mechanicIcon }).addTo(map);
        driverMarker = L.marker([driverLat, driverLng], { icon: driverIcon })
            .addTo(map)
            .bindPopup(`
                <div style="font-size:14px">
                    <strong>${driverName}</strong><br>
                    Vehicle: ${vehicleInfo || ''}<br>
                    Service: ${serviceRequested || ''}<br>
                    Notes: ${notes || ''}
                </div>
            `).openPopup();

        // Fetch shortest route
        const serviceUrl = `https://router.project-osrm.org/route/v1/driving/${mechLng},${mechLat};${driverLng},${driverLat}?alternatives=true&overview=full&geometries=geojson`;
        try {
            const resp = await fetch(serviceUrl);
            const data = await resp.json();
            if (!data || !data.routes || data.routes.length === 0) {
                const group = L.featureGroup([mechMarker, driverMarker]);
                map.fitBounds(group.getBounds().pad(0.3));
                alert('No route found between mechanic and driver.');
                return;
            }
            let best = data.routes.reduce((a, b) => a.distance <= b.distance ? a : b);
            routeLayer = L.geoJSON(best.geometry, { style: { color: '#2b8ae2', weight: 6 } }).addTo(map);
            const bounds = routeLayer.getBounds();
            bounds.extend(mechMarker.getLatLng());
            bounds.extend(driverMarker.getLatLng());
            map.fitBounds(bounds.pad(0.2));

            const distKm = (best.distance / 1000).toFixed(2);
            const durationMin = Math.round(best.duration / 60);
            const info = document.createElement('div');
            info.id = 'map-eta-overlay';
            info.style.position = 'absolute';
            info.style.top = '10px';
            info.style.left = '50%';
            info.style.transform = 'translateX(-50%)';
            info.style.zIndex = '1000';
            info.style.background = '#0f172a';
            info.style.color = '#fff';
            info.style.padding = '10px 20px';
            info.style.borderRadius = '99px';
            info.style.boxShadow = '0 4px 12px rgba(0,0,0,0.2)';
            info.style.display = 'flex';
            info.style.alignItems = 'center';
            info.style.gap = '15px';
            info.style.fontSize = '0.95rem';
            info.style.fontWeight = '600';
            info.style.pointerEvents = 'none';
            info.style.whiteSpace = 'nowrap';
            
            info.innerHTML = `
                <div style="display:flex; align-items:center; gap:6px;"><i class="fas fa-road" style="color:#94a3b8;"></i> ${distKm} km</div>
                <div style="width:1px; height:20px; background:rgba(255,255,255,0.2);"></div>
                <div style="display:flex; align-items:center; gap:6px;"><i class="fas fa-clock" style="color:#3b82f6;"></i> ETA: <span style="color:#3b82f6; font-size:1.1rem;">${durationMin} min</span></div>
            `;
            
            const mapContainer = document.getElementById('map');
            const oldInfo = document.getElementById('map-eta-overlay');
            if (oldInfo) oldInfo.remove();
            mapContainer.appendChild(info);
        } catch (err) {
            console.error('Routing failed', err);
            const group = L.featureGroup([mechMarker, driverMarker]);
            map.fitBounds(group.getBounds().pad(0.3));
            alert('Routing failed.');
        }
    }

    // Close modal (keep map instance)
    function closeMapModal() {
        document.getElementById('mapModal').style.display = 'none';
        // Optionally clear markers to save memory, but not required
    }

    // Close modal when clicking outside
    window.onclick = function (event) {
        if (event.target == document.getElementById('mapModal')) closeMapModal();
    }

    // ---------- Notification popup ----------
    function showPopup(message, success = true) {
        const popup = document.getElementById("popup");
        popup.innerText = message || '';
        popup.style.background = success ? '#10b981' : '#ef4444';
        popup.style.display = 'block';
        popup.style.opacity = '1';
        setTimeout(() => { popup.style.opacity = '0'; }, 2000);
        setTimeout(() => { popup.style.display = 'none'; popup.style.opacity = '1'; }, 2600);
    }

    // ---------- AJAX functions ----------
    async function postForm(url, params) {
        try {
            const body = new URLSearchParams(params).toString();
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body
            });
            return await res.json();
        } catch(e) {
            console.error('postForm error', e);
            return { status: 'error', message: 'Server or network error' };
        }
    }

    async function acceptBooking(id) {
        const data = await postForm('/mechanics_tracer/forms/bookings/accept_booking.php', { booking_id: id });
        showPopup(data.message, data.status === 'success');
        if(data.status === 'success') reloadBookings();
    }
    async function completeBooking(id) {
        const data = await postForm('/mechanics_tracer/forms/bookings/complete_booking.php', { booking_id: id });
        showPopup(data.message, data.status === 'success');
        if(data.status === 'success') reloadBookings();
    }
    async function cancelBooking(id) {
        if(!confirm('Cancel this booking?')) return;
        const data = await postForm('/mechanics_tracer/forms/bookings/cancel_booking.php', { booking_id: id });
        showPopup(data.message, data.status === 'success');
        if(data.status === 'success') reloadBookings();
    }

    // ---------- Reload bookings (updates cards and sections, preserves active card) ----------
    let reloadInProgress = false;
    function reloadBookings() {
        if(reloadInProgress) return;
        reloadInProgress = true;
        
        MT_Loader.showSection('bookings-section-wrapper');

        // Remember which card is active
        const activeCard = document.querySelector('.card.active');
        const activeStatus = activeCard ? activeCard.id.replace('card-', '') : 'pending';

        fetch(window.location.href, { cache: 'no-store' })
        .then(r => r.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // Update booking sections
            ['pending','active','completed','cancelled'].forEach(id => {
                const newSection = doc.getElementById(id);
                const curSection = document.getElementById(id);
                if(newSection && curSection) curSection.innerHTML = newSection.innerHTML;
            });

            // Update card counts and active state
            const pendingCount = (doc.querySelectorAll('#pending .booking') || []).length;
            const activeCount = (doc.querySelectorAll('#active .booking') || []).length;
            const completedCount = (doc.querySelectorAll('#completed .booking') || []).length;
            const cancelledCount = (doc.querySelectorAll('#cancelled .booking') || []).length;

            document.querySelector('#card-pending .card-left h3').textContent = pendingCount;
            document.querySelector('#card-active .card-left h3').textContent = activeCount;
            document.querySelector('#card-completed .card-left h3').textContent = completedCount;
            document.querySelector('#card-cancelled .card-left h3').textContent = cancelledCount;

            // Re-apply active class to the previously active card
            document.querySelectorAll('.card').forEach(card => card.classList.remove('active'));
            const cardToActivate = document.getElementById('card-' + activeStatus);
            if (cardToActivate) {
                cardToActivate.classList.add('active');
                // Show the corresponding section
                document.querySelectorAll('.booking-section').forEach(sec => sec.style.display = 'none');
                document.getElementById(activeStatus).style.display = 'block';
            } else {
                // Fallback
                document.getElementById('card-pending').classList.add('active');
                document.getElementById('pending').style.display = 'block';
            }

            MT_Loader.hideSection('bookings-section-wrapper');
            reloadInProgress = false;
        })
        .catch(err => {
            console.error('reloadBookings error', err);
            MT_Loader.hideSection('bookings-section-wrapper');
            reloadInProgress = false;
        });
    }

    // Auto refresh every 10 seconds
    setInterval(reloadBookings, 10000);
</script>

<?php
// Helper function to render a booking card (no phone number)
function renderBooking($bookings, $mechanic_lat, $mechanic_lng) {
    foreach($bookings as $b):
        $driver_lat = ($b['driver_latitude'] === null ? 'null' : $b['driver_latitude']);
        $driver_lng = ($b['driver_longitude'] === null ? 'null' : $b['driver_longitude']);
        $vehicle = trim(($b['driver_vehicle_make'] ?? '') . ' ' . ($b['driver_vehicle_model'] ?? ''));
        ?>
        <div class="booking" id="booking-<?php echo $b['id']; ?>">
            <div class="booking-info">
                <p><strong><i class="fas fa-user"></i> Driver</strong> <?php echo htmlspecialchars($b['driver_name']); ?></p>
                <p><strong><i class="fas fa-car"></i> Vehicle</strong> <?php echo htmlspecialchars($b['driver_vehicle_type'] . ' - ' . $vehicle); ?></p>
                <p><strong><i class="fas fa-tools"></i> Service</strong> <?php echo htmlspecialchars($b['service_requested']); ?></p>
                <p><strong><i class="fas fa-clock"></i> Booked</strong> <?php echo htmlspecialchars($b['created_at']); ?></p>
                <div class="status-badge">
                    <span class="status <?php echo $b['booking_status']; ?>"><?php echo ucfirst($b['booking_status']); ?></span>
                </div>
            </div>
            <div class="actions">
                <?php if($b['booking_status']=='pending'): ?>
                    <button class="accept" onclick="acceptBooking(<?php echo $b['id']; ?>)"><i class="fas fa-check"></i> Accept</button>
                    <button class="cancel" onclick="cancelBooking(<?php echo $b['id']; ?>)"><i class="fas fa-times"></i> Cancel</button>
                <?php elseif($b['booking_status']=='accepted'): ?>
                    <button class="complete" onclick="completeBooking(<?php echo $b['id']; ?>)"><i class="fas fa-flag-checkered"></i> Complete</button>
                    <button class="chat-icon" onclick="MT_Chat.open(<?php echo $b['id']; ?>, '<?php echo addslashes($b['driver_name']); ?>')"><i class="fas fa-comment"></i> Chat</button>
                <?php else: ?>
                    <button class="chat-icon" style="grid-column: span 2;" onclick="MT_Chat.open(<?php echo $b['id']; ?>, '<?php echo addslashes($b['driver_name']); ?>')"><i class="fas fa-comment"></i> Chat</button>
                <?php endif; ?>
                
                <button class="view-location" onclick="openMapModal(
                    <?php echo $mechanic_lat; ?>,
                    <?php echo $mechanic_lng; ?>,
                    <?php echo $driver_lat; ?>,
                    <?php echo $driver_lng; ?>,
                    '<?php echo htmlspecialchars($b['driver_name']); ?>',
                    '<?php echo htmlspecialchars($vehicle); ?>',
                    '<?php echo htmlspecialchars($b['service_requested']); ?>',
                    '<?php echo htmlspecialchars($b['notes']); ?>'
                )"><i class="fas fa-map-marker-alt"></i> View on Map</button>
            </div>
        </div>
    <?php
    endforeach;
}
?>

</body>
</html>