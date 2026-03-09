<?php
session_start();
require_once("../config.php");

// Only drivers can access
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'driver'){
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get mechanic ID from URL
if(!isset($_GET['mechanic_id']) || empty($_GET['mechanic_id'])){
    die("Mechanic not specified.");
}

$mechanic_id = intval($_GET['mechanic_id']);

// Fetch mechanic details
$stmt = $conn->prepare("SELECT * FROM mechanics WHERE id = ?");
$stmt->bind_param("i", $mechanic_id);
$stmt->execute();
$result = $stmt->get_result();
$mechanic = $result->fetch_assoc();

if(!$mechanic){
    die("Mechanic not found.");
}

// Fetch driver details (for booking foreign key and vehicle info)
$stmt = $conn->prepare("SELECT id, vehicle_type, vehicle_make, vehicle_model, vehicle_year FROM drivers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();

if(!$driver){
    die("Driver profile not found. Please complete your profile first.");
}

$driver_id = $driver['id']; // Use this for bookings table

// Handle booking submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $services = $_POST['services'] ?? [];
    $problem_description = $_POST['problem_description'] ?? '';
    $driver_latitude = $_POST['driver_latitude'] ?? 0;
    $driver_longitude = $_POST['driver_longitude'] ?? 0;

    if(empty($services)){
        $error = "Please select at least one service.";
    } else {
        $services_str = implode(",", $services);
        $vehicle_type = $driver['vehicle_type']; // auto from profile

        $stmt = $conn->prepare("INSERT INTO bookings (driver_id, mechanic_id, service_requested, vehicle_type, notes, driver_latitude, driver_longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssdd", $driver_id, $mechanic_id, $services_str, $vehicle_type, $problem_description, $driver_latitude, $driver_longitude);
        $stmt->execute();

        if($stmt->affected_rows > 0){
            $booking_id = $stmt->insert_id;
            // Adjust chat.php path if needed
            header("Location: /mechanics_tracer/forms/chat.php?booking_id=".$booking_id);
            exit();
        } else {
            $error = "Failed to create booking. Please try again.";
        }
    }
}

// Convert mechanic services to array
$mechanic_services = explode(",", $mechanic['services_offered']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Book Mechanic</title>
<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; margin: 0; padding: 0; }
    .container { max-width: 700px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
    h2 { color: #333; text-align: center; }
    .mechanic-info { background: #e6f7ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid #1890ff; }
    .mechanic-info p { margin: 5px 0; }
    label { display: block; margin: 8px 0; font-weight: 500; }
    input[type="text"], textarea { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; margin-top: 5px; }
    input[type="checkbox"] { margin-right: 8px; }
    button { background: #1890ff; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; font-size: 16px; cursor: pointer; margin-top: 15px; transition: background 0.3s; }
    button:hover { background: #0056b3; }
    .error { color: red; margin-bottom: 15px; }
    .vehicle-info { background: #fffbe6; padding: 12px; border-left: 5px solid #ffc107; border-radius: 6px; margin: 15px 0; }
    a.edit-profile { color: #1890ff; font-weight: 500; text-decoration: none; }
    a.edit-profile:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="container">
    <h2>Book <?php echo htmlspecialchars($mechanic['garage_name']); ?></h2>

    <div class="mechanic-info">
        <p><strong>Experience:</strong> <?php echo htmlspecialchars($mechanic['experience']); ?> years</p>
        <p><strong>Vehicle Types:</strong> <?php echo htmlspecialchars($mechanic['vehicle_types']); ?></p>
    </div>

    <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>

    <form method="POST" id="bookingForm">
        <h3>Select Services Needed:</h3>
        <?php foreach($mechanic_services as $service): ?>
            <label>
                <input type="checkbox" name="services[]" value="<?php echo trim($service); ?>">
                <?php echo trim($service); ?>
            </label>
        <?php endforeach; ?>

        <h3>Describe Your Car Problem:</h3>
        <textarea name="problem_description" rows="4" placeholder="Describe your car problem here..."></textarea>

        <h3>Vehicle Info:</h3>
        <div class="vehicle-info">
            <?php echo htmlspecialchars($driver['vehicle_type'] . ' - ' . $driver['vehicle_make'] . ' ' . $driver['vehicle_model'] . ' (' . $driver['vehicle_year'] . ')'); ?>
        </div>
        <p><a href="/mechanics_tracer/forms/profile/driver_profile.php" class="edit-profile">Edit Profile if details are incorrect</a></p>

        <!-- Hidden fields for coordinates -->
        <input type="hidden" name="driver_latitude" id="driver_latitude">
        <input type="hidden" name="driver_longitude" id="driver_longitude">

        <button type="submit">Confirm Booking</button>
    </form>
</div>

<script>
// Get driver's location
if(navigator.geolocation){
    navigator.geolocation.getCurrentPosition(function(position){
        document.getElementById('driver_latitude').value = position.coords.latitude;
        document.getElementById('driver_longitude').value = position.coords.longitude;
    }, function(err){
        console.warn("Geolocation error: " + err.message);
    });
} else {
    alert("Geolocation is not supported by your browser.");
}
</script>

</body>
</html>