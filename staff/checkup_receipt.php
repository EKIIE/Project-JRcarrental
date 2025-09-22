<?php
// staff/checkup_receipt.php
require("../db.php");
session_start();

if (!isset($_GET['checkup_id'])) {
  echo "Missing checkup_id";
  exit;
}
$checkup_id = (int)$_GET['checkup_id'];
$emp_returner = $_SESSION['employee_id'] ?? 0;

// ดึงข้อมูล checkup + rentals + bookings + cars + customers
$sql =
  "SELECT  ch.*, 
    r.rental_id, r.booking_id, r.user_id, r.car_id,
    b.start_date, b.end_date, b.total_price AS booking_total, 
    c.brand, c.model, c.license_plate, c.daily_rate, c.deposit, c.image_path,
    cus.firstname, cus.lastname, cus.phone_number, cus.email
FROM checkups ch
JOIN rentals r   ON ch.rental_id = r.rental_id
JOIN bookings b  ON r.booking_id = b.booking_id
JOIN cars c      ON r.car_id = c.car_id
LEFT JOIN customers cus ON r.user_id = cus.user_id
WHERE ch.checkup_id = $checkup_id
LIMIT 1;
";
$rs = mysqli_query($conn, $sql);
$rec = mysqli_fetch_assoc($rs);
if (!$rec) {
  echo "ไม่พบข้อมูล";
  exit;
}

// คำนวณยอดที่ต้องจ่าย: (daily_rate * days) + penalty - deposit
$days = (new DateTime($rec['start_date']))->diff(new DateTime($rec['end_date']))->days;
if ($days <= 0) $days = 1; // กันกรณีวันเท่ากัน
$base_rent   = (float)$rec['daily_rate'] * $days;
$penalty     = (float)$rec['penalty_fee'];
$deposit     = (float)$rec['deposit'];
$amount_due  = $base_rent + $penalty - $deposit;
if ($amount_due < 0) $amount_due = 0.00;

$paid_cash         = (int)$rec['paid_cash'] === 1;
$payment_verified  = (int)$rec['payment_verified'] === 1;
$slip              = $rec['payment_slip'];

// promptpay
$promptpay = '0923519141'; // เปลี่ยนเป็นของร้าน
$amountFormatted = number_format($amount_due, 2, '.', '');
$qrUrl = "https://promptpay.io/{$promptpay}/{$amountFormatted}.png";
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <title>ใบเสร็จรับเงิน - คืนรถ</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .receipt {
      max-width: 860px;
      margin: auto;
    }

    .car-thumb {
      max-height: 140px;
      object-fit: cover;
    }

    .qrb {
      border: 1px solid #eee;
      padding: 16px;
      border-radius: 10px;
      display: inline-block;
    }

    @media print {
      .no-print {
        display: none !important;
      }

      .receipt {
        box-shadow: none;
      }

      .receipt .no-break {
        page-break-inside: avoid;
        break-inside: avoid;
      }

      .print-row {
        display: block !important;
        width: 100% !important;
      }

      .print-col-left {
        float: left !important;
        width: 50% !important;
      }

      .print-col-right {
        float: right !important;
        width: 50% !important;
      }
      .print-col-bottom {
        clear: both !important;
        width: 100% !important;
      }
    }
  </style>
</head>

