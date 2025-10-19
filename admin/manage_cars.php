<?php
session_start();
require("../db.php");

// ตรวจสอบสิทธิ์เฉพาะ admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/* ---------- ฟังก์ชันช่วยอัปโหลดรูปอย่างปลอดภัย ---------- */
function ensure_dir($dir)
{
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}
function safe_image_upload($file, $prefix, $upload_dir, $max_mb = 8)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return [false, "ไม่พบไฟล์หรืออัปโหลดผิดพลาด"];
    }

    $size_ok = $file['size'] > 0 && $file['size'] <= ($max_mb * 1024 * 1024);
    if (!$size_ok) return [false, "ไฟล์ใหญ่เกินไป (เกิน {$max_mb}MB)"];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
    ];
    if (!isset($allowed[$mime])) return [false, "ชนิดไฟล์ไม่ถูกต้อง (รองรับ JPG/PNG)"];

    $ext = $allowed[$mime];
    $name = $prefix . bin2hex(random_bytes(8)) . "." . $ext;

    ensure_dir($upload_dir);
    $dest = rtrim($upload_dir, "/") . "/" . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) return [false, "ย้ายไฟล์ไม่สำเร็จ"];
    return [true, $name];
}

// ดึงรายการประเภทรถ
$types = [];
$resTypes = mysqli_query($conn, "SELECT * FROM car_types ORDER BY type_name ASC");
while ($resTypes && $r = mysqli_fetch_assoc($resTypes)) {
    $types[] = $r;
}
// ดึงรายการเชื้อเพลิง
$gases = [];
$resGas = mysqli_query($conn, "SELECT * FROM car_gas ORDER BY gas_name ASC");
while ($resGas && $r = mysqli_fetch_assoc($resGas)) {
    $gases[] = $r;
}

