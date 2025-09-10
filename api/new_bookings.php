<?php
// api/new_bookings.php
require("../db.php");
header('Content-Type: application/json');

$since = isset($_GET['since']) ? (int)$_GET['since'] : 0; // unix timestamp จาก client
$sql = "SELECT COUNT(*) AS c 
        FROM bookings 
        WHERE payment_status='waiting' 
          AND created_at > FROM_UNIXTIME(?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $since);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
echo json_encode([
  'count' => (int)$row['c'],
  'server_time' => time(), // ส่งกลับให้ client sync เวลา
]);
