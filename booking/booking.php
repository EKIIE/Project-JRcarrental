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

// $deposit = $car['deposit'];
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

  <!-- Pikaday CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pikaday/css/pikaday.css">
  <!-- Moment.js for better date format (optional) -->
  <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
  <!-- Pikaday JS -->
  <script src="https://cdn.jsdelivr.net/npm/pikaday/pikaday.js"></script>

</head>

<body>

  <div class="container py-5">
    <h2>จองรถ: <?= $car['brand'] ?> <?= $car['model'] ?></h2>
    <div class="row">
      <div class="col-md-6">
        <?php $carImg = "../uploads/cars/" . $car['image_path']; ?>
        <img src="<?= $carImg ?>" class="img-fluid" alt="car image">


        <p class="mt-3"><strong>ค่าบริการ:</strong> <?= number_format($car['daily_rate']) ?> บาท/วัน </p>
        <!-- <p class="mt-3"><strong>ค่ามัดจำ:</strong> 20%</p> -->
        <p><strong>รายละเอียด:</strong> <?= $car['description'] ?></p>
      </div>
      <div class="col-md-6">
        <form method="post" action="payment_qr.php">
          <input type="hidden" name="car_id" value="<?= $car['car_id'] ?>">
          <input type="hidden" name="rate" value="<?= $car['daily_rate'] ?>">

          <div class="mb-3">
            <label>เลือกวันที่เริ่มเช่า</label>
            <input type="text" name="start_date" id="start_date" class="form-control">
            <!-- <input type="date" name="start_date" id="start_date" class="form-control" required> -->
          </div>
          <div class="mb-3">
            <label>เลือกวันที่คืนรถ</label>
            <input type="text" name="end_date" id="end_date" class="form-control">
            <!-- <input type="date" name="end_date" id="end_date" class="form-control" required> -->
          </div>
          <script>
            const pickerStart = new Pikaday({
              field: document.getElementById('start_date'),
              format: 'YYYY-MM-DD',
              minDate: new Date(),
              onSelect: function(date) {
                pickerEnd.setMinDate(date); // ป้องกันเลือกวันคืนก่อนวันรับ
              }
            });

            const pickerEnd = new Pikaday({
              field: document.getElementById('end_date'),
              format: 'YYYY-MM-DD',
              minDate: new Date(),
            });
          </script>


          <div class="mb-3">
            <label for="pickup_time">เลือกเวลา (เวลาทำการ 6.00-19.00)</label>
            <div class="d-flex gap-2">
              <!-- เลือกชั่วโมง -->
              <select id="pickup_hour" class="form-select" style="max-width:120px;" required>
                <?php for ($h = 6; $h <= 18; $h++): ?>
                  <option value="<?= sprintf('%02d', $h) ?>"><?= sprintf('%02d', $h) ?></option>
                <?php endfor; ?>
              </select>

              <!-- เลือกนาที -->
              <select id="pickup_minute" class="form-select" style="max-width:120px;" required>
                <option value="00">00</option>
                <option value="30">30</option>
              </select>

              <!-- input hidden ไว้ส่งค่า HH:MM ไป backend -->
              <input type="hidden" name="pickup_time" id="pickup_time">
            </div>
            <script>
              const hourSel = document.getElementById("pickup_hour");
              const minSel = document.getElementById("pickup_minute");
              const hiddenPickup = document.getElementById("pickup_time");

              function updatePickupTime() {
                hiddenPickup.value = hourSel.value + ":" + minSel.value;
              }

              hourSel.addEventListener("change", updatePickupTime);
              minSel.addEventListener("change", updatePickupTime);

              // ตั้งค่าเริ่มต้น
              updatePickupTime();
            </script>

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
            <!-- <label>ค่ามัดจำ 20% </label> -->
            <input type="hidden" id="total_price" class="form-control" readonly>
            <input type="hidden" name="total_price" id="hidden_total">
          </div>


          <div id="breakdown" class="border rounded p-3 bg-light mb-3" style="display:none">
            <div class="d-flex justify-content-between">
              <span>ค่ามัดจำ</span>
              <strong id="bd_deposit"></strong>
            </div>
            <div class="d-flex justify-content-between">
              <span>ค่าเช่า <span id="bd_days"></span> วัน</span>
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

    const start = document.getElementById('start_date');
    const end = document.getElementById('end_date');
    const total = document.getElementById('total_price');
    const hiddenTotal = document.getElementById('hidden_total');

    const bd = {
      box: document.getElementById('breakdown'),
      deposit: document.getElementById('bd_deposit'),
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

        let rentTotal = 0;

        if (days >= 30) {
          // ✅ คิดแบบรายเดือน
          const months = Math.floor(days / 30);
          const leftover = days % 30;
          const monthlyRate = (rate * 30) * 0.85; // ส่วนลด 15%
          rentTotal = (months * monthlyRate) + (leftover * rate);
        } else if (days >= 15 && days < 30) {
          // ✅ คิดแบบรายวัน + ลด 5%
          rentTotal = (rate * days) * 0.95;
        } else {
          // ✅ คิดแบบรายวันปกติ
          rentTotal = rate * days;
        }

        const deposit = Math.ceil(rentTotal * 0.2);

        // อัปเดต UI
        total.value = numberFmt(deposit) + ' บาท';
        hiddenTotal.value = deposit;

        bd.deposit.textContent = numberFmt(deposit) + ' บาท';
        bd.days.textContent = days;
        bd.rentTotal.textContent = numberFmt(rentTotal) + ' บาท';
        bd.paynow.textContent = numberFmt(deposit) + ' บาท';
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
  <!-- เช็คว่ารถว่างไหม -->
  <script>
    function checkAvailability() {
      if (start.value && end.value) {
        fetch("check_availability.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded"
            },
            body: `car_id=<?= $car['car_id'] ?>&start_date=${start.value}&end_date=${end.value}`
          })
          .then(res => res.json())
          .then(data => {
            const btn = document.querySelector("button[type=submit]");
            if (!data.available) {
              alert(data.msg);
              btn.disabled = true; // ❌ ปิดปุ่ม
              btn.classList.add("btn-secondary");
              btn.classList.remove("btn-success");
              btn.textContent = "รถไม่ว่างในวันที่เลือก";
            } else {
              btn.disabled = false; // ✅ เปิดปุ่ม
              btn.classList.add("btn-success");
              btn.classList.remove("btn-secondary");
              btn.textContent = "ยืนยันการจอง";
            }
          })
          .catch(err => console.error(err));
      }
    }

    start.addEventListener("change", checkAvailability);
    end.addEventListener("change", checkAvailability);
  </script>

</body>

</html>