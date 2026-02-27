<?php
// session_start();
// // include "../config.php"; 
// require_once("../config.php");

// // protect page
// if (!isset($_SESSION['user_id'])) {
//   // header("Location: new/forms/auth/login.php");
//   exit();
// }

// if ($_SERVER["REQUEST_METHOD"] == "POST") {

//   $user_id = $_SESSION['user_id'];

//   $garage_name = $_POST['garage_name'] ?? '';
//   $experience = $_POST['experience'] ?? '';
//   $certifications = $_POST['certifications'] ?? '';

//   $vehicle_types = isset($_POST['vehicle_types'])
//     ? implode(",", $_POST['vehicle_types'])
//     : '';

//   $services_offered = isset($_POST['services_offered'])
//     ? implode(",", $_POST['services_offered'])
//     : '';

//   $latitude = $_POST['latitude'] ?? '';
//   $longitude = $_POST['longitude'] ?? '';

//   $availability = "available";

  // Before inserting
  // $stmt = $conn->prepare("SELECT id FROM mechanics WHERE user_id = ?");
  // $stmt->bind_param("i", $user_id);
  // $stmt->execute();
  // $result = $stmt->get_result();

  // if ($result->num_rows > 0) {
  //   $error = "You already have a mechanic profile.";
  // } else {
  //   // proceed with INSERT


  //   $sql = "INSERT INTO mechanics
  //   (user_id, garage_name, experience, certifications, vehicle_types, services_offered, latitude, longitude, availability)
  //   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

  //   $stmt = $conn->prepare($sql);
    // $stmt->bind_param(
    //   "isissssss",
    //   $user_id,
    //   $garage_name,
    //   $experience,
    //   $certifications,
    //   $vehicle_types,
    //   $services_offered,
    //   $latitude,
    //   $longitude,
    //   $availability
    // );

//     if ($stmt->execute()) {

//       // update users table
//       $update = $conn->prepare("UPDATE users SET  role='mechanic', profile_completed=1 WHERE id=?");
//       $update->bind_param("i", $user_id);
//       $update->execute();

//       header("Location: " . DASHBOARD_URL . "mechanic_dashboard.php");
//       exit();
//     } else {
//       $error = "Error saving profile: " . $stmt->error;
//     }
//   }
// }

session_start();
require_once("../config.php");

// Protect page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $garage_name = trim($_POST['garage_name'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $certifications = trim($_POST['certifications'] ?? '');

    $vehicle_types = isset($_POST['vehicle_types']) 
        ? implode(",", $_POST['vehicle_types']) 
        : '';

    $services_offered = isset($_POST['services_offered']) 
        ? implode(",", $_POST['services_offered']) 
        : '';

    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');

    $availability = "available";

    // Validation
    if (empty($garage_name) || empty($experience) || empty($vehicle_types) || empty($services_offered)) {
        $error = "Please fill in all required fields.";
    } else {

        // Check if mechanic profile exists
        $check = $conn->prepare("SELECT id FROM mechanics WHERE user_id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            // ✅ UPDATE profile
            $sql = "UPDATE mechanics SET
                    garage_name=?,
                    experience=?,
                    certifications=?,
                    vehicle_types=?,
                    services_offered=?,
                    latitude=?,
                    longitude=?,
                    availability=?
                    WHERE user_id=?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sissssssi",
                $garage_name,
                $experience,
                $certifications,
                $vehicle_types,
                $services_offered,
                $latitude,
                $longitude,
                $availability,
                $user_id
            );

        } else {
            // ✅ INSERT new profile
            $sql = "INSERT INTO mechanics
            (user_id, garage_name, experience, certifications, vehicle_types, services_offered, latitude, longitude, availability)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "isissssss",
                $user_id,
                $garage_name,
                $experience,
                $certifications,
                $vehicle_types,
                $services_offered,
                $latitude,
                $longitude,
                $availability
            );
        }

        if ($stmt->execute()) {

            // Update users table
            $update = $conn->prepare("UPDATE users SET role='mechanic', profile_completed=1 WHERE id=?");
            $update->bind_param("i", $user_id);
            $update->execute();

            $_SESSION['role'] = "mechanic";
            $_SESSION['profile_completed'] = 1;

            header("Location: " . DASHBOARD_URL . "mechanic_dashboard.php");
            exit();

        } else {
            // ❌ hide technical error from user
            error_log("Mechanic profile error: " . $stmt->error);
            $error = "Something went wrong while saving your profile. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mechanic Profile</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background: #f4f6f8;
    }

    .profile-container {
      max-width: 700px;
      width: 95%;
      margin: 30px auto;
      padding: 25px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .profile-container h1 {
      text-align: center;
      font-size: 1.8rem;
      margin-bottom: 5px;
    }

    .profile-container .subtitle {
      text-align: center;
      font-size: 0.95rem;
      color: #555;
      margin-bottom: 20px;
    }

    form .form-group {
      display: flex;
      flex-direction: column;
      margin-bottom: 15px;
    }

    form .form-group label {
      font-weight: 600;
      margin-bottom: 5px;
    }

    form .form-group input,
    form .form-group select {
      padding: 12px;
      border-radius: 8px;
      border: 1px solid #dcdde1;
      font-size: 1rem;
    }

    .checkbox-group {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 15px;
    }

    .checkbox-group label {
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 5px;
      padding: 5px 10px;
      background: #f1f3f6;
      border-radius: 8px;
      cursor: pointer;
    }

    #map {
      width: 100%;
      height: 250px;
      border-radius: 12px;
      margin-bottom: 10px;
      border: 1px solid #dcdde1;
    }

    .lat-lon {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }

    .lat-lon input {
      flex: 1;
      padding: 10px;
      border-radius: 8px;
      border: 1px solid #dcdde1;
      font-size: 1rem;
      background: #f9f9f9;
    }

    .btn-primary {
      width: 100%;
      padding: clamp(12px, 2.5vw, 16px);
      border: none;
      border-radius: 10px;
      background: #2c3e50;
      color: #fff;
      font-size: clamp(1rem, 3vw, 1.2rem);
      cursor: pointer;
      transition: 0.3s ease;
    }

    .btn-primary:hover {
      background: #1a252f;
    }

    @media(max-width:600px) {
      .lat-lon {
        flex-direction: column;
      }

      .checkbox-group {
        justify-content: flex-start;
      }
    }

    .error {
      background: #ffe5e5;
      color: #b00020;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 12px;
      text-align: center;
    }
  </style>
