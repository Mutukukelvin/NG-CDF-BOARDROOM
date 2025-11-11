<?php
// Simple endpoint that returns booked time_slot values for a given room + date
header('Content-Type: application/json; charset=utf-8');
include 'db_connect.php';

$room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
$booking_date = isset($_POST['date']) ? $_POST['date'] : '';

if (!$room_id || !$booking_date) {
    echo json_encode(['booked' => []]);
    $conn->close();
    exit();
}

$sql = "SELECT time_slot FROM bookings WHERE room_id = ? AND booking_date = ? AND status IN ('Pending','Approved')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $room_id, $booking_date);
$stmt->execute();
$res = $stmt->get_result();

$booked = [];
while ($row = $res->fetch_assoc()) {
    $booked[] = $row['time_slot'];
}

$stmt->close();
$conn->close();

echo json_encode(['booked' => $booked]);
?>