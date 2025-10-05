<?php
require("../db.php");
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $carId    = (int)($_POST["car_id"] ?? 0);
    $filename = trim($_POST["filename"] ?? '');

    if ($carId > 0 && $filename !== '') {
        // ลบไฟล์ใน server
        $path = "../uploads/cars/" . basename($filename);
        if (file_exists($path)) {
            @unlink($path);
        }

        // ลบจากฐานข้อมูล
        $stmt = mysqli_prepare($conn, "DELETE FROM car_images WHERE car_id = ? AND image_path = ?");
        mysqli_stmt_bind_param($stmt, "is", $carId, $filename);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "msg" => "ลบข้อมูล DB ไม่สำเร็จ"]);
        }
    } else {
        echo json_encode(["status" => "error", "msg" => "ข้อมูลไม่ครบ"]);
    }
}
