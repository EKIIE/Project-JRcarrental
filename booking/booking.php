<?php
include("../db.php");
session_start();

if (!isset($_SESSION['user_type'])) {
  header("Location: auth/login.php");
  exit();
}

$car_id = $_GET['id'];
$car = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM cars WHERE car_id = $car_id"));
if (!$car) {
  echo "ไม่พบข้อมูลรถ";
  exit();
}

$deposit = $car['deposit'];
?>

<!-- NAVBAR +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ -->

<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="index.php">JR Car Rental</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarContent">
      <ul class="navbar-nav ms-auto">

        <li class="nav-item">
          <a class="nav-link" href="../index.php">หน้าหลัก</a>
        </li>

        <?php if (!isset($_SESSION['user_type'])): ?>
          <!-- Guest -->
          <li class="nav-item">
            <a class="nav-link" href="auth/register.php">สมัครสมาชิก</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="auth/login.php">เข้าสู่ระบบ</a>
          </li>

        <?php elseif ($_SESSION['user_type'] == 'customer'): ?>
          <!-- ลูกค้า -->
          <li class="nav-item">
            <a class="nav-link" href="booking_history.php">ประวัติการจอง</a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              บัญชีของฉัน
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="../profile.php">แก้ไขข้อมูลส่วนตัว</a></li>
              <li><a class="dropdown-item" href="../auth/logout.php">ออกจากระบบ</a></li>
            </ul>
          </li>

        <?php elseif ($_SESSION['user_type'] == 'staff'): ?>
          <!-- พนักงาน -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              เมนูพนักงาน
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="../staff/checkup.php">ตรวจสอบรถ</a></li>
              <li><a class="dropdown-item" href="../staff/return_car.php">รับคืนรถ</a></li>
              <li><a class="dropdown-item" href="../profile.php">แก้ไขข้อมูลส่วนตัว</a></li>
              <li><a class="dropdown-item" href="../auth/logout.php">ออกจากระบบ</a></li>
            </ul>
          </li>

        <?php elseif ($_SESSION['user_type'] == 'admin'): ?>
          <!-- ผู้ดูแลระบบ -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              ผู้ดูแลระบบ
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="../admin/dashboard.php">แดชบอร์ด</a></li>
              <li><a class="dropdown-item" href="../admin/manage_staff.php">จัดการพนักงาน</a></li>
              <li><a class="dropdown-item" href="../admin/manage_cars.php">จัดการรถ</a></li>
              <li><a class="dropdown-item" href="../admin/manage_bookings.php">จัดการการจอง</a></li>
              <li><a class="dropdown-item" href="../profile.php">แก้ไขข้อมูลส่วนตัว</a></li>
              <li><a class="dropdown-item" href="../auth/logout.php">ออกจากระบบ</a></li>
            </ul>
          </li>
        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>

