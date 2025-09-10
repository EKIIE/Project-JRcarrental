<?php
session_start();
require("../db.php");

// ตรวจสอบสิทธิ์เฉพาะ admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}


// ====== ประมวลผลเพิ่มพนักงานแบบ AJAX ======
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username'])) {
    header('Content-Type: application/json; charset=utf-8');
    ob_clean();
    // file_put_contents("debug.txt", print_r($_POST, true)); 

    $username = $_POST["username"];
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $email = $_POST["email"];
    $firstname = $_POST["firstname"];
    $lastname = $_POST["lastname"];
    $phone_number = $_POST["phone_number"];
    $address = $_POST["address"];
    $user_type = 'staff';

    if ($password !== $confirm_password) {
        echo json_encode(["status" => "error", "message" => "รหัสผ่านไม่ตรงกัน"]);
        exit;
    }

    if (!preg_match('/^[0-9]{9,10}$/', $phone_number)) {
        echo json_encode(["status" => "error", "message" => "เบอร์โทรต้องเป็นตัวเลข 9-10 หลัก"]);
        exit;
    }

    $upload_dir = "../uploads/licenses/";
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];

    function uploadFile($file, $upload_dir, $fieldname)
    {
        global $allowed_types;
        if ($file['error'] === 0 && in_array($file['type'], $allowed_types)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid($fieldname . "_") . '.' . $ext;
            $destination = $upload_dir . $filename;
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            if (move_uploaded_file($file['tmp_name'], $destination)) return $filename;
        }
        return false;
    }

    $driver_license_path = uploadFile($_FILES["drivers_license"], $upload_dir, "license");
    $passport_license_path = uploadFile($_FILES["passport_license"], $upload_dir, "passport");

    if (!$driver_license_path || !$passport_license_path) {
        echo json_encode(["status" => "error", "message" => "อัปโหลดไฟล์ล้มเหลว หรือไฟล์ไม่ถูกต้อง"]);
        exit;
    }

    // $check = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' OR email='$email' OR phone_number='$phone_number'");
    // if (mysqli_num_rows($check) > 0) {
    //     echo json_encode(["status" => "error", "message" => "ชื่อผู้ใช้ อีเมล หรือเบอร์โทรนี้ถูกใช้ไปแล้ว"]);
    //     exit;
    // }
    // เช็คซ้ำแบบแยกตาราง
    $check_user = mysqli_query($conn, "SELECT * FROM users WHERE username='$username'");
    $check_emp = mysqli_query($conn, "SELECT * FROM employees WHERE email='$email' OR phone_number='$phone_number'");

    if (mysqli_num_rows($check_user) > 0 || mysqli_num_rows($check_emp) > 0) {
        echo json_encode(["status" => "error", "message" => "ชื่อผู้ใช้ อีเมล หรือเบอร์โทรนี้ถูกใช้ไปแล้ว"]);
        exit;
    }


    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    // $sql = "INSERT INTO users (username, password, email, firstname, lastname, phone_number, address, drivers_license, passport_license, user_type) 
    // VALUES ('$username', '$hashed_password', '$email', '$firstname', '$lastname', '$phone_number', '$address', '$driver_license_path', '$passport_license_path', '$user_type')";
    $insert_user = "INSERT INTO users (username, password, user_type) VALUES ('$username', '$hashed_password', 'staff')";

    if (mysqli_query($conn, $insert_user)) {
        $user_id = mysqli_insert_id($conn);
        $insert_employee = "INSERT INTO employees (user_id, email, firstname, lastname, phone_number, address, drivers_license, passport_license) 
                            VALUES ('$user_id', '$email', '$firstname', '$lastname', '$phone_number', '$address', '$driver_license_path', '$passport_license_path')";
        if (mysqli_query($conn, $insert_employee)) {
            echo json_encode([
                "status" => "success",
                "message" => "เพิ่มพนักงานเรียบร้อยแล้ว",
                "data" => [
                    "user_id" => $user_id,
                    "username" => $username,
                    "firstname" => $firstname,
                    "lastname" => $lastname,
                    "email" => $email,
                    "phone_number" => $phone_number,
                    "address" => $address
                ]
            ]);
        } else {
            // ถ้า insert employee ล้มเหลว → rollback users
            mysqli_query($conn, "DELETE FROM users WHERE user_id = $user_id");
            echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาดในการเพิ่มข้อมูลพนักงาน"]);
        }
    }
    exit();
}

