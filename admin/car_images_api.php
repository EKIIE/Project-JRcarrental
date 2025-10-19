<?php
require('../db.php');
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบพารามิเตอร์
if (empty($_GET['car_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing car_id']);
    exit;
}

$car_id = (int)$_GET['car_id'];
if ($car_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid car_id']);
    exit;
}

// ดึงข้อมูลจากฐานข้อมูล
$q = mysqli_query($conn, "SELECT image_path FROM car_images WHERE car_id = $car_id");
$images = [];

if ($q && mysqli_num_rows($q) > 0) {
    while ($row = mysqli_fetch_assoc($q)) {
        $path = trim($row['image_path']);
        // ✅ ถ้าเป็น URL แล้ว ให้ใช้ URL ตรงๆ
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $images[] = $path;
        } 
        // ✅ ถ้าไม่ใช่ URL ให้เติม path ในเซิร์ฟเวอร์
        else {
            $images[] = "../uploads/cars/" . $path;
        }
    }
}

echo json_encode([
    'status' => 'success',
    'images' => $images
], JSON_UNESCAPED_UNICODE);
exit;
