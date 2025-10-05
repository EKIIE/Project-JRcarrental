<?php
include("db.php");
session_start();
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>JR Car Rental | รถให้เช่า</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">

  <style>
    body {
      font-family: 'Kanit', sans-serif;
      background-color: #fdfaf6;
      color: #3a2c2c;
    }

    .navbar {
      font-weight: 500;
      background-color: #3a2c2c !important;
    }

    .navbar .nav-link,
    .navbar-brand {
      color: #fff !important;
      transition: 0.2s ease;
    }

    .navbar .nav-link:hover {
      color: #d4b499 !important;
    }

    .hero-box {
      max-width: 100%;
      border-radius: 1.5rem;
      background-color: #000;
    }

    .hero-overlay {
      text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
    }

    .hero a.btn-light:hover {
      background-color: #d4b499;
      color: #fff;
      border: none;
    }

    .card {
      border: none;
      border-radius: 1rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      transition: transform 0.2s ease-in-out;
      background: #ffffffff;
    }

    .card:hover {
      transform: translateY(-3px);
    }

    .card-title {
      font-weight: 600;
      color: #4b382a;
    }

    .btn-outline-secondary {
      border-color: #d4b499;
      color: #4b382a;
    }

    .btn-outline-secondary:hover {
      background-color: #d4b499;
      color: white;
    }

    footer {
      background-color: #3a2c2c;
      color: #f8f4ee;
      padding: 20px 0;
      margin-top: 40px;
    }

    @media (max-width: 768px) {
      h2 {
        font-size: 1.5rem;
      }
    }
  </style>
</head>

<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold" href="index.php">JR Car Rental</a>
      <button class="navbar-toggler bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarContent">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="index.php">หน้าหลัก</a></li>

          <?php if (!isset($_SESSION['user_type'])): ?>
            <li class="nav-item"><a class="nav-link" href="auth/register.php">สมัครสมาชิก</a></li>
            <li class="nav-item"><a class="nav-link" href="auth/login.php">เข้าสู่ระบบ</a></li>
          <?php elseif ($_SESSION['user_type'] == 'customer'): ?>
            <li class="nav-item"><a class="nav-link" href="booking/booking_history.php">ประวัติการจอง</a></li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">บัญชีของฉัน</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="profile.php">แก้ไขข้อมูลส่วนตัว</a></li>
                <li><a class="dropdown-item" href="auth/logout.php">ออกจากระบบ</a></li>
              </ul>
            </li>
          <?php elseif ($_SESSION['user_type'] == 'staff'): ?>
            <li class="nav-item"><a class="nav-link" href="staff/staff_dashboard.php">แดชบอร์ดพนักงาน</a></li>
            <li class="nav-item"><a class="nav-link" href="auth/logout.php">ออกจากระบบ</a></li>
          <?php elseif ($_SESSION['user_type'] == 'admin'): ?>
            <li class="nav-item"><a class="nav-link" href="admin/dashboard.php">แดชบอร์ดผู้ดูแล</a></li>
            <li class="nav-item"><a class="nav-link" href="auth/logout.php">ออกจากระบบ</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero py-5">
    <div class="container">
      <div class="hero-box position-relative rounded-4 overflow-hidden shadow-sm">
        <img src="img/banner.jpg" class="w-100" style="height: 420px; object-fit: cover; filter: brightness(65%);">
        <div class="hero-overlay position-absolute top-50 start-50 translate-middle text-center text-white px-3">
          <h1 class="fw-bold mb-3" style="font-size: 2.5rem;">ยินดีต้อนรับสู่ JR Car Rental</h1>
          <p class="mb-4" style="font-size: 1.2rem;">บริการเช่ารถคุณภาพ ราคายุติธรรม พร้อมให้บริการทุกการเดินทางของคุณ</p>
          <!-- <a href="#cars" class="btn btn-light px-4 py-2 fw-semibold">ดูรถที่ให้บริการ</a> -->
        </div>
      </div>
    </div>
  </section>


  <!-- Content -->
  <div class="container py-5">
    <h2 class="fw-semibold mb-4 text-center">รายการรถเช่า</h2>
    <div class="row g-4">
      <?php
      $cars = mysqli_query($conn, "SELECT * FROM cars WHERE status != 'maintenance'");
      if (mysqli_num_rows($cars) == 0) {
        echo "<div class='text-center text-muted py-5'>— ยังไม่มีรถให้เช่าในขณะนี้ —</div>";
      }
      while ($car = mysqli_fetch_assoc($cars)): ?>
        <div class="col-md-4 col-sm-6">
          <div class="card h-100">
            <img src="uploads/cars/<?= $car['image_path'] ?>" class="card-img-top" alt="<?= $car['model'] ?>"
              style="height:200px; object-fit:cover; border-top-left-radius:1rem; border-top-right-radius:1rem;">
            <div class="card-body text-center">
              <h5 class="card-title"><?= $car['brand'] . " " . $car['model'] ?></h5>
              <p class="text-muted">ราคา <?= number_format($car['daily_rate']) ?> บาท/วัน</p>
              <button class="btn btn-outline-secondary w-100"
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
      <?php endwhile; ?>
    </div>
  </div>

  <!-- REVIEWS -->
  <section id="reviews" class="py-5">
    <div class="container">
      <h2 class="section-title">เสียงจากลูกค้าของเรา</h2>
      <div class="row g-4">
        <div class="col-md-4">
          <div class="review-card">
            <p>"บริการดีมาก รถใหม่สะอาด พนักงานเป็นกันเองมากค่ะ"</p>
            <small class="text-muted">— คุณพลอย</small>
          </div>
        </div>
        <div class="col-md-4">
          <div class="review-card">
            <p>"เช่ารถง่าย ระบบจองออนไลน์สะดวกสุด ๆ แนะนำเลยครับ"</p>
            <small class="text-muted">— คุณภัทร</small>
          </div>
        </div>
        <div class="col-md-4">
          <div class="review-card">
            <p>"ราคาคุ้มค่ากับคุณภาพ บริการตรงเวลาและเชื่อถือได้"</p>
            <small class="text-muted">— คุณตาล</small>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CONTACT -->
  <section id="contact" class="py-5">
    <div class="container text-center">
      <h2 class="section-title">ติดต่อเรา</h2>
      <p>📍 38 ซ.12 ถ.มหาโชค ต.ป่าตัน อ.เมือง จ.เชียงใหม่ 50300</p>
      <p>📞 โทร: 099-123-4567 | ✉️ อีเมล: info@jrcarrental.com</p>
      <!-- <a href="https://line.me/R/" class="btn btn-dark mt-2 px-4">แชทผ่าน LINE</a> -->
    </div>
  </section>

  <!-- Modal -->
  <div class="modal fade" id="carDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content rounded-3">
        <div class="modal-header">
          <h5 class="modal-title">รายละเอียดรถ</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <img id="modalCarImage" src="" class="img-fluid mb-3 rounded-3" alt="car"
            style="height:400px; object-fit:cover;">
          <h5 id="modalCarTitle"></h5>
          <p id="modalCarDescription"></p>
          <p><strong>ค่าบริการ:</strong> <span id="modalCarRate"></span> บาท/วัน</p>
        </div>
        <div class="modal-footer">
          <a id="modalBookBtn" href="#" class="btn btn-dark px-4">จองรถ</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="text-center">
    <div class="container">
      <p class="mb-0 small">© 2025 JR Car Rental. All rights reserved.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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