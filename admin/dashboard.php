<?php
require("../db.php");
session_start();

if ($_SESSION['user_type'] !== 'admin') {
    echo "Access Denied";
    exit();
}

// ดึงข้อมูลสรุป
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users"))['total'];
$total_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM bookings WHERE booking_status = 'approved'"))['total'];
$total_pending_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM bookings WHERE booking_status = 'pending'"))['total'];
$total_cars = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM cars"))['total'];
$available_cars = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM cars WHERE status = 'available'"))['total'];
?>


<!DOCTYPE html>
<html lang="th">

<head>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap');

        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f4f7f9;
        }

        .navbar-brand {
            font-weight: 600;
        }

        .card-icon {
            font-size: 3rem;
            color: #fff;
        }

        .card-title {
            font-weight: 500;
            color: #555;
        }

        .card-value {
            font-weight: 600;
            font-size: 2.5rem;
        }

        .rounded-3 {
            border-radius: 1rem !important;
        }

        .p-4 {
            padding: 1.5rem !important;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }

        .dashboard-title {
            font-weight: 600;
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .shadow-sm {
            box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075) !important;
        }
    </style>
</head>

<body>

    <!-- NAVBAR +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">JR Car Rental</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">หน้าหลัก</a>
                    </li>
                    <!-- ผู้ดูแลระบบ -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            ผู้ดูแลระบบ
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php">แดชบอร์ด</a></li>
                            <li><a class="dropdown-item" href="manage_staff.php">จัดการพนักงาน</a></li>
                            <li><a class="dropdown-item" href="manage_cars.php">จัดการรถ</a></li>
                            <li><a class="dropdown-item" href="manage_bookings.php">จัดการการจอง</a></li>
                            <li><a class="dropdown-item" href="../profile.php">แก้ไขข้อมูลส่วนตัว</a></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">ออกจากระบบ</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ -->
    <div class="dashboard-container">
        <h1 class="dashboard-title text-center mt-5 mb-4">แดชบอร์ดผู้ดูแลระบบ</h1>
        <div class="row g-4 mb-5">

            <!-- Card: ผู้ใช้ทั้งหมด -->
            <div class="col-md-6 col-lg-3">
                <div class="card p-4 rounded-3 shadow-sm text-center" style="background-color: #f7e69d;">
                    <div class="card-body">
                        <h5 class="card-title">ผู้ใช้ทั้งหมด</h5>
                        <p class="card-value"><?= $total_users ?></p>
                    </div>
                </div>
            </div>

            <!-- Card: รายการจองที่รอการอนุมัติ -->
            <div class="col-md-6 col-lg-3">
                <div class="card p-4 rounded-3 shadow-sm text-center" style="background-color: #fca5a5;">
                    <div class="card-body">
                        <h5 class="card-title">รายการจองที่รออนุมัติ</h5>
                        <p class="card-value"><?= $total_pending_requests ?></p>
                    </div>
                </div>
            </div>

            <!-- Card: รถยนต์ที่ใช้ได้ -->
            <div class="col-md-6 col-lg-3">
                <div class="card p-4 rounded-3 shadow-sm text-center" style="background-color: #66e0a8;">
                    <div class="card-body">
                        <h5 class="card-title">รถยนต์ที่ใช้ได้</h5>
                        <p class="card-value"><?= $available_cars ?></p>
                    </div>
                </div>
            </div>

            <!-- Card: รถยนต์ทั้งหมด -->
            <div class="col-md-6 col-lg-3">
                <div class="card p-4 rounded-3 shadow-sm text-center" style="background-color: #8cbbf5;">
                    <div class="card-body">
                        <h5 class="card-title">รถยนต์ทั้งหมด</h5>
                        <p class="card-value"><?= $total_cars ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- ซ้าย: เมนูจัดการ -->
            <div class="col-12 col-lg-6">
                <h3 class="fw-bold mb-3">Manage </h3>
                <div class="list-group">
                    <a href="manage_booking.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
                        <h5 class="mb-1 fw-bold">จัดการรายการจอง</h5>
                        <p class="mb-1">ดูรายการจองทั้งหมด, อนุมัติ, หรือปฏิเสธคำขอจอง</p>
                    </a>
                    <a href="manage_staff.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
                        <h5 class="mb-1 fw-bold">จัดการพนักงาน</h5>
                        <p class="mb-1">เพิ่มหรือแก้ไขข้อมูลของพนักงาน</p>
                    </a>
                    <a href="manage_customer.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
                        <h5 class="mb-1 fw-bold">ข้อมูลลูกค้า</h5>
                        <p class="mb-1">ข้อมูลลูกค้า</p>
                    </a>
                </div>
            </div>

            <!-- ขวา: Maintenance / ค่าใช้จ่ายรถ -->
            <div class="col-12 col-lg-6">
                <h3 class="fw-bold mb-3">Maintenance </h3>
                <div class="list-group">
                    <a href="manage_cars.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
                        <h5 class="mb-1 fw-bold">จัดการรถยนต์</h5>
                        <p class="mb-1">เพิ่ม, แก้ไข, หรือลบข้อมูลรถยนต์ในระบบ</p>
                    </a>
                    <a href="car_expenses.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
                        <h5 class="mb-1 fw-bold">บันทึกเกี่ยวกับรถ</h5>
                        <p class="mb-1">ประวัติซ่อมบำรุง ค่าใช้จ่าย และกำหนดนัดรอบถัดไป</p>
                    </a>
                    <a href="rental_overview.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
                        <h5 class="mb-1 fw-bold">ข้อมูลการเช่า</h5>
                        <p class="mb-1">ประวัติการเช่ารถ ข้อมูลเช็คสถาพใบเสร็จและสลิป</p>
                    </a>
                </div>
            </div>
        </div>


        <div class="mt-5 text-center">
            <a href="../auth/logout.php" class="btn btn-danger btn-lg rounded-pill px-5 shadow">ออกจากระบบ</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>