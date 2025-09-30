<?php
require('../db.php');
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$events = [];

// Maintenance
$q = mysqli_query($conn, "SELECT maintenance_id, maintenance_date, description FROM maintenance");
while ($r = mysqli_fetch_assoc($q)) {
    $events[] = [
        'id' => 'm_' . $r['maintenance_id'],
        'title' => 'ซ่อม: ' . substr($r['description'], 0, 20),
        'start' => $r['maintenance_date'],
        'color' => '#e74c3c' // แดง
    ];
}

// Installments
$q2 = mysqli_query($conn, "SELECT installment_id, inst_date, company FROM installments");
while ($r = mysqli_fetch_assoc($q2)) {
    $events[] = [
        'id' => 'p_' . $r['installment_id'],
        'title' => 'ค่างวด: ' . $r['company'],
        'start' => $r['inst_date'],
        'color' => '#f39c12' // ส้ม
    ];
}

// Insurances
$q3 = mysqli_query($conn, "SELECT insurance_id, insu_date, insu_type FROM insurances");
while ($r = mysqli_fetch_assoc($q3)) {
    $events[] = [
        'id' => 'i_' . $r['insurance_id'],
        'title' => 'ประกัน: ' . $r['insu_type'],
        'start' => $r['insu_date'],
        'color' => '#27ae60' // เขียว
    ];
}

echo json_encode($events, JSON_UNESCAPED_UNICODE);
exit;
