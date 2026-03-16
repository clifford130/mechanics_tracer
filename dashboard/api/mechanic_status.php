<?php
session_start();
require_once(__DIR__ . "/../../forms/config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mechanic') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$new_status = isset($_POST['availability']) ? (int)$_POST['availability'] : 0;

$stmt = $conn->prepare("UPDATE mechanics SET availability = ? WHERE user_id = ?");
$stmt->bind_param("ii", $new_status, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'availability' => $new_status]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
