<?php
require("../db.php");
session_start();

// อนุญาตทั้ง staff และ admin
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
  echo "Access Denied";
  exit();
}
if (!isset($_SESSION['employee_id'])) {
  echo "<script>alert('ไม่พบ employee_id ใน session (ตั้งค่าตอน login)'); history.back();</script>";
  exit;
}

$rental_id = isset($_GET['rental_id']) ? (int)$_GET['rental_id'] : 0;
if ($rental_id <= 0) {
  echo "Missing rental_id";
  exit;
}

$rental = mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT r.*, b.start_date, b.end_date, c.daily_rate, c.deposit, c.car_id
FROM rentals r
JOIN bookings b ON r.booking_id = b.booking_id
JOIN cars c ON b.car_id = c.car_id
WHERE r.rental_id = $rental_id"
));
if (!$rental) {
  echo "ไม่พบข้อมูลการเช่า";
  exit;
}

$booking_id = (int)$rental['booking_id'];
$emp_id     = (int)$_SESSION['employee_id'];
$car_id     = (int)$rental['car_id'];
$daily_rate = (float)$rental['daily_rate'];
$deposit    = (float)$rental['deposit'];
$end_date   = new DateTime($rental['end_date']);

// ===========================
// CONFIG ค่าปรับ (แก้ได้ตามจริง)
// ===========================
$PENALTY_FUEL_NOT_FULL = 2000.00;
$PENALTY_TIRE_DMG      = 3500.00;
$PENALTY_KEY_LOST      = 3500.00;
$PENALTY_SMELL         = 3000.00;
// คืนหลังเที่ยง (late_return) => ชาร์จเพิ่ม 1 วันของ daily_rate
$LATE_ADD_DAYS         = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // รับค่าจากฟอร์ม (checkbox -> 1/0)
  $fuel_notfull   = isset($_POST['fuel_notfull']) ? 1 : 0;
  $tire_damaged   = isset($_POST['tire_damaged']) ? 1 : 0;
  $key_lost       = isset($_POST['key_lost']) ? 1 : 0;
  $smell_detected = isset($_POST['smell_detected']) ? 1 : 0;
  $late_return    = isset($_POST['late_return']) ? 1 : 0;
  $damage_notes   = $_POST['damage_notes'] ?? '';

  // คำนวณค่าปรับรวม
  $penalty = 0.00;
  if ($fuel_notfull)   $penalty += $PENALTY_FUEL_NOT_FULL;
  if ($tire_damaged)   $penalty += $PENALTY_TIRE_DMG;
  if ($key_lost)       $penalty += $PENALTY_KEY_LOST;
  if ($smell_detected) $penalty += $PENALTY_SMELL;
  if ($late_return)    $penalty += ($daily_rate * $LATE_ADD_DAYS);

  // บันทึกลง checkups (final)
  mysqli_begin_transaction($conn);
  try {
    // INSERT checkups
    $sql = "INSERT INTO checkups
          (rental_id, employee_id, checkup_type, checkup_date,
           fuel_notfull, tire_damaged, key_lost, smell_detected, late_return,
           damage_notes, penalty_fee, paid_cash, payment_verified)
          VALUES (?, ?, 'final', NOW(),
           ?, ?, ?, ?, ?, ?, ?, 0, 0)";
    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
      $stmt,
      "iiiiiiisd",
      $rental['rental_id'],
      $emp_id,
      $fuel_notfull,
      $tire_damaged,
      $key_lost,
      $smell_detected,
      $late_return,
      $damage_notes,
      $penalty
    );

    if (!mysqli_stmt_execute($stmt)) {
      throw new Exception("Insert checkup fail: " . mysqli_stmt_error($stmt));
    }
    $checkup_id = mysqli_insert_id($conn);

    // คำนวณจำนวนวันเช่า
    $start_date = new DateTime($rental['start_date']);
    $end_date   = new DateTime($rental['end_date']);
    $days = $start_date->diff($end_date)->days;
    if ($days <= 0) $days = 1;

    // คำนวณค่าเช่า
    $base_rent = $daily_rate * $days;
    // ส่วนลด
    $discount = 0;
    if ($days >= 30) {
      $discount = 0.15; // 15%
    } elseif ($days >= 15) {
      $discount = 0.05; // 5%
    }
    // คำนวณยอดสุทธิหลังหักส่วนลด
    $total_amount = $base_rent - ($base_rent * $discount);

    // UPDATE rentals
    $sql2 = "UPDATE rentals 
         SET emp_returner=?, actual_return_date=NOW(), 
             rental_status='completed', total_amount=?, updated_at=NOW() 
         WHERE rental_id=?";
    $stmt2 = mysqli_prepare($conn, $sql2);
    mysqli_stmt_bind_param($stmt2, "idi", $emp_id, $total_amount, $rental['rental_id']);

    if (!mysqli_stmt_execute($stmt2)) {
      throw new Exception("Update rentals fail: " . mysqli_stmt_error($stmt2));
    }

    // UPDATE cars
    $sql3 = "UPDATE cars SET status='available' WHERE car_id=?";
    $stmt3 = mysqli_prepare($conn, $sql3);
    mysqli_stmt_bind_param($stmt3, "i", $car_id);
    if (!mysqli_stmt_execute($stmt3)) {
      throw new Exception("Update cars fail: " . mysqli_stmt_error($stmt3));
    }
    mysqli_commit($conn);

    header("Location: checkup_receipt.php?checkup_id=" . $checkup_id);
    exit;
  } catch (Exception $e) {
    mysqli_rollback($conn);
    echo "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <title>ตรวจสอบสภาพรถ (คืนรถ)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .box {
      max-width: 820px;
      margin: auto
    }
  </style>
