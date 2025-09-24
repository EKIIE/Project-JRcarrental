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
$deposit20   = $rent_total * 0.20; // มัดจำ 20%
// $grand_total = $rent_total + $deposit20;
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
            padding: 20px 30px;
        }

        .contract-box {
            max-width: 1200px;
            margin: auto;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .border-bottom {
            border-bottom: 1px solid #000;
            /* padding-bottom: 0.5rem; */
            margin-bottom: 0.5rem;
        }

        .mt-4 {
            margin-top: 0.3rem !important;
        }

        .mt-5 {
            margin-top: 0.2rem !important;
        }

        .mt-6 {
            margin-top: -0.5rem !important;
            font-size: 0.8rem;
            margin-bottom: 0.2rem;
        }

        h6 {
            font-weight: bold;
            margin-bottom: 0.05rem;
        }

        .sign-box {
            height: 40px;
            border-bottom: 0.8px solid #555;
            width: 250px;
            margin-top: 0px;
            margin-left: auto;
            margin-right: auto;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-light">
    <!-- <div class="receipt bg-white shadow-sm p-4 my-4 rounded-3"> -->
    <div class="contract-box">
        <div class="mt-6 text-end">
            <small>Date/วันที่: <?= date('d/m/Y') ?></small>
        </div>
        <!-- ส่วนหัว -->
        <!-- <div class="d-flex justify-content-between align-items-start"> -->
        <div class="header-section border-bottom">
            <div style="margin-left: 2.5%;">
                <div class="section-title">Car Rental Agreement <small>หนังลือสัญญาเช่ารถยนต์</small></div>
                <small style="font-size: 0.6rem;">Rental <?= $data['rental_id'] ?></small>
                <!-- <small style="font-size: 0.6rem;"> | 38 ซ.12 ถ.มหาโชค ต.ป่าตัน อ.เมือง จ.เชียงใหม่ 50300</small> -->
                <!-- <small style="font-size: 0.6rem;"> | เลขประจำตัวผู้เสียภาษี 1201000003473</small> -->
            </div>
            <div>
                <img src="../img/jrlogo4.jpg" alt="JR Logo" height="50">
            </div>
        </div>

        <!-- ข้อมูล 3 ส่วน -->
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <h6 style="font-size: 0.7rem; line-height: 1.1;">ข้อมูลรถที่เช่า</h6>
                    <p style="font-size: 0.6rem; margin-bottom: 0.1rem;">
                        Vehicle check out date รับรถ: <?= date('j F Y', $pickup) ?>
                        ยี่ห้อ / รุ่น: <?= htmlspecialchars($data['brand'] . ' ' . $data['model']) ?><br>
                        <!-- </p>
                    <p style="font-size: 0.6rem; margin-bottom: 0.1rem;"> -->
                        Vehicle due back date คืนรถ: <?= date('j F Y', $return) ?>
                        ทะเบียน: <?= htmlspecialchars($data['license_plate']) ?><br>
                        Total /รวมเวลา: <?= $days ?> วัน
                    </p>
                </td>
                <td style="width: 33%; vertical-align: top; text-align: right;">
                    <h6 style="font-size: 0.7rem;">รายละเอียดราคา</h6>
                    <p style="font-size: 0.6rem; margin-bottom: 0.1rem;">
                        ค่าบริการต่อวัน: <?= number_format($daily_rate, 2) ?> บาท<br>
                        มัดจำ 20%: <?= number_format($deposit20, 2) ?> บาท<br>
                        รวมทั้งหมด: <?= number_format($rent_total, 2) ?> บาท
                    </p>
                </td>
            </tr>
        </table>

        <!-- เงื่อนไข -->
        <div class="mt-4">
            <p style="font-size: 0.8rem; margin-left: 2.5%; margin-bottom: 1%;">Covenants ข้อตกลงในสัญญา :</p>
            <!-- <br> -->
            <ol>
                <!-- 1 -->
                <li style="font-size: 0.55rem;">
                    The deposit is to be used in the event of loss or
                    damage to the vehicle and be returned in the absence thereof.
                </li>
                <small style="font-size: 0.45rem;">
                    ผู้เช่าจะถูกหักเงินประกัน ในกรณีที่รถยนต์เช่าเกิดการสูญหาย หรือเกิดความเสียหาย
                    ชำรุดต่อตัวรถยนต์เช่า ตลอดจนอุปกรณ์ทั้งปวง แต่หากทางผู้ให้เช่าตรวจสภาพแล้วว่าไม่เกิดความเสียหายใดๆ
                    และรถเช่าถูกคืนในสภาพเดิม เงินประกันจะถูกคืนให้แก่ผู้เช่าในวันที่ครบกำหนด
                </small>
                <!-- 2 -->
                <li style="font-size: 0.55rem;">
                    For every damage of the vehicle the rental shall pay deductible THB 3,500
                </li>
                <small style="font-size: 0.45rem;">
                    กรณีเกิดความเสียหายต่อตัวรถ ผู้เช่าจะเป็นผู้รับผิดชำระค่าเสียหายส่วนเกินนั้น เป็นเงิน 3,500 บาท
                </small>
                <!-- 3 -->
                <li style="font-size: 0.55rem;">
                    For totally damage to or loss of the vehicle/ the Renter shall pay deductible of
                    THB <?= number_format($deposit, 0) ?> totally damage or loss.
                </li>
                <small style="font-size: 0.45rem;">
                    กรณีสูญหาย หรือเกิดความเสียหายต่อตัวรถจนไม่สามารถซ่อมได้ ผู้เช่าจะเป็นผู้รับผิดชำระค่าเสียหายส่วนเกินนั้น
                    เป็นเงิน <?= number_format($deposit, 0) ?> บาท ต่อการชำรุดเสียหายหรือสูญหาย
                </small>
                <!-- 4 -->
                <li style="font-size: 0.55rem;">
                    The vehicle is fully insured, however, No insurance coverage is granted if the Renter
                    is involved in an accident cused by or under the influence of alcohol and/or drugs.
                    In this case, the Renter shall fully indemnify the Company for any and all loss and damage.
                </li>
                <small style="font-size: 0.45rem;">ประกันภัยจะไม่ครอบคลุม ในกรณีที่ผู้เช่าและรถยนต์ให้เช่าประสบอุบัติ
                    อันเนื่องมาจากความมึนเมาจากเครื่องดื่มแอลกอฮอล์ ยาเสพติดทุกชนิด ผู้เช่าจะต้องรับผิดชอบค่าใช้จ่าย
                    และสินไหมทดแทน ที่อาจเกิดขึ้นให้คู่กรณีในเหตุการณ์เอง
                </small>
                <!-- 5 -->
                <li style="font-size: 0.55rem;">
                    The vehicle is to be returned with a full tank. If this is not the case, the Renter shall
                    pay THB 2,000 xxน้ำมันxx.
                </li>
                <small style="font-size: 0.45rem;">
                    ผู้เช่าจะต้องเติมน้ำมันรถยนต์ให้เช่าเต็มถังในเวลาครบกำหนด หากไม่เช่นนั้นผู้เช่าจะต้องเสียค่าใช้จ่ายเพิ่ม เป็นเงิน 2,000 บาท
                </small>
                <!-- 6 -->
                <li style="font-size: 0.55rem;">
                    Damage to tyres must be repaired and fully carried by the Renter.
                </li>
                <small style="font-size: 0.45rem;">
                    ผู้เช่าเป็นผู้รับผิดชอบความเสียหายใดๆที่เกิดขึ้นกับยางรถยนต์
                </small>
                <!-- 7 -->
                <li style="font-size: 0.55rem;">
                    For loss of car key the Renter shall pay THB 3,500 for replacement.
                </li>
                <small style="font-size: 0.45rem;">
                    หากผู้เช่าทำกุญแจรถหาย จะต้องเสียค่าใช้จ่ายทดแทนเป็นเงิน 3,500 บาท
                </small>
                <!-- 8 -->
                <li style="font-size: 0.55rem;">
                    Smoking and transporting pets in the vehicle are NOT allowed, subject to a minimum of THB 3,000 fine.
                </li>
                <small style="font-size: 0.45rem;">
                    ผู้เช่าจะต้องไม่สูบบุหรี่ขณะขับขี่ หรือใช้รถยนต์ขนย้ายสัตว์เลี้ยงทุกชนิด หากฝ่าฝืนจะถูกปรับเป็นเงินจำนวน 3,000 บาท
                </small>
                <!-- 9 -->
                <li style="font-size: 0.55rem;">
                    Passort or ID plus above deposit (minus any applicable deductibles) in cash, shall be handed over to
                    the Renter upon vehicle return.
                </li>
                <small style="font-size: 0.45rem;">
                    ผู้ให้เช่าจะคืนหนังสือเดินทางหรือบัตรประจำตัวประชาชนและเงินประกัน (หลังหักค่าใช้จ่ายในกรณีเสียหาย)
                </small>
                <!-- 10 -->
                <li style="font-size: 0.55rem;">
                    The Company will not be responsible in case the Renter is involved in criminal activities and the
                    contract shall be canceled immediately.
                </li>
                <small style="font-size: 0.45rem;">
                    ผู้ให้เช่าหรือตัวแทนมีสิทธิ์เข้าตรวจสอบรถยนต์ให้เช่าได้ทุกกรณื และหากพบว่าผู้เช่านำรถยนต์ไปใช้ในทางที่ผิดกฎหมาย เจ้าหน้าที่สามารถ
                    ดำเนินคดีได้ในทันที โดยผู้ให้เช่าจะไม่มีส่วนรับผิดชอบใดๆ และผู้ให้เช่าสามารถยกเลิกสัญญา พร้อมยึดเงินประกันได้ทันที
                    โดยไม่จำเป็นต้องได้รับความยินยอมจากผู้เช่า
                </small>
                <!-- 11 -->
                <li style="font-size: 0.55rem;">
                    The rented vehicle shall be returned no later than time and date above, if not the Renter will be
                    charged for another full day of rental.
                </li>
                <small style="font-size: 0.45rem;">
                    หากผู้เช่าคืนรถเช่าเกินเวลาที่กำหนด จะถูกคิดค่าบริการเพิ่มเป็นอีก 1 วัน
                </small>
                <!-- 12 -->
                <li style="font-size: 0.55rem;">
                    The vehicle is for the Renter's personal use only.
                </li>
                <small style="font-size: 0.45rem;">
                    ไม่อนุญาตให้ให้ผู้เช่านำรถยนต์เช่าไปให้เช่าช่วงต่อ หรือเพื่อหากำไร
                </small>
            </ol>
        </div>
        <div style="justify-content: flex-end;">
            <p style="font-size: 0.75rem; margin-bottom: .5rem;">ทั้งสองฝ่ายได้ตกลงทำความเข้าใจข้อความและเจตนาข้างต้นถูกต้องทุกประการแล้ว จึงลงลายมือชื่อไว้เป็นหลักฐาน</p>

            <div style="text-align: left; margin-left: 5%;">
                <td style="width: 33%; vertical-align: center;">
                    <h6 style="font-size: 0.7rem;">ข้อมูลผู้เช่า</h6>
                    <p style="font-size: 0.6rem; margin-bottom: 0.1rem;">
                        ชื่อ: <?= htmlspecialchars($data['firstname'] . ' ' . $data['lastname']) ?>
                        โทร: <?= htmlspecialchars($data['phone_number']) ?>
                        อีเมล: <?= htmlspecialchars($data['email']) ?>
                    </p>
                </td>
            </div>
            <!-- ลายเซ็น -->
            <div class="row mt-5">
                <div class="col-6 text-center">
                    <div style="margin-top: 1.5px;">
                        <p style="font-size: 0.5rem;margin-bottom: 0.1rem;">Vehicle Check out ตรวจสภาพก่อนส่ง</p>
                        <img src="../img/check.jpg" height="135">
                    </div>
                    <div class="sign-box"></div>
                    <!-- <br> -->
                    <small>ผู้เช่า</small>
                </div>
                <div class="col-6 text-center">
                    <div style="margin-top: 1.5px;">
                        <p style="font-size: 0.5rem;margin-bottom: 0.1rem;">Vehicle Check in ตรวจสภาพรับรถ</p>
                        <img src="../img/check.jpg" height="135">
                    </div>
                    <div class="sign-box"></div>
                    <!-- <br> -->
                    <small>ผู้ให้เช่า</small>
                </div>
            </div>

            <div class="text-center no-print mt-4">
                <button onclick="window.print()" class="btn btn-primary">Print</button>
            </div>
        </div>
    </div>
</body>

</html>