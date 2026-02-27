<?php
session_start();
// include("../forms/config.php");
require_once("../forms/config.php");

// protect page
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
body{
  margin:0;
  font-family:'Segoe UI', sans-serif;
  background:#f4f6f8;
}

.dashboard-container{
  min-height:100vh;
  padding:20px;
}

.header{
  background:#2c3e50;
  color:white;
  padding:20px;
  border-radius:12px;
  margin-bottom:20px;
}

.header h1{
  margin:0;
  font-size:1.6rem;
}

.cards{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
  gap:15px;
}

.card{
  background:white;
  border-radius:12px;
  padding:20px;
  box-shadow:0 8px 25px rgba(0,0,0,0.1);
  text-align:center;
  transition:transform 0.2s;
}

.card:hover{
  transform:scale(1.05);
}

.card img{
  width:50px;
  margin-bottom:10px;
}

.card h3{
  margin:10px 0;
  color:#2c3e50;
}

.card button{
  margin-top:10px;
  padding:10px 15px;
  border:none;
  border-radius:8px;
  background:#3498db;
  color:white;
  cursor:pointer;
  font-size:0.9rem;
}

.card button:hover{
  background:#2980b9;
}

.logout{
  margin-top:20px;
  text-align:center;
}

.logout a{
  text-decoration:none;
  color:white;
  background:#e74c3c;
  padding:10px 20px;
  border-radius:8px;
}

.logout a:hover{
  background:#c0392b;
}
</style>
</head>

<body>

<div class="dashboard-container">

  <div class="header">
    <h1>Welcome, <?php echo htmlspecialchars($user_name); ?> 👋</h1>
    <p>Your Driver Dashboard</p>
  </div>

  <div class="cards">

    <div class="card">
      <img src="icons/request.svg">
      <h3>Request Mechanic</h3>
      <button onclick="location.href='request_mechanic.php'">Open</button>
    </div>

    <div class="card">
      <img src="icons/profile.svg">
      <h3>My Profile</h3>
      <button onclick="location.href='../forms/profile/driver_profile.php'">View</button>
      <button><a href="../forms/profile/driver_profile.php"> edit profile</a></button>
    </div>

    <div class="card">
      <img src="icons/history.svg">
      <h3>Service History</h3>
      <button>Coming Soon</button>
    </div>

    <div class="card">
      <img src="icons/map.svg">
      <h3>Nearby Mechanics</h3>
      <button>Coming Soon</button>
    </div>

  </div>

  <div class="logout">
    <a href="../forms/auth/logout.php">Logout</a>
    
  </div>

</div>

</body>
</html>