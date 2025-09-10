<?php
// staff/checkup_mark_cash.php
require("../db.php");
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$checkup_id = (int)($_POST['checkup_id'] ?? 0);
if (!$checkup_id) { echo "missing"; exit; }

mysqli_query($conn, "UPDATE checkups 
                     SET paid_cash = 1, payment_verified = 1
                     WHERE checkup_id = $checkup_id");

// ✅ อัปเดตรถคืนสถานะว่าง และ rental_status = 'completed'
$ch = mysqli_fetch_assoc(mysqli_query($conn, "SELECT rental_id FROM checkups WHERE checkup_id = $checkup_id"));
$rental_id = (int)$ch['rental_id'];

mysqli_query($conn, 
"    UPDATE rentals r
    JOIN cars c ON r.car_id = c.car_id
    SET r.rental_status = 'completed',
        c.status = 'available'
    WHERE r.rental_id = $rental_id
");

echo "<script>alert('บันทึกการชำระเงินสดแล้ว'); window.location='checkup_receipt.php?checkup_id={$checkup_id}';</script>";
