<?php
session_start();
require_once("../forms/config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver'){
    header("Location: " . FORMS_URL . "auth/login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? "Driver";

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
.main{margin-left:230px;padding:20px;transition:0.3s;}
.sidebar.collapsed ~ .main{margin-left:70px;}
.header{background:linear-gradient(135deg,#2c3e50,#3498db);color:white;padding:20px;border-radius:12px;margin-bottom:20px;}
.booking-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px;}
.booking-form select,.booking-form input,.booking-form button{padding:12px;border-radius:8px;border:1px solid #ccc;font-size:1rem;}
.booking-form button{background:#3498db;color:white;border:none;cursor:pointer;}
#map{height:420px;border-radius:12px;margin-top:20px;}
.results{display:flex;gap:15px;overflow-x:auto;margin-top:20px;}
.result-card{min-width:220px;background:white;padding:15px;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,0.1);}
@media(max-width:900px){.sidebar{transform:translateX(-100%);}.sidebar.mobile-open{transform:translateX(0);}.hamburger{display:block;}.toggle-btn{display:none;}.main{margin-left:0;padding-top:60px;}}
@media(max-width:600px){.booking-form{grid-template-columns:1fr;}.results{flex-direction:column;}}
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
<li><a href="map_view.php">🗺️ <span class="text">Map View</span></a></li>
<li><a href="bookings.php">📋 <span class="text">My Bookings</span></a></li>
<li><a href="rate_mechanic.php">⭐ <span class="text">Ratings</span></a></li>
<li><a href="../forms/auth/logout.php">🚪 <span class="text">Logout</span></a></li>
</ul>
</div>
<div class="main">
<div class="header">
<h1>Welcome, <?php echo htmlspecialchars($user_name); ?> 👋</h1>
<p>Find and book mechanics near you</p>
</div>

<h2>Quick Search</h2>
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
<input type="text" id="location" placeholder="Auto detecting location..." readonly>
<button type="button" onclick="searchMechanics()">Find Mechanics</button>
</form>

<h2>Nearby Mechanics</h2>
<div id="map"></div>
<div class="results" id="results"></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
function toggleDesktop(){document.getElementById("sidebar").classList.toggle("collapsed");}
function toggleMobile(){document.getElementById("sidebar").classList.toggle("mobile-open");}
document.addEventListener("click",function(e){var sidebar=document.getElementById("sidebar");var hamburger=document.querySelector(".hamburger");if(!sidebar.contains(e.target)&&!hamburger.contains(e.target)){sidebar.classList.remove("mobile-open");}});

// Initialize map
// Initialize map
var map = L.map('map').setView([-0.825893,34.609497],13);

// ADD THIS (missing tile layer)
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
}).addTo(map);

var allMarkers = [];

// Mechanics from PHP
var mechanics = <?php echo json_encode($mechanics); ?>;

// Add mechanic markers
mechanics.forEach(function(m){
    if(m.latitude && m.longitude){
        var marker = L.marker([m.latitude,m.longitude]).addTo(map);
        marker.bindPopup("<b>"+m.garage_name+"</b><br>Services: "+m.services_offered+"<br>Experience: "+m.experience+" yrs<br><button onclick='bookMechanic("+m.id+")'>Book</button>");
        allMarkers.push(marker);
    }
});

// Fit map bounds to markers
if(allMarkers.length>0){
    var group = L.featureGroup(allMarkers);
    map.fitBounds(group.getBounds().pad(0.2));
} else {
    map.setView([-0.0917,34.7680],13);
}

// Detect driver location
if(navigator.geolocation){
    navigator.geolocation.getCurrentPosition(function(pos){
        var lat = pos.coords.latitude;
        var lng = pos.coords.longitude;
        document.getElementById("location").value = lat.toFixed(5)+","+lng.toFixed(5);

        // Add marker for your location
        var userMarker = L.marker([lat,lng]).addTo(map).bindPopup("📍 Your Location").openPopup();

        // Center map on your location
        map.setView([lat,lng], 14);
    });
}

// Search
function searchMechanics(){
    var service=document.getElementById("serviceType").value.toLowerCase();
    var vehicle=document.getElementById("vehicleType").value.toLowerCase();
    var results=document.getElementById("results");
    results.innerHTML="";
    var found=0;
    mechanics.forEach(function(m){
        var mService=(m.services_offered||"").toLowerCase();
        var mVehicle=(m.vehicle_types||"").toLowerCase();
        if((service==="" || mService.includes(service)) && (vehicle==="" || mVehicle.includes(vehicle))){
            found++;
            var card=document.createElement("div");
            card.className="result-card";
            card.innerHTML="<strong>"+m.garage_name+"</strong><br>Services: "+m.services_offered+"<br>Experience: "+m.experience+" yrs<br><br><button onclick='focusMechanic("+m.id+")'>View</button> <button onclick='bookMechanic("+m.id+")'>Book</button>";
            results.appendChild(card);
        }
    });
    if(found===0) results.innerHTML="<div class='result-card'>No mechanics matched your search.</div>";
}

// Focus mechanic on map
function focusMechanic(id){
    mechanics.forEach(function(m){
        if(m.id==id){
            map.setView([m.latitude,m.longitude],15);
        }
    });
}

// Booking simulation
function bookMechanic(id){
    alert("Booking request sent to mechanic ID: "+id);
}
</script>
</body>
</html>