<?php
// staff/cancel_booking.php
session_start();
require("../db.php");

if (empty($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
    echo "<script>alert('Access Denied'); window.location='../home/index.php';</script>";
    exit;
}

$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$car_id     = isset($_POST['car_id']) ? (int)$_POST['car_id'] : 0;
$ok = 0;

if ($booking_id > 0 && $car_id > 0) {
    mysqli_begin_transaction($conn);

    try {
        // 1) ตรวจสอบว่ายังยกเลิกได้ (กำหนดเงื่อนไขตามที่ใช้จริง)
        //    ที่ง่ายสุด: ยกเลิกได้เฉพาะ booking ที่ยัง 'pending'
        $q1 = mysqli_prepare($conn, "SELECT booking_status, payment_status FROM bookings WHERE booking_id=? FOR UPDATE");
        mysqli_stmt_bind_param($q1, 'i', $booking_id);
        mysqli_stmt_execute($q1);
        $res = mysqli_stmt_get_result($q1);
        $bk = mysqli_fetch_assoc($res);

        if ($bk && $bk['booking_status'] === 'pending') {
            // 2) อัปเดต booking เป็น cancelled
            $q2 = mysqli_prepare($conn, "UPDATE bookings SET booking_status='cancelled' WHERE booking_id=?");
            mysqli_stmt_bind_param($q2, 'i', $booking_id);
            mysqli_stmt_execute($q2);

            // (ถ้าต้องการ ปรับ payment_status เป็น 'rejected' ด้วย)
            // $q2b = mysqli_prepare($conn, "UPDATE bookings SET payment_status='rejected' WHERE booking_id=?");
            // mysqli_stmt_bind_param($q2b, 'i', $booking_id);
            // mysqli_stmt_execute($q2b);

            // 3) ปล่อยรถกลับเป็น available
            $q3 = mysqli_prepare($conn, "UPDATE cars SET status='available' WHERE car_id=?");
            mysqli_stmt_bind_param($q3, 'i', $car_id);
            mysqli_stmt_execute($q3);

            mysqli_commit($conn);
            $ok = 1;
        } else {
            // ยกเลิกไม่ได้ เพราะสถานะไม่ตรง
            mysqli_rollback($conn);
        }
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        // log error ถ้าต้องการ
    }
}

header("Location: staff_dashboard.php?cancelled=" . ($ok ? '1' : '0'));
exit();