// ====== ลบพนักงาน ======
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);

    if ($id == $_SESSION['user_id']) {
        $_SESSION['error'] = "ไม่สามารถลบบัญชีของตัวเองได้";
    } else {
        // ดึงข้อมูลไฟล์เอกสารก่อนลบ
        // $fileQuery = mysqli_query($conn, "SELECT drivers_license, passport_license FROM users WHERE user_id = $id AND user_type = 'staff'");
        $fileQuery = mysqli_query($conn, "SELECT e.drivers_license, e.passport_license, u.user_id FROM employees e INNER JOIN users u ON e.user_id = u.user_id WHERE u.user_id = $id AND u.user_type = 'staff'");
        if ($fileQuery && mysqli_num_rows($fileQuery) > 0) {
            $fileData = mysqli_fetch_assoc($fileQuery);

            // ลบไฟล์ใบขับขี่
            if (!empty($fileData['drivers_license']) && file_exists("../uploads/licenses/" . $fileData['drivers_license'])) {
                unlink("../uploads/licenses/" . $fileData['drivers_license']);
            }

            // ลบไฟล์พาสปอร์ต/บัตรประชาชน
            if (!empty($fileData['passport_license']) && file_exists("../uploads/licenses/" . $fileData['passport_license'])) {
                unlink("../uploads/licenses/" . $fileData['passport_license']);
            }

            // ลบข้อมูลจากฐานข้อมูล
            // $deleteSQL = "DELETE FROM users WHERE user_id = $id AND user_type = 'staff'";
            // if (mysqli_query($conn, $deleteSQL)) {
            //     $_SESSION['success'] = "ลบพนักงานและเอกสารเรียบร้อยแล้ว";
            // } else {
            //     $_SESSION['error'] = "เกิดข้อผิดพลาดในการลบ: " . mysqli_error($conn);
            // }
            mysqli_query($conn, "DELETE FROM employees WHERE user_id = $id");
            mysqli_query($conn, "DELETE FROM users WHERE user_id = $id");
            $_SESSION['success'] = "ลบพนักงานและเอกสารเรียบร้อยแล้ว";
        } else {
            $_SESSION['error'] = "ไม่พบพนักงานที่ต้องการลบ";
        }
    }
    header("Location: manage_staff.php");
    exit();
}

// ดึงข้อมูลพนักงาน
// $sql = "SELECT * FROM users WHERE user_type = 'staff' ORDER BY user_id DESC";
$sql = "SELECT e.*, u.username FROM employees e 
        INNER JOIN users u ON e.user_id = u.user_id 
        WHERE u.user_type = 'staff' 
        ORDER BY u.user_id DESC";
$result = mysqli_query($conn, $sql);
?>

<!-- NAVBAR +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ -->

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>จัดการพนักงาน | JR Car Rental</title>

    <!-- Bootstrap & Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        :root {
            /* โทนหลัก JR */
            --jr-bg: #f4f7f9;
            --jr-cream: #f7e69d;
            --jr-brown: #6b4f3b;
            --jr-brown-2: #8a6a54;
            --jr-muted: #555;
        }

        body {
            font-family: 'Kanit', sans-serif;
            background: var(--jr-bg);
        }

        .rounded-3 {
            border-radius: 1rem !important;
        }

        .shadow-sm {
            box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075) !important;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }

        .page-title {
            font-weight: 600;
            font-size: 2rem;
        }

        .subtext {
            color: var(--jr-muted);
        }

        /* Navbar */
        .navbar-brand {
            font-weight: 600;
        }

        .navbar-dark .navbar-brand,
        .navbar-dark .nav-link {
            letter-spacing: .2px;
        }

        /* ปุ่มธีมอบอุ่น */
        .btn-primary {
            background: var(--jr-brown);
            border-color: var(--jr-brown);
        }

        .btn-primary:hover {
            background: var(--jr-brown-2);
            border-color: var(--jr-brown-2);
        }

        .btn-outline-secondary:hover {
            color: #fff;
        }

        /* การ์ดตาราง */
        .card-table .card-header {
            background: #fff7d9;
            /* ครีมอ่อน */
            border-bottom: 1px solid rgba(0, 0, 0, .05);
        }

        .card-table .card-header h5 {
            margin: 0;
            font-weight: 600;
        }

        .table thead th {
            white-space: nowrap;
        }

        .table> :not(caption)>*>* {
            padding: .9rem .8rem;
        }

        /* Modal โค้งนุ่ม */
        .modal-content {
            border-radius: 1rem;
        }

        .modal-header {
            border-bottom: 1px solid rgba(0, 0, 0, .06);
        }

        .modal-footer {
            border-top: 1px solid rgba(0, 0, 0, .06);
        }

        /* ช่องพรีวิวเอกสาร */
        #licensePreview,
        #passportPreview {
            min-height: 240px;
            background: #fff;
            border: 1px dashed rgba(0, 0, 0, .15) !important;
            border-radius: .75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #licensePreview img,
        #passportPreview img {
            max-height: 220px;
            border-radius: .5rem;
        }
    </style>
