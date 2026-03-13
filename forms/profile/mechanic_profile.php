<?php
session_start();
require_once("../config.php");

// Protect page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";

// Fetch existing mechanic profile if it exists (for editing)
$existing = null;
$stmt = $conn->prepare("SELECT * FROM mechanics WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resExisting = $stmt->get_result();
if ($resExisting && $resExisting->num_rows > 0) {
    $existing = $resExisting->fetch_assoc();
    // Decode the JSON service_ids into an array
    $existing['service_ids_array'] = json_decode($existing['service_ids'] ?? '[]', true);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $garage_name = trim($_POST['garage_name'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $certifications = trim($_POST['certifications'] ?? '');

    // Vehicle types: stored as comma-separated string
    $vehicle_types = isset($_POST['vehicle_types']) 
        ? implode(",", $_POST['vehicle_types']) 
        : '';

    // ---- Service IDs: sanitize and convert to integers ----
    $raw_ids = $_POST['service_ids'] ?? [];
    // Convert to integers and remove any that are not > 0
    $service_ids = array_filter(array_map('intval', $raw_ids), function($id) { return $id > 0; });
    $service_ids_json = json_encode(array_values($service_ids)); // re-index

    // For debugging (remove after testing)
    error_log("POST service_ids raw: " . print_r($raw_ids, true));
    error_log("Sanitized service_ids: " . print_r($service_ids, true));
    error_log("JSON to save: " . $service_ids_json);

    // Build comma-separated service names for the old services_offered column
    $service_names = [];
    if (!empty($service_ids)) {
        $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
        $types = str_repeat('i', count($service_ids));
        $stmt = $conn->prepare("SELECT service_name FROM services WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$service_ids);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $service_names[] = $row['service_name'];
        }
    }
    $services_offered = implode(',', $service_names);

    $latitude = trim($_POST['latitude'] ?? '');
    $longitude = trim($_POST['longitude'] ?? '');
    $availability = 1;

    // Validation
    if (empty($garage_name) || empty($experience) || empty($vehicle_types) || empty($service_ids)) {
        $error = "Please fill in all required fields and select at least one service.";
    } else {

        // Check if mechanic profile exists
        $check = $conn->prepare("SELECT id FROM mechanics WHERE user_id = ?");
        $check->bind_param("i", $user_id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            // UPDATE profile
            $sql = "UPDATE mechanics SET
                    garage_name=?,
                    experience=?,
                    certifications=?,
                    vehicle_types=?,
                    services_offered=?,
                    service_ids=?,
                    latitude=?,
                    longitude=?,
                    availability=?
                    WHERE user_id=?";

            $stmt = $conn->prepare($sql);
            // types: s, i, s, s, s, s, s, s, i, i
            $stmt->bind_param(
                "sissssssii",
                $garage_name,
                $experience,
                $certifications,
                $vehicle_types,
                $services_offered,
                $service_ids_json,
                $latitude,
                $longitude,
                $availability,
                $user_id
            );
        } else {
            // INSERT new profile
            $sql = "INSERT INTO mechanics
                    (user_id, garage_name, experience, certifications, vehicle_types, services_offered, service_ids, latitude, longitude, availability)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            // types: i, s, i, s, s, s, s, s, s, i
            $stmt->bind_param(
                "isissssssi",
                $user_id,
                $garage_name,
                $experience,
                $certifications,
                $vehicle_types,
                $services_offered,
                $service_ids_json,
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
            // Log the actual error and show a generic message
            error_log("Mechanic profile error: " . $stmt->error);
            // Temporarily show the actual error for debugging (remove after fixing)
            $error = "Database error: " . $stmt->error;  // ← remove this line after testing
            // $error = "Something went wrong while saving your profile. Please try again.";
        }
    }
}

// Load available services grouped by category from services table
$serviceCategories = [];
$svcRes = $conn->query("SELECT id, category, service_name FROM services ORDER BY category, service_name");
if ($svcRes) {
    while ($row = $svcRes->fetch_assoc()) {
        $cat = $row['category'];
        if (!isset($serviceCategories[$cat])) $serviceCategories[$cat] = [];
        $serviceCategories[$cat][] = $row;
    }
}

// Pre-fill values from POST or existing mechanic record
function old_or_existing($key, $default = '') {
    global $existing;
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        return htmlspecialchars(trim($_POST[$key] ?? ''), ENT_QUOTES);
    }
    if ($existing && isset($existing[$key])) {
        return htmlspecialchars($existing[$key], ENT_QUOTES);
    }
    return htmlspecialchars($default, ENT_QUOTES);
}

// Determine selected vehicle types
$selected_vehicle_types = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['vehicle_types'])) {
    $selected_vehicle_types = (array)$_POST['vehicle_types'];
} elseif ($existing && !empty($existing['vehicle_types'])) {
    $selected_vehicle_types = array_map('trim', explode(',', $existing['vehicle_types']));
}

// Determine selected service IDs (ensure integers)
$selected_service_ids = [];
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['service_ids'])) {
    $selected_service_ids = array_map('intval', (array)$_POST['service_ids']);
} elseif ($existing && !empty($existing['service_ids_array'])) {
    $selected_service_ids = $existing['service_ids_array'];
}

