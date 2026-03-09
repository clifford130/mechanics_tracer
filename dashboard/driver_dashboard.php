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
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif;}
body{background:#f4f6f8;}
.sidebar{position:fixed;top:0;left:0;width:230px;height:100vh;background:#2c3e50;color:white;transition:0.3s;z-index:1000;overflow:auto;}
.sidebar.collapsed{width:70px;}
.sidebar h2{padding:20px;text-align:center;font-size:1.2rem;}
.sidebar ul{list-style:none;}
.sidebar ul li a{display:flex;align-items:center;gap:10px;padding:15px 20px;color:white;text-decoration:none;}
.sidebar ul li a:hover{background:#3498db;}
.sidebar span.text{white-space:nowrap;}
.sidebar.collapsed span.text{display:none;}
.toggle-btn{position:absolute;top:15px;right:-15px;background:#3498db;color:white;padding:6px 9px;border-radius:50%;cursor:pointer;}
.hamburger{display:none;position:fixed;top:15px;left:15px;font-size:28px;color:#2c3e50;cursor:pointer;z-index:1100;}
.main{margin-left:230px;padding:20px;transition:0.3s;display:flex;flex-direction:column;}
.sidebar.collapsed ~ .main{margin-left:70px;}
.header{background:linear-gradient(135deg,#2c3e50,#3498db);color:white;padding:20px;border-radius:12px;margin-bottom:20px;width:100%;}
.booking-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px;width:100%;}
.booking-form select,.booking-form input,.booking-form button{padding:12px;border-radius:8px;border:1px solid #ccc;font-size:1rem;}
.booking-form button{background:#3498db;color:white;border:none;cursor:pointer;}
.dashboard-container{display:flex;gap:20px;}
#map{flex:1;height:600px;border-radius:12px;}
.results{width:320px;max-height:600px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;}
.result-card{background:white;padding:15px;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,0.1);}
.result-card button{margin-top:8px;padding:6px 10px;background:#3498db;color:white;border:none;border-radius:6px;cursor:pointer;width:48%;margin-right:2%;}
.result-card button:last-child{margin-right:0;}
@media(max-width:900px){
    .dashboard-container{flex-direction:column;}
    #map{height:400px;width:100%;}
    .results{width:100%;max-height:250px;flex-direction:row;overflow-x:auto;}
}
</style>
</head>
<body>
<div class="hamburger" onclick="toggleMobile()">☰</div>
<div class="sidebar" id="sidebar">
<div class="toggle-btn" onclick="toggleDesktop()">☰</div>
<h2><?php echo htmlspecialchars($user_name); ?></h2>
<ul>
<li><a href="dashboard.php">🏠 <span class="text">Dashboard</span></a></li>
<li><a href="profile.php">👤 <span class="text">My Profile</span></a></li>
<li><a href="find_mechanics.php">🔍 <span class="text">Find Mechanics</span></a></li>
<li><a href="bookings.php">📋 <span class="text">My Bookings</span></a></li>
<li><a href="rate_mechanic.php">⭐ <span class="text">Ratings</span></a></li>
<li><a href="../forms/auth/logout.php">🚪 <span class="text">Logout</span></a></li>
</ul>
</div>
<div class="main">
<div class="header">
<h1 id="driverGreeting"></h1>
<p>Find and book mechanics near you</p>
</div>
<form class="booking-form">
<select id="serviceType">
<option value="">Select Service</option>
<option value="engine">Engine</option>
<option value="tyres">Tyres</option>
<option value="battery">Battery</option>
<option value="brakes">Brakes</option>
</select>
<select id="vehicleType">
<option value="">Vehicle Type</option>
<option value="car">Car</option>
<option value="truck">Truck</option>
<option value="motorcycle">Motorcycle</option>
<option value="motorbike">Motorbike</option>
</select>
<button type="button" onclick="searchMechanics()">Search</button>
</form>

<div class="dashboard-container">
<div id="map"></div>
<div class="results" id="results"></div>
</div>
</div>

<!-- Booking Modal -->
<div id="bookingModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">

<div style="background:white; padding:20px; border-radius:10px; width:300px; text-align:center;">

<h3>Confirm Booking</h3>

<p id="bookingText"></p>

<div style="margin-top:15px;">

<button id="confirmBookBtn"
style="background:#3498db;color:white;padding:8px 15px;border:none;border-radius:6px;">
Book
</button>

<button onclick="closeBookingModal()"
style="background:#ccc;padding:8px 15px;border:none;border-radius:6px;">
Cancel
</button>

</div>

</div>
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
        el.textContent = greeting + " " + driverName + " 👋";
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

// ---------- Sidebar toggles ----------
function toggleDesktop(){ document.getElementById("sidebar").classList.toggle("collapsed"); }
function toggleMobile(){ document.getElementById("sidebar").classList.toggle("mobile-open"); }
document.addEventListener("click", function(e){
  var sidebar = document.getElementById("sidebar");
  var hamburger = document.querySelector(".hamburger");
  if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
    sidebar.classList.remove("mobile-open");
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

// ---------- get road info per mechanic and per mode ----------
// mode = 'driving' | 'foot' | 'bike'
function getRoadInfo(mech, mode) {
    // important: call fetchBestRoute separately for each mode to guarantee distinct mode times
    return fetchBestRoute(mode, 'false', driverLng, driverLat, mech.longitude, mech.latitude)
    .then(best => {
        if (!best) {
            if (mode === 'driving') { mech.roadDistance = null; mech.roadDuration = null; mech._driving_best_summary = null; }
            if (mode === 'foot') mech.etaFoot = null;
            if (mode === 'bike') mech.etaBoda = null;
            return mech;
        }
        if (mode === 'driving') {
            mech.roadDistance = best.distance / 1000;           // km
            mech.roadDuration = best.duration / 60;            // minutes
            mech._driving_best_summary = { distance: best.distance, duration: best.duration }; // raw meters and seconds
        } else if (mode === 'foot') {
            mech.etaFoot = best.duration / 60;
        } else if (mode === 'bike') {
            mech.etaBoda = best.duration / 60;
        }
        return mech;
    }).catch(err => {
        console.warn("getRoadInfo error", err);
        if (mode === 'driving') { mech.roadDistance = null; mech.roadDuration = null; mech._driving_best_summary = null; }
        if (mode === 'foot') mech.etaFoot = null;
        if (mode === 'bike') mech.etaBoda = null;
        return mech;
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

// ---------- render results, mark nearest ----------
function renderResults(filterService = '', filterVehicle = '') {
    var container = document.getElementById('results');
    container.innerHTML = '';

    var filtered = mechanics.filter(m => {
        var svc = (m.services_offered || '').toLowerCase();
        var veh = (m.vehicle_types || '').toLowerCase();
        return (filterService === '' || svc.includes(filterService)) && (filterVehicle === '' || veh.includes(filterVehicle));
    });

    // sort by driving distance (nulls go last)
    filtered.sort((a,b) => (a.roadDistance == null ? 9999 : a.roadDistance) - (b.roadDistance == null ? 9999 : b.roadDistance));

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
            <div class="result-actions" style="margin-top:6px">
              <button onclick="focusMechanic(${m.id})">View</button>
              <button onclick="bookMechanic(${m.id})">Book</button>
            </div>
        `;
        container.appendChild(card);
    });

    // scroll nearest into view for better UX
    setTimeout(() => {
      var nearestEl = container.querySelector('.result-card.nearest');
      if (nearestEl) nearestEl.scrollIntoView({behavior:'smooth', block:'center'});
    }, 120);
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

// booking mechanic 
function bookMechanic(id){
    window.location.href = "/mechanics_tracer/forms/bookings/book_mechanic.php?mechanic_id=" + id;
}

function closeBookingModal(){
    document.getElementById("bookingModal").style.display="none";
}

document.getElementById("confirmBookBtn").onclick = function(){

    let service = document.getElementById("serviceType").value;
    let vehicle = document.getElementById("vehicleType").value;

    fetch("../forms/bookings/create_booking.php",{
        method:"POST",
        headers:{
            "Content-Type":"application/json"
        },
        body:JSON.stringify({
            mechanic_id:selectedMechanic,
            service:service,
            vehicle:vehicle,
            lat:driverLat,
            lng:driverLng
        })
    })
    .then(res=>res.json())
    .then(data=>{

        if(data.success){
            alert("Booking request sent successfully ✔");
            closeBookingModal();
        }
        else{
            alert("Booking failed");
        }

    });
};

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

    // For each mechanic perform three profile requests in parallel, but batch mechanics to avoid overwhelming OSRM.
    var concurrency = 5;
    var queue = mechanics.slice().filter(m => m.latitude && m.longitude);

    function worker() {
        if (queue.length === 0) return Promise.resolve();
        var batch = queue.splice(0, concurrency);
        return Promise.all(batch.map(m => {
            // query foot, bike, driving in parallel for each mechanic
            return Promise.all([
                getRoadInfo(m, 'foot'),
                getRoadInfo(m, 'bike'),
                getRoadInfo(m, 'driving')
            ]).then(() => {
                // compute matatu as 15% slower than driving if driving info exists
                if (typeof m.roadDuration === 'number') m.etaMatatu = m.roadDuration * 1.15;
                return m;
            }).catch(()=>m);
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
    var vehicle = document.getElementById("vehicleType").value.toLowerCase();
    renderResults(service, vehicle);
}

// initial layout fix
setTimeout(function(){ resizeMap(); }, 300);


// styling markers

</script>
</body>
</html>