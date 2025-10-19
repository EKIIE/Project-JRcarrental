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
$q = mysqli_query($conn, "
    SELECT 
        m.maintenance_id,
        c.license_plate,
        m.maintenance_date,
        m.description,
        m.cost
    FROM maintenance m
    LEFT JOIN cars c ON m.car_id = c.car_id
    WHERE m.maintenance_date IS NOT NULL
");
while ($r = mysqli_fetch_assoc($q)) {
    $events[] = [
        'id' => 'm_' . $r['maintenance_id'],
        'title' => ' ' . ($r['license_plate'] ?? 'ไม่ระบุ'),
        'start' => $r['maintenance_date'],
        'description' => $r['description'] . ' (' . number_format($r['cost'], 0) . ' บาท)',
        'color' => '#e74c3c'
    ];
}

// Installments
$q2 = mysqli_query($conn, "
    SELECT 
        i.installment_id,
        c.license_plate,
        i.inst_date,
        i.company,
        i.monthly
    FROM installments i
    LEFT JOIN cars c ON i.car_id = c.car_id
    WHERE i.inst_date IS NOT NULL
");
while ($r = mysqli_fetch_assoc($q2)) {
    $events[] = [
        'id' => 'p_' . $r['installment_id'],
        'title' => 'ค่างวด: ' . ($r['license_plate'] ?? 'ไม่ระบุ'),
        'start' => $r['inst_date'],
        'description' => 'บริษัท ' . $r['company'] . ' (' . number_format($r['monthly'], 0) . ' บาท)',
        'color' => '#f39c12'
    ];
}

// Insurances
$q3 = mysqli_query($conn, "
    SELECT 
        insurance_id,
        c.license_plate,
        insu_date,
        insu_type,
        monthly
    FROM insurances i
    LEFT JOIN cars c ON i.car_id = c.car_id
    WHERE i.insu_date IS NOT NULL
");
while ($r = mysqli_fetch_assoc($q3)) {
    $events[] = [
        'id' => 'i_' . $r['insurance_id'],
        'title' => 'ประกัน: ' . ($r['license_plate'] ?? 'ไม่ระบุ'),
        'start' => $r['insu_date'],
        'description' => $r['insu_type'] . ' (' . number_format($r['monthly'], 0) . ' บาท)',
        'color' => '#27ae60'
    ];
}

echo json_encode($events, JSON_UNESCAPED_UNICODE);
exit;
