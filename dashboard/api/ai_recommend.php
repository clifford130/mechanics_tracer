<?php
session_start();
require_once(__DIR__ . "/../../forms/config.php");
require_once(__DIR__ . "/ai_extract.php");
require_once(__DIR__ . "/get_mechanics.php");

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

if (!$data || empty($data['problem'])) {
    echo json_encode(['success' => false, 'message' => 'Problem description is required']);
    exit();
}

$problem = trim($data['problem']);
$driver_lat = isset($data['lat']) ? floatval($data['lat']) : null;
$driver_lng = isset($data['lng']) ? floatval($data['lng']) : null;

// Haversine formula to compute distance in KM
function computeDistance($lat1, $lon1, $lat2, $lon2) {
    if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) return 50.0; // Assume far if unknown
    
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

global $conn;

// 1. Fetch all services
$services_list = [];
$res = $conn->query("SELECT id, service_name FROM services");
while ($row = $res->fetch_assoc()) {
    $services_list[] = [
        'id' => (int)$row['id'], 
        'service_name' => $row['service_name']
    ];
}

// 2. Call AI extraction
$extract_result = extractServiceIDs($problem, $services_list);

if (!$extract_result['success']) {
    echo json_encode(['success' => false, 'message' => 'AI processing failed: ' . $extract_result['message']]);
    exit();
}

$detected_service_ids = $extract_result['service_ids'];

if (empty($detected_service_ids)) {
    echo json_encode(['success' => false, 'message' => 'No services detected. Please rephrase your problem.']);
    exit();
}

// Detect service names for response
$detected_service_names = [];
foreach ($services_list as $s) {
    if (in_array($s['id'], $detected_service_ids)) {
        $detected_service_names[] = $s['service_name'];
    }
}

// 3. Get mechanics 
$mechanics = getMechanicsByServices($detected_service_ids);

if (empty($mechanics)) {
    echo json_encode([
        'success' => true, 
        'detected_services' => $detected_service_names, 
        'mechanics' => [],
        'message' => 'Services found, but no mechanics are currently offering them.'
    ]);
    exit();
}

$total_requested_count = count($detected_service_ids);

// 4. Score and Rank mechanics
foreach ($mechanics as &$m) {
    // 40% Service Match
    $service_score = 0;
    if ($total_requested_count > 0) {
        $service_score = ($m['match_count'] / $total_requested_count) * 0.40;
    }
    
    // 25% Distance
    $distance_score = 0;
    $dist_km = computeDistance($driver_lat, $driver_lng, $m['latitude'], $m['longitude']);
    $m['distance_km'] = round($dist_km, 2);
    
    // Inverse distance score: closer gets higher score. Cap max distance penalty.
    // E.g., at 0km = 1.0 (25%), at 4km = 0.2 (5%), at 24km = ~0.04 (1%)
    $dist_normalized = 1 / (1 + $dist_km);
    $distance_score = $dist_normalized * 0.25;

    // 25% Rating
    $rating_score = 0;
    if (isset($m['avg_rating'])) {
        $rating_score = ($m['avg_rating'] / 5.0) * 0.25;
    }

    // 10% Availability
    $avail_score = ((int)$m['availability'] === 1 ? 1.0 : 0.2) * 0.10;

    $m['final_score'] = $service_score + $distance_score + $rating_score + $avail_score;
}
unset($m);

// Sort descending by final score
usort($mechanics, function($a, $b) {
    return $b['final_score'] <=> $a['final_score'];
});

// Take Top 3
$top3 = array_slice($mechanics, 0, 3);

echo json_encode([
    'success' => true,
    'detected_service_ids' => $detected_service_ids,
    'detected_services' => $detected_service_names,
    'mechanics' => $top3
]);
?>
