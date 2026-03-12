<?php
session_start();
require_once("../forms/config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver'){
    header("Location: " . FORMS_URL . "auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = "Driver";

// Fetch driver name from users table
$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if($row = $result->fetch_assoc()){
    // Only take the first name
    $user_name = explode(" ", $row['full_name'])[0];
}

// Fetch mechanics from DB
$mechanics = [];
$sql = "SELECT id, garage_name, vehicle_types, services_offered, latitude, longitude, experience
        FROM mechanics";
$res = $conn->query($sql);
if($res){
    while($row = $res->fetch_assoc()){
        $row['latitude'] = floatval($row['latitude']);
        $row['longitude'] = floatval($row['longitude']);
        $mechanics[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Driver Dashboard</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif;}
body{background:#f4f6f8;display:flex;flex-direction:column;min-height:100vh;overflow-x:hidden;}

/* Accessibility helpers */
.sr-only{
    position:absolute;
    width:1px;
    height:1px;
    padding:0;
    margin:-1px;
    overflow:hidden;
    clip:rect(0,0,0,0);
    border:0;
}

/* Layout (match mechanic dashboard) */
.app-wrapper{display:flex;flex:1;}

/* Sidebar (match mechanic dashboard) */
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

/* Main content */
.main-content{
    flex:1;
    padding:24px 32px;
    overflow-y:auto;
    max-width:1400px;
    margin:0 auto;
    width:100%;
}

/* Top bar */
.top-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:15px;}
.greeting h1{font-size:1.8rem;color:#0f172a;font-weight:600;}
.greeting p{color:#475569;margin-top:4px;font-size:1rem;}
.menu-toggle{
    background:none;border:none;font-size:1.8rem;color:#1e293b;cursor:pointer;display:none;
}

/* Search pill (Uber‑style over map) */
.search-card{
    position:absolute;
    top:16px;
    left:50%;
    transform:translateX(-50%);
    z-index:500;
    background:white;
    border-radius:999px;
    padding:6px 10px;
    box-shadow:0 8px 24px rgba(15,23,42,0.25);
    border:1px solid rgba(148,163,184,0.5);
}
.booking-form{
    display:flex;
    align-items:center;
    gap:8px;
}
.booking-form select{
    padding:8px 12px;
    border-radius:999px;
    border:1px solid transparent;
    font-size:0.95rem;
    outline:none;
    background:#f1f5f9;
}
.booking-form select:focus{border-color:#1890ff;box-shadow:0 0 0 2px rgba(24,144,255,0.35);background:#ffffff;}
.booking-form button{
    padding:9px 14px;
    border-radius:999px;
    background:#111827;
    color:#f9fafb;
    border:none;
    cursor:pointer;
    font-weight:600;
    font-size:0.9rem;
    display:inline-flex;
    align-items:center;
    gap:6px;
}
.booking-form button:hover{filter:brightness(0.97);}

/* Map + bottom sheet results (Uber‑like) */
.map-wrapper{
    position:relative;
    border-radius:24px;
    overflow:hidden;
    box-shadow:0 10px 30px rgba(15,23,42,0.18);
    border:1px solid #e2e8f0;
    background:#020617;
}
#map{
    height:calc(100vh - 180px);
    min-height:420px;
    width:100%;
}
.results{
    position:absolute;
    left:0;
    right:0;
    bottom:0;
    max-height:45vh;
    background:rgba(248,250,252,0.98);
    backdrop-filter:blur(10px);
    border-radius:18px 18px 0 0;
    padding:12px 14px 10px;
    overflow-y:auto;
    display:flex;
    flex-direction:column;
    gap:10px;
}
.results::before{
    content:"";
    width:42px;
    height:4px;
    border-radius:999px;
    background:#cbd5f5;
    opacity:0.8;
    position:sticky;
    top:-4px;
    margin:0 auto 6px;
    display:block;
}
.result-card{
    background:white;
    padding:16px 16px;
    border-radius:16px;
    border:1px solid #e9edf2;
    box-shadow:0 2px 8px rgba(0,0,0,0.02);
}
.result-card.nearest{border-color:#1890ff;background:#f0f7ff;}
.meta{color:#0f172a;font-weight:700;margin-bottom:6px;}
.small{color:#475569;font-size:0.95rem;margin-top:4px;}
.result-actions{display:flex;gap:10px;margin-top:10px;}
.result-actions button{
    flex:1;
    padding:10px 12px;
    background:#1890ff;
    color:white;
    border:none;
    border-radius:999px;
    cursor:pointer;
    font-weight:600;
    font-size:0.9rem;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
}
.result-actions button.secondary{background:#0f172a;}
.result-actions button:hover{filter:brightness(0.95);transform:translateY(-1px);}

/* Responsive (match mechanic behavior) */
@media (max-width: 1024px){
    #map{height:calc(100vh - 160px);}
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
    .menu-toggle{display:block;}
    .main-content{padding:16px;}
    .search-card{
        top:14px;
        left:50%;
        transform:translateX(-50%);
        width:auto;
        max-width:92%;
    }
    .booking-form{flex-wrap:nowrap;}
    .booking-form select{max-width:170px;}
    .results{
        max-height:55vh;
        padding:10px 10px 8px;
    }
}
</style>
</head>
<body>
<div class="app-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <h2><i class="fas fa-car" style="margin-right: 8px;"></i><?php echo htmlspecialchars($user_name); ?></h2>
      <p id="driverGreetingSub">Find and book mechanics near you</p>
    </div>
    <nav class="nav-links">
      <a href="driver_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
      <a href="../forms/bookings/driver_bookings.php"><i class="fas fa-calendar-check"></i> My Bookings</a>
      <a href="rate_mechanic.php"><i class="fas fa-star"></i> Ratings</a>
    </nav>
    <div class="sidebar-footer">
      <a href="../forms/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <div class="top-bar">
      <div class="greeting">
        <h1 id="driverGreeting"></h1>
        <p>Choose a service, then see nearby mechanics with real driving ETAs.</p>
      </div>
      <button class="menu-toggle" id="menuToggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
        <i class="fas fa-bars"></i>
      </button>
    </div>

    <div class="map-wrapper">
      <!-- Uber‑style floating filter pill -->
      <div class="search-card">
        <form class="booking-form" onsubmit="return false;">
          <label for="serviceType" class="sr-only">Service type</label>
          <select id="serviceType" aria-label="Service type">
            <option value="">All services</option>
            <option value="engine">Engine</option>
            <option value="tyres">Tyres</option>
            <option value="battery">Battery</option>
            <option value="brakes">Brakes</option>
          </select>
          <button type="button" onclick="searchMechanics()">
            <i class="fas fa-sliders-h"></i> Filter
          </button>
        </form>
      </div>

      <div id="map" aria-label="Nearby mechanics map"></div>
      <div class="results" id="results" aria-label="Nearby mechanics list"></div>
    </div>
  </main>
</div>



<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>

    // ---------- smart greeting ----------
var driverName = "<?php echo htmlspecialchars($user_name); ?>";

function getGreeting(){
    var hour = new Date().getHours();

    if(hour < 12){
        return "Good morning";
    }
    else if(hour < 17){
        return "Good afternoon";
    }
    else{
        return "Good evening";
    }
}

function loadDriverGreeting(){
    var greeting = getGreeting();
    var el = document.getElementById("driverGreeting");
    if(el){
        el.textContent = greeting + ", " + driverName + " 👋";
    }
}

// run when page loads
loadDriverGreeting();

    // ---------- helper UI: resize map so it never disappears ----------
function resizeMap() {
  var header = document.querySelector('.header');
  var booking = document.querySelector('.booking-form');
  var mapEl = document.getElementById('map');
  var resultsEl = document.getElementById('results');

  var used = 0;
  if (header) used += header.getBoundingClientRect().height;
  if (booking) used += booking.getBoundingClientRect().height;
  // small padding
  used += 36;

  var h = window.innerHeight - used;
  if (h < 280) h = 280;
  mapEl.style.height = h + 'px';
  // make results max-height match map
  if (resultsEl) resultsEl.style.maxHeight = h + 'px';

  if (window._mapInstance) {
    window._mapInstance.invalidateSize();
  }
}
window.addEventListener('resize', resizeMap);
window.addEventListener('orientationchange', resizeMap);

// Close sidebar when clicking outside on mobile (match mechanic dashboard behavior)
document.addEventListener('click', function(event) {
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('menuToggle');
  if (!sidebar || !toggle) return;
  if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
    sidebar.classList.remove('open');
  }
});

// ---------- data from PHP ----------
var mechanics = <?php echo json_encode($mechanics); ?> || [];

// ---------- defaults ----------
var driverLat = -1.286389;
var driverLng = 36.817223;

// ---------- icons ----------
var driverIcon = L.icon({iconUrl:'https://cdn-icons-png.flaticon.com/512/684/684908.png',iconSize:[32,32],iconAnchor:[16,32]});
var mechanicIcon = L.icon({iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',iconSize:[25,41],iconAnchor:[12,41],popupAnchor:[1,-34]});

// ---------- map init ----------
var map = L.map('map', {preferCanvas:true}).setView([driverLat,driverLng],13);
window._mapInstance = map; // for resizeMap
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);

// ---------- state ----------
var driverMarker = null;
var routeLine = null;

// ---------- ETA formatting ----------
function formatETA(minutes){
    if (minutes === null || minutes === undefined || isNaN(minutes)) return '—';
    minutes = Math.round(Number(minutes) || 0);
    if (minutes < 60) return minutes + " min";
    var hrs = Math.floor(minutes / 60);
    var mins = minutes % 60;
    return mins === 0 ? hrs + " hr" : hrs + " hr " + mins + " min";
}

// ---------- speed model (derive walk/boda/matatu from driving distance) ----------
// Speeds in km/h – tweak to fit your real-world expectations
var WALK_SPEED = 4.5;    // average walking speed
var BODA_SPEED = 25;     // motorcycle/boda
var MATATU_SPEED = 20;   // public transport
var CAR_DEFAULT_SPEED = 35; // used only as a fallback if OSRM duration missing

function fillModeEtasFromDistance(mech){
    if (typeof mech.roadDistance !== 'number' || isNaN(mech.roadDistance)) {
        mech.etaFoot = mech.etaBoda = mech.etaMatatu = mech.roadDuration = null;
        return;
    }
    var d = mech.roadDistance; // km
    // if OSRM already provided a driving duration, keep it; otherwise derive one
    if (typeof mech.roadDuration !== 'number' || isNaN(mech.roadDuration)) {
        mech.roadDuration = (d / CAR_DEFAULT_SPEED) * 60;
    }
    mech.etaFoot   = (d / WALK_SPEED)   * 60;
    mech.etaBoda   = (d / BODA_SPEED)   * 60;
    mech.etaMatatu = (d / MATATU_SPEED) * 60;
}

// ---------- fetch best route (picks shortest-duration alternative) ----------
function fetchBestRoute(profile, overview, lng1, lat1, lng2, lat2) {
    var url = `https://router.project-osrm.org/route/v1/${profile}/${lng1},${lat1};${lng2},${lat2}?overview=${overview}&geometries=geojson&alternatives=true&annotations=duration,distance`;
    return fetch(url).then(res => res.json()).then(data => {
        if (!data || !data.routes || data.routes.length === 0) return null;
        // choose shortest-duration route (best for ETA). If you prefer shortest-distance, adjust here.
        var best = data.routes.reduce((min, r) => (r.duration < min.duration ? r : min), data.routes[0]);
        return best;
    }).catch(err => {
        console.warn("OSRM request failed for", profile, err);
        return null;
    });
}

// ---------- draw shortest driving route with full geometry ----------
// picks shortest-duration alternative (fetchBestRoute already chooses it)
function drawRoute(mech) {
    if (!mech) return;
    // clear previous
    if (routeLine) { try { map.removeLayer(routeLine); } catch (e) {} routeLine = null; }

    // If we already prefetched a full geometry earlier, use it.
    if (mech._driving_geometry && mech._driving_geometry.coordinates && mech._driving_geometry.coordinates.length) {
        var coords = mech._driving_geometry.coordinates.map(c => [c[1], c[0]]);
        routeLine = L.polyline(coords, { color:'#3498db', weight:5, opacity:0.95, lineJoin:'round' }).addTo(map);

        var carMinutes = mech._driving_best_summary ? mech._driving_best_summary.duration / 60 : mech.roadDuration;
        var etaText = `Walk: ${formatETA(mech.etaFoot)} | Boda: ${formatETA(mech.etaBoda)} | Matatu: ${formatETA(mech.etaMatatu)} | Car: ${formatETA(carMinutes)}`;
        var distKm = mech._driving_best_summary ? (mech._driving_best_summary.distance / 1000).toFixed(2) : (mech.roadDistance ? mech.roadDistance.toFixed(2) : '—');

        mech.marker.bindPopup(
            `<b>${mech.garage_name}</b><br>
             Services: ${mech.services_offered || ''}<br>
             Distance: ${distKm} km<br>
             ETA: ${etaText}<br>
             <div style="margin-top:8px"><button onclick="bookMechanic(${mech.id})">Book</button></div>`
        ).openPopup();

        try { map.fitBounds(routeLine.getBounds(), { padding:[60,60] }); } catch(e) { console.warn(e); }
        return;
    }

    // otherwise fetch full driving geometry (alternatives=true) and pick shortest-duration route
    return fetchBestRoute('driving', 'full', driverLng, driverLat, mech.longitude, mech.latitude)
    .then(best => {
        if (!best) {
            // fallback: show popup with mode ETAs already computed
            var etaTextFallback = `Walk: ${formatETA(mech.etaFoot)} | Boda: ${formatETA(mech.etaBoda)} | Matatu: ${formatETA(mech.etaMatatu)} | Car: ${formatETA(mech.roadDuration)}`;
            mech.marker.bindPopup(`<b>${mech.garage_name}</b><br>Services: ${mech.services_offered || ''}<br>Distance: —<br>ETA: ${etaTextFallback}<br><div style="margin-top:8px"><button onclick="bookMechanic(${mech.id})">Book</button></div>`).openPopup();
            return;
        }

        // store geometry + summary for reuse
        mech._driving_geometry = best.geometry;
        mech._driving_best_summary = { distance: best.distance, duration: best.duration };
        mech.roadDistance = best.distance / 1000;
        mech.roadDuration = best.duration / 60;

        // matatu approx 15% slower than car (adjust multiplier if desired)
        mech.etaMatatu = mech.roadDuration * 1.15;

        var coords = best.geometry.coordinates.map(c => [c[1], c[0]]);
        routeLine = L.polyline(coords, { color:'#3498db', weight:5, opacity:0.95, lineJoin:'round' }).addTo(map);

        var carMinutes = best.duration / 60;
        var etaText = `Walk: ${formatETA(mech.etaFoot)} | Boda: ${formatETA(mech.etaBoda)} | Matatu: ${formatETA(mech.etaMatatu)} | Car: ${formatETA(carMinutes)}`;
        var distKm = (best.distance / 1000).toFixed(2);

        mech.marker.bindPopup(
            `<b>${mech.garage_name}</b><br>
             Services: ${mech.services_offered || ''}<br>
             Distance: ${distKm} km<br>
             ETA: ${etaText}<br>
             <div style="margin-top:8px"><button onclick="bookMechanic(${mech.id})">Book</button></div>`
        ).openPopup();

        try { map.fitBounds(routeLine.getBounds(), { padding:[60,60] }); } catch(e) { console.warn(e); }
    }).catch(err => {
        console.warn('drawRoute error', err);
        var etaTextFallback = `Walk: ${formatETA(mech.etaFoot)} | Boda: ${formatETA(mech.etaBoda)} | Matatu: ${formatETA(mech.etaMatatu)} | Car: ${formatETA(mech.roadDuration)}`;
        mech.marker.bindPopup(`<b>${mech.garage_name}</b><br>Services: ${mech.services_offered || ''}<br>Distance: —<br>ETA: ${etaTextFallback}<br><div style="margin-top:8px"><button onclick="bookMechanic(${mech.id})">Book</button></div>`).openPopup();
    });
}

// ---------- render results, mark nearest + update map ----------
function renderResults(filterService = '') {
    var container = document.getElementById('results');
    container.innerHTML = '';

    var filtered = mechanics.filter(m => {
        var svc = (m.services_offered || '').toLowerCase();
        return (filterService === '' || svc.includes(filterService));
    });

    // sort by driving distance (nulls go last)
    filtered.sort((a,b) => (a.roadDistance == null ? 9999 : a.roadDistance) - (b.roadDistance == null ? 9999 : b.roadDistance));

    // Update marker visibility / emphasis on the map based on filter
    var filteredIds = new Set(filtered.map(m => String(m.id)));
    mechanics.forEach(m => {
        if (!m.marker) return;
        if (filteredIds.size === 0 || filteredIds.has(String(m.id))) {
            // fully visible when matches filter (or when no filter applied)
            m.marker.setOpacity(1);
        } else {
            // de‑emphasise non‑matching mechanics
            m.marker.setOpacity(0.2);
        }
    });

    if (filtered.length === 0) {
        container.innerHTML = "<div class='result-card'><div class='meta'>No mechanics found</div></div>";
        return;
    }

    var nearestId = filtered[0].id;

    filtered.forEach(m => {
        var card = document.createElement('div');
        card.className = 'result-card' + (m.id === nearestId ? ' nearest' : '');
        card.setAttribute('data-mech-id', m.id);

        // IMPORTANT: do NOT force fallback zeros with "|| 0" here — show '—' if missing so differences are visible
        var etaText = `Walk: ${formatETA(m.etaFoot)} | Boda: ${formatETA(m.etaBoda)} | Matatu: ${formatETA(m.etaMatatu)} | Car: ${formatETA(m.roadDuration)}`;
        var distanceText = (m.roadDistance != null) ? (m.roadDistance.toFixed(2) + ' km') : '—';

        card.innerHTML = `
            <div class="meta"><strong>${m.garage_name}</strong></div>
            <div class="small">${m.services_offered || ''}</div>
            <div class="small">Experience: ${m.experience || 0} yrs</div>
            <div class="small">Distance: ${distanceText}</div>
            <div class="small">ETA: ${etaText}</div>
            <div class="result-actions">
              <button class="secondary" onclick="focusMechanic(${m.id})"><i class="fas fa-route"></i> View</button>
              <button onclick="bookMechanic(${m.id})"><i class="fas fa-calendar-check"></i> Book</button>
            </div>
        `;
        container.appendChild(card);
    });

    // scroll nearest into view for better UX
    setTimeout(() => {
      var nearestEl = container.querySelector('.result-card.nearest');
      if (nearestEl) nearestEl.scrollIntoView({behavior:'smooth', block:'center'});
    }, 120);

    // automatically focus the nearest mechanic from the filtered list on the map
    if (nearestId != null) {
        focusMechanic(nearestId);
    }
}

// ---------- focus + booking ----------
function focusMechanic(id) {
    var mech = mechanics.find(x => x.id == id);
    if (!mech) return;
    // center then draw
    map.setView([mech.latitude, mech.longitude], 15);
    // ensure we draw using the best/full geometry if available
    drawRoute(mech);
}

// booking mechanic (redirect to booking page to keep flow consistent)
function bookMechanic(id){
    window.location.href = "/mechanics_tracer/forms/bookings/book_mechanic.php?mechanic_id=" + id;
}

// ---------- place markers and fetch mode routes intelligently ----------
function loadMechanics() {
    // clear any existing markers (if reloading)
    mechanics.forEach(m => {
        m.latitude = Number(m.latitude);
        m.longitude = Number(m.longitude);
        if (!m.latitude || !m.longitude) return;
        if (m.marker) {
          try { map.removeLayer(m.marker); } catch(e){ }
        }
        // create marker
        var marker = L.marker([m.latitude, m.longitude], { icon: mechanicIcon }).addTo(map);
        marker.bindPopup(`<b>${m.garage_name}</b><br>Services: ${m.services_offered || ''}<br>Experience: ${m.experience || 0} yrs`);
        m.marker = marker;
    });

    // For each mechanic perform one driving-profile request in parallel (we derive walk/boda/matatu from distance),
    // but batch mechanics to avoid overwhelming OSRM.
    var concurrency = 5;
    var queue = mechanics.slice().filter(m => m.latitude && m.longitude);

    function worker() {
        if (queue.length === 0) return Promise.resolve();
        var batch = queue.splice(0, concurrency);
        return Promise.all(batch.map(m => {
            return fetchBestRoute('driving','false', driverLng, driverLat, m.longitude, m.latitude)
                .then(best => {
                    if (best) {
                        m.roadDistance = best.distance / 1000; // km
                        m.roadDuration = best.duration / 60;   // minutes
                        m._driving_best_summary = { distance: best.distance, duration: best.duration };
                        fillModeEtasFromDistance(m);
                    } else {
                        m.roadDistance = null;
                        m.roadDuration = null;
                        m._driving_best_summary = null;
                        fillModeEtasFromDistance(m);
                    }
                    return m;
                }).catch(() => {
                    m.roadDistance = null;
                    m.roadDuration = null;
                    m._driving_best_summary = null;
                    fillModeEtasFromDistance(m);
                    return m;
                });
        })).then(() => worker());
    }

    // run workers
    return worker().then(() => {
        // all mechanics loaded with their ETA/distance
        // automatically pick nearest by driving distance
        var candidates = mechanics.filter(m => typeof m.roadDistance === 'number' && !isNaN(m.roadDistance));
        candidates.sort((a,b) => a.roadDistance - b.roadDistance);
        var nearest = candidates[0];
        renderResults();
        if (nearest) {
            // optionally prefetch full driving geometry for the nearest mechanic for fastest draw
            fetchBestRoute('driving','full', driverLng, driverLat, nearest.longitude, nearest.latitude)
              .then(fullBest => {
                  if (fullBest) {
                      nearest._driving_geometry = fullBest.geometry;
                      nearest._driving_best_summary = { distance: fullBest.distance, duration: fullBest.duration };
                      nearest.roadDistance = fullBest.distance / 1000;
                      nearest.roadDuration = fullBest.duration / 60;
                      nearest.etaMatatu = nearest.roadDuration * 1.15;
                  }
                  drawRoute(nearest);
              }).catch(()=> drawRoute(nearest));
        } else {
            // if no driving routes, just center map on driver
            map.setView([driverLat, driverLng], 13);
        }
    }).catch(err => {
        console.warn("Error loading mechanics", err);
        renderResults();
    });
}

// ---------- geolocation ----------
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
        driverLat = pos.coords.latitude;
        driverLng = pos.coords.longitude;
        if (driverMarker) map.removeLayer(driverMarker);
        driverMarker = L.marker([driverLat, driverLng], { icon: driverIcon }).addTo(map).bindPopup("📍 You are here").openPopup();
        map.setView([driverLat, driverLng], 13);
        resizeMap();
        loadMechanics();
    }, function(err) {
        // fallback to defaults
        driverMarker = L.marker([driverLat, driverLng], { icon: driverIcon }).addTo(map);
        resizeMap();
        loadMechanics();
    }, { enableHighAccuracy: true, timeout: 5000 });
} else {
    resizeMap();
    loadMechanics();
}

// ---------- search ----------
function searchMechanics() {
    var service = document.getElementById("serviceType").value.toLowerCase();
    renderResults(service);
}

// initial layout fix
setTimeout(function(){ resizeMap(); }, 300);


// styling markers

</script>
</body>
</html>