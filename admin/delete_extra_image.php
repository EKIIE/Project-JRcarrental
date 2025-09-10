<?php
require("connect_db.php"); // เชื่อมต่อฐานข้อมูล

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $carId = $_POST["car_id"] ?? '';
  $filename = $_POST["filename"] ?? '';

  if ($carId && $filename) {
    // ลบไฟล์ใน server
    $path = "../uploads/cars/" . basename($filename);
    if (file_exists($path)) {
      unlink($path);
    }

    // ลบในฐานข้อมูล
    $stmt = $db->prepare("DELETE FROM car_images WHERE car_id = ? AND image_name = ?");
    $stmt->bind_param("is", $carId, $filename);
    $stmt->execute();

    echo json_encode(["status" => "success"]);
  } else {
    echo json_encode(["status" => "error", "msg" => "ข้อมูลไม่ครบ"]);
  }
}
