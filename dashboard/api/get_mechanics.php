<?php
// get_mechanics.php - called internally by ai_recommend.php or client-side
require_once(__DIR__ . "/../../forms/config.php");

function getMechanicsByServices($service_ids) {
    global $conn;
    
    if (empty($service_ids)) {
        return [];
    }
    
    // Build JSON_CONTAINS condition
    $conditions = [];
    $params = [];
    $types = '';
    
    foreach ($service_ids as $sid) {
        $conditions[] = "JSON_CONTAINS(m.service_ids, ?)";
        $params[] = json_encode((int)$sid);
        $types .= 's';
    }
    
    $where = implode(' OR ', $conditions);
    
    $sql = "SELECT m.id, m.garage_name, m.vehicle_types, m.services_offered, 
                   m.latitude, m.longitude, m.experience, m.service_ids, m.availability,
                   ROUND(AVG(r.stars), 1) as avg_rating, COUNT(r.id) as rating_count
            FROM mechanics m
            LEFT JOIN ratings r ON r.mechanic_id = m.id
            WHERE $where
            GROUP BY m.id";
            
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $mechanics = [];
    while ($row = $result->fetch_assoc()) {
        $row['latitude'] = floatval($row['latitude']);
        $row['longitude'] = floatval($row['longitude']);
        $mechanic_services = json_decode($row['service_ids'] ?? '[]', true) ?: [];
        $row['match_count'] = count(array_intersect($service_ids, $mechanic_services));
        $mechanics[] = $row;
    }
    
    return $mechanics;
}

// If called directly via HTTP GET (for testing or frontend use)
if (basename($_SERVER['PHP_SELF']) == 'get_mechanics.php') {
    header('Content-Type: application/json');
    $service_ids = isset($_GET['services']) ? array_map('intval', $_GET['services']) : [];
    echo json_encode(getMechanicsByServices($service_ids));
    exit;
}
?>