</head>

<body>

  <div class="profile-container">
    <h1>Mechanic Profile</h1>
    <p class="subtitle">Complete your garage profile for accurate recommendations</p>

    <?php if (isset($error)): ?>
      <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">


      <!-- Garage Info -->
      <div class="form-group">
        <label for="garage_name">Garage Name <span style="color:red">*</span></label>
        <input type="text" name="garage_name" id="garage_name" placeholder="Enter your garage name" required>
      </div>

      <div class="form-group">
        <label for="experience">Years of Experience <span style="color:red">*</span></label>
        <input type="number" name="experience" id="experience" placeholder="Enter years of experience" required>
      </div>

      <div class="form-group">
        <label for="certifications">Certifications / Skills (optional)</label>
        <input type="text" name="certifications" id="certifications" placeholder="e.g. Engine Specialist, Brake Expert">
      </div>

      <!-- Vehicle Types -->
      <label>Vehicle Types You Service <span style="color:red">*</span></label>
      <div class="checkbox-group">
        <label><input type="checkbox" name="vehicle_types[]" value="Car">Car</label>
        <label><input type="checkbox" name="vehicle_types[]" value="Truck">Truck</label>
        <label><input type="checkbox" name="vehicle_types[]" value="Motorbike">Motorbike</label>
        <label><input type="checkbox" name="vehicle_types[]" value="Van">Van</label>
        <label><input type="checkbox" name="vehicle_types[]" value="Bus">Bus</label>
      </div>

      <!-- Services Offered -->
      <label>Services You Offer <span style="color:red">*</span></label>
      <div class="checkbox-group">
        <label><input type="checkbox" name="services_offered[]" value="Oil Change">Oil Change</label>
        <label><input type="checkbox" name="services_offered[]" value="Engine Repair">Engine Repair</label>
        <label><input type="checkbox" name="services_offered[]" value="Tire Replacement">Tire Replacement</label>
        <label><input type="checkbox" name="services_offered[]" value="Brake Adjustment">Brake Adjustment</label>
        <label><input type="checkbox" name="services_offered[]" value="Chain Lubrication">Chain Lubrication</label>
        <label><input type="checkbox" name="services_offered[]" value="Battery Replacement">Battery Replacement</label>
      </div>

      <!-- GPS Map -->
      <label>Garage Location <span style="color:red">*</span></label>
      <div id="map"></div>
      <div class="lat-lon">
        <input type="text" id="latitude" name="latitude" placeholder="Latitude" readonly required>
        <input type="text" id="longitude" name="longitude" placeholder="Longitude" readonly required>
      </div>

      <button type="submit" class="btn-primary">Save Profile</button>
    </form>
  </div>

  <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
  <script>
    // Initialize map
    window.addEventListener('load', function() {
      var map = L.map('map').setView([-1.2921, 36.8219], 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
      }).addTo(map);

      // Locate user
      map.locate({
          setView: true,
          maxZoom: 15
        })
        .on('locationfound', function(e) {
          var lat = e.latlng.lat;
          var lng = e.latlng.lng;
          document.getElementById('latitude').value = lat;
          document.getElementById('longitude').value = lng;
          L.marker([lat, lng]).addTo(map).bindPopup("Garage Location").openPopup();
        })
        .on('locationerror', function() {
          alert("GPS access denied. Please enable location to complete profile.");
        });
    });
  </script>
</body>

</html>