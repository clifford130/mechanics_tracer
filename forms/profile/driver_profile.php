<?php
// session_start();
// include "../config.php"; // adjust path if needed

// // Protect page
// if(!isset($_SESSION['user_id'])){
//     header("Location: ../auth/login.php");
//     exit();
// }


// session_start();
// // include "../config.php"; // adjust path if needed
// require_once("../config.php");
// // Protect page
// if(!isset($_SESSION['user_id'])){
//     header("Location: ../auth/login.php");
//     exit();
// }

// $user_id = $_SESSION['user_id'];
// $error = "";

// // Process form
// if($_SERVER["REQUEST_METHOD"] == "POST"){

    // $vehicle_type  = trim($_POST['vehicle_type']);
    // $vehicle_make  = trim($_POST['vehicle_make']);
    // $vehicle_model = trim($_POST['vehicle_model']);
    // $vehicle_year  = trim($_POST['vehicle_year']);

    // // Checkbox array
    // if(isset($_POST['service_preferences'])){
    //     $service_preferences = implode(",", $_POST['service_preferences']);
    // } else {
    //     $service_preferences = "";
    // }

    // Validation
    // if(empty($vehicle_type) || empty($vehicle_make) || empty($vehicle_model) || empty($vehicle_year) || empty($service_preferences)){
    //     $error = "Please fill in all required fields.";
    // }
    // else{

        // Insert into driver_profiles
        // $sql = "INSERT INTO drivers
        // (user_id, vehicle_type, vehicle_make, vehicle_model, vehicle_year, service_preferences)
        // VALUES (?, ?, ?, ?, ?, ?)";

        // $stmt = $conn->prepare($sql);
        // $stmt->bind_param("isssis", 
        //     $user_id, 
        //     $vehicle_type, 
        //     $vehicle_make, 
        //     $vehicle_model, 
        //     $vehicle_year, 
        //     $service_preferences
        // );

        // if($stmt->execute()){

            // Update users table
            // $update = "UPDATE users SET role='driver', profile_completed=1 WHERE id=?";
            // $stmt2 = $conn->prepare($update);
            // $stmt2->bind_param("i", $user_id);
            // $stmt2->execute();

            // // Update session
            // $_SESSION['role'] = "driver";
            // $_SESSION['profile_completed'] = 1;

            // Redirect to dashboard
            // header("Location: /new/dashboard/driver_dashboard.php");
            // exit();

    //     } else {
    //         $error = "Something went wrong. Try again.";
    //     }
    // }
// }
session_start();
require_once("../config.php");

