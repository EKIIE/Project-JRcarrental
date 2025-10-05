<?php
require('../db.php');
session_start();
header('Content-Type: application/json; charset=utf-8');

// ต้องให้ admin หรือ user ที่มีสิทธิ์เข้าถึง
if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$events = [];

// เอามาจาก rentals + bookings
$q = mysqli_query($conn, "SELECT r.rental_id, b.start_date, b.end_date, c.license_plate 
                          FROM rentals r 
                            JOIN cars c ON r.car_id = c.car_id
                          JOIN bookings b ON r.booking_id = b.booking_id");
while ($r = mysqli_fetch_assoc($q)) {
    $events[] = [
        'id' => 'rent_' . $r['rental_id'],
        'title' => 'ทะเบียน ' . $r['license_plate'],
        'start' => $r['start_date'],
        'end'   => $r['end_date'],
        'color' => '#3498db' // น้ำเงิน
    ];
}

echo json_encode($events, JSON_UNESCAPED_UNICODE);
exit;
