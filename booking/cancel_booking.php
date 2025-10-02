<?php
include("../db.php");
session_start();

if (!isset($_SESSION['user_id'])) {
  echo json_encode(["success" => false, "msg" => "กรุณาเข้าสู่ระบบ"]);
  exit();
}

$booking_id = (int)($_POST['booking_id'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

if ($booking_id <= 0) {
  echo json_encode(["success" => false, "msg" => "ไม่พบรหัสการจอง"]);
  exit();
}

// ตรวจสอบว่าเป็นของ user นี้จริง
$sql = "UPDATE bookings 
        SET booking_status = 'cancelled' 
        WHERE booking_id = ? AND user_id = ? AND booking_status IN ('pending','confirmed')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
  echo json_encode(["success" => true, "msg" => "ยกเลิกการจองเรียบร้อย"]);
} else {
  echo json_encode(["success" => false, "msg" => "ไม่สามารถยกเลิกการจองนี้ได้"]);
}