$lat_value = '';
$lng_value = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lat_value = htmlspecialchars($_POST['latitude'] ?? '', ENT_QUOTES);
    $lng_value = htmlspecialchars($_POST['longitude'] ?? '', ENT_QUOTES);
} elseif ($existing) {
    $lat_value = htmlspecialchars($existing['latitude'], ENT_QUOTES);
    $lng_value = htmlspecialchars($existing['longitude'], ENT_QUOTES);
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
    /* ===== Your existing CSS (keep exactly as before) ===== */
    body{
      margin:0;
      font-family:'Segoe UI',sans-serif;
      background:#f4f6f8;
      color:#111827;
    }
    .page-shell{
      max-width:1120px;
      margin:32px auto 40px;
      padding:0 16px;
    }
    .page-header{
      margin-bottom:18px;
    }
    .page-title{
      font-size:1.8rem;
      font-weight:650;
      color:#020617;
      margin-bottom:4px;
    }
    .page-subtitle{
      font-size:0.96rem;
      color:#6b7280;
    }
    .profile-layout{
      display:grid;
      grid-template-columns:minmax(0,1.1fr) minmax(0,1.4fr);
      gap:18px;
      align-items:flex-start;
    }
    .card{
      background:#ffffff;
      border-radius:16px;
      padding:18px 18px 16px;
      box-shadow:0 10px 25px rgba(15,23,42,0.06);
      border:1px solid #e5e7eb;
    }
    .card-header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      margin-bottom:10px;
    }
    .card-title{
      font-size:1rem;
      font-weight:600;
      color:#020617;
    }
    .card-sub{
      font-size:0.8rem;
      color:#6b7280;
      margin-bottom:8px;
    }
    .form-group{
      display:flex;
      flex-direction:column;
      margin-bottom:12px;
    }
    .form-group label{
      font-size:0.9rem;
      font-weight:600;
      margin-bottom:4px;
      color:#111827;
    }
    .form-group small{
      font-size:0.78rem;
      color:#9ca3af;
      margin-top:2px;
    }
    .text-input{
      padding:10px 11px;
      border-radius:10px;
      border:1px solid #d1d5db;
      font-size:0.94rem;
      outline:none;
      background:#ffffff;
    }
    .text-input:focus{
      border-color:#0f172a;
      box-shadow:0 0 0 2px rgba(15,23,42,0.18);
    }
    .pill-checkboxes{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
    }
    .pill-label{
      position:relative;
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:7px 12px;
      border-radius:999px;
      border:1px solid #d1d5db;
      background:#f9fafb;
      font-size:0.86rem;
      cursor:pointer;
      transition:all .15s ease;
    }
    .pill-label input{
      position:absolute;
      inset:0;
      opacity:0;
      cursor:pointer;
    }
    .pill-dot{
      width:12px;height:12px;
      border-radius:999px;
      border:2px solid #9ca3af;
      background:#f9fafb;
    }
    .pill-label span.txt{
      color:#111827;
    }
    .pill-label:hover{
      background:#e5e7eb;
    }
    .pill-label.active{
      background:#0f172a;
      border-color:#0f172a;
    }
    .pill-label.active .pill-dot{
      border-color:#22c55e;
      background:#22c55e;
    }
    .pill-label.active span.txt{
      color:#f9fafb;
    }
    .services-grid{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:10px;
    }
    .service-card{
      border-radius:14px;
      border:1px solid #e5e7eb;
      padding:10px 11px 8px;
      background:#ffffff;
    }
    .service-card-header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      margin-bottom:6px;
    }
    .service-card-title{
      font-size:0.9rem;
      font-weight:600;
      color:#020617;
    }
    .info-icon{
      width:18px;
      height:18px;
      border-radius:999px;
      border:1px solid #d1d5db;
      font-size:0.7rem;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      color:#6b7280;
      position:relative;
      cursor:default;
    }
    .info-icon:hover{
      background:#f3f4f6;
    }
    .info-icon span.tooltip{
      position:absolute;
      bottom:120%;
      right:0;
      transform:translateY(4px);
      background:#111827;
      color:#f9fafb;
      padding:6px 8px;
      border-radius:6px;
      font-size:0.75rem;
      max-width:220px;
      line-height:1.35;
      opacity:0;
      pointer-events:none;
      transition:opacity .15s ease;
      z-index:10;
    }
    .info-icon:hover span.tooltip{
      opacity:1;
    }
    .service-list{
      display:flex;
      flex-wrap:wrap;
      gap:6px;
    }
    .service-chip{
      position:relative;
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:5px 10px;
      border-radius:999px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
      font-size:0.8rem;
      cursor:pointer;
      transition:all .15s;
    }
    .service-chip input{
      position:absolute;
      inset:0;
      opacity:0;
      cursor:pointer;
    }
    .service-chip:hover{
      background:#e5e7eb;
    }
    .service-chip.active{
      background:#111827;
      border-color:#111827;
      color:#f9fafb;
    }
    #map{
      width:100%;
      height:230px;
      border-radius:12px;
      margin-top:6px;
      border:1px solid #e5e7eb;
    }
    .lat-lon{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      margin-top:8px;
    }
    .lat-lon input{
      flex:1;
      padding:8px 9px;
      border-radius:9px;
      border:1px solid #e5e7eb;
      font-size:0.86rem;
      background:#f9fafb;
    }
    .error{
      background:#fee2e2;
      color:#b91c1c;
      padding:9px 10px;
      border-radius:10px;
      font-size:0.9rem;
      margin-bottom:12px;
      text-align:center;
    }
    .form-actions{
      display:flex;
      justify-content:flex-end;
      margin-top:16px;
    }
    .btn-primary{
      padding:11px 22px;
      border-radius:999px;
      border:none;
      background:#0f172a;
      color:#f9fafb;
      font-size:0.95rem;
      font-weight:600;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      gap:8px;
      box-shadow:0 10px 18px rgba(15,23,42,0.25);
    }
    .btn-primary:hover{
      filter:brightness(1.05);
    }
    @media (max-width: 900px){
      .profile-layout{
        grid-template-columns:1fr;
      }
      .page-shell{
        margin-top:22px;
      }
      .form-actions{
        justify-content:center;
      }
      .btn-primary{
        width:100%;
        justify-content:center;
      }
    }
  </style>
