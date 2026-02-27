<?php
session_start();
include("../forms/config.php");


if (!isset($_SESSION['user_id'])) {
    header("Location: " . FORMS_URL . "auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM mechanics WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Mechanic Dashboard</title>
<style>
body {
  margin:0;
  font-family:'Segoe UI', sans-serif;
  background:#f4f6f8;
}
.dashboard {
  max-width:800px;
  margin:30px auto;
  background:#fff;
  padding:25px;
  border-radius:12px;
  box-shadow:0 8px 25px rgba(0,0,0,0.1);
}
h1 {
  text-align:center;
  margin-bottom:10px;
}
.card {
  background:#f1f3f6;
  padding:15px;
  border-radius:10px;
  margin-bottom:15px;
}
.btn {
  display:inline-block;
  padding:12px 18px;
  background:#2c3e50;
  color:#fff;
  border-radius:8px;
  text-decoration:none;
  margin-top:10px;
}
.btn:hover {
  background:#1a252f;
}
</style>
</head>
<body>

<div class="dashboard">
  <h1>Welcome, <?php echo htmlspecialchars($profile['garage_name']); ?> 🔧</h1>

  <div class="card">
    <strong>Experience:</strong> <?php echo $profile['experience']; ?> years
  </div>

  <div class="card">
    <strong>Vehicle Types:</strong> <?php echo $profile['vehicle_types']; ?>
  </div>

  <div class="card">
    <strong>Services Offered:</strong> <?php echo $profile['services_offered']; ?>
  </div>

  <div class="card">
    <strong>Location:</strong> 
    Lat: <?php echo $profile['latitude']; ?> , 
    Long: <?php echo $profile['longitude']; ?>
  </div>

  <a href="<?php echo FORMS_URL; ?>profile/mechanic_profile.php" class="btn">Edit Profile</a>
  <a href="<?php echo FORMS_URL; ?>auth/logout.php" class="btn">Logout</a>
</div>

</body>
</html>