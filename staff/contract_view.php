<?php
require('../db.php');
session_start();
date_default_timezone_set('Asia/Bangkok');

$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) {
    die("Missing booking_id");
}

$sql = "SELECT 
  r.rental_id,
  b.start_date, b.end_date, b.total_price, 
  c.brand, c.model, c.license_plate, c.daily_rate, c.deposit,
  cus.firstname, cus.lastname, cus.phone_number, cus.email
FROM rentals r
JOIN bookings b ON r.booking_id = b.booking_id
JOIN cars c ON r.car_id = c.car_id
JOIN customers cus ON r.user_id = cus.user_id
WHERE r.booking_id = $booking_id
LIMIT 1";

$result = mysqli_query($conn, $sql);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    die("ไม่พบข้อมูลการเช่า");
}

// ================== คำนวณราคา ==================
$pickup = strtotime($data['start_date']);
$return = strtotime($data['end_date']);
$days = max(1, ceil(($return - $pickup) / (60 * 60 * 24))); // จำนวนวันเช่า

$daily_rate  = (float)($data['daily_rate'] ?? 0);
$deposit     = (float)($data['deposit'] ?? 0);
$rent_total  = $daily_rate * $days;
$grand_total = $rent_total + $deposit;
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>สัญญาเช่ารถ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            padding: 40px;
        }
        .contract-box { max-width: 1200px; margin: auto; }
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .section-title { font-weight: bold; margin-bottom: 0.5rem; }
        .border-bottom { border-bottom: 1px solid #000; padding-bottom: 0.5rem; margin-bottom: 1rem; }
        .sign-box { height: 80px; border-bottom: 1px solid #000; width: 250px; margin-top: 40px; }
        @media print { .no-print { display: none; } }
    </style>
</head>

<body>
    <div class="contract-box">
        <!-- ส่วนหัว -->
        <div class="header-section border-bottom">
            <div style="margin-left: 5%;">
                <h5>ใบสัญญาเช่ารถยนต์</h5>
                <small>Rental <?= $data['rental_id'] ?> | เลขประจำตัวผู้เสียภาษี 1201000003473</small><br>
                <small>38 ซ.12 ถ.มหาโชค ต.ป่าตัน อ.เมือง จ.เชียงใหม่ 50300</small>
            </div>
            <div>
                <img src="../img/jrlogo4.jpg" alt="JR Logo" height="70">
            </div>
        </div>

        <!-- ข้อมูล 3 ส่วน -->
        <table style="width: 100%;">
            <tr>
                <td style="width: 33%; vertical-align: top;">
                    <h6>ข้อมูลผู้เช่า</h6>
                    ชื่อ: <?= htmlspecialchars($data['firstname'].' '.$data['lastname']) ?><br>
                    โทร: <?= htmlspecialchars($data['phone_number']) ?><br>
                    อีเมล: <?= htmlspecialchars($data['email']) ?>
                </td>

                <td style="width: 33%; vertical-align: top;">
                    <h6>ข้อมูลรถที่เช่า</h6>
                    ยี่ห้อ / รุ่น: <?= htmlspecialchars($data['brand'].' '.$data['model']) ?><br>
                    ทะเบียน: <?= htmlspecialchars($data['license_plate']) ?><br>
                    รับรถ: <?= date('j F Y', $pickup) ?><br>
                    คืนรถ: <?= date('j F Y', $return) ?><br>
                    ระยะเวลาเช่า: <?= $days ?> วัน
                </td>

                <td style="width: 33%; vertical-align: top; text-align: right;">
                    <h6>รายละเอียดราคา</h6>
                    ค่าบริการ (<?= number_format($daily_rate, 2) ?> × <?= $days ?> วัน): <?= number_format($rent_total, 2) ?> บาท<br>
                    มัดจำ: <?= number_format($deposit, 2) ?> บาท<br>
                    รวมทั้งหมด: <?= number_format($grand_total, 2) ?> บาท
                </td>
            </tr>
        </table>

        <!-- เงื่อนไข -->
        <div class="mt-4">
            <div class="section-title">เงื่อนไขการเช่า</div>
            <ol>
                <li>ผู้เช่าจะต้องรับผิดชอบหากรถเกิดความเสียหาย...</li>
                <li>บริษัทขอสงวนสิทธิ์ไม่คืนเงินในกรณีคืนก่อนกำหนด...</li>
                <li>รถต้องคืนพร้อมน้ำมันเต็มถัง...</li>
                <li>สัญญาข้อที่...</li>
                <li>สัญญาข้อที่...</li>
                <li>สัญญาข้อที่...</li>
            </ol>
        </div>

        <div style="justify-content: flex-end;">
            <!-- ลายเซ็น -->
            <div class="row mt-5">
                <div class="col-6 text-center">
                    <div class="sign-box"></div>
                    <small>ลายเซ็นผู้เช่า</small>
                </div>
                <div class="col-6 text-center">
                    <div class="sign-box"></div>
                    <small>ลายเซ็นผู้ให้เช่า</small>
                </div>
            </div>
            
            <div class="mt-4 text-end">
                <small>วันที่พิมพ์เอกสาร: <?= date('d/m/Y') ?></small>
            </div>
            
            <div class="text-center no-print mt-4">
                <button onclick="window.print()" class="btn btn-primary">Print</button>
            </div>
        </div>
    </div>
</body>
</html>
