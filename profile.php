<?php
session_start();
require("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

$userQuery = mysqli_query($conn, "SELECT * FROM users WHERE user_id = $user_id");
$user = mysqli_fetch_assoc($userQuery);

$profile_picture = !empty($user['profile_picture']) ? 'uploads/licenses/' . $user['profile_picture'] : 'img/default_profile.png';

// ดึงข้อมูลจากตาราง employees หรือ customers ตาม user_type
if ($user_type == 'staff') {
    $infoQuery = mysqli_query($conn, "SELECT * FROM employees WHERE user_id = $user_id");
} elseif ($user_type == 'customer') {
    $infoQuery = mysqli_query($conn, "SELECT * FROM customers WHERE user_id = $user_id");
} else {
    $infoQuery = false; // admin อาจไม่มีข้อมูลเพิ่มเติม
}

$info = $infoQuery ? mysqli_fetch_assoc($infoQuery) : [];

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
                            <li><a class="dropdown-item" href="profile.php">ข้อมูลส่วนตัว</a></li>
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
                            <li><a class="dropdown-item" href="profile.php">ข้อมูลส่วนตัว</a></li>
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
                            <li><a class="dropdown-item" href="profile.php">ข้อมูลส่วนตัว</a></li>
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
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลส่วนตัว</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: "Kanit", sans-serif;
            background: linear-gradient(180deg, #f9f6f1 0%, #f4ede6 100%);
            color: #3a2c2c;
            min-height: 100vh;
        }

        .navbar {
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

        /* กล่องโปรไฟล์หลัก */
        .card {
            border: none;
            background-color: #fffdf8;
            border-radius: 1.2rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .card h3 {
            color: #4b382a;
            font-weight: 600;
        }

        /* ปุ่ม */
        .btn-primary {
            background-color: #b78752;
            border: none;
        }

        .btn-primary:hover {
            background-color: #8c5a2e;
        }

        .btn-secondary {
            background-color: #d4b499;
            color: #3a2c2c;
            border: none;
        }

        .btn-secondary:hover {
            background-color: #b78752;
            color: #fff;
        }

        .btn-outline-dark {
            border-color: #3a2c2c;
            color: #3a2c2c;
        }

        .btn-outline-dark:hover {
            background-color: #3a2c2c;
            color: #fffdf8;
        }

        /* โปรไฟล์รูป */
        .profile-img-wrapper {
            position: relative;
            width: 130px;
            height: 130px;
            margin: 0 auto 15px;
            cursor: pointer;
        }

        .profile-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #d4b499;
            background-color: #fffaf5;
        }

        .edit-icon {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #3a2c2cae;
            color: white;
            padding: 6px;
            border-radius: 50%;
            font-size: 14px;
            display: none;
        }

        .profile-img-wrapper:hover .edit-icon {
            display: block;
        }

        /* Popup */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .popup-content {
            background: #fffdf8;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
        }

        .popup-content img {
            max-width: 100%;
            max-height: 500px;
            border-radius: 8px;
        }

        /* Modal edit */
        .modal-content {
            background-color: #fffdf8;
            border-radius: 1rem;
            border: 1px solid #f1e3d3;
        }

        .modal-header,
        .modal-footer {
            border: none;
        }

        .modal-title {
            color: #4b382a;
            font-weight: 600;
        }

        .form-label {
            color: #4b382a;
            font-weight: 500;
        }

        /* footer */
        footer {
            background-color: #3a2c2c;
            color: #f8f4ee;
            padding: 20px 0;
            margin-top: 60px;
            text-align: center;
        }

        /* SweetAlert theme */
        .swal2-popup {
            font-family: "Kanit", sans-serif !important;
            border-radius: 12px !important;
            background: #fffdf8 !important;
            color: #3a2c2c !important;
        }

        .swal2-title {
            color: #4b382a !important;
        }

        .swal2-styled.swal2-confirm {
            background-color: #b78752 !important;
        }
    </style>
</head>

<body>

    <div class="container py-5">
        <div class="card mx-auto shadow p-4" style="max-width: 600px;">
            <div class="text-center">
                <h3 class="mb-3">ข้อมูลส่วนตัว</h3>
                <form action="update_profile_picture.php" method="POST" enctype="multipart/form-data">
                    <label class="profile-img-wrapper" for="profileInput">
                        <img src="<?= $profile_picture ?>" class="profile-img" alt="profile">
                        <i class="fas fa-pen edit-icon"></i>
                        <input type="file" id="profileInput" name="profile_picture" accept="image/*" onchange="this.form.submit()" hidden>
                    </label>
                </form>
            </div>
            <div class="mt-3">
                <p><strong>ชื่อผู้ใช้:</strong> <?= htmlspecialchars($user['username']) ?></p>
                <p><strong>ชื่อจริง:</strong> <?= htmlspecialchars($info['firstname']) ?> <?= htmlspecialchars($info['lastname']) ?></p>
                <p><strong>อีเมล:</strong> <?= htmlspecialchars($info['email']) ?></p>
                <p><strong>เบอร์โทร:</strong> <?= htmlspecialchars($info['phone_number']) ?></p>
                <p><strong>ที่อยู่:</strong> <?= nl2br(htmlspecialchars($info['address'])) ?></p>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4 justify-content-center">
                <!-- <a href="edit_profile.php" class="btn btn-primary">แก้ไขข้อมูล</a> -->
                <div class="d-flex flex-wrap gap-2 mt-4 justify-content-center">
                    <button class="btn btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#editProfileModal">
                        แก้ไขข้อมูล
                    </button>
                    <button class="btn btn-secondary" onclick="openPopup('uploads/licenses/<?= $info['drivers_license'] ?>')">ดูใบขับขี่</button>
                    <button class="btn btn-secondary" onclick="openPopup('uploads/licenses/<?= $info['passport_license'] ?>')">ดูบัตรประชาชน</button>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-outline-dark">กลับหน้าแรก</a>
            </div>
        </div>
    </div>

    <div class="popup-overlay" id="popup" onclick="handleOverlayClick(event)">
        <div class="popup-content">
            <img id="popup-img" src="" alt="popup image">
            <br><br>
            <button class="btn btn-danger" onclick="closePopup()">ปิด</button>
        </div>
    </div>

    <?php include("includes/footer.php"); ?>


    <!-- Modal Edit -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="update_profile.php" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileLabel">แก้ไขข้อมูลส่วนตัว</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">ชื่อจริง</label>
                        <input type="text" name="firstname" class="form-control" value="<?= htmlspecialchars($info['firstname']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">นามสกุล</label>
                        <input type="text" name="lastname" class="form-control" value="<?= htmlspecialchars($info['lastname']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">อีเมล</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($info['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">เบอร์โทร</label>
                        <input type="tel" name="phone_number" class="form-control"
                            value="<?= htmlspecialchars($info['phone_number'] ?? '') ?>"
                            pattern="[0-9]{9,10}"
                            title="กรอกตัวเลข 9-10 หลัก" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ที่อยู่</label>
                        <textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($info['address']) ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-success">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
    <!-- End Modal Edit -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function openPopup(src) {
            document.getElementById('popup-img').src = src;
            document.getElementById('popup').style.display = 'flex';
        }

        function closePopup() {
            document.getElementById('popup').style.display = 'none';
        }

        function handleOverlayClick(event) {
            if (event.target.id === 'popup') {
                closePopup();
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePopup();
            }
        });
    </script>

    <?php if (isset($_SESSION['success'])): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: '<?= $_SESSION['success'] ?>',
                timer: 2000,
                showConfirmButton: false
            });
        </script>
    <?php unset($_SESSION['success']);
    endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: '<?= $_SESSION['error'] ?>',
                confirmButtonText: 'ตกลง'
            });
        </script>
    <?php unset($_SESSION['error']);
    endif; ?>

</body>

</html>