<?php
session_start();
require_once("../forms/config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver'){
    header("Location: " . FORMS_URL . "auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = "Driver";

$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if($row = $result->fetch_assoc()){
    $user_name = explode(" ", $row['full_name'])[0];
}

// ----- Filtering logic (unchanged) -----
$selected_services = isset($_GET['services']) ? (array)$_GET['services'] : [];
$mechanics = [];
$show_all_when_no_filter = true;

if (!empty($selected_services)) {
    $selected_ids = array_filter(array_map('intval', $selected_services), function($id) { return $id > 0; });

    if (!empty($selected_ids)) {
        $conditions = [];
        $params = [];
        $types = '';
        foreach ($selected_ids as $sid) {
            $conditions[] = "JSON_CONTAINS(service_ids, ?)";
            $params[] = json_encode($sid);
            $types .= 's';
        }
        $where = implode(' OR ', $conditions);

        $sql = "SELECT id, garage_name, vehicle_types, services_offered, 
                       latitude, longitude, experience, service_ids
                FROM mechanics
                WHERE $where";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $row['latitude'] = floatval($row['latitude']);
            $row['longitude'] = floatval($row['longitude']);
            $mechanic_services = json_decode($row['service_ids'] ?? '[]', true) ?: [];
            $row['match_count'] = count(array_intersect($selected_ids, $mechanic_services));
            $mechanics[] = $row;
        }

        usort($mechanics, function($a, $b) {
            if ($a['match_count'] == $b['match_count']) return 0;
            return ($a['match_count'] > $b['match_count']) ? -1 : 1;
        });
    }
} elseif ($show_all_when_no_filter) {
    $sql = "SELECT id, garage_name, vehicle_types, services_offered, 
                   latitude, longitude, experience, service_ids
            FROM mechanics";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $row['latitude'] = floatval($row['latitude']);
        $row['longitude'] = floatval($row['longitude']);
        $mechanics[] = $row;
    }
}

$servicesByCat = [];
$catRes = $conn->query("SELECT category, id, service_name FROM services ORDER BY category, service_name");
while ($row = $catRes->fetch_assoc()) {
    $servicesByCat[$row['category']][] = $row;
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
/* ===== RESET & GLOBAL ===== */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif;}
body{background:#f4f6f8;display:flex;flex-direction:column;min-height:100vh;overflow-x:hidden;}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;}

/* ===== SIDEBAR (unchanged) ===== */
.app-wrapper{display:flex;flex:1;}
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

/* ===== MAIN CONTENT ===== */
.main-content{
    flex:1;
    padding:24px 32px;
    overflow-y:auto;
    max-width:1400px;
    margin:0 auto;
    width:100%;
}

/* ----- Top bar: greeting left, filter button right ----- */
.top-bar{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:16px;
    flex-wrap:wrap;
    gap:10px;
}
.greeting h1{
    font-size:1.8rem;
    color:#0f172a;
    font-weight:600;
    line-height:1.2;
}
.greeting p{
    color:#475569;
    margin-top:2px;
    font-size:0.95rem;
}
.menu-toggle{
    background:none;
    border:none;
    font-size:1.8rem;
    color:#1e293b;
    cursor:pointer;
    display:none;
}

/* Filter button */
.filter-btn {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 10px 20px;
    font-size: 1rem;
    font-weight: 600;
    color: #0f172a;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 8px;
}
.filter-btn i {
    color: #1890ff;
}
.filter-btn:hover {
    background: #f8fafc;
}

/* Filter modal (unchanged) */
.filter-modal {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    align-items: center;
    justify-content: center;
}
.filter-modal.active {
    display: flex;
}
.filter-modal-content {
    background: white;
    border-radius: 24px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    padding: 24px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}
.filter-modal-content h3 {
    margin-bottom: 20px;
    font-size: 1.4rem;
}
.filter-categories {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px,1fr));
    gap: 15px;
    margin-bottom: 20px;
}
.filter-category {
    background: #f8fafc;
    padding: 12px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}
.filter-category strong {
    display: block;
    margin-bottom: 8px;
    color: #0f172a;
}
.filter-category label {
    display: block;
    font-size: 0.9rem;
    margin: 5px 0;
}
.filter-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 20px;
}
.filter-actions button, .filter-actions a {
    padding: 10px 20px;
    border-radius: 999px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
}
.filter-actions .apply {
    background: #0f172a;
    color: white;
}
.filter-actions .clear {
    background: #e2e8f0;
    color: #0f172a;
}
.filter-actions .cancel {
    background: transparent;
    border: 1px solid #cbd5e1;
    color: #475569;
}

