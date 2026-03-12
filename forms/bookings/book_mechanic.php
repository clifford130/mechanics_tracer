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

// Fetch driver first name for sidebar greeting
$driver_name = "Driver";
$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resUser = $stmt->get_result();
if($rowUser = $resUser->fetch_assoc()){
    $parts = explode(" ", $rowUser['full_name']);
    $driver_name = $parts[0];
}

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
            header("Location: /mechanics_tracer/forms/bookings/driver_bookings.php?booking_id=".$booking_id);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;}
        body{background:#f4f6f8;display:flex;flex-direction:column;min-height:100vh;}

        /* Layout: reuse driver dashboard structure */
        .app-wrapper{display:flex;flex:1;}
        .sidebar{
            width:260px;
            background:#1e293b;
            color:#e2e8f0;
            display:flex;
            flex-direction:column;
            position:sticky;
            top:0;
            height:100vh;
        }
        .sidebar-header{padding:22px 20px;border-bottom:1px solid #334155;}
        .sidebar-header h2{font-size:1.3rem;font-weight:600;color:#fff;margin-bottom:4px;}
        .sidebar-header p{font-size:0.9rem;color:#94a3b8;}
        .nav-links{flex:1;padding:18px 0;}
        .nav-links a{
            display:flex;align-items:center;gap:12px;
            padding:10px 20px;
            color:#cbd5e1;text-decoration:none;font-size:0.95rem;
        }
        .nav-links a i{width:22px;text-align:center;}
        .nav-links a:hover,.nav-links a.active{background:#2d3a4f;color:#fff;border-left:4px solid #0f172a;}
        .sidebar-footer{padding:18px 20px;border-top:1px solid #334155;}
        .sidebar-footer a{display:flex;align-items:center;gap:10px;color:#f87171;text-decoration:none;font-weight:500;font-size:0.95rem;}

        .main-content{
            flex:1;
            padding:22px 28px;
            max-width:1100px;
            margin:0 auto;
            width:100%;
        }
        .top-bar{
            display:flex;
            align-items:center;
            justify-content:space-between;
            flex-wrap:wrap;
            gap:10px;
            margin-bottom:18px;
        }
        .top-bar h1{font-size:1.6rem;color:#020617;}
        .top-bar p{color:#6b7280;font-size:0.95rem;}

        .quick-actions{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }
        .qa-btn{
            padding:7px 12px;
            border-radius:999px;
            border:1px solid #e5e7eb;
            background:#ffffff;
            color:#111827;
            font-size:0.85rem;
            display:inline-flex;
            align-items:center;
            gap:6px;
            text-decoration:none;
            cursor:pointer;
        }

        .booking-shell{
            background:#f9fafb;
            border-radius:20px;
            padding:18px 18px 20px;
            box-shadow:0 12px 30px rgba(15,23,42,0.08);
            border:1px solid #e5e7eb;
            display:grid;
            grid-template-columns:minmax(0,1.1fr) minmax(0,1.4fr);
            gap:18px;
        }
        .summary{
            border-right:1px solid #e5e7eb;
            padding-right:10px;
        }
        .summary-title{
            font-size:1.1rem;
            font-weight:600;
            color:#0f172a;
            margin-bottom:4px;
        }
        .summary-sub{
            font-size:0.9rem;
            color:#6b7280;
            margin-bottom:12px;
        }
        .tag-row{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;}
        .tag{
            padding:4px 9px;
            border-radius:999px;
            background:#e5e7eb;
            font-size:0.8rem;
            color:#111827;
        }
        .stat-list{margin-top:8px;font-size:0.9rem;color:#4b5563;}
        .stat-list p{margin:3px 0;}

        .form-side{
            padding-left:4px;
        }
        .error{color:#b91c1c;background:#fee2e2;border-radius:8px;padding:8px 10px;font-size:0.9rem;margin-bottom:10px;}
        form h2{font-size:1.05rem;margin-bottom:6px;color:#020617;}
        .services-list{
            display:flex;
            flex-wrap:wrap;
            gap:6px;
            margin-bottom:12px;
        }
        .service-chip{
            position:relative;
            display:inline-flex;
            align-items:center;
            gap:6px;
            padding:6px 10px;
            border-radius:999px;
            border:1px solid #d1d5db;
            background:#ffffff;
            font-size:0.85rem;
            cursor:pointer;
            transition:all .15s;
        }
        .service-chip input{
            position:absolute;
            opacity:0;
            inset:0;
            cursor:pointer;
        }
        .service-chip span.check{
            width:14px;height:14px;
            border-radius:999px;
            border:2px solid #9ca3af;
            display:inline-block;
        }
        .service-chip span.label{color:#111827;}
        .service-chip.checked{
            border-color:#0f172a;
            background:#111827;
        }
        .service-chip.checked span.check{
            border-color:#22c55e;
            background:#22c55e;
        }
        .service-chip.checked span.label{
            color:#f9fafb;
        }

        label.field-label{
            display:block;
            font-size:0.85rem;
            font-weight:600;
            color:#111827;
            margin:8px 0 4px;
        }
        textarea{
            width:100%;
            min-height:90px;
            resize:vertical;
            padding:9px 10px;
            border-radius:10px;
            border:1px solid #d1d5db;
            font-size:0.9rem;
            outline:none;
            background:#ffffff;
        }
        textarea:focus{
            border-color:#0f172a;
            box-shadow:0 0 0 2px rgba(15,23,42,0.2);
        }

        .vehicle-info{
            margin-top:8px;
            padding:8px 10px;
            border-radius:10px;
            background:#fefce8;
            border:1px solid #fde68a;
            font-size:0.88rem;
            color:#422006;
        }
        a.edit-profile{
            display:inline-flex;
            align-items:center;
            gap:6px;
            margin-top:6px;
            font-size:0.84rem;
            color:#111827;
            text-decoration:none;
            font-weight:500;
        }
        a.edit-profile:hover{text-decoration:underline;}

        .actions{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            margin-top:14px;
        }
        .btn-primary{
            flex:1;
            padding:10px 16px;
            border-radius:999px;
            border:none;
            background:#0f172a;
            color:#f9fafb;
            font-weight:600;
            font-size:0.9rem;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:8px;
        }
        .btn-secondary{
            padding:9px 12px;
            border-radius:999px;
            border:1px solid #d1d5db;
            background:#ffffff;
            color:#111827;
            font-size:0.85rem;
            cursor:pointer;
            display:inline-flex;
            align-items:center;
            gap:6px;
        }
        .hint{margin-top:6px;font-size:0.78rem;color:#6b7280;}

        @media (max-width: 900px){
            .app-wrapper{flex-direction:column;}
            .sidebar{
                position:relative;
                width:100%;
                height:auto;
            }
            .main-content{padding:16px 16px 22px;}
            .booking-shell{grid-template-columns:1fr;}
            .summary{border-right:none;border-bottom:1px solid #e5e7eb;padding-right:0;margin-bottom:10px;padding-bottom:10px;}
        }
    </style>
</head>
<body>

<div class="app-wrapper">
    <!-- Sidebar (same structure as driver dashboard) -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-car" style="margin-right:6px;"></i><?php echo htmlspecialchars($driver_name); ?></h2>
            <p>Booking with <?php echo htmlspecialchars($mechanic['garage_name']); ?></p>
        </div>
        <nav class="nav-links">
            <a href="/mechanics_tracer/dashboard/driver_dashboard.php"><i class="fas fa-map"></i> Map & mechanics</a>
            <a href="/mechanics_tracer/forms/bookings/driver_bookings.php"><i class="fas fa-calendar-check"></i> My bookings</a>
            <a href="/mechanics_tracer/forms/profile/driver_profile.php"><i class="fas fa-user"></i> My profile</a>
        </nav>
        <div class="sidebar-footer">
            <a href="/mechanics_tracer/forms/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main-content">
        <div class="top-bar">
            <div>
                <h1>Confirm your request</h1>
                <p>Review details, choose services, then send the booking.</p>
            </div>
            <div class="quick-actions">
                <a href="/mechanics_tracer/dashboard/driver_dashboard.php" class="qa-btn">
                    <i class="fas fa-map-marked-alt"></i> Back to map
                </a>
                <a href="/mechanics_tracer/forms/bookings/driver_bookings.php" class="qa-btn">
                    <i class="fas fa-list-ul"></i> View my bookings
                </a>
            </div>
        </div>

        <section class="booking-shell">
            <div class="summary">
                <div class="summary-title"><?php echo htmlspecialchars($mechanic['garage_name']); ?></div>
                <div class="summary-sub">Specialized in <?php echo htmlspecialchars($mechanic['vehicle_types']); ?></div>

                <div class="tag-row">
                    <span class="tag"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($mechanic['experience']); ?> yrs experience</span>
                    <?php if(!empty(trim($mechanic_services[0]))): ?>
                        <span class="tag"><i class="fas fa-tools"></i> <?php echo htmlspecialchars(trim($mechanic_services[0])); ?> & more</span>
                    <?php endif; ?>
                </div>

                <div class="stat-list">
                    <p><strong>Your vehicle:</strong>
                        <?php echo htmlspecialchars($driver['vehicle_type'] . ' - ' . $driver['vehicle_make'] . ' ' . $driver['vehicle_model'] . ' (' . $driver['vehicle_year'] . ')'); ?>
                    </p>
                    <p style="font-size:0.86rem;margin-top:4px;color:#6b7280;">
                        Your live location will be attached to help the mechanic find you quickly.
                    </p>
                </div>
            </div>

            <div class="form-side" aria-label="Booking form">
                <?php if(isset($error)): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" id="bookingForm">
                    <h2>What do you need help with?</h2>
                    <div class="services-list">
                        <?php foreach($mechanic_services as $service): ?>
                            <?php $trimmed = trim($service); if($trimmed === '') continue; ?>
                            <label class="service-chip">
                                <input type="checkbox" name="services[]" value="<?php echo htmlspecialchars($trimmed); ?>" onchange="this.parentElement.classList.toggle('checked', this.checked)">
                                <span class="check"></span>
                                <span class="label"><?php echo htmlspecialchars($trimmed); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <label for="problem_description" class="field-label">Describe the problem</label>
                    <textarea id="problem_description" name="problem_description" placeholder="Example: The car struggles to start in the morning and makes a clicking sound..."></textarea>

                    <!-- Hidden fields for coordinates -->
                    <input type="hidden" name="driver_latitude" id="driver_latitude">
                    <input type="hidden" name="driver_longitude" id="driver_longitude">

                    <div class="actions">
                        <button type="button" class="btn-secondary" onclick="window.history.back();">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-paper-plane"></i> Send booking
                        </button>
                    </div>
                    <p class="hint">You can track and manage this request from the “My bookings” section.</p>
                </form>
            </div>
        </section>
    </main>
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