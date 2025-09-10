<?php
// booking/generate_qr.php
session_start();
date_default_timezone_set('Asia/Bangkok');

// ต้องมี temp_booking และยังไม่หมดเวลา
if (empty($_SESSION['temp_booking']) || empty($_SESSION['expires_at']) || time() > $_SESSION['expires_at']) {
    http_response_code(403);
    exit('expired');
}

// ยอดที่ต้องจ่ายตอนนี้ = มัดจำ ที่เราเก็บไว้ใน session ตอนเข้าหน้า QR
$amount = isset($_SESSION['temp_booking']['total_price']) ? (float)$_SESSION['temp_booking']['total_price'] : 0.0;
if ($amount <= 0) {
    http_response_code(400);
    exit('invalid amount');
}

// เบอร์/เลขพร้อมเพย์ (ย้ายไปไฟล์ config ก็ได้)
$promptpay = '0923519141';

// สร้าง URL และดึงรูปจาก promptpay.io
$qrUrl = sprintf('https://promptpay.io/%s/%.2f.png', $promptpay, $amount);
$img = @file_get_contents($qrUrl);
if ($img === false) {
    http_response_code(502);
    exit('cannot fetch qr');
}

// ส่งภาพกลับ + กัน cache
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo $img;