// Protect page
if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $vehicle_type  = trim($_POST['vehicle_type']);
    $vehicle_make  = trim($_POST['vehicle_make']);
    $vehicle_model = trim($_POST['vehicle_model']);
    $vehicle_year  = trim($_POST['vehicle_year']);

    if(isset($_POST['service_preferences'])){
        $service_preferences = implode(",", $_POST['service_preferences']);
    } else {
        $service_preferences = "";
    }

    // Validation
    if(empty($vehicle_type) || empty($vehicle_make) || empty($vehicle_model) || empty($vehicle_year) || empty($service_preferences)){
        $error = "Please fill in all required fields.";
    } else {

        // ✅ Check if profile already exists
        $check = $conn->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $result = $check->get_result();

        if($result->num_rows > 0){
            // ✅ UPDATE existing profile
            $sql = "UPDATE drivers SET 
                    vehicle_type=?, 
                    vehicle_make=?, 
                    vehicle_model=?, 
                    vehicle_year=?, 
                    service_preferences=?
                    WHERE user_id=?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi",
                $vehicle_type,
                $vehicle_make,
                $vehicle_model,
                $vehicle_year,
                $service_preferences,
                $user_id
            );

        } else {
            // ✅ INSERT new profile
            $sql = "INSERT INTO drivers
            (user_id, vehicle_type, vehicle_make, vehicle_model, vehicle_year, service_preferences)
            VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssis",
                $user_id,
                $vehicle_type,
                $vehicle_make,
                $vehicle_model,
                $vehicle_year,
                $service_preferences
            );
        }

        if($stmt->execute()){

            // Update users table
            $update = "UPDATE users SET role='driver', profile_completed=1 WHERE id=?";
            $stmt2 = $conn->prepare($update);
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();

            // Update session
            $_SESSION['role'] = "driver";
            $_SESSION['profile_completed'] = 1;

            header("Location: /new/dashboard/driver_dashboard.php");
            exit();

        } else {
            $error = "Something went wrong. Try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Complete Your Driver Profile</title>
<style>
/* === YOUR DESIGN (UNCHANGED) === */
body {
  margin:0;
  font-family:'Segoe UI', sans-serif;
  background:#f4f6f8;
  line-height:1.5;
}
.profile-container {
  max-width:600px;
  width:95%;
  margin:40px auto;
  padding:2rem;
  background:#fff;
  border-radius:12px;
  box-shadow:0 8px 25px rgba(0,0,0,0.1);
}
h1 {
  text-align:center;
  font-size:clamp(1.5rem, 4vw, 1.8rem);
  margin-bottom:0.5rem;
}
.subtitle {
  text-align:center;
  font-size:clamp(0.85rem, 2.5vw, 1rem);
  color:#555;
  margin-bottom:1.5rem;
}
.form-group {
  display:flex;
  flex-direction:column;
  margin-bottom:1rem;
}
.form-group label {
  font-weight:600;
  margin-bottom:0.3rem;
}
.form-group input, .form-group select {
  padding:0.9rem 1rem;
  border-radius:8px;
  border:1px solid #dcdde1;
}
.checkbox-group {
  display:flex;
  flex-wrap:wrap;
  gap:0.5rem;
  margin-bottom:1.5rem;
}
.checkbox-group label {
  font-size:0.9rem;
  display:flex;
  align-items:center;
  gap:5px;
  padding:5px 10px;
  background:#f1f3f6;
  border-radius:8px;
}
.btn-primary {
  width:100%;
  padding:12px;
  border:none;
  border-radius:10px;
  background:#2c3e50;
  color:#fff;
  font-size:1rem;
}
.error-msg {
  background:#ffe6e6;
  color:#b30000;
  padding:10px;
  border-radius:8px;
  margin-bottom:10px;
  text-align:center;
}
</style>
</head>
<body>

<div class="profile-container">
  <h1>Driver Profile</h1>
  <p class="subtitle">Complete your profile before accessing the dashboard</p>

  <?php if($error): ?>
    <div class="error-msg"><?php echo $error; ?></div>
  <?php endif; ?>

  <form method="POST">

    <div class="form-group">
      <label>Vehicle Type *</label>
      <select name="vehicle_type" required>
        <option value="">Select Vehicle Type</option>
        <option>Car</option>
        <option>Truck</option>
        <option>Motorbike</option>
        <option>Van</option>
        <option>Bus</option>
      </select>
    </div>

    <div class="form-group">
      <label>Vehicle Make *</label>
      <input type="text" name="vehicle_make" required>
    </div>

    <div class="form-group">
      <label>Vehicle Model *</label>
      <input type="text" name="vehicle_model" required>
    </div>

    <div class="form-group">
      <label>Vehicle Year *</label>
      <input type="number" name="vehicle_year" required>
    </div>

    <label>Service Preferences *</label>
    <div class="checkbox-group">
      <label><input type="checkbox" name="service_preferences[]" value="Oil Change"> Oil Change</label>
      <label><input type="checkbox" name="service_preferences[]" value="Engine Repair"> Engine Repair</label>
      <label><input type="checkbox" name="service_preferences[]" value="Tire Replacement"> Tire Replacement</label>
      <label><input type="checkbox" name="service_preferences[]" value="Brake Adjustment"> Brake Adjustment</label>
    </div>

    <button type="submit" class="btn-primary">Save Profile</button>

  </form>
</div>

</body>
</html>