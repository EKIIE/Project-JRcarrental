<?php
require('../db.php');
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id) {
    echo json_encode(['error' => 'Missing ID']);
    exit;
}

// แยกประเภทจาก prefix เช่น m_, i_, p_, rent_
if (strpos($id, 'm_') === 0) {
    $realId = (int)str_replace('m_', '', $id);
    $q = mysqli_query($conn, "SELECT * FROM maintenance WHERE maintenance_id=$realId");
    $data = mysqli_fetch_assoc($q);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

if (strpos($id, 'i_') === 0) {
    $realId = (int)str_replace('i_', '', $id);
    $q = mysqli_query($conn, "SELECT * FROM insurances WHERE insurance_id=$realId");
    $data = mysqli_fetch_assoc($q);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

if (strpos($id, 'p_') === 0) {
    $realId = (int)str_replace('p_', '', $id);
    $q = mysqli_query($conn, "SELECT * FROM installments WHERE installment_id=$realId");
    $data = mysqli_fetch_assoc($q);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

if (strpos($id, 'rent_') === 0) {
    $realId = (int)str_replace('rent_', '', $id);
    $q = mysqli_query($conn, "
        SELECT r.*, b.*, c.brand, c.model, c.license_plate
        FROM rentals r
        JOIN bookings b ON r.booking_id=b.booking_id
        JOIN cars c ON r.car_id=c.car_id
        WHERE r.rental_id=$realId
    ");
    $data = mysqli_fetch_assoc($q);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
