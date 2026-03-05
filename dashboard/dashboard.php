<?php
session_start();
require_once("../forms/config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver'){
    header("Location: " . FORMS_URL . "auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? "Driver";

// Fetch driver location
$stmt = $conn->prepare("SELECT latitude, longitude FROM drivers WHERE user_id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$driver_location = ['lat'=>0,'lng'=>0];
if($result->num_rows>0){
    $row = $result->fetch_assoc();
    if(!empty($row['latitude']) && !empty($row['longitude'])){
        $driver_location['lat'] = $row['latitude'];
        $driver_location['lng'] = $row['longitude'];
    }
}

// Fetch mechanics
$mechanics = [];
$sql = "SELECT id, garage_name, vehicle_types, services_offered, latitude, longitude, experience, certifications, rating 
        FROM mechanics WHERE availability='available'";
$res = $conn->query($sql);
if($res){
    while($m = $res->fetch_assoc()){
        $mechanics[] = $m;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Driver Dashboard</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif;}
body{background:#f4f6f8;}

/* Sidebar */
.sidebar{position:fixed;top:0;left:0;width:230px;height:100vh;background:#2c3e50;color:white;transition:0.3s;z-index:1000;overflow:auto;}
.sidebar.collapsed{width:70px;}
.sidebar h2{padding:20px;text-align:center;font-size:1.2rem;}
.sidebar ul{list-style:none;}
.sidebar ul li a{display:flex;align-items:center;gap:10px;padding:15px 20px;color:white;text-decoration:none;}
.sidebar ul li a:hover{background:#3498db;}
.sidebar span.text{white-space:nowrap;}
.sidebar.collapsed span.text{display:none;}
.toggle-btn{position:absolute;top:15px;right:-15px;background:#3498db;color:white;padding:5px 8px;border-radius:50%;cursor:pointer;}
.hamburger{display:none;position:fixed;top:15px;left:15px;font-size:1.8rem;color:#2c3e50;cursor:pointer;z-index:1100;}

/* Main content */
.main{margin-left:230px;padding:20px;transition:0.3s;}
.sidebar.collapsed ~ .main{margin-left:70px;}

/* Header */
.header{background:linear-gradient(135deg,#2c3e50,#3498db);color:white;padding:20px;border-radius:12px;margin-bottom:20px;}

/* Booking form */
.booking-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px;}
.booking-form select,.booking-form input,.booking-form button{padding:12px;border-radius:8px;border:1px solid #ccc;font-size:1rem;}
.booking-form button{background:#3498db;color:white;border:none;cursor:pointer;}

/* Status cards */
.status-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px;}
.status-card{background:white;padding:15px;border-radius:12px;text-align:center;box-shadow:0 5px 15px rgba(0,0,0,0.1);}

/* Results cards */
.results{display:flex;gap:15px;overflow-x:auto;margin-bottom:20px;}
.result-card{min-width:220px;background:white;padding:15px;border-radius:12px;box-shadow:0 5px 15px rgba(0,0,0,0.1);cursor:pointer;}
.result-card.highlight{border:2px solid #3498db;background:#ecf5fc;}

/* Map */
#map{height:400px;border-radius:12px;margin-bottom:20px;}

/* Mobile */
@media(max-width:900px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.mobile-open{transform:translateX(0);}
  .hamburger{display:block;}
  .toggle-btn{display:none;}
  .main{margin-left:0;padding-top:60px;}
}
@media(max-width:600px){
  .booking-form{grid-template-columns:1fr;}
  .status-cards{grid-template-columns:1fr;}
  .results{flex-direction:column;}
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
    <li><a href="map_view.php">🗺️ <span class="text">Map View</span></a></li>
    <li><a href="bookings.php">📋 <span class="text">My Bookings</span></a></li>
    <li><a href="rate_mechanic.php">⭐ <span class="text">Ratings</span></a></li>
    <li><a href="../forms/auth/logout.php">🚪 <span class="text">Logout</span></a></li>
  </ul>
</div>
<div class="main">
  <div class="header">
    <h1>Welcome, <?php echo htmlspecialchars($user_name); ?> 👋</h1>
    <p>Book a mechanic in one step!</p>
  </div>

  <!-- Quick Booking Form -->
  <h2>Book a Service Now</h2>
  <form class="booking-form" id="bookingForm">
    <select id="serviceType">
      <option value="">Select Service</option>
      <option value="engine">Engine</option>
      <option value="tyres">Tyres</option>
      <option value="battery">Battery</option>
      <option value="brakes">Brakes</option>
    </select>
    <select id="vehicleType">
      <option value="">Select Vehicle</option>
      <option value="car">Car</option>
      <option value="truck">Truck</option>
      <option value="motorcycle">Motorcycle</option>
    </select>
    <input type="text" id="location" placeholder="Your Location">
    <button type="button" onclick="searchMechanics()">Find Mechanics</button>
  </form>

  <!-- Status Summary -->
  <h2>Service Status</h2>
  <div class="status-cards">
    <div class="status-card">Pending ⏳<br><strong>2</strong></div>
    <div class="status-card">Accepted ✅<br><strong>1</strong></div>
    <div class="status-card">Completed 🏁<br><strong>5</strong></div>
  </div>

  <!-- Map -->
  <h2>Nearby Mechanics</h2>
  <div id="map"></div>

  <!-- Results -->
  <div class="results" id="results"></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Sidebar
function toggleDesktop(){ document.getElementById("sidebar").classList.toggle("collapsed"); }
function toggleMobile(){ document.getElementById("sidebar").classList.toggle("mobile-open"); }
document.addEventListener('click',function(e){
  var sidebar=document.getElementById("sidebar");
  var hamburger=document.querySelector('.hamburger');
  if(!sidebar.contains(e.target)&&!hamburger.contains(e.target)){
    sidebar.classList.remove('mobile-open');
  }
});

// Initialize Map
var map=L.map('map').setView([<?php echo $driver_location['lat']; ?>,<?php echo $driver_location['lng']; ?>],14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);

var mechanics=<?php echo json_encode($mechanics); ?>;
var markers=[];
var highlightedCard=null;

// Add markers
mechanics.forEach((m,i)=>{
  var marker=L.marker([m.latitude,m.longitude]).addTo(map)
    .bindPopup("<b>"+m.garage_name+"</b><br>"+m.services_offered+"<br>Status: Available<br><button onclick='bookMechanic("+i+")'>Book</button>");
  marker.mechanicIndex=i;
  marker.on('click',function(){
    highlightCard(i);
  });
  markers.push(marker);
});

// Highlight card
function highlightCard(index){
  var cards=document.querySelectorAll('.result-card');
  cards.forEach(c=>c.classList.remove('highlight'));
  if(cards[index]) cards[index].classList.add('highlight');
  map.setView([mechanics[index].latitude,mechanics[index].longitude],15);
}

// Search Mechanics
function searchMechanics(){
  var service=document.getElementById("serviceType").value;
  var resultsDiv=document.getElementById("results");
  resultsDiv.innerHTML="";
  var filtered=mechanics.filter(m=> (!service || m.services_offered.includes(service)));
  filtered.forEach((m,i)=>{
    var card=document.createElement("div");
    card.className="result-card";
    card.innerHTML="<strong>"+m.garage_name+"</strong><br>"+m.services_offered+"<br>Status: Available<br><button onclick='bookMechanic("+i+")'>Book</button>";
    card.addEventListener('click',()=>{ highlightCard(i); });
    resultsDiv.appendChild(card);
  });
}

// Book mechanic
function bookMechanic(index){
  alert("Booking sent for "+mechanics[index].garage_name);
}

// Auto detect location
if(navigator.geolocation){
  navigator.geolocation.getCurrentPosition(position=>{
    var lat=position.coords.latitude;
    var lng=position.coords.longitude;
    document.getElementById("location").value = lat.toFixed(5)+","+lng.toFixed(5);
    map.setView([lat,lng],14);
    // Optional: send AJAX to update DB
  });
}
</script>
</body>
</html>