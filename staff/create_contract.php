<?php
session_start();
require("../db.php");
date_default_timezone_set('Asia/Bangkok');

// อนุญาตทั้ง staff และ admin
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    echo "Access Denied";
    exit();
}

$booking_id = (int)($_POST['booking_id'] ?? 0);
if ($booking_id <= 0) {
    die("Invalid booking_id");
}

$emp_id = (int)($_SESSION['employee_id'] ?? 0);

$booking = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM bookings WHERE booking_id={$booking_id}"));
if (!$booking) {
    die("ไม่พบข้อมูลการจอง");
}

$user_id     = (int)$booking['user_id'];
$car_id      = (int)$booking['car_id'];

$pickup_date = $booking['start_date'];
$return_date = $booking['end_date'];
$total_price = (float)$booking['total_price'];

// ดึงราคาต่อวัน
$car  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM cars WHERE car_id={$car_id}")) ?: [];
// $rate = isset($booking['rate']) ? (float)$booking['rate'] : (float)($car['daily_rate'] ?? 0);
$rate = isset($booking['rate']) ? (float)$booking['rate'] : 0;
if ($rate <= 0 && !empty($car['daily_rate'])) {
    $rate = (float)$car['daily_rate'];
}
$days = max(1, (int)ceil((strtotime($return_date) - strtotime($pickup_date)) / 86400));
// $rent_total = ($rate > 0 ? $rate : 0) * $days;
$rent_total = round($rate * $days, 2);

// transaction
mysqli_begin_transaction($conn);
try {
    $exists = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT rental_id FROM rentals WHERE booking_id={$booking_id}")
    );

    if (!$exists) {
        $sql = "INSERT INTO rentals
            (booking_id, user_id, car_id, emp_deliver, actual_pickup_date, rental_status, contract_file, total_amount, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'active', NULL, ?, NOW(), NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            'iiiisd',   // booking_id=i, user_id=i, car_id=i, emp_deliver=i, pickup_date=s, rent_total=d
            $booking_id,
            $user_id,
            $car_id,
            $emp_id,
            $pickup_date,
            $rent_total
        );
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(mysqli_stmt_error($stmt));
        }
    }

    // อัปเดตสถานะ booking และรถ
    $b1 = mysqli_prepare($conn, "UPDATE bookings SET booking_status='confirmed', payment_status='approved' WHERE booking_id=?");
    mysqli_stmt_bind_param($b1, 'i', $booking_id);
    if (!mysqli_stmt_execute($b1)) {
        throw new Exception(mysqli_stmt_error($b1));
    }

    $u2 = mysqli_prepare($conn, "UPDATE cars SET status='rented' WHERE car_id=?");
    mysqli_stmt_bind_param($u2, 'i', $car_id);
    if (!mysqli_stmt_execute($u2)) {
        throw new Exception(mysqli_stmt_error($u2));
    }

    mysqli_commit($conn);
    echo "<script>alert('สร้างสัญญาเรียบร้อยแล้ว'); window.location='staff_dashboard.php';</script>";
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "<script>alert('เกิดข้อผิดพลาด: {$e->getMessage()}'); window.location='staff_dashboard.php';</script>";
}
exit;