/* ---------- เพิ่มรถแบบ AJAX ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['brand'])) {
    header('Content-Type: application/json; charset=utf-8');

    $brand         = trim($_POST["brand"] ?? '');
    $model         = trim($_POST["model"] ?? '');
    $year          = trim($_POST["year"] ?? '');
    $license_plate = trim($_POST["license_plate"] ?? '');
    $color         = trim($_POST["color"] ?? '');
    $type          = trim($_POST["car_type"] ?? '');
    $daily_rate    = trim($_POST["daily_rate"] ?? '');
    $deposit       = trim($_POST["deposit"] ?? '0');
    $description   = trim($_POST["description"] ?? '');
    $status        = 'maintenance'; // เริ่มต้นส่งเข้าซ่อม/ตรวจสภาพก่อนปล่อยเช่าจริง

    // ตรวจสอบข้อมูล
    if (!ctype_digit($year) || strlen($year) != 4 || (int)$year < 1900 || (int)$year > (int)date('Y')) {
        echo json_encode(["status" => "error", "message" => "ปีรถไม่ถูกต้อง"]);
        exit;
    }
    if (!is_numeric($daily_rate) || (float)$daily_rate <= 0) {
        echo json_encode(["status" => "error", "message" => "ราคารายวันไม่ถูกต้อง"]);
        exit;
    }
    if (!is_numeric($deposit) || (float)$deposit < 0) {
        echo json_encode(["status" => "error", "message" => "ค่ามัดจำไม่ถูกต้อง"]);
        exit;
    }

    $upload_dir = "../uploads/cars/";
    ensure_dir($upload_dir);

    // เช็คทะเบียนซ้ำ (prepared)
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM cars WHERE license_plate = ?");
    mysqli_stmt_bind_param($stmt, "s", $license_plate);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $dup);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($dup > 0) {
        echo json_encode(["status" => "error", "message" => "ทะเบียนรถนี้มีอยู่แล้ว"]);
        exit;
    }

    //--------------------------------------------------------------------------
    // Upload main image
    // อัปโหลดรูปหลัก
    // [$okMain, $main_or_err] = safe_image_upload($_FILES['main_image'] ?? null, "main_", $upload_dir);
    // if (!$okMain) {
    //     echo json_encode(["status" => "error", "message" => $main_or_err]);
    //     exit;
    // }
    // $main_image_path = $main_or_err;

    // ใช้ URL แทนรูปอัปโหลด
    $main_image_url = trim($_POST['main_image_url'] ?? '');
    if (!filter_var($main_image_url, FILTER_VALIDATE_URL)) {
        echo json_encode(["status" => "error", "message" => "URL รูปหลักไม่ถูกต้อง"]);
        exit;
    }
    $main_image_path = $main_image_url; // เก็บ URL แทนไฟล์
    //--------------------------------------------------------------------------

    // Insert cars (prepared)
    $type_id = (int)($_POST["car_type"] ?? 0);
    $gas_id  = (int)($_POST["gas_id"] ?? 0);

    $stmt = mysqli_prepare($conn, "INSERT INTO cars 
    (brand, model, year, license_plate, color, type_id, gas_id, daily_rate, deposit, status, image_path, description)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param(
        $stmt,
        "ssissiiddsss", // <-- น่าจะมีปัญหาตรงนี้
        $brand,
        $model,
        $year,
        $license_plate,
        $color,
        $type_id,
        $gas_id,
        $daily_rate,
        $deposit,
        $status,
        $main_image_path,
        $description
    );


    $ok = mysqli_stmt_execute($stmt);
    $new_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        @unlink($upload_dir . $main_image_path);
        echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาดในการเพิ่มรถ"]);
        exit;
    }

    /*
    // อัปโหลดรูปเพิ่มเติม (optional)
    if (isset($_FILES['extra_images']) && !empty($_FILES['extra_images']['tmp_name'][0])) {
        $count = count($_FILES['extra_images']['tmp_name']);
        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name'     => $_FILES['extra_images']['name'][$i],
                'type'     => $_FILES['extra_images']['type'][$i],
                'tmp_name' => $_FILES['extra_images']['tmp_name'][$i],
                'error'    => $_FILES['extra_images']['error'][$i],
                'size'     => $_FILES['extra_images']['size'][$i],
            ];
            [$okX, $xname] = safe_image_upload($file, "extra_", $upload_dir);
            if ($okX) {
                $stmt = mysqli_prepare($conn, "INSERT INTO car_images (car_id, image_path) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "is", $new_id, $xname);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
    */
    $extra_image_urls = trim($_POST["extra_image_urls"] ?? '');
    if ($extra_image_urls !== '') {
        $urls = array_map('trim', explode(',', $extra_image_urls));
        foreach ($urls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $stmt = mysqli_prepare($conn, "INSERT INTO car_images (car_id, image_path) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "is", $new_id, $url);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }

    echo json_encode([
        "status" => "success",
        "message" => "เพิ่มรถเรียบร้อยแล้ว",
        "data" => [
            "car_id" => $new_id,
            "brand" => $brand,
            "model" => $model,
            "license_plate" => $license_plate,
            "image_path" => $main_image_path,
        ]
    ]);
    exit;
}

/* ---------- ลบรถ + ลบไฟล์ทั้งหมด ---------- */
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // ดึงรูปหลัก
    $qMain = mysqli_query($conn, "SELECT image_path FROM cars WHERE car_id = {$id}");
    $main = ($qMain && mysqli_num_rows($qMain)) ? mysqli_fetch_assoc($qMain)['image_path'] : '';

    // ดึงรูปเพิ่มเติม
    $extra = [];
    $qEx = mysqli_query($conn, "SELECT image_path FROM car_images WHERE car_id = {$id}");
    while ($qEx && $row = mysqli_fetch_assoc($qEx)) {
        $extra[] = $row['image_path'];
    }

    // ลบ DB
    mysqli_query($conn, "DELETE FROM car_images WHERE car_id = {$id}");
    $okDel = mysqli_query($conn, "DELETE FROM cars WHERE car_id = {$id}");

    // ลบไฟล์
    $base = "../uploads/cars/";
    if (!empty($main) && file_exists($base . $main)) @unlink($base . $main);
    foreach ($extra as $x) {
        if (!empty($x) && file_exists($base . $x)) @unlink($base . $x);
    }

    $_SESSION[$okDel ? 'success' : 'error'] = $okDel ? "ลบรถและรูปภาพเรียบร้อยแล้ว" : "เกิดข้อผิดพลาดในการลบ: " . mysqli_error($conn);
    header("Location: manage_cars.php");
    exit();
}

/* ---------- ดึงข้อมูลรถ (พร้อมค้นหา) ---------- */
$search = trim($_GET['search'] ?? '');
$where  = '';
if ($search !== '') {
    $safe = mysqli_real_escape_string($conn, $search);
    $where = "WHERE brand LIKE '%{$safe}%' OR model LIKE '%{$safe}%' OR license_plate LIKE '%{$safe}%'";
}
$sql = "SELECT c.*, ct.type_name, cg.gas_name 
        FROM cars c
        LEFT JOIN car_types ct ON c.type_id = ct.type_id
        LEFT JOIN car_gas cg ON c.gas_id = cg.gas_id
        {$where} 
        ORDER BY c.car_id DESC";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรถ | JR Car Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --jr-bg: #f4f7f9;
            --jr-cream: #fff7d9;
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

        /* ปุ่มธีมเดียวกัน */
        .btn-primary {
            background: var(--jr-brown);
            border-color: var(--jr-brown);
        }

        .btn-primary:hover {
            background: var(--jr-brown-2);
            border-color: var(--jr-brown-2);
        }

        /* การ์ดตารางแบบ staff */
        .card-table .card-header {
            background: var(--jr-cream);
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

        /* รูป thumbnail */
        .car-thumb {
            width: 100px;
            height: auto;
            cursor: pointer;
            transition: opacity .2s, box-shadow .2s;
        }

        .car-thumb:hover {
            opacity: .9;
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15);
        }

        /* โมดอลนุ่ม */
        .modal-content {
            border-radius: 1rem;
        }

        .modal-header,
        .modal-footer {
            border-color: rgba(0, 0, 0, .06);
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="dashboard.php">JR Car Rental</a>
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
                            <li><a class="dropdown-item" href="manage_staff.php">จัดการพนักงาน</a></li>
                            <li><a class="dropdown-item active" href="manage_cars.php">จัดการรถ</a></li>
                            <li><a class="dropdown-item" href="manage_bookings.php">จัดการการจอง</a></li>
                            <li><a class="dropdown-item" href="../profile.php">ข้อมูลส่วนตัว</a></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">ออกจากระบบ</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- /NAVBAR -->

    <div class="dashboard-container">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h3 class="page-title">จัดการรถ</h3>
                <div class="subtext">เพิ่ม/แก้ไข/ดูรูป และสถานะของรถ</div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCarModal">
                    <i class="fas fa-plus me-1"></i> เพิ่มรถ
                </button>
                <form class="d-flex" method="GET">
                    <input type="text" class="form-control" name="search" placeholder="ค้นหา (ยี่ห้อ/รุ่น/ทะเบียน)" value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-outline-secondary ms-2" type="submit">ค้นหา</button>
                </form>
            </div>
        </div>

        <div class="card rounded-3 shadow-sm card-table">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">รายการรถ</h5>
                <span class="text-muted small">รวม <?= number_format($result ? mysqli_num_rows($result) : 0) ?> คัน</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>รูปภาพ</th>
                                <th>ยี่ห้อ/รุ่น</th>
                                <th>ปี</th>
                                <th>ทะเบียน</th>
                                <th>สี</th>
                                <th>ประเภท</th>
                                <th>ราคา/วัน</th>
                                <th>สถานะ</th>
                                <th class="text-center">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?= (int)$row['car_id'] ?></td>
                                        <td>
                                            <img
                                                src="<?= filter_var($row['image_path'], FILTER_VALIDATE_URL)
                                                            ? htmlspecialchars($row['image_path'])
                                                            : '../uploads/cars/' . htmlspecialchars($row['image_path']) ?>"
                                                alt="รูป<?= htmlspecialchars($row['brand']) ?>"
                                                class="car-thumb rounded shadow-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#carGalleryModal"
                                                data-id="<?= (int)$row['car_id'] ?>">
                                        </td>
                                        <td><?= htmlspecialchars($row['brand']) . ' ' . htmlspecialchars($row['model']) ?></td>
                                        <td><?= htmlspecialchars($row['year']) ?></td>
                                        <td><?= htmlspecialchars($row['license_plate']) ?></td>
                                        <td><?= htmlspecialchars($row['color']) ?></td>
                                        <td><?= htmlspecialchars($row['type_name'] ?? '') ?></td>
                                        <td><?= number_format((float)$row['daily_rate'], 2) ?></td>
                                        <td>
                                            <?php if ($row['status'] === 'available'): ?>
                                                <span class="badge bg-success">ว่าง</span>
                                            <?php elseif ($row['status'] === 'rented'): ?>
                                                <span class="badge bg-warning text-dark">ถูกเช่า</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">ซ่อมบำรุง</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="#"
                                                class="btn btn-sm btn-warning"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editCarModal"
                                                data-id="<?= (int)$row['car_id'] ?>"
                                                data-brand="<?= htmlspecialchars($row['brand']) ?>"
                                                data-model="<?= htmlspecialchars($row['model']) ?>"
                                                data-year="<?= htmlspecialchars($row['year']) ?>"
                                                data-license="<?= htmlspecialchars($row['license_plate']) ?>"
                                                data-color="<?= htmlspecialchars($row['color']) ?>"
                                                data-type="<?= htmlspecialchars($row['type_id']) ?>"
                                                data-gas="<?= htmlspecialchars($row['gas_id']) ?>"
                                                data-status="<?= htmlspecialchars($row['status']) ?>"
                                                data-rate="<?= htmlspecialchars($row['daily_rate']) ?>"
                                                data-deposit="<?= htmlspecialchars($row['deposit']) ?>"
                                                data-desc="<?= htmlspecialchars($row['description'] ?? '') ?>"
                                                data-mainimg="<?= htmlspecialchars($row['image_path']) ?>">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="manage_cars.php?delete=<?= (int)$row['car_id'] ?>" class="btn btn-sm btn-danger"
                                                onclick="return confirm('ลบรถคันนี้?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">ไม่พบข้อมูลรถ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: แกลเลอรีรูป -->
        <div class="modal fade" id="carGalleryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content rounded-3">
                    <div class="modal-header">
                        <h5 class="modal-title">รูปรถเพิ่มเติม</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="car-gallery-content">
                        <div class="text-center">กำลังโหลด...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: เพิ่มรถ -->
        <div class="modal fade" id="addCarModal" tabindex="-1" aria-labelledby="addCarModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form id="addCarForm" enctype="multipart/form-data" class="modal-content rounded-3">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addCarModalLabel">เพิ่มรถ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">ยี่ห้อ</label>
                                <input type="text" name="brand" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">รุ่น</label>
                                <input type="text" name="model" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ปี</label>
                                <input type="number" name="year" class="form-control" min="1900" max="<?= date('Y') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ทะเบียนรถ</label>
                                <input type="text" name="license_plate" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">สี</label>
                                <select name="color" class="form-select" required>
                                    <option value="" disabled selected>เลือกสี</option>
                                    <option value="Red">แดง</option>
                                    <option value="Blue">น้ำเงิน</option>
                                    <option value="Black">ดำ</option>
                                    <option value="White">ขาว</option>
                                    <option value="Silver">เงิน</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">ประเภทรถ</label>
                                <select name="car_type" class="form-select" required>
                                    <option value="" disabled selected>เลือกประเภท</option>
                                    <?php foreach ($types as $t): ?>
                                        <option value="<?= $t['type_id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">เชื้อเพลิง</label>
                                <select name="gas_id" class="form-select" required>
                                    <option value="" disabled selected>เลือกเชื้อเพลิง</option>
                                    <?php foreach ($gases as $g): ?>
                                        <option value="<?= $g['gas_id'] ?>"><?= htmlspecialchars($g['gas_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">ราคารายวัน (บาท)</label>
                                <input type="number" name="daily_rate" class="form-control" step="50" required>
                            </div>
                            <div class="col-md-6">
                                <label for="deposit" class="form-label">ค่ามัดจำ (บาท)</label>
                                <input type="number" class="form-control" name="deposit" id="deposit" min="0" step="50" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">รูปหลัก</label>
                                <input type="url" name="main_image_url" class="form-control" placeholder="https://..." required>
                                <!-- <input type="file" name="main_image" class="form-control" accept="image/jpeg,image/png" required> -->
                            </div>
                            <div class="col-12">
                                <label class="form-label">รูปเพิ่มเติม</label>
                                <textarea name="extra_image_urls" class="form-control" rows="2" placeholder="https://... , https://..."></textarea>
                                <!-- <input type="file" name="extra_images[]" class="form-control" multiple accept="image/jpeg,image/png"> -->
                            </div>
                            <div class="col-12">
                                <label class="form-label">คำอธิบาย</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
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

        <!-- Modal: แก้ไขรถ -->
        <div class="modal fade" id="editCarModal" tabindex="-1" aria-labelledby="editCarLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form id="editCarForm" enctype="multipart/form-data" class="modal-content rounded-3">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCarLabel">แก้ไขข้อมูลรถ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="car_id" id="edit_car_id">

                        <!-- รูปหลัก -->
                        <div class="mb-3 text-center">
                            <img id="edit_main_image_preview" src="" alt="รูปหลัก" class="rounded shadow" style="width:200px; height:auto; object-fit:cover; cursor:pointer;">
                            <input type="url" name="main_image_url" id="edit_main_image_url" class="form-control" placeholder="https://..." required>
                            <!-- <input type="file" name="main_image" id="edit_main_image" class="d-none" accept="image/jpeg,image/png"> -->
                            <div class="text-muted small">คลิกที่รูปเพื่อเปลี่ยนรูปหลัก</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">ยี่ห้อ</label>
                                <input type="text" name="brand" id="edit_brand" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">รุ่น</label>
                                <input type="text" name="model" id="edit_model" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ปี</label>
                                <input type="number" name="year" id="edit_year" class="form-control" min="1900" max="<?= date('Y') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ทะเบียนรถ</label>
                                <input type="text" name="license_plate" id="edit_license" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">สี</label>
                                <select name="color" id="edit_color" class="form-select" required>
                                    <option value="" disabled>เลือกสี</option>
                                    <option value="Red">แดง</option>
                                    <option value="Blue">น้ำเงิน</option>
                                    <option value="Black">ดำ</option>
                                    <option value="White">ขาว</option>
                                    <option value="Silver">เงิน</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ประเภทรถ</label>
                                <select name="car_type" id="edit_type" class="form-select" required>
                                    <?php foreach ($types as $t): ?>
                                        <option value="<?= $t['type_id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">เชื้อเพลิง</label>
                                <select name="gas_id" id="edit_gas" class="form-select" required>
                                    <?php foreach ($gases as $g): ?>
                                        <option value="<?= $g['gas_id'] ?>"><?= htmlspecialchars($g['gas_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">สถานะ</label>
                                <select name="status" id="edit_status" class="form-select" required>
                                    <option value="available">ว่าง</option>
                                    <option value="maintenance">ซ่อมบำรุง</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ราคารายวัน</label>
                                <input type="number" name="daily_rate" id="edit_rate" class="form-control" step="50" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ค่ามัดจำ (บาท)</label>
                                <input type="number" name="deposit" id="edit_deposit" class="form-control" min="0" step="50" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">คำอธิบาย</label>
                                <textarea name="description" id="edit_desc" class="form-control" rows="3"></textarea>
                            </div>

                            <!-- รูปเพิ่มเติม -->
                            <div class="col-12">
                                <label class="form-label">รูปเพิ่มเติม</label>
                                <div id="edit_extra_images_preview" class="d-flex flex-wrap gap-2"></div>
                                <textarea name="extra_image_urls" id="edit_extra_image_urls" class="form-control" rows="2"></textarea>
                                <!-- <input type="file" name="extra_images[]" class="form-control mt-2" multiple accept="image/jpeg,image/png"> -->
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            /* --------- MODAL แก้ไข: preload ข้อมูล --------- */
            const editModal = document.getElementById('editCarModal');
            editModal.addEventListener('show.bs.modal', (ev) => {
                const btn = ev.relatedTarget;
                const cid = btn.getAttribute('data-id');

                document.getElementById('edit_car_id').value = cid;
                document.getElementById('edit_brand').value = btn.getAttribute('data-brand');
                document.getElementById('edit_model').value = btn.getAttribute('data-model');
                document.getElementById('edit_year').value = btn.getAttribute('data-year');
                document.getElementById('edit_license').value = btn.getAttribute('data-license');
                document.getElementById('edit_color').value = btn.getAttribute('data-color');
                document.getElementById('edit_type').value = btn.getAttribute('data-type');
                document.getElementById('edit_gas').value = btn.getAttribute('data-gas');
                document.getElementById('edit_status').value = btn.getAttribute('data-status');
                document.getElementById('edit_rate').value = btn.getAttribute('data-rate');
                document.getElementById('edit_deposit').value = btn.getAttribute('data-deposit') ?? 0;
                document.getElementById('edit_desc').value = btn.getAttribute('data-desc') || '';
                // document.getElementById('edit_main_image_preview').src = "../uploads/cars/" + (btn.getAttribute('data-mainimg') || '');
                const src = btn.getAttribute('data-mainimg') || '';
                const preview = document.getElementById('edit_main_image_preview');
                preview.src = src.startsWith('http') ?
                    src :
                    '../uploads/cars/' + src || 'https://placehold.co/200x120?text=No+Image';


                // โหลดรูปเพิ่มเติม
                fetch(`car_images_api.php?car_id=${cid}`)
                    .then(res => res.json())
                    .then(data => {
                        const box = document.getElementById('edit_extra_images_preview');
                        if (data.status === 'success' && data.images.length > 0) {
                            box.innerHTML = data.images.map(img => `
                    <div class="position-relative d-inline-block m-1">
                        <img src="${img}" class="rounded shadow-sm" style="width:100px;">
                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 delete-extra-img"
                                data-filename="${img}" data-car-id="${cid}">&times;</button>
                    </div>`).join('');
                        } else {
                            box.innerHTML = '<div class="text-muted">ไม่มีรูปเพิ่มเติม</div>';
                        }
                    });
            });
            //<img src="../uploads/cars/${img}" class="rounded shadow-sm" style="width:100px;">
            //<img src="${img}" class="rounded shadow-sm" style="width:100px;">

            // เปลี่ยนรูปหลัก (แก้ไข)
            /* const mainPrev = document.getElementById('edit_main_image_preview');
            const mainInput = document.getElementById('edit_main_image');
            mainPrev.addEventListener('click', () => mainInput.click());
            mainInput.addEventListener('change', (e) => {
                const f = e.target.files[0];
                if (f) {
                    const rd = new FileReader();
                    rd.onload = e2 => mainPrev.src = e2.target.result;
                    rd.readAsDataURL(f);
                }
            }); */
            // คลิกที่รูปแล้ว focus ช่อง URL
            const mainPrev = document.getElementById('edit_main_image_preview');
            const mainUrlInput = document.getElementById('edit_main_image_url');
            if (mainPrev && mainUrlInput) {
                mainPrev.addEventListener('click', () => mainUrlInput.focus());
            }

            /* --------- แกลเลอรี: โหลดตอนเปิด --------- */
            const carGalleryModal = document.getElementById('carGalleryModal');
            carGalleryModal.addEventListener('show.bs.modal', (ev) => {
                const img = ev.relatedTarget;
                const carId = img.getAttribute('data-id');
                const gallery = document.getElementById('car-gallery-content');

                gallery.innerHTML = '<div class="text-center">กำลังโหลด...</div>';
                fetch(`car_images_api.php?car_id=${carId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success' && data.images.length > 0) {
                            gallery.innerHTML = '<div class="row">' + data.images.map(im => `
                    <div class="col-md-4 mb-3">
                        <img src="${im}" class="img-fluid rounded shadow-sm">
                    </div>`).join('') + '</div>';
                        } else {
                            gallery.innerHTML = '<p class="text-danger">ไม่พบรูปรถเพิ่มเติม</p>';
                        }
                    });
            });
            //<img src="../uploads/cars/${im}" class="img-fluid rounded shadow-sm">
            //<img src="${im}" class="img-fluid rounded shadow-sm">

            /* --------- ลบรูปเพิ่มเติม (ปุ่มใน modal edit) --------- */
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('delete-extra-img')) {
                    const filename = e.target.dataset.filename;
                    const carId = e.target.dataset.carId;
                    Swal.fire({
                        title: 'ลบรูปนี้?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'ลบ',
                        cancelButtonText: 'ยกเลิก'
                    }).then((res) => {
                        if (res.isConfirmed) {
                            fetch('delete_extra_image.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body: `car_id=${encodeURIComponent(carId)}&filename=${encodeURIComponent(filename)}`
                                }).then(r => r.json())
                                .then(d => {
                                    if (d.status === 'success') {
                                        e.target.closest('.position-relative').remove();
                                    } else {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'ลบไม่สำเร็จ',
                                            text: d.message || 'กรุณาลองใหม่'
                                        });
                                    }
                                });
                        }
                    });
                }
            });

            /* --------- เพิ่มรถ (AJAX) --------- */
            document.getElementById("addCarForm").addEventListener("submit", function(e) {
                e.preventDefault();
                const fd = new FormData(this);
                fetch("manage_cars.php", {
                        method: "POST",
                        body: fd
                    })
                    .then(async (res) => { // **เปลี่ยน** เพื่อจัดการข้อความที่ไม่ใช่ JSON
                        const text = await res.text();
                        try {
                            // พยายามแปลงเป็น JSON
                            return JSON.parse(text);
                        } catch (e) {
                            // หากแปลงไม่สำเร็จ ให้แสดงข้อความเต็ม ๆ จาก PHP
                            console.error("Non-JSON Response Error:", text);
                            throw new Error("SERVER_ERROR_NOT_JSON: " + text);
                        }
                    })
                    .then(data => {
                        if (data.status === "success") {
                            Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ!',
                                    text: data.message,
                                    timer: 1800,
                                    showConfirmButton: false
                                })
                                .then(() => location.reload());
                            bootstrap.Modal.getInstance(document.getElementById('addCarModal')).hide();
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
                        let errMsg = err.message.includes("SERVER_ERROR_NOT_JSON") ?
                            "เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง โปรดตรวจสอบ PHP Error Log" :
                            'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์';

                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: errMsg
                        });
                    });
            });

            /* --------- แก้ไขรถ (ต้องมีไฟล์ car_update.php) --------- */
            document.getElementById("editCarForm").addEventListener("submit", function(e) {
                e.preventDefault();
                const fd = new FormData(this);
                fetch("car_update.php", {
                        method: "POST",
                        body: fd
                    })
                    .then(async (res) => {
                        const text = await res.text();
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error("BAD_JSON: " + text);
                        }
                    })

                    .then(data => {
                        if (data.status === "success") {
                            Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ!',
                                    text: data.message,
                                    timer: 1800,
                                    showConfirmButton: false
                                })
                                .then(() => location.reload());
                            bootstrap.Modal.getInstance(document.getElementById('editCarModal')).hide();
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