<!-- NAVBAR +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ -->

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>จองรถ - <?= $car['brand'] ?> <?= $car['model'] ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

  <div class="container py-5">
    <h2>จองรถ: <?= $car['brand'] ?> <?= $car['model'] ?></h2>
    <div class="row">
      <div class="col-md-6">
        <?php $carImg = "../uploads/cars/" . $car['image_path']; ?>
        <img src="<?= $carImg ?>" class="img-fluid" alt="car image">

        <p class="mt-3"><strong>ค่าบริการ:</strong> <?= number_format($car['daily_rate']) ?> บาท/วัน</p>
        <p class="mt-1">
          <strong>ค่ามัดจำ:</strong> <?= number_format($deposit) ?> บาท
          <small class="text-muted">ชำระตอนจอง</small>
        </p>
        <p><strong>รายละเอียด:</strong> <?= $car['description'] ?></p>
      </div>
      <div class="col-md-6">
        <form method="post" action="payment_qr.php">
          <input type="hidden" name="car_id" value="<?= $car['car_id'] ?>">
          <input type="hidden" name="rate" value="<?= $car['daily_rate'] ?>">
          <input type="hidden" name="deposit" value="<?= $deposit ?>">

          <div class="mb-3">
            <label>วันที่เริ่มเช่า</label>
            <input type="date" name="start_date" id="start_date" class="form-control" required>
          </div>
          <div class="mb-3">
            <label>วันที่คืนรถ</label>
            <input type="date" name="end_date" id="end_date" class="form-control" required>
          </div>

          <div class="mb-3">
            <label for="location" class="form-label">สถานที่รับ-คืนรถ</label>
            <select class="form-select" name="location" id="location" required>
              <option value="">-- กรุณาเลือก --</option>
              <option value="หน้าร้าน">หน้าร้าน</option>
              <option value="สนามบิน">สนามบิน</option>
              <option value="ปั๊มปตทข้างสนามบิน">ปั๊มปตทข้างสนามบิน</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="note" class="form-label">หมายเหตุ (ถ้ามี)</label>
            <textarea name="note" id="note" class="form-control" rows="3" placeholder="หมายเหตุ...."></textarea>
          </div>

          <div class="mb-3">
            <label>ค่ามัดจำรถ</label>
            <input type="text" id="total_price" class="form-control" readonly>
            <input type="hidden" name="total_price" id="hidden_total">
          </div>

          <!-- สรุปรายการ -->
          <div id="breakdown" class="border rounded p-3 bg-light mb-3" style="display:none">
            <div class="d-flex justify-content-between">
              <span>ค่ามัดจำ</span>
              <strong id="bd_deposit"></strong>
            </div>
            <div class="d-flex justify-content-between">
              <span>ค่าเช่า <span id="bd_days"></span> วัน (ชำระวันคืนรถ)</span>
              <strong id="bd_rent_total"></strong>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between">
              <span>ชำระค่ามัดจำเพื่อทำการจอง</span>
              <strong id="bd_paynow" class="text-success"></strong>
            </div>
          </div>

          <button type="submit" class="btn btn-success">ยืนยันการจอง</button>
        </form>
      </div>
    </div>
  </div>

  <?php include("../includes/footer.php"); ?>

  <script>
    const rate = <?= (float)$car['daily_rate'] ?>;
    const deposit = <?= (float)$deposit ?>;

    const start = document.getElementById('start_date');
    const end = document.getElementById('end_date');
    const total = document.getElementById('total_price');
    const hiddenTotal = document.getElementById('hidden_total');

    const bd = {
      box: document.getElementById('breakdown'),
      deposit: document.getElementById('bd_deposit'),
      rate: document.getElementById('bd_rate'),
      days: document.getElementById('bd_days'),
      rentTotal: document.getElementById('bd_rent_total'),
      paynow: document.getElementById('bd_paynow'),
    };

    function numberFmt(n) {
      return n.toLocaleString('th-TH', {
        minimumFractionDigits: 0
      });
    }

    function calculatePrice() {
      if (start.value && end.value) {
        const d1 = new Date(start.value);
        const d2 = new Date(end.value);

        // ต่างกันเป็นจำนวนวันปัดขึ้น และอย่างน้อย 1 วัน
        const msPerDay = 24 * 60 * 60 * 1000;
        const diff = Math.ceil((d2 - d1) / msPerDay);
        const days = Math.max(1, diff);

        // เงินที่ต้องจ่ายตอนนี้ = ค่ามัดจำเท่านั้น
        const payNow = deposit;

        // ค่าเช่ารวม (แสดงไว้เฉยๆ ยังไม่เก็บตอนนี้)
        const rentTotal = rate * days;

        // อัปเดต UI
        total.value = numberFmt(payNow) + ' บาท';
        hiddenTotal.value = payNow;

        bd.deposit.textContent = numberFmt(deposit) + ' บาท';
        // bd.rate.textContent = numberFmt(rate) + ' บาท/วัน';
        bd.days.textContent = days;
        bd.rentTotal.textContent = numberFmt(rentTotal) + ' บาท';
        bd.paynow.textContent = numberFmt(payNow) + ' บาท';
        bd.box.style.display = 'block';

        // เก็บ days เพิ่มไปกับฟอร์ม (ถ้ายังไม่มีให้เพิ่ม input hidden)
        let hd = document.getElementById('hidden_days');
        if (!hd) {
          hd = document.createElement('input');
          hd.type = 'hidden';
          hd.name = 'days';
          hd.id = 'hidden_days';
          document.querySelector('form').appendChild(hd);
        }
        hd.value = days;
      }
    }

    start.addEventListener("change", () => {
      // กำหนด min ของ end ให้ไม่ก่อน start (อนุญาตวันเดียวกันได้)
      if (start.value) end.min = start.value;
      calculatePrice();
    });
    end.addEventListener("change", calculatePrice);
  </script>


</body>

</html>