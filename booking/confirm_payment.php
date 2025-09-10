<?php
session_start();
require("../db.php");
date_default_timezone_set('Asia/Bangkok');

// ต้องมีข้อมูลการจองใน session
if (empty($_SESSION['temp_booking']) || empty($_SESSION['user_id'])) {
    header("Location: ../booking.php");
    exit();
}

// ตรวจหมดเวลา 10 นาที (ตั้งค่าไว้ตอนเข้าหน้าชำระเงิน)
if (empty($_SESSION['expires_at']) || time() > $_SESSION['expires_at']) {
    unset($_SESSION['temp_booking'], $_SESSION['expires_at']);
    header("Location: ../booking.php?expired=1");
    exit();
}

// ต้องล็อกอิน
if (empty($_SESSION['user_id'])) {
    echo "กรุณาเข้าสู่ระบบก่อนทำรายการ";
    exit();
}

// ต้องมีไฟล์สลิปและอัปโหลดสำเร็จ
if (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
    echo "อัปโหลดสลิปไม่สำเร็จ กรุณาลองใหม่";
    exit();
}

// ===== 1) จัดการไฟล์สลิปอย่างปลอดภัย =====
$upload_dir = __DIR__ . '/../uploads/slips/';  // โฟลเดอร์จริงบนเซิร์ฟเวอร์
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ตรวจชนิดไฟล์
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $_FILES['slip']['tmp_name']);
finfo_close($finfo);
$allowed = ['image/jpeg','image/png'];
if (!in_array($mime, $allowed)) die ("ไฟล์ต้องเป็นรูปภาพเท่านั้น (JPG, PNG)");
if($_FILES['slip']['size'] > 5 * 1024 * 1024) die ("ไฟล์สลิปใหญ่เกินไป (เกิน 5MB)");

// ตั้งชื่อไฟล์
$ext = strtolower(pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION));
$filename = 'slip_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target_path = $upload_dir . $filename;

if (!move_uploaded_file($_FILES['slip']['tmp_name'], $target_path)) {
    die("อัปโหลดไฟล์ไม่สำเร็จ");
}

// ===== 2) เตรียมข้อมูลเพื่อ INSERT =====
$booking  = $_SESSION['temp_booking'];
$user_id  = (int) $_SESSION['user_id'];
$car_id   = (int) $booking['car_id'];
$start    = $booking['start_date'];   // ควรเป็น DATETIME string ที่ valid
$end      = $booking['end_date'];
$total    = (float) $booking['total_price'];
$location = $booking['location'];
$note     = $booking['note'];

// แนะนำ: payment_status ใช้ 'waiting' ให้ตรง enum, booking_status ใช้ 'pending'
$payment_status = 'waiting_review';
$booking_status = 'pending';

// ===== 3) INSERT ด้วย Prepared Statement =====
$sql = "INSERT INTO bookings
(user_id, car_id, start_date, end_date, total_price, booking_status, location, note, payment_proof, payment_status, created_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    @unlink($target_path);
    echo "เกิดข้อผิดพลาด (prepare): " . mysqli_error($conn);
    exit();
}

mysqli_stmt_bind_param(
    $stmt,
    'iissdsssss',
    $user_id,
    $car_id,
    $start,
    $end,
    $total,
    $booking_status,
    $location,
    $note,
    $filename,       // เก็บ 'ชื่อไฟล์' เฉย ๆ
    $payment_status
);

if (mysqli_stmt_execute($stmt)) {
    unset($_SESSION['temp_booking'], $_SESSION['expires_at']);
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
      <meta charset="UTF-8">
      <title>จองสำเร็จ</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
      <script>
        document.addEventListener("DOMContentLoaded", () => {
          Swal.fire({
            icon: 'success',
            title: 'การจองสำเร็จ',
            text: 'ระบบได้บันทึกการจองของคุณแล้ว',
            showConfirmButton: false,
            timer: 2000
          }).then(() => {
            window.location.href = "booking_history.php";
          });
        });
      </script>
    </body>
    </html>
    <?php
    exit();
} else {
    @unlink($target_path);  // ลบไฟล์สลิปที่อัปโหลดไปแล้ว
    die ("บันทึกการจองไม่สำเร็จ: " . mysqli_stmt_error($stmt));
}

// ===== 4) (ทางเลือก) กันซ้อนจองรถ =====
// แนะนำอย่า set เป็น 'rented' ตรงนี้ ให้จองสำเร็จแล้วค่อยไปขั้นตอนทำสัญญา/ส่งรถ
// ถ้าต้องการบล็อคชั่วคราวให้เปลี่ยนเป็นสถานะ 'reserved' แทน (ถ้ามีใน schema)
// mysqli_query($conn, "UPDATE cars SET status = 'reserved' WHERE car_id = {$car_id}");

// ===== 5) แจ้งเตือน staff/admin =====
// $msg = "ลูกค้า #{$user_id} ส่งสลิปจองรถ #{$car_id} แล้ว รอตรวจสอบสลิป";
// $ins1 = mysqli_query($conn, "INSERT INTO notifications (user_type, message, created_at) VALUES ('staff', '$msg', NOW())");
// $ins2 = mysqli_query($conn, "INSERT INTO notifications (user_type, message, created_at) VALUES ('admin', '$msg', NOW())");

// ===== 6) เคลียร์ session แล้วพาไปหน้า success =====
unset($_SESSION['temp_booking'], $_SESSION['expires_at']);
// header("Location: booking_history.php?new=1");
exit();
