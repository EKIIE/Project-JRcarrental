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
$user_id    = (int)($_POST['user_id'] ?? 0);
$car_id     = (int)($_POST['car_id'] ?? 0);
$emp_id     = (int)($_SESSION['employee_id'] ?? 0); // พนักงานที่ login อยู่

if ($booking_id <= 0 || $user_id <= 0 || $car_id <= 0 || $emp_id <= 0) {
  die("Invalid data");
}

$booking_status  = 'waiting';
$payment_status  = 'approved';

mysqli_begin_transaction($conn);

try {
  // 1) อัปเดตสถานะการจอง
  $sql1 = "UPDATE bookings
           SET booking_status=?, payment_status=?
           WHERE booking_id=?";
  $stmt1 = mysqli_prepare($conn, $sql1);
  mysqli_stmt_bind_param($stmt1, 'ssi', $booking_status, $payment_status, $booking_id);
  if (!mysqli_stmt_execute($stmt1)) {
    throw new Exception(mysqli_stmt_error($stmt1));
  }

  mysqli_commit($conn);
  header("Location: staff_dashboard.php?ok=1");
  exit();
} catch (Exception $e) {
  mysqli_rollback($conn);
  die("Confirm failed: " . $e->getMessage());
}