/* ===== MAP (full height, no results panel) ===== */
.map-wrapper{
    position:relative;
    border-radius:24px;
    overflow:hidden;
    box-shadow:0 10px 30px rgba(15,23,42,0.18);
    border:1px solid #e2e8f0;
    background:#020617;
}
#map{
    height:calc(100vh - 140px); /* more space because no results panel */
    min-height:500px;
    width:100%;
    z-index:1;
}

/* Simple instruction overlay (optional) */
.map-instruction {
    position: absolute;
    top: 20px;
    left: 20px;
    background: white;
    padding: 8px 16px;
    border-radius: 999px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    font-size: 0.9rem;
    color: #0f172a;
    z-index: 10;
    pointer-events: none;
    border: 1px solid #e2e8f0;
}
.map-instruction i {
    color: #1890ff;
    margin-right: 6px;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px){
    #map{height:calc(100vh - 120px);}
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
    .top-bar{margin-bottom:12px;}
    .greeting h1{font-size:1.5rem;}
    #map{height:calc(100vh - 100px);}
    .map-instruction{top:10px; left:10px; font-size:0.8rem;}
}
</style>
</head>
<body>
<div class="app-wrapper">
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

  <main class="main-content">
    <!-- Top bar: greeting left, filter button right -->
    <div class="top-bar">
      <div class="greeting">
        <h1 id="driverGreeting"></h1>
        <p>Click a marker to see details and book.</p>
      </div>
      <div>
        <button class="filter-btn" id="filterBtn"><i class="fas fa-sliders-h"></i> Filter by services</button>
      </div>
      <button class="menu-toggle" id="menuToggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
        <i class="fas fa-bars"></i>
      </button>
    </div>

    <!-- Filter modal -->
    <div class="filter-modal" id="filterModal">
      <div class="filter-modal-content">
        <h3>Select required services</h3>
        <form method="GET" action="driver_dashboard.php" id="filterForm">
          <div class="filter-categories">
            <?php foreach ($servicesByCat as $cat => $services): ?>
              <div class="filter-category">
                <strong><?php echo htmlspecialchars($cat); ?></strong>
                <?php foreach ($services as $svc): ?>
                  <label>
                    <input type="checkbox" name="services[]" value="<?php echo $svc['id']; ?>"
                      <?php echo in_array($svc['id'], $selected_services) ? 'checked' : ''; ?>>
                    <?php echo htmlspecialchars($svc['service_name']); ?>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="filter-actions">
            <button type="submit" class="apply">Apply Filter</button>
            <a href="driver_dashboard.php" class="clear">Clear</a>
            <button type="button" class="cancel" onclick="closeFilterModal()">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Map with instruction overlay -->
    <div class="map-wrapper">
      <div class="map-instruction"><i class="fas fa-hand-pointer"></i> Click a marker to view & book</div>
      <div id="map" aria-label="Nearby mechanics map"></div>
    </div>
  </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // ----- GREETING -----
    var driverName = "<?php echo htmlspecialchars($user_name); ?>";
    function getGreeting(){
        var hour = new Date().getHours();
        if(hour < 12) return "Good morning";
        else if(hour < 17) return "Good afternoon";
        else return "Good evening";
    }
    function loadDriverGreeting(){
        var greeting = getGreeting();
        var el = document.getElementById("driverGreeting");
        if(el) el.textContent = greeting + ", " + driverName + " 👋";
    }
    loadDriverGreeting();

    // ----- FILTER MODAL -----
    const filterBtn = document.getElementById('filterBtn');
    const filterModal = document.getElementById('filterModal');
    function openFilterModal() { filterModal.classList.add('active'); }
    function closeFilterModal() { filterModal.classList.remove('active'); }
    filterBtn.addEventListener('click', openFilterModal);
    filterModal.addEventListener('click', function(e) {
        if (e.target === filterModal) closeFilterModal();
    });

    // ----- MAP RESIZE -----
    function resizeMap() {
        var mapEl = document.getElementById('map');
        // Calculate height based on window minus top bar (approx 80px)
        var used = 80;
        var h = window.innerHeight - used;
        if (h < 500) h = 500;
        mapEl.style.height = h + 'px';
        if (window._mapInstance) window._mapInstance.invalidateSize();
    }
    window.addEventListener('resize', resizeMap);
    window.addEventListener('orientationchange', resizeMap);

    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('menuToggle');
        if (!sidebar || !toggle) return;
        if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('open');
        }
    });

    // ----- MECHANICS DATA -----
    var mechanics = <?php echo json_encode($mechanics); ?> || [];
    console.log('Mechanics loaded:', mechanics.length);

    // ----- MAP INIT -----
    var driverLat = -1.286389;
    var driverLng = 36.817223;
    var driverIcon = L.icon({iconUrl:'https://cdn-icons-png.flaticon.com/512/684/684908.png',iconSize:[32,32],iconAnchor:[16,32]});
    var mechanicIcon = L.icon({iconUrl:'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',iconSize:[25,41],iconAnchor:[12,41],popupAnchor:[1,-34]});

    var map = L.map('map', {preferCanvas:true}).setView([driverLat,driverLng],13);
    window._mapInstance = map;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);

    var driverMarker = null;
    var routeLine = null;
    var mechanicsLoaded = false;

    // ----- FIT MAP ONCE -----
    function fitMapOnce() {
        if (!mechanicsLoaded) return;
        setTimeout(function() {
            var markers = [];
            if (driverMarker) markers.push(driverMarker);
            mechanics.forEach(m => { if (m.marker) markers.push(m.marker); });
            if (markers.length > 0) {
                var group = L.featureGroup(markers);
                var padding = window.innerWidth < 768 ? [70, 70] : [50, 50];
                map.fitBounds(group.getBounds(), { padding: padding, maxZoom: 15 });
            } else {
                map.setView([driverLat, driverLng], 13);
            }
        }, 400);
    }

    // ----- ETA & ROUTING -----
    function formatETA(minutes){
        if (minutes === null || minutes === undefined || isNaN(minutes)) return '—';
        minutes = Math.round(Number(minutes) || 0);
        if (minutes < 60) return minutes + " min";
        var hrs = Math.floor(minutes / 60);
        var mins = minutes % 60;
        return mins === 0 ? hrs + " hr" : hrs + " hr " + mins + " min";
    }

    var WALK_SPEED = 4.5, BODA_SPEED = 25, MATATU_SPEED = 20, CAR_DEFAULT_SPEED = 35;
    function fillModeEtasFromDistance(mech){
        if (typeof mech.roadDistance !== 'number' || isNaN(mech.roadDistance)) {
            mech.etaFoot = mech.etaBoda = mech.etaMatatu = mech.roadDuration = null;
            return;
        }
        var d = mech.roadDistance;
        if (typeof mech.roadDuration !== 'number' || isNaN(mech.roadDuration)) {
            mech.roadDuration = (d / CAR_DEFAULT_SPEED) * 60;
        }
        mech.etaFoot   = (d / WALK_SPEED)   * 60;
        mech.etaBoda   = (d / BODA_SPEED)   * 60;
        mech.etaMatatu = (d / MATATU_SPEED) * 60;
    }

    function fetchBestRoute(profile, overview, lng1, lat1, lng2, lat2) {
        var url = `https://router.project-osrm.org/route/v1/${profile}/${lng1},${lat1};${lng2},${lat2}?overview=${overview}&geometries=geojson&alternatives=true&annotations=duration,distance`;
        return fetch(url).then(res => res.json()).then(data => {
            if (!data || !data.routes || data.routes.length === 0) return null;
            var best = data.routes.reduce((min, r) => (r.duration < min.duration ? r : min), data.routes[0]);
            return best;
        }).catch(err => { console.warn("OSRM error", err); return null; });
    }

    function updatePopupWithRoute(mech, best) {
        if (best) {
            mech._driving_geometry = best.geometry;
            mech._driving_best_summary = { distance: best.distance, duration: best.duration };
            mech.roadDistance = best.distance / 1000;
            mech.roadDuration = best.duration / 60;
            mech.etaMatatu = mech.roadDuration * 1.15;
        }
        var etaText = `Walk: ${formatETA(mech.etaFoot)} | Boda: ${formatETA(mech.etaBoda)} | Matatu: ${formatETA(mech.etaMatatu)} | Car: ${formatETA(mech.roadDuration)}`;
        var distKm = mech.roadDistance ? mech.roadDistance.toFixed(2) : '—';
        var popupContent = `<b>${mech.garage_name}</b><br>Services: ${mech.services_offered || ''}<br>Distance: ${distKm} km<br>ETA: ${etaText}<br><div style="margin-top:8px"><button onclick="bookMechanic(${mech.id})" style="background:#1890ff; color:white; border:none; padding:8px 16px; border-radius:999px; cursor:pointer; font-weight:600;">Book Now</button></div>`;
        mech.marker.bindPopup(popupContent);
    }

    function drawRoute(mech) {
        if (!mech) return;
        if (routeLine) { try { map.removeLayer(routeLine); } catch (e) {} routeLine = null; }

        if (!mech.marker) {
            console.warn('Marker missing for mechanic', mech.id);
            return;
        }

        if (mech._driving_geometry && mech._driving_geometry.coordinates && mech._driving_geometry.coordinates.length) {
            var coords = mech._driving_geometry.coordinates.map(c => [c[1], c[0]]);
            routeLine = L.polyline(coords, { color:'#3498db', weight:5, opacity:0.95, lineJoin:'round' }).addTo(map);
            updatePopupWithRoute(mech, null);
            map.fitBounds(routeLine.getBounds(), { padding:[60,60] });
            return;
        }

        return fetchBestRoute('driving','full', driverLng, driverLat, mech.longitude, mech.latitude).then(best => {
            if (best) {
                var coords = best.geometry.coordinates.map(c => [c[1], c[0]]);
                routeLine = L.polyline(coords, { color:'#3498db', weight:5, opacity:0.95, lineJoin:'round' }).addTo(map);
                updatePopupWithRoute(mech, best);
                map.fitBounds(routeLine.getBounds(), { padding:[60,60] });
            } else {
                updatePopupWithRoute(mech, null);
            }
        }).catch(err => {
            console.warn(err);
            updatePopupWithRoute(mech, null);
        });
    }

    function bookMechanic(id){
        window.location.href = "/mechanics_tracer/forms/bookings/book_mechanic.php?mechanic_id=" + id;
    }

    // ----- LOAD MECHANICS -----
    function loadMechanics() {
        mechanics.forEach(m => {
            m.latitude = Number(m.latitude);
            m.longitude = Number(m.longitude);
            if (!m.latitude || !m.longitude) return;
            if (m.marker) try { map.removeLayer(m.marker); } catch(e){}
            var marker = L.marker([m.latitude, m.longitude], { icon: mechanicIcon }).addTo(map);
            marker.bindPopup(`<b>${m.garage_name}</b><br>Services: ${m.services_offered || ''}<br><i>Loading ETA...</i>`);
            m.marker = marker;
        });

        var queue = mechanics.slice().filter(m => m.latitude && m.longitude);
        var concurrency = 5;
        function worker() {
            if (queue.length === 0) return Promise.resolve();
            var batch = queue.splice(0, concurrency);
            return Promise.all(batch.map(m => {
                return fetchBestRoute('driving','false', driverLng, driverLat, m.longitude, m.latitude)
                    .then(best => {
                        if (best) {
                            m.roadDistance = best.distance / 1000;
                            m.roadDuration = best.duration / 60;
                            m._driving_best_summary = { distance: best.distance, duration: best.duration };
                        } else {
                            m.roadDistance = null; m.roadDuration = null; m._driving_best_summary = null;
                        }
                        fillModeEtasFromDistance(m);
                        updatePopupWithRoute(m, null);
                        return m;
                    }).catch(() => {
                        m.roadDistance = null; m.roadDuration = null; m._driving_best_summary = null;
                        fillModeEtasFromDistance(m);
                        updatePopupWithRoute(m, null);
                        return m;
                    });
            })).then(() => worker());
        }
        return worker().then(() => {
            mechanicsLoaded = true;
            fitMapOnce();
        }).catch(err => {
            console.warn("Error loading mechanics", err);
            mechanicsLoaded = true;
            fitMapOnce();
        });
    }

    // ----- GEOLOCATION -----
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            driverLat = pos.coords.latitude;
            driverLng = pos.coords.longitude;
            if (driverMarker) map.removeLayer(driverMarker);
            driverMarker = L.marker([driverLat, driverLng], { icon: driverIcon }).addTo(map).bindPopup("📍 You are here").openPopup();
            resizeMap();
            loadMechanics();
        }, function(err) {
            driverMarker = L.marker([driverLat, driverLng], { icon: driverIcon }).addTo(map);
            resizeMap();
            loadMechanics();
        }, { enableHighAccuracy: true, timeout: 5000 });
    } else {
        driverMarker = L.marker([driverLat, driverLng], { icon: driverIcon }).addTo(map);
        resizeMap();
        loadMechanics();
    }

    setTimeout(function(){ resizeMap(); }, 300);
</script>
</body>
</html>