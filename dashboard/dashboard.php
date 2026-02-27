<?php
session_start();
require_once("../forms/config.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver'){
    header("Location: " . FORMS_URL . "auth/login.php");
    exit();
}

$user_name = $_SESSION['name'] ?? "Driver";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Driver Dashboard</title>

<style>
*{
  margin:0;
  padding:0;
  box-sizing:border-box;
  font-family:'Segoe UI', sans-serif;
}

body{
  background:#f4f6f8;
}

/* ---------- Sidebar ---------- */
.sidebar{
  position:fixed;
  top:0;
  left:0;
  width:230px;
  height:100vh;
  background:#2c3e50;
  color:white;
  transition:0.3s;
  overflow:hidden;
  z-index:1000;
}

.sidebar.collapsed{
  width:70px;
}

.sidebar h2{
  padding:20px;
  font-size:1.2rem;
  text-align:center;
}

.sidebar ul{
  list-style:none;
}

.sidebar ul li a{
  display:flex;
  align-items:center;
  gap:10px;
  padding:15px 20px;
  color:white;
  text-decoration:none;
  transition:0.2s;
}

.sidebar ul li a:hover{
  background:#3498db;
}

.sidebar ul li span.text{
  white-space:nowrap;
}

.sidebar.collapsed span.text{
  display:none;
}

/* Toggle button */
.toggle-btn{
  position:absolute;
  top:15px;
  right:-15px;
  background:#3498db;
  color:white;
  padding:5px 8px;
  border-radius:50%;
  cursor:pointer;
  font-size:1.1rem;
}

/* ---------- Mobile Hamburger ---------- */
.hamburger{
  display:none;
  position:fixed;
  top:15px;
  left:15px;
  font-size:1.8rem;
  cursor:pointer;
  color:#2c3e50;
  z-index:1100;
}

/* ---------- Main Content ---------- */
.main{
  margin-left:230px;
  padding:20px;
  transition:0.3s;
}

.sidebar.collapsed ~ .main{
  margin-left:70px;
}

/* ---------- Header ---------- */
.header{
  background:linear-gradient(135deg,#2c3e50,#3498db);
  color:white;
  padding:20px;
  border-radius:12px;
  margin-bottom:20px;
}

.header h1{
  font-size:1.6rem;
}

/* ---------- Cards ---------- */
.cards{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
  gap:15px;
}

.card{
  background:white;
  border-radius:12px;
  padding:20px;
  box-shadow:0 5px 15px rgba(0,0,0,0.1);
  text-align:center;
}

.card h3{
  margin-bottom:10px;
  color:#2c3e50;
}

.card button{
  padding:10px 15px;
  border:none;
  border-radius:8px;
  background:#3498db;
  color:white;
  cursor:pointer;
}

/* ---------- Recommended ---------- */
.recommended{
  display:flex;
  gap:15px;
  overflow-x:auto;
  margin-bottom:20px;
}

.recommend-card{
  min-width:220px;
  background:white;
  padding:15px;
  border-radius:12px;
  box-shadow:0 5px 15px rgba(0,0,0,0.1);
}

/* ---------- Mobile ---------- */
@media(max-width:900px){
  .sidebar{
    transform:translateX(-100%);
  }

  .sidebar.mobile-active{
    transform:translateX(0);
  }

  .hamburger{
    display:block;
  }

  .main{
    margin-left:0;
    padding-top:60px;
  }

  .toggle-btn{
    display:none;
  }
}

@media(max-width:600px){
  .header h1{
    font-size:1.3rem;
  }

  .cards{
    grid-template-columns:1fr;
  }
}
</style>
</head>

<body>

<div class="hamburger" onclick="toggleMobileMenu()">☰</div>

<div class="sidebar" id="sidebar">
  <div class="toggle-btn" onclick="toggleSidebar()">☰</div>
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
    <p>Your Driver Dashboard</p>
  </div>

  <h2>⭐ Recommended Mechanics</h2>
  <div class="recommended">
    <div class="recommend-card">
      <strong>Mike Auto Garage</strong><br>
      Engine • 0.5km<br>
      <button>Book</button>
    </div>
    <div class="recommend-card">
      <strong>John Mechanics</strong><br>
      Tyres • Available<br>
      <button>Book</button>
    </div>
  </div>

  <h2>Quick Actions</h2>
  <div class="cards">
    <div class="card">
      <h3>Find Mechanics</h3>
      <button onclick="location.href='find_mechanics.php'">Open</button>
    </div>
    <div class="card">
      <h3>Map View</h3>
      <button onclick="location.href='map_view.php'">Open</button>
    </div>
    <div class="card">
      <h3>My Bookings</h3>
      <button onclick="location.href='bookings.php'">Open</button>
    </div>
    <div class="card">
      <h3>Profile</h3>
      <button onclick="location.href='profile.php'">Open</button>
    </div>
  </div>

</div>

<script>
function toggleSidebar(){
  document.getElementById("sidebar").classList.toggle("collapsed");
}

function toggleMobileMenu(){
  document.getElementById("sidebar").classList.toggle("mobile-active");
}
</script>

</body>
</html>