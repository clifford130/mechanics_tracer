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
$mechanic_lat = $mechanic['latitude'];
$mechanic_lng = $mechanic['longitude'];

// Fetch bookings related to this mechanic
$stmt = $conn->prepare("
    SELECT b.*, d.vehicle_type AS driver_vehicle_type, d.vehicle_make AS driver_vehicle_make, d.vehicle_model AS driver_vehicle_model, d.vehicle_year AS driver_vehicle_year, u.full_name AS driver_name, u.phone AS driver_phone
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

<!-- Leaflet + styles -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
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
.modal-content { background:#fff; margin:6% auto; padding:10px; border-radius:10px; max-width:900px; box-shadow:0 6px 18px rgba(0,0,0,0.2); }
.modal-header { display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #ddd; padding:10px 15px; }
.close { color:#aaa; font-size:28px; font-weight:bold; cursor:pointer; }
.close:hover { color:#000; }
#map { width:100%; height:500px; border-radius:8px; }
/* Popup */
#popup { position:fixed; top:20px; right:20px; background:#27ae60; color:white; padding:12px 20px; border-radius:6px; display:none; z-index:2000; font-weight:bold; box-shadow:0 6px 18px rgba(0,0,0,0.12); transition: opacity .4s ease; }
/* Responsive */
@media(max-width:900px){ #map{height:420px;} }
@media(max-width:600px){
    .booking { flex-direction:column; align-items:flex-start; }
    .actions { justify-content:flex-start; margin-top:10px; width:100%; gap:8px; }
    .tabs { flex-direction:column; }
}
</style>
</head>
<body>

<header>
    <h1><?php echo htmlspecialchars($mechanic['garage_name']); ?> Dashboard</h1>
    <a href="/mechanics_tracer/forms/auth/logout.php">Logout</a>
</header>

<div class="container">
    <div class="tabs">
        <div class="tab active" id="tab_pending" onclick="showTab('pending')">Pending (<?php echo count($pending); ?>)</div>
        <div class="tab" id="tab_active" onclick="showTab('active')">Active (<?php echo count($active); ?>)</div>
        <div class="tab" id="tab_completed" onclick="showTab('completed')">Completed (<?php echo count($completed); ?>)</div>
        <div class="tab" id="tab_cancelled" onclick="showTab('cancelled')">Cancelled (<?php echo count($cancelled); ?>)</div>
    </div>

<div id="popup">Message</div>

    <?php
    function renderBooking($bookings, $mechanic_lat, $mechanic_lng){
        foreach($bookings as $b):
            // ensure safe defaults
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
                        <!-- AJAX accept/cancel: no form to avoid page navigation -->
                        <button class="accept" onclick="acceptBooking(<?php echo $b['id']; ?>)">Accept</button>
                        <button class="cancel" onclick="cancelBooking(<?php echo $b['id']; ?>)">Cancel</button>
                    <?php elseif($b['booking_status']=='accepted'): ?>
                        <button class="complete" onclick="completeBooking(<?php echo $b['id']; ?>)">Complete</button>
                    <?php endif; ?>

                    <button class="chat-icon" title="Open chat">💬 Chat</button>

                    <!-- View location preserves your existing signature -->
                    <button class="view-location"
                        onclick="openMapModal(
                            <?php echo $mechanic_lat; ?>,
                            <?php echo $mechanic_lng; ?>,
                            <?php echo $driver_lat; ?>,
                            <?php echo $driver_lng; ?>,
                            '<?php echo htmlspecialchars($b['driver_name']); ?>',
                            '<?php echo htmlspecialchars($b['driver_phone']); ?>',
                            '<?php echo htmlspecialchars($vehicle); ?>',
                            '<?php echo htmlspecialchars($b['service_requested']); ?>',
                            '<?php echo htmlspecialchars($b['notes']); ?>'
                        )">🗺️ View on Map</button>
                </div>
            </div>
        <?php
        endforeach;
    }
    ?>

    <!-- Booking Sections -->

<div id="pending" class="booking-section">
<?php
if(empty($pending)){
    echo "<p>No pending bookings.</p>";
}else{
    renderBooking($pending,$mechanic_lat,$mechanic_lng);
}
?>
</div>

<div id="active" class="booking-section" style="display:none;">
<?php
if(empty($active)){
    echo "<p>No active bookings.</p>";
}else{
    renderBooking($active,$mechanic_lat,$mechanic_lng);
}
?>
</div>

<div id="completed" class="booking-section" style="display:none;">
<?php
if(empty($completed)){
    echo "<p>No completed bookings.</p>";
}else{
    renderBooking($completed,$mechanic_lat,$mechanic_lng);
}
?>
</div>

<div id="cancelled" class="booking-section" style="display:none;">
<?php
if(empty($cancelled)){
    echo "<p>No cancelled bookings.</p>";
}else{
    renderBooking($cancelled,$mechanic_lat,$mechanic_lng);
}
?>
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

<!-- Leaflet -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
/* --------- Tabs --------- */
function showTab(tabId){
    document.querySelectorAll('.booking-section').forEach(sec=>sec.style.display='none');
    document.getElementById(tabId).style.display='block';
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    const el = document.getElementById('tab_'+tabId);
    if(el) el.classList.add('active');
}

/* --------- Map + routing (kept compatible with your original) --------- */
let map = null;
let mechMarker = null;
let driverMarker = null;
let routeLayer = null;
let currentInfoControl = null;

function clearMapLayers(){
    try{
        if(routeLayer){ map.removeLayer(routeLayer); routeLayer = null; }
        if(mechMarker){ map.removeLayer(mechMarker); mechMarker = null; }
        if(driverMarker){ map.removeLayer(driverMarker); driverMarker = null; }
        if(currentInfoControl && map){ map.removeControl(currentInfoControl); currentInfoControl = null; }
    }catch(e){
        console.warn(e);
    }
}

/**
 * Opens modal and shows mechanic + driver markers and the shortest route (by distance)
 */
async function openMapModal(mechLat, mechLng, driverLat, driverLng, driverName, driverPhone, vehicleInfo, serviceRequested, notes){
    document.getElementById('mapTitle').textContent = driverName ? driverName + "'s Location" : "Driver Location";
    document.getElementById('mapModal').style.display = 'block';

    const validCoord = (v) => v !== null && v !== undefined && v !== '' && !isNaN(parseFloat(v)) && parseFloat(v) !== 0;

    if(!validCoord(driverLat) || !validCoord(driverLng)){
        alert("Driver location not available.");
        return;
    }

    setTimeout(async ()=>{

        // remove existing map instance (keeps behavior stable)
        if(map){
            map.remove();
            map = null;
        }
        clearMapLayers();

        map = L.map('map').setView([ (mechLat + driverLat)/2, (mechLng + driverLng)/2 ], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ maxZoom:19 }).addTo(map);

        const mechanicIcon = L.icon({
            iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl:'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            iconSize:[25,41], iconAnchor:[12,41], popupAnchor:[1,-34], shadowSize:[41,41]
        });

        const driverIcon = L.icon({
            iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
            shadowUrl:'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            iconSize:[25,41], iconAnchor:[12,41], popupAnchor:[1,-34], shadowSize:[41,41]
        });

        // Use global variables so clearMapLayers can remove them later
        mechMarker = L.marker([mechLat, mechLng], {icon: mechanicIcon}).addTo(map);
        driverMarker = L.marker([driverLat, driverLng], {icon: driverIcon})
            .addTo(map)
            .bindPopup(
                `<div style="font-size:14px"><strong>${driverName}</strong><br>Phone: ${driverPhone || ''}<br>Vehicle: ${vehicleInfo || ''}<br>Service: ${serviceRequested || ''}<br>Notes: ${notes || ''}</div>`
            ).openPopup();

        // ROUTE API - OSRM alternatives, pick shortest by distance
        const serviceUrl = `https://router.project-osrm.org/route/v1/driving/${mechLng},${mechLat};${driverLng},${driverLat}?alternatives=true&overview=full&geometries=geojson`;

        try{
            const resp = await fetch(serviceUrl);
            const data = await resp.json();

            if(!data || !data.routes || data.routes.length === 0){
                const group = L.featureGroup([mechMarker, driverMarker]);
                map.fitBounds(group.getBounds().pad(0.3));
                alert('No route found between mechanic and driver.');
                return;
            }

            // choose shortest route by distance
            let best = data.routes.reduce((a,b)=> a.distance <= b.distance ? a : b);
            routeLayer = L.geoJSON(best.geometry, { style: { color: '#2b8ae2', weight:6 } }).addTo(map);

            // fit to route + markers
            const bounds = routeLayer.getBounds();
            bounds.extend(mechMarker.getLatLng());
            bounds.extend(driverMarker.getLatLng());
            map.fitBounds(bounds.pad(0.2));

            // add info control and store reference
            const distKm = (best.distance/1000).toFixed(2);
            const durationMin = Math.round(best.duration/60);
            const info = L.control({position:'topright'});
            info.onAdd = function(){
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

        }catch(err){
            console.error('Routing failed', err);
            const group = L.featureGroup([mechMarker, driverMarker]);
            map.fitBounds(group.getBounds().pad(0.3));
            alert('Routing failed.');
        }

    },100);
}

function closeMapModal(){
    document.getElementById('mapModal').style.display='none';
    if(map){
        if(currentInfoControl && map) { map.removeControl(currentInfoControl); currentInfoControl = null; }
        map.remove();
        map = null;
    }
    clearMapLayers();
}

window.onclick = function(event){
    if(event.target == document.getElementById('mapModal')) closeMapModal();
}

/* --------- Small non-blocking popup that fades --------- */
function showPopup(message, success=true){
    const popup = document.getElementById("popup");
    popup.innerText = message || '';
    popup.style.background = success ? '#27ae60' : '#e74c3c';
    popup.style.display = 'block';
    popup.style.opacity = '1';
    // fade after 2s
    setTimeout(()=> { popup.style.opacity = '0'; }, 2000);
    // hide after fade
    setTimeout(()=> { popup.style.display = 'none'; popup.style.opacity = '1'; }, 2600);
}

/* --------- AJAX actions (assumes endpoints return JSON: {status:'success'|'error', message:'...'} ) --------- */
async function postForm(url, params){
    try{
        const body = new URLSearchParams(params).toString();
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        });
        return await res.json();
    }catch(e){
        console.error('postForm error', e);
        return { status: 'error', message: 'Server or network error' };
    }
}

async function acceptBooking(id){
    const data = await postForm('/mechanics_tracer/forms/bookings/accept_booking.php', { booking_id: id });
    showPopup(data.message, data.status === 'success');
    if(data.status === 'success') reloadBookings();
}

async function completeBooking(id){
    const data = await postForm('/mechanics_tracer/forms/bookings/complete_booking.php', { booking_id: id });
    showPopup(data.message, data.status === 'success');
    if(data.status === 'success') reloadBookings();
}

async function cancelBooking(id){
    if(!confirm('Cancel this booking?')) return;
    const data = await postForm('/mechanics_tracer/forms/bookings/cancel_booking.php', { booking_id: id });
    showPopup(data.message, data.status === 'success');
    if(data.status === 'success') reloadBookings();
}

/* --------- Reload only booking sections and update counters, preserving active tab --------- */
let reloadInProgress = false;
function reloadBookings(){
    if(reloadInProgress) return;
    reloadInProgress = true;

    // remember active tab (so we keep it visible after reload)
    const activeTabEl = document.querySelector('.tab.active');
    const activeTabId = activeTabEl ? activeTabEl.id : 'tab_pending'; // e.g. tab_pending

    fetch(window.location.href, { cache: 'no-store' })
    .then(r=>r.text())
    .then(html=>{
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // sections to replace
        const sections = ['pending','active','completed','cancelled'];
        sections.forEach(id=>{
            const newSection = doc.getElementById(id);
            const curSection = document.getElementById(id);
            if(newSection && curSection){
                curSection.innerHTML = newSection.innerHTML;
            }
        });

        // update counters from server DOM
        const pendingCount = (doc.querySelectorAll('#pending .booking') || []).length;
        const activeCount = (doc.querySelectorAll('#active .booking') || []).length;
        const completedCount = (doc.querySelectorAll('#completed .booking') || []).length;
        const cancelledCount = (doc.querySelectorAll('#cancelled .booking') || []).length;

        const tabPending = document.getElementById('tab_pending');
        const tabActive  = document.getElementById('tab_active');
        const tabCompleted = document.getElementById('tab_completed');
        const tabCancelled = document.getElementById('tab_cancelled');

        if(tabPending) tabPending.textContent = `Pending (${pendingCount})`;
        if(tabActive) tabActive.textContent = `Active (${activeCount})`;
        if(tabCompleted) tabCompleted.textContent = `Completed (${completedCount})`;
        if(tabCancelled) tabCancelled.textContent = `Cancelled (${cancelledCount})`;

        // reapply active tab class (preserve previously active)
        document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
        if(activeTabId){
            const keep = document.getElementById(activeTabId);
            if(keep) keep.classList.add('active');
            // show corresponding section
            const which = activeTabId.replace('tab_', '');
            document.querySelectorAll('.booking-section').forEach(s=>s.style.display='none');
            const sec = document.getElementById(which);
            if(sec) sec.style.display = 'block';
        }

        reloadInProgress = false;
    })
    .catch(err=>{
        console.error('reloadBookings error', err);
        reloadInProgress = false;
    });
}

// auto refresh every 10 seconds
setInterval(reloadBookings, 10000);

</script>

</body>
</html>