</head>

<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">JR Car Rental</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php">หน้าหลัก</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">ผู้ดูแลระบบ</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php">แดชบอร์ด</a></li>
                            <li><a class="dropdown-item active" href="manage_staff.php">จัดการพนักงาน</a></li>
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
    <!-- /NAVBAR -->

    <div class="dashboard-container">
        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h3 class="page-title">จัดการพนักงาน</h3>
                <div class="subtext">เพิ่ม/แก้ไขพนักงาน และดูเอกสารประกอบ</div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                    <i class="fas fa-user-plus me-1"></i> เพิ่มพนักงาน
                </button>
                <form class="d-flex" method="GET">
                    <input type="text" class="form-control" name="search" placeholder="ค้นหา..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                    <button class="btn btn-outline-secondary ms-2" type="submit">ค้นหา</button>
                </form>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card rounded-3 shadow-sm card-table">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">รายการพนักงาน</h5>
                <span class="text-muted small">รวม <?= number_format(mysqli_num_rows($result) ?? 0) ?> รายการ</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>ชื่อผู้ใช้</th>
                                <th>ชื่อ-นามสกุล</th>
                                <th>อีเมล</th>
                                <th>เบอร์โทร</th>
                                <th>ที่อยู่</th>
                                <th class="text-center">เอกสาร</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?= $row['user_id'] ?></td>
                                        <td><?= htmlspecialchars($row['username']) ?></td>
                                        <td><?= htmlspecialchars($row['firstname']) ?> <?= htmlspecialchars($row['lastname']) ?></td>
                                        <td><?= htmlspecialchars($row['email']) ?></td>
                                        <td><?= htmlspecialchars($row['phone_number']) ?></td>
                                        <td><?= htmlspecialchars($row['address']) ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-info"
                                                data-bs-toggle="modal"
                                                data-bs-target="#docsModal"
                                                data-license="<?= htmlspecialchars($row['drivers_license']) ?>"
                                                data-passport="<?= htmlspecialchars($row['passport_license']) ?>"
                                                data-fullname="<?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?>">
                                                ดูเอกสาร
                                            </button>
                                        </td>
                                        <td class="text-center">
                                            <a href="#"
                                                class="btn btn-sm btn-warning"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editStaffModal"
                                                data-id="<?= $row['user_id'] ?>"
                                                data-username="<?= htmlspecialchars($row['username']) ?>"
                                                data-email="<?= htmlspecialchars($row['email']) ?>"
                                                data-firstname="<?= htmlspecialchars($row['firstname']) ?>"
                                                data-lastname="<?= htmlspecialchars($row['lastname']) ?>"
                                                data-phone="<?= htmlspecialchars($row['phone_number']) ?>"
                                                data-address="<?= htmlspecialchars($row['address']) ?>">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="manage_staff.php?delete=<?= $row['user_id'] ?>" class="btn btn-sm btn-danger"
                                                onclick="return confirm('ลบพนักงานคนนี้?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">ไม่พบข้อมูลพนักงาน</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: Add Staff -->
        <div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form id="addStaffForm" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addStaffModalLabel">เพิ่มพนักงานใหม่</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" name="firstname" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="lastname" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone_number" class="form-control" pattern="[0-9]{9,10}" title="กรอกตัวเลข 9-10 หลัก" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">ใบขับขี่ (JPG/PNG)</label>
                                <input type="file" name="drivers_license" class="form-control" accept="image/jpeg,image/jpg,image/png" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">บัตรประชาชน/พาสปอร์ต (JPG/PNG)</label>
                                <input type="file" name="passport_license" class="form-control" accept="image/jpeg,image/jpg,image/png" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Docs -->
        <div class="modal fade" id="docsModal" tabindex="-1" aria-labelledby="docsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="docsModalLabel">เอกสารพนักงาน</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6>ใบขับขี่</h6>
                                <div id="licensePreview" class="p-2 text-center bg-white"></div>
                            </div>
                            <div class="col-md-6">
                                <h6>บัตรประชาชน / พาสปอร์ต</h6>
                                <div id="passportPreview" class="p-2 text-center bg-white"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Edit Staff -->
        <div class="modal fade" id="editStaffModal" tabindex="-1" aria-labelledby="editStaffLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="staff_update.php" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editStaffLabel">แก้ไขข้อมูลพนักงาน</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" id="edit_username" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" id="edit_email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" name="firstname" id="edit_firstname" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="lastname" id="edit_lastname" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone_number" id="edit_phone" class="form-control" pattern="[0-9]{9,10}" title="กรอกตัวเลข 9-10 หลัก" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" id="edit_address" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div><!-- /.dashboard-container -->

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal แสดงเอกสาร
            var docsModal = document.getElementById('docsModal');
            docsModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var license = button.getAttribute('data-license');
                var passport = button.getAttribute('data-passport');
                var fullname = button.getAttribute('data-fullname') || '';

                // ใส่ชื่อพนักงานที่หัวโมดอล
                var titleEl = document.getElementById('docsModalLabel');
                titleEl.innerHTML = fullname 
                ? `เอกสารพนักงาน <span class="fw-light">— ${fullname}</span>` 
                : 'เอกสารพนักงาน';

                var licensePath = '../uploads/licenses/' + license;
                var passportPath = '../uploads/licenses/' + passport;

                document.getElementById('licensePreview').innerHTML =
                    /\.(jpg|jpeg|png)$/i.test(license) ?
                    `<img src="${licensePath}" class="img-fluid">` :
                    `<a class="btn btn-outline-secondary" href="${licensePath}" target="_blank"><i class="fa-regular fa-file-pdf me-1"></i> เปิดไฟล์ PDF</a>`;

                document.getElementById('passportPreview').innerHTML =
                    /\.(jpg|jpeg|png)$/i.test(passport) ?
                    `<img src="${passportPath}" class="img-fluid">` :
                    `<a class="btn btn-outline-secondary" href="${passportPath}" target="_blank"><i class="fa-regular fa-file-pdf me-1"></i> เปิดไฟล์ PDF</a>`;
            });

            // Modal แก้ไข: preload ค่าลงฟอร์ม
            var editModal = document.getElementById('editStaffModal');
            editModal.addEventListener('show.bs.modal', function(event) {
                var b = event.relatedTarget;
                document.getElementById('edit_user_id').value = b.getAttribute('data-id');
                document.getElementById('edit_username').value = b.getAttribute('data-username');
                document.getElementById('edit_email').value = b.getAttribute('data-email');
                document.getElementById('edit_firstname').value = b.getAttribute('data-firstname');
                document.getElementById('edit_lastname').value = b.getAttribute('data-lastname');
                document.getElementById('edit_phone').value = b.getAttribute('data-phone');
                document.getElementById('edit_address').value = b.getAttribute('data-address');
            });

            // เพิ่มพนักงาน (AJAX เดิม)
            document.getElementById("addStaffForm").addEventListener("submit", function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                fetch("manage_staff.php", {
                        method: "POST",
                        body: formData,
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === "success") {
                            document.querySelector(".card-table tbody").insertAdjacentHTML("afterbegin", `
                    <tr>
                        <td>${data.data.user_id}</td>
                        <td>${data.data.username}</td>
                        <td>${data.data.firstname} ${data.data.lastname}</td>
                        <td>${data.data.email}</td>
                        <td>${data.data.phone_number}</td>
                        <td>${data.data.address}</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                    </tr>
                `);
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ!',
                                text: data.message,
                                timer: 1800,
                                showConfirmButton: false
                            });
                            bootstrap.Modal.getInstance(document.getElementById('addStaffModal')).hide();
                            this.reset();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: data.message
                            });
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์'
                        });
                    });
            });
        });
    </script>

    <?php if (isset($_SESSION['success'])): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ!',
                text: '<?= htmlspecialchars($_SESSION['success']) ?>',
                timer: 1800,
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
                text: '<?= htmlspecialchars($_SESSION['error']) ?>',
                confirmButtonText: 'ตกลง'
            });
        </script>
    <?php unset($_SESSION['error']);
    endif; ?>

</body>

</html>