</head>

<body class="bg-light">
  <div class="box bg-white shadow-sm p-4 my-4 rounded-3">
    <h3 class="mb-3">ตรวจสอบสภาพรถ – คืนรถ</h3>
    <div class="text-muted mb-3">
      Booking #<?= $booking_id ?> | Rental #<?= $rental['rental_id'] ?> |
      กำหนดคืน: <?= date('d/m/Y H:i', strtotime($rental['end_date'])) ?>
    </div>

    <form method="post">
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="fuel_notfull" name="fuel_notfull">
        <label class="form-check-label" for="fuel_notfull">น้ำมันไม่เต็ม (+<?= number_format($PENALTY_FUEL_NOT_FULL, 2) ?>)</label>
      </div>

      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="tire_damaged" name="tire_damaged">
        <label class="form-check-label" for="tire_damaged">ยางรถเสียหาย (+<?= number_format($PENALTY_TIRE_DMG, 2) ?>)</label>
      </div>

      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="key_lost" name="key_lost">
        <label class="form-check-label" for="key_lost">กุญแจรถหาย (+<?= number_format($PENALTY_KEY_LOST, 2) ?>)</label>
      </div>

      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="smell_detected" name="smell_detected">
        <label class="form-check-label" for="smell_detected">พบกลิ่นบุหรี่/ขนสัตว์ (+<?= number_format($PENALTY_SMELL, 2) ?>)</label>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="late_return" name="late_return">
        <label class="form-check-label" for="late_return">คืนหลัง 12:00 (+คิดเพิ่ม <?= $LATE_ADD_DAYS ?> วัน × <?= number_format($daily_rate, 2) ?>)</label>
      </div>

      <div class="mb-3">
        <label class="form-label">หมายเหตุ/รายละเอียดความเสียหาย</label>
        <textarea name="damage_notes" class="form-control" rows="3" placeholder="เช่น รอยขีดข่วน, ทำความสะอาดเพิ่ม ฯลฯ"></textarea>
      </div>

      <button class="btn btn-primary">บันทึกการตรวจสอบและไปหน้าใบเสร็จ</button>
      <a href="staff_dashboard.php" class="btn btn-outline-secondary">ยกเลิก</a>
    </form>
  </div>
</body>

</html>