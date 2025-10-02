<?php
include("db.php");
session_start();
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
          <a class="nav-link" href="index.php">หน้าหลัก</a>
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
            <a class="nav-link" href="booking/booking_history.php">ประวัติการจอง</a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              บัญชีของฉัน
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="profile.php">แก้ไขข้อมูลส่วนตัว</a></li>
              <li><a class="dropdown-item" href="auth/logout.php">ออกจากระบบ</a></li>
            </ul>
          </li>

        <?php elseif ($_SESSION['user_type'] == 'staff'): ?>
          <!-- พนักงาน -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              เมนูพนักงาน
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="staff/checkup.php">ตรวจสอบรถ</a></li>
              <li><a class="dropdown-item" href="staff/return_car.php">รับคืนรถ</a></li>
              <li><a class="dropdown-item" href="profile.php">แก้ไขข้อมูลส่วนตัว</a></li>
              <li><a class="dropdown-item" href="auth/logout.php">ออกจากระบบ</a></li>
            </ul>
          </li>

        <?php elseif ($_SESSION['user_type'] == 'admin'): ?>
          <!-- ผู้ดูแลระบบ -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
              ผู้ดูแลระบบ
            </a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="admin/dashboard.php">แดชบอร์ด</a></li>
              <li><a class="dropdown-item" href="admin/manage_staff.php">จัดการพนักงาน</a></li>
              <li><a class="dropdown-item" href="admin/manage_cars.php">จัดการรถ</a></li>
              <li><a class="dropdown-item" href="admin/manage_bookings.php">จัดการการจอง</a></li>
              <li><a class="dropdown-item" href="profile.php">แก้ไขข้อมูลส่วนตัว</a></li>
              <li><a class="dropdown-item" href="auth/logout.php">ออกจากระบบ</a></li>
            </ul>
          </li>
        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>

<!-- NAVBAR +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ -->

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>

  <link rel="stylesheet" href="css/style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</head>


<body>

  <div class="container py-4">
    <h2 class="mb-4">รถที่มีให้เช่า</h2>
    <div class="row">
      <?php
      $cars = mysqli_query($conn, "SELECT * FROM cars WHERE status != 'maintenance'");
      while ($car = mysqli_fetch_assoc($cars)) {
      ?>
        <div class="col-md-4">
          <div class="card mb-4">
            <img src="uploads/cars/<?= $car['image_path'] ?>"
              class="card-img-top"
              alt="<?= $car['model'] ?>"
              style="width:100%; height:200px; object-fit:cover;">

            <div class="card-body">
              <h5 class="card-title"><?= $car['brand'] ?> <?= $car['model'] ?></h5>
              <p class="card-text">ราคา <?= number_format($car['daily_rate']) ?> บาท/วัน</p>
              <button class="btn btn-outline-secondary"
                onclick='openCarDetail(<?= json_encode([
                                          "id" => $car["car_id"],
                                          "brand" => $car["brand"],
                                          "model" => $car["model"],
                                          "description" => $car["description"],
                                          "rate" => $car["daily_rate"],
                                          "image" => $car["image_path"]
                                        ]) ?>)'>
                ดูรายละเอียด
              </button>
            </div>
          </div>
        </div>
      <?php } ?>
    </div>
  </div>

  <!-- Car Detail Modal -->
  <div class="modal fade" id="carDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">รายละเอียดรถ</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <img id="modalCarImage" src="" class="img-fluid mb-3" alt="car" style="height: 400px; width: 100%; object-fit: cover;">
          <h5 id="modalCarTitle"></h5>
          <p id="modalCarDescription"></p>
          <p><strong>ค่าบริการ:</strong> <span id="modalCarRate"></span> บาท/วัน</p>
        </div>
        <div class="modal-footer">
          <a id="modalBookBtn" href="#" class="btn btn-primary">จองรถ</a>
        </div>
      </div>
    </div>
  </div>
  <!-- Car Detail Modal -->


  <?php include("includes/footer.php"); ?>

  <script>
    function openCarDetail(car) {
      document.getElementById('modalCarImage').src = 'uploads/cars/' + car.image;
      document.getElementById('modalCarTitle').textContent = car.brand + ' ' + car.model;
      document.getElementById('modalCarDescription').textContent = car.description;
      document.getElementById('modalCarRate').textContent = parseFloat(car.rate).toFixed(0);

      <?php if (isset($_SESSION['user_type'])): ?>
        document.getElementById('modalBookBtn').href = 'booking/booking.php?id=' + car.id;
      <?php else: ?>
        document.getElementById('modalBookBtn').href = 'auth/login.php';
      <?php endif; ?>

      new bootstrap.Modal(document.getElementById('carDetailModal')).show();
    }
  </script>

</body>

</html>