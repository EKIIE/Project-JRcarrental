<?php
session_start();
require("../db.php");

header('Content-Type: application/json; charset=utf-8');
ob_clean(); // กัน output อื่นปะปน JSON

// ---- สิทธิ์ & เมธอด ----
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_type'] ?? '') !== 'admin' ||
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {
    echo json_encode(["status" => "error", "message" => "ไม่มีสิทธิ์ในการเข้าถึง"]);
    exit();
}

// ---- Helper: upload ----
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
    if ($file['size'] <= 0 || $file['size'] > $max_mb * 1024 * 1024) {
        return [false, "ไฟล์ใหญ่เกินไป (เกิน {$max_mb}MB)"];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
    ];
    if (!isset($allowed[$mime])) {
        return [false, "ไฟล์รูปภาพไม่ถูกต้อง (รองรับ JPG/PNG)"];
    }
    $ext = $allowed[$mime];
    $name = $prefix . bin2hex(random_bytes(8)) . "." . $ext;
    ensure_dir($upload_dir);
    $dest = rtrim($upload_dir, "/") . "/" . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return [false, "อัปโหลดรูปใหม่ล้มเหลว"];
    }
    return [true, $name];
}

// ---- รับค่า & validate ----
$car_id        = (int)($_POST['car_id'] ?? 0);
$brand         = trim($_POST['brand'] ?? '');
$model         = trim($_POST['model'] ?? '');
$year          = trim($_POST['year'] ?? '');
$license_plate = trim($_POST['license_plate'] ?? '');
$color         = trim($_POST['color'] ?? '');
$type          = trim($_POST['car_type'] ?? '');
$daily_rate    = trim($_POST['daily_rate'] ?? '');
$deposit       = trim($_POST['deposit'] ?? '');
$description   = trim($_POST['description'] ?? '');

$status = $_POST['status'];
$allowed = ['available', 'rented', 'maintenance'];
if (!in_array($status, $allowed)) {
    echo json_encode(["status" => "error", "message" => "ค่าสถานะไม่ถูกต้อง"]);
    exit;
}

if ($car_id <= 0) {
    echo json_encode(["status" => "error", "message" => "ไม่พบรหัสรถที่จะแก้ไข"]);
    exit;
}

// ปีรถ
$year_now = (int)date('Y');
if (!ctype_digit($year) || (int)$year < 1900 || (int)$year > $year_now) {
    echo json_encode(["status" => "error", "message" => "ปีรถไม่ถูกต้อง"]);
    exit;
}
// ราคา/มัดจำ
if (!is_numeric($daily_rate) || (float)$daily_rate <= 0) {
    echo json_encode(["status" => "error", "message" => "ราคารายวันไม่ถูกต้อง"]);
    exit;
}
if (!is_numeric($deposit) || (float)$deposit < 0) {
    echo json_encode(["status" => "error", "message" => "ค่ามัดจำไม่ถูกต้อง"]);
    exit;
}

// ---- ตรวจว่ารถมีอยู่จริง ----
$existStmt = mysqli_prepare($conn, "SELECT image_path FROM cars WHERE car_id = ?");
mysqli_stmt_bind_param($existStmt, "i", $car_id);
mysqli_stmt_execute($existStmt);
mysqli_stmt_bind_result($existStmt, $old_main_image);
$found = mysqli_stmt_fetch($existStmt);
mysqli_stmt_close($existStmt);

if (!$found) {
    echo json_encode(["status" => "error", "message" => "ไม่พบข้อมูลรถนี้"]);
    exit;
}

// ---- ตรวจทะเบียนซ้ำ (ยกเว้นตัวเอง) ----
$dupStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM cars WHERE license_plate = ? AND car_id <> ?");
mysqli_stmt_bind_param($dupStmt, "si", $license_plate, $car_id);
mysqli_stmt_execute($dupStmt);
mysqli_stmt_bind_result($dupStmt, $dup);
mysqli_stmt_fetch($dupStmt);
mysqli_stmt_close($dupStmt);

if ($dup > 0) {
    echo json_encode(["status" => "error", "message" => "ทะเบียนรถนี้มีอยู่แล้ว"]);
    exit;
}

// ---- อัปโหลดรูปหลักใหม่ (ถ้ามีไฟล์แนบ) ----
$upload_dir = "../uploads/cars/";
$new_main_image = null;

if (isset($_FILES["main_image"]) && $_FILES["main_image"]["error"] === 0) {
    [$ok, $res] = safe_image_upload($_FILES["main_image"], "main_", $upload_dir);
    if (!$ok) {
        echo json_encode(["status" => "error", "message" => $res]);
        exit;
    }
    $new_main_image = $res;
}

$year_i    = (int)$year;
$daily_d   = (float)$daily_rate;
$deposit_d = (float)$deposit;

// ---- เริ่มทรานแซกชัน ----
mysqli_begin_transaction($conn);
try {
    // ประกอบ UPDATE แบบ dynamic (ถ้ามีรูปใหม่ ให้อัปเดต image_path ด้วย)
    $sql = "UPDATE cars SET brand = ?, model = ?, year = ?, license_plate = ?, color = ?, car_type = ?, daily_rate = ?, deposit = ?, description = ?, status = ?";
    if ($new_main_image !== null) {
        $sql .= ", image_path = ?";
    }
    $sql .= " WHERE car_id = ?";

    if ($new_main_image !== null) {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "ssisssddsssi",
            $brand,
            $model,
            $year_i,
            $license_plate,
            $color,
            $type,
            $daily_d,
            $deposit_d,
            $description,
            $status,
            $new_main_image,
            $car_id
        );
    } else {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "ssisssddssi",
            $brand,
            $model,
            $year_i,
            $license_plate,
            $color,
            $type,
            $daily_d,
            $deposit_d,
            $description,
            $status,
            $car_id
        );
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("อัปเดตข้อมูลรถไม่สำเร็จ");
    }
    mysqli_stmt_close($stmt);

    // อัปโหลดรูปเพิ่มเติม (ถ้ามี)
    if (!empty($_FILES['extra_images']['tmp_name'][0])) {
        $count = count($_FILES['extra_images']['tmp_name']);
        $ins = mysqli_prepare($conn, "INSERT INTO car_images (car_id, image_path) VALUES (?, ?)");
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
                mysqli_stmt_bind_param($ins, "is", $car_id, $xname);
                if (!mysqli_stmt_execute($ins)) {
                    throw new Exception("บันทึกรูปเพิ่มเติมไม่สำเร็จ");
                }
            } else {
                // ถ้ารูปใดรูปหนึ่งไม่ผ่าน จะข้ามรูปนั้นไป (หรือจะ throw ก็ได้)
                // throw new Exception($xname);
            }
        }
        mysqli_stmt_close($ins);
    }

    // ถึงจุดนี้อัปเดตรูปหลักใหม่สำเร็จแล้ว → ลบรูปเก่าทิ้ง (ถ้ามี)
    if ($new_main_image !== null && !empty($old_main_image)) {
        $old_path = $upload_dir . $old_main_image;
        if (file_exists($old_path)) {
            @unlink($old_path);
        }
    }

    mysqli_commit($conn);
    echo json_encode(["status" => "success", "message" => "อัปเดตข้อมูลรถเรียบร้อยแล้ว"]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    // ถ้าอัปโหลดรูปใหม่ไปแล้วแต่ fail DB ให้ลบไฟล์ใหม่ทิ้ง
    if ($new_main_image !== null && file_exists($upload_dir . $new_main_image)) {
        @unlink($upload_dir . $new_main_image);
    }
    echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาดในการอัปเดตข้อมูลรถ"]);
}
