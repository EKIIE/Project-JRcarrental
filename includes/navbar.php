<?php
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
            <a class="nav-link" href="booking_history.php">ประวัติการจอง</a>
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