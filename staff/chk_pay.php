<?php
// staff/checkup_pay_upload.php
require("../db.php");
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$checkup_id = (int)($_POST['checkup_id'] ?? 0);
if (!$checkup_id) { echo "missing checkup_id"; exit; }

// อัปโหลดไฟล์
if (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
    echo "<script>alert('อัปโหลดสลิปไม่สำเร็จ');history.back();</script>"; exit;
}

$allowed = ['jpg','jpeg','png','pdf'];
$ext = strtolower(pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    echo "<script>alert('ไฟล์ไม่รองรับ');history.back();</script>"; exit;
}

if (!is_dir('../uploads/slips')) { mkdir('../uploads/slips', 0775, true); }
$filename = 'slip_'.time().'_'.bin2hex(random_bytes(3)).'.'.$ext;
$target   = "../uploads/slips/".$filename;
if (!move_uploaded_file($_FILES['slip']['tmp_name'], $target)) {
    echo "<script>alert('บันทึกไฟล์ไม่สำเร็จ');history.back();</script>"; exit;
}

// อัปเดตตาราง checkups
mysqli_query($conn, "UPDATE checkups 
                     SET payment_slip = '".mysqli_real_escape_string($conn,$filename)."',
                         payment_verified = 0,  /* รอตรวจ */
                         paid_cash = 0
                     WHERE checkup_id = $checkup_id");

// (ถ้าต้องการ log ลง payments ด้วย ก็ทำได้)
// ตัวอย่าง (อ้าง rental_id จาก checkups):
// $ch = mysqli_fetch_assoc(mysqli_query($conn, "SELECT rental_id FROM checkups WHERE checkup_id = $checkup_id"));
// $rental_id = (int)$ch['rental_id'];
// mysqli_query($conn, "INSERT INTO payments(rental_id, user_id, amount, payment_date, payment_method, status, transaction_id)
//                      VALUES ($rental_id, {USER_ID}, {AMOUNT}, NOW(), 'qr', 'pending', '$filename')");

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

echo "<script>alert('อัปโหลดสลิปเรียบร้อย รอตรวจสอบ'); window.location='checkup_receipt.php?checkup_id={$checkup_id}';</script>";
