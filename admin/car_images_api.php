<?php
require('../db.php');
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['car_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing car_id']);
    exit;
}
$car_id = intval($_GET['car_id']);
$q = mysqli_query($conn, "SELECT image_path FROM car_images WHERE car_id = $car_id");
$images = [];
while ($row = mysqli_fetch_assoc($q)) {
    $images[] = $row['image_path'];
}
echo json_encode(['status' => 'success', 'images' => $images]);
