<?php
include("../db.php");
session_start();

// ตรวจสอบว่ามีข้อมูลส่งมาครบ
if (!isset($_POST['car_id'], $_POST['start_date'], $_POST['end_date'])) {
    echo json_encode(["available" => false, "msg" => "ข้อมูลไม่ครบ"]);
    exit;
}

$car_id = (int)$_POST['car_id'];

// เพิ่มเวลาครอบช่วงวัน เพื่อให้ match กับข้อมูลในฐานข้อมูล (ถ้ามีเวลาเก็บอยู่)
$start_date = $_POST['start_date'] . " 00:00:00";
$end_date   = $_POST['end_date'] . " 23:59:59";

$sql = "SELECT * FROM bookings 
        WHERE car_id = ? 
          AND booking_status IN ('pending','confirmed')
          AND (
              (start_date <= ? AND end_date >= ?) 
              OR (start_date <= ? AND end_date >= ?) 
              OR (start_date >= ? AND end_date <= ?)
          )";

$stmt = $conn->prepare($sql);
$stmt->bind_param("issssss", 
    $car_id, 
    $start_date, $start_date, 
    $end_date, $end_date, 
    $start_date, $end_date
);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    echo json_encode(["available" => false, "msg" => "รถไม่ว่างให้บริการในช่วงวันที่เลือก"]);
} else {
    echo json_encode(["available" => true]);
}
?>