<body class="bg-light">
  <div class="receipt bg-white shadow-sm p-4 my-4 rounded-3">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <h3 class="mb-1">ใบเสร็จรับเงิน / การคืนรถ</h3>
        <div class="text-muted">เลขที่ตรวจรับคืน : <?= $rec['checkup_id'] ?> | Rental <?= $rec['rental_id'] ?></div>
        <small>เลขประจำตัวผู้เสียภาษี : 1201000003473</small><br>
        <small>38 ซ.12 ถ.มหาโชค ต.ป่าตัน อ.เมือง จ.เชียงใหม่ 50300</small>
      </div>
      <img src="../img/jrlogo4.jpg" alt="JR Logo" style="max-height:80px; width:auto;">
    </div>
    <hr>

    <div class="row g-4 no-break print-row">
      <div class="col-md-6 print-col-left">
        <h5>ข้อมูลลูกค้า</h5>
        <div>ชื่อ: <?= htmlspecialchars($rec['firstname'] . ' ' . $rec['lastname']) ?></div>
        <div>โทร: <?= htmlspecialchars($rec['phone_number']) ?></div>
        <div>อีเมล: <?= htmlspecialchars($rec['email']) ?></div>
        <!-- </div> -->
        <!-- <hr class="my-3"> -->
        <br>
        <!-- <div class="col-md-3"> -->
        <h5>ข้อมูลรถ</h5>
        <div><?= htmlspecialchars($rec['brand'] . ' ' . $rec['model']) ?> | <?= htmlspecialchars($rec['license_plate']) ?></div>
        <div>ช่วงเช่า: <?= date('d/m/Y', strtotime($rec['start_date'])) ?> - <?= date('d/m/Y', strtotime($rec['end_date'])) ?> (<?= $days ?> วัน)</div>
        <div>ตรวจคืนเมื่อ: <?= date('d/m/Y H:i', strtotime($rec['checkup_date'])) ?></div>
      </div>
      <div class="col-md-6 print-col-right">
        <!-- <hr> -->
        <h5>รายละเอียดค่าปรับ</h5>
        <ul>
          <?php if ($rec['fuel_notfull']): ?>
            <li>น้ำมันไม่เต็ม (+<?= number_format(2000, 2) ?> บาท)</li>
          <?php endif; ?>
          <?php if ($rec['tire_damaged']): ?>
            <li>ยางรถเสียหาย (+<?= number_format(3500, 2) ?> บาท)</li>
          <?php endif; ?>
          <?php if ($rec['key_lost']): ?>
            <li>กุญแจรถหาย (+<?= number_format(3500, 2) ?> บาท)</li>
          <?php endif; ?>
          <?php if ($rec['smell_detected']): ?>
            <li>พบกลิ่นบุหรี่หรือขนสัตว์ (+<?= number_format(3000, 2) ?> บาท)</li>
          <?php endif; ?>
          <?php if ($rec['late_return']): ?>
            <li>คืนรถหลังเวลา 12:00 น. (+<?= number_format($rec['daily_rate'], 2) ?> บาท)</li>
          <?php endif; ?>
          <?php if (!empty($rec['damage_notes'])): ?>
            <li>หมายเหตุเพิ่มเติม: <?= htmlspecialchars($rec['damage_notes']) ?></li>
          <?php endif; ?>

          <?php if (
            !$rec['fuel_notfull'] &&
            !$rec['tire_damaged'] &&
            !$rec['key_lost'] &&
            !$rec['smell_detected'] &&
            !$rec['late_return'] &&
            empty($rec['damage_notes'])
          ): ?>
            <li>ไม่มีค่าปรับ</li>
          <?php endif; ?>
        </ul>

      </div>
    </div>

    <div class="no-break print-row mt-3 print-col-bottom">
      <hr>
      <h5>สรุปยอด</h5>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <tr>
            <td>ค่าบริการ (<?= number_format($rec['daily_rate'], 2) ?> × <?= $days ?> วัน)</td>
            <td class="text-end"><?= number_format($base_rent, 2) ?></td>
          </tr>
          <tr>
            <td>ค่าปรับ / ค่าเสียหาย</td>
            <td class="text-end"><?= number_format($penalty, 2) ?></td>
          </tr>
          <tr>
            <td>หักมัดจำ</td>
            <td class="text-end text-danger">-<?= number_format($deposit, 2) ?></td>
          </tr>
          <tr class="table-light">
            <th>ยอดสุทธิที่ต้องจ่าย</th>
            <th class="text-end"><?= number_format($amount_due, 2) ?> บาท</th>
          </tr>
        </table>
      </div>
    </div>

    <?php if ($amount_due <= 0): ?>
      <div class="alert alert-success">ไม่มียอดค้างชำระ ✅</div>
    <?php else: ?>
      <?php if ($paid_cash): ?>
        <div class="alert alert-info">พนักงานระบุว่า “ลูกค้าชำระเป็นเงินสด” แล้ว ✅</div>
      <?php elseif ($payment_verified): ?>
        <div class="alert alert-success">ตรวจสอบการชำระผ่านสลิปเรียบร้อย ✅</div>
        <?php if ($slip): ?>
          <div><a class="btn btn-outline-secondary btn-sm" href="../uploads/slips/<?= htmlspecialchars($slip) ?>" target="_blank">ดูสลิป</a></div>
        <?php endif; ?>
      <?php else: ?>
        <div class="row g-4 no-break print-row print-col-left">
          <div class="col-md-6">
            <h6 class="mb-2">ชำระด้วย QR พร้อมเพย์</h6>
            <div class="qrb">
              <img src="<?= $qrUrl ?>" alt="PromptPay QR" width="200" height="200">
              <div class="small text-center mt-2 text-muted">สแกนจ่าย: <?= number_format($amount_due, 2) ?> บาท</div>
            </div>
            <div class="small text-muted mt-2">เบอร์พร้อมเพย์: <?= htmlspecialchars($promptpay) ?></div>
          </div>
          <div class="col-md-6 no-print">
            <h6 class="mb-2">อัปโหลดสลิปโอน</h6>
            <form class="border p-3 rounded" method="post" enctype="multipart/form-data" action="chk_pay.php">
              <input type="hidden" name="checkup_id" value="<?= $checkup_id ?>">
              <div class="mb-2">
                <input type="file" name="slip" class="form-control" accept="image/*,application/pdf" required>
              </div>
              <button class="btn btn-success w-100">อัปโหลด & แจ้งชำระเงิน</button>
              <div class="form-text">ไฟล์ที่รองรับ: jpg, jpeg, png, pdf</div>
            </form>

            <div class="mt-3">
              <form method="post" action="chk_cash.php" class="d-grid">
                <input type="hidden" name="checkup_id" value="<?= $checkup_id ?>">
                <button class="btn btn-outline-primary">ชำระเงินสดแล้ว (บันทึก)</button>
              </form>
            </div>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <!-- <hr> -->
    <div class="d-flex gap-2 no-print">
      <button class="btn btn-secondary" onclick="window.print()">พิมพ์ใบเสร็จ</button>
      <a class="btn btn-outline-dark" href="staff_dashboard.php">กลับแดชบอร์ด</a>
    </div>
  </div>
</body>

</html>