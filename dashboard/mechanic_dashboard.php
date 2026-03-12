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
    <title>Mechanic Dashboard | Mechanics Tracer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            grid-template-columns: repeat(4, 1fr);
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
        
        /* Booking cards */
        .booking {
            padding: 18px 20px;
            margin-bottom: 15px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid #e9edf2;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            transition: 0.2s;
        }
        .booking:hover { border-color: #cbd5e1; }
        .booking-info { flex: 1 1 60%; min-width: 250px; }
        .booking-info p { margin: 6px 0; color: #1e293b; font-size: 0.95rem; }
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
        
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
            flex: 1 1 30%;
            min-width: 200px;
        }
        button, .chat-icon, .view-location {
            padding: 8px 14px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.15s;
        }
        .accept { background: #10b981; color: white; }
        .complete { background: #3b82f6; color: white; }
        .cancel { background: #ef4444; color: white; }
        .chat-icon { background: #2563eb; color: white; }
        .view-location { background: #f59e0b; color: white; }
        button:hover, .chat-icon:hover, .view-location:hover { filter: brightness(0.9); transform: translateY(-1px); }
        
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
            <a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="bookings.php"><i class="fas fa-calendar-check"></i> Bookings</a>
            <a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a>
            <a href="settings.php"><i class="fas fa-sliders-h"></i> Settings</a>
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
                <p>Here's what's happening with your bookings today.</p>
            </div>
            <button class="menu-toggle" id="menuToggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
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

        <!-- Booking sections (no separate tabs) -->
        <div id="pending" class="booking-section">
            <?php
            if(empty($pending)) echo "<p>No pending bookings.</p>";
            else renderBooking($pending, $mechanic_lat, $mechanic_lng);
            ?>
        </div>
        <div id="active" class="booking-section" style="display:none;">
            <?php
            if(empty($active)) echo "<p>No active bookings.</p>";
            else renderBooking($active, $mechanic_lat, $mechanic_lng);
            ?>
        </div>
        <div id="completed" class="booking-section" style="display:none;">
            <?php
            if(empty($completed)) echo "<p>No completed bookings.</p>";
            else renderBooking($completed, $mechanic_lat, $mechanic_lng);
            ?>
        </div>
        <div id="cancelled" class="booking-section" style="display:none;">
            <?php
            if(empty($cancelled)) echo "<p>No cancelled bookings.</p>";
            else renderBooking($cancelled, $mechanic_lat, $mechanic_lng);
            ?>
        </div>
    </main>
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
            const info = L.control({ position: 'topright' });
            info.onAdd = function () {
                const d = L.DomUtil.create('div');
                d.style.background = 'rgba(255,255,255,0.95)';
                d.style.padding = '8px';
                d.style.borderRadius = '6px';
                d.style.boxShadow = '0 2px 6px rgba(0,0,0,0.12)';
                d.innerHTML = `<strong>Shortest route</strong><br>Distance: ${distKm} km<br>ETA: ${durationMin} min`;
                return d;
            };
            info.addTo(map);
            currentInfoControl = info;
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

            reloadInProgress = false;
        })
        .catch(err => {
            console.error('reloadBookings error', err);
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
                <p><strong>Driver:</strong> <?php echo htmlspecialchars($b['driver_name']); ?></p>
                <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($b['driver_vehicle_type'] . ' - ' . $vehicle); ?></p>
                <p><strong>Service:</strong> <?php echo htmlspecialchars($b['service_requested']); ?></p>
                <p><strong>Booked On:</strong> <?php echo htmlspecialchars($b['created_at']); ?></p>
                <p class="status <?php echo $b['booking_status']; ?>"><?php echo ucfirst($b['booking_status']); ?></p>
            </div>
            <div class="actions">
                <?php if($b['booking_status']=='pending'): ?>
                    <button class="accept" onclick="acceptBooking(<?php echo $b['id']; ?>)"><i class="fas fa-check"></i> Accept</button>
                    <button class="cancel" onclick="cancelBooking(<?php echo $b['id']; ?>)"><i class="fas fa-times"></i> Cancel</button>
                <?php elseif($b['booking_status']=='accepted'): ?>
                    <button class="complete" onclick="completeBooking(<?php echo $b['id']; ?>)"><i class="fas fa-flag-checkered"></i> Complete</button>
                <?php endif; ?>
                <button class="chat-icon" title="Chat with driver"><i class="fas fa-comment"></i></button>
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