</head>
<body>

<div class="page-shell">
  <header class="page-header">
    <h1 class="page-title">Complete Your Garage Profile for Accurate Recommendations</h1>
    <p class="page-subtitle">We use your garage details, vehicle types and services to match you with the right drivers.</p>
  </header>

  <?php if (isset($error)): ?>
    <div class="error"><?php echo $error; ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="profile-layout">
      <!-- Left: garage info + vehicle types + location -->
      <section class="card" aria-label="Garage information">
        <div class="card-header">
          <div class="card-title">Garage information</div>
        </div>
        <p class="card-sub">Tell drivers who you are and where your garage is located.</p>

        <div class="form-group">
          <label for="garage_name">Garage Name <span style="color:#b91c1c">*</span></label>
          <input type="text" class="text-input" name="garage_name" id="garage_name" placeholder="e.g. Skyline Auto Garage" required
                 value="<?php echo old_or_existing('garage_name'); ?>">
        </div>

        <div class="form-group">
          <label for="experience">Years of Experience <span style="color:#b91c1c">*</span></label>
          <input type="number" class="text-input" name="experience" id="experience" min="0" max="80"
                 placeholder="How many years have you been a mechanic?" required
                 value="<?php echo old_or_existing('experience'); ?>">
        </div>

        <div class="form-group">
          <label for="certifications">Certifications / Skills <span style="font-weight:400;color:#9ca3af;">(optional)</span></label>
          <input type="text" class="text-input" name="certifications" id="certifications"
                 placeholder="e.g. Engine specialist, Brake expert, ECU diagnostics"
                 value="<?php echo old_or_existing('certifications'); ?>">
          <small>Add any formal training or special skills you want drivers to see.</small>
        </div>

        <div class="form-group">
          <label>Vehicle Types You Service <span style="color:#b91c1c">*</span></label>
          <div class="pill-checkboxes">
            <?php
            $vehicleOptions = ['Car','Truck','Motorbike','Van','Bus'];
            foreach ($vehicleOptions as $v):
                $isChecked = in_array($v, $selected_vehicle_types);
            ?>
              <label class="pill-label <?php echo $isChecked ? 'active' : ''; ?>">
                <input type="checkbox" name="vehicle_types[]" value="<?php echo htmlspecialchars($v, ENT_QUOTES); ?>"
                       <?php echo $isChecked ? 'checked' : ''; ?>
                       onchange="this.parentElement.classList.toggle('active', this.checked)">
                <span class="pill-dot"></span>
                <span class="txt"><?php echo htmlspecialchars($v); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label>Garage Location <span style="color:#b91c1c">*</span></label>
          <small>We’ll use this to calculate distance and ETA for nearby drivers.</small>
          <div id="map"></div>
          <div class="lat-lon">
            <input type="text" id="latitude" name="latitude" placeholder="Latitude" readonly required value="<?php echo $lat_value; ?>">
            <input type="text" id="longitude" name="longitude" placeholder="Longitude" readonly required value="<?php echo $lng_value; ?>">
          </div>
        </div>
      </section>

      <!-- Right: services offered (using service IDs) -->
      <section class="card" aria-label="Services offered">
        <div class="card-header">
          <div class="card-title">Services you offer</div>
        </div>
        <p class="card-sub">Pick all services you can confidently provide. This powers search and bookings.</p>

        <div class="services-grid">
          <?php
          $categoryHelp = [
              'Engine Services' => 'Diagnostics, repairs and maintenance related to the engine and fuel system.',
              'Electrical Services' => 'Battery, alternator, wiring, starters and electronic diagnostics.',
              'Brake System' => 'Brake pads, discs, fluids and ABS related work.',
              'Tire & Wheel' => 'Tire changes, puncture repair, alignment and balancing.',
              'Transmission' => 'Clutch, gearbox and transmission related repairs.',
              'Suspension & Steering' => 'Shocks, springs and steering stability issues.',
              'General Maintenance' => 'Regular service items like oil, filters and spark plugs.',
              'Emergency Roadside' => 'On-the-road help like jump starts, towing and flat tires.'
          ];

          foreach ($serviceCategories as $cat => $rows):
            $tip = $categoryHelp[$cat] ?? 'Services related to this system of the vehicle.';
          ?>
            <div class="service-card">
              <div class="service-card-header">
                <div class="service-card-title"><?php echo htmlspecialchars($cat); ?></div>
                <div class="info-icon">
                  ?
                  <span class="tooltip"><?php echo htmlspecialchars($tip); ?></span>
                </div>
              </div>
              <div class="service-list">
                <?php foreach ($rows as $svc):
                    $isSel = in_array($svc['id'], $selected_service_ids);
                ?>
                  <label class="service-chip <?php echo $isSel ? 'active' : ''; ?>">
                    <input type="checkbox" name="service_ids[]" value="<?php echo $svc['id']; ?>"
                           <?php echo $isSel ? 'checked' : ''; ?>
                           onchange="this.parentElement.classList.toggle('active', this.checked)">
                    <?php echo htmlspecialchars($svc['service_name']); ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-primary">
        Save garage profile
      </button>
    </div>
  </form>
</div>

<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script>
  window.addEventListener('load', function() {
    var initialLat = <?php echo $lat_value !== '' ? floatval($lat_value) : -1.2921; ?>;
    var initialLng = <?php echo $lng_value !== '' ? floatval($lng_value) : 36.8219; ?>;

    var map = L.map('map').setView([initialLat, initialLng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var marker = null;

    if (!isNaN(initialLat) && !isNaN(initialLng) && "<?php echo $lat_value; ?>" !== "" && "<?php echo $lng_value; ?>" !== "") {
      marker = L.marker([initialLat, initialLng]).addTo(map).bindPopup("Garage Location").openPopup();
    }

    if ("<?php echo $lat_value; ?>" === "" || "<?php echo $lng_value; ?>" === "") {
      map.locate({ setView: true, maxZoom: 15 })
        .on('locationfound', function(e) {
          var lat = e.latlng.lat;
          var lng = e.latlng.lng;
          document.getElementById('latitude').value = lat;
          document.getElementById('longitude').value = lng;
          if (marker) map.removeLayer(marker);
          marker = L.marker([lat, lng]).addTo(map).bindPopup("Garage Location").openPopup();
        })
        .on('locationerror', function() {});
    }
  });
</script>
</body>
</html>