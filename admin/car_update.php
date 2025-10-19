<?php
session_start();
require("../db.php");

header('Content-Type: application/json; charset=utf-8');
ob_clean();

// ---- ตรวจสอบสิทธิ์ ----
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['user_type'] ?? '') !== 'admin' ||
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {
    echo json_encode(["status" => "error", "message" => "ไม่มีสิทธิ์"]);
    exit();
}

// ---- helper ----
function ensure_dir($dir)
{
    if (!is_dir($dir)) mkdir($dir, 0775, true);
}
function safe_image_upload($file, $prefix, $upload_dir, $max_mb = 8)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return [false, "ไฟล์ผิดพลาด"];
    if ($file['size'] <= 0 || $file['size'] > $max_mb * 1024 * 1024) return [false, "ไฟล์ใหญ่เกิน"];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!isset($allowed[$mime])) return [false, "ชนิดไฟล์ไม่ถูกต้อง"];
    $ext = $allowed[$mime];
    $name = $prefix . bin2hex(random_bytes(8)) . "." . $ext;
    ensure_dir($upload_dir);
    $dest = rtrim($upload_dir, "/") . "/" . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return [false, "อัปโหลดล้มเหลว"];
    return [true, $name];
}

// ---- รับค่า ----
$car_id        = (int)($_POST['car_id'] ?? 0);
$brand         = trim($_POST['brand'] ?? '');
$model         = trim($_POST['model'] ?? '');
$year          = (int)($_POST['year'] ?? 0);
$license_plate = trim($_POST['license_plate'] ?? '');
$color         = trim($_POST['color'] ?? '');
$type_id       = (int)($_POST['car_type'] ?? 0);
$gas_id        = (int)($_POST['gas_id'] ?? 0);
$daily_rate    = (float)($_POST['daily_rate'] ?? 0);
$deposit       = (float)($_POST['deposit'] ?? 0);
$description   = trim($_POST['description'] ?? '');
$status        = $_POST['status'] ?? 'maintenance';

if ($car_id <= 0) {
    echo json_encode(["status" => "error", "message" => "ไม่พบรถ"]);
    exit;
}

// ---- check ปี/ราคา ----
$year_now = (int)date('Y');
if ($year < 1900 || $year > $year_now) {
    echo json_encode(["status" => "error", "message" => "ปีไม่ถูกต้อง"]);
    exit;
}
if ($daily_rate <= 0) {
    echo json_encode(["status" => "error", "message" => "ราคารายวันไม่ถูกต้อง"]);
    exit;
}
if ($deposit < 0) {
    echo json_encode(["status" => "error", "message" => "ค่ามัดจำไม่ถูกต้อง"]);
    exit;
}

// ---- ตรวจซ้ำทะเบียน ----
$dupStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM cars WHERE license_plate=? AND car_id<>?");
mysqli_stmt_bind_param($dupStmt, "si", $license_plate, $car_id);
mysqli_stmt_execute($dupStmt);
mysqli_stmt_bind_result($dupStmt, $dup);
mysqli_stmt_fetch($dupStmt);
mysqli_stmt_close($dupStmt);
if ($dup > 0) {
    echo json_encode(["status" => "error", "message" => "ทะเบียนซ้ำ"]);
    exit;
}

// ---- อัปโหลดรูปใหม่ ----
$upload_dir = "../uploads/cars/";
$new_main_image = null;
// ✅ ถ้ามี URL ส่งมาก็ใช้ URL เลย
if (!empty($_POST['main_image_url'])) {
    $url = trim($_POST['main_image_url']);
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(["status" => "error", "message" => "URL รูปหลักไม่ถูกต้อง"]);
        exit;
    }
    $new_main_image = $url;
}
// ✅ ถ้าไม่มี URL แต่มีไฟล์ ให้ใช้ไฟล์แทน
elseif (isset($_FILES["main_image"]) && $_FILES["main_image"]["error"] === 0) {
    [$ok, $res] = safe_image_upload($_FILES["main_image"], "main_", $upload_dir);
    if (!$ok) {
        echo json_encode(["status" => "error", "message" => $res]);
        exit;
    }
    $new_main_image = $res;
}

// ---- Update ----
mysqli_begin_transaction($conn);
try {
    if ($new_main_image) {
        $sql = "UPDATE cars SET brand=?,model=?,year=?,license_plate=?,color=?,type_id=?,gas_id=?,daily_rate=?,deposit=?,description=?,status=?,image_path=? WHERE car_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "ssissiidssssi",
            $brand,
            $model,
            $year,
            $license_plate,
            $color,
            $type_id,
            $gas_id,
            $daily_rate,
            $deposit,
            $description,
            $status,
            $new_main_image,
            $car_id
        );
    } else {
        $sql = "UPDATE cars SET brand=?,model=?,year=?,license_plate=?,color=?,type_id=?,gas_id=?,daily_rate=?,deposit=?,description=?,status=? WHERE car_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "ssissiidsssi",
            $brand,
            $model,
            $year,
            $license_plate,
            $color,
            $type_id,
            $gas_id,
            $daily_rate,
            $deposit,
            $description,
            $status,
            $car_id
        );
    }

    if (!mysqli_stmt_execute($stmt)) throw new Exception("DB update fail");
    mysqli_stmt_close($stmt);

    // ---- อัปโหลดรูปเพิ่มเติม ----
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
                $stmtX = mysqli_prepare($conn, "INSERT INTO car_images (car_id, image_path) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmtX, "is", $car_id, $xname);
                mysqli_stmt_execute($stmtX);
                mysqli_stmt_close($stmtX);
            }
        }
    }
    // ✅ เพิ่มรูปเพิ่มเติมจาก URL
    $extra_image_urls = trim($_POST["extra_image_urls"] ?? '');
    if ($extra_image_urls !== '') {
        $urls = array_map('trim', explode(',', $extra_image_urls));
        foreach ($urls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $stmtX = mysqli_prepare($conn, "INSERT INTO car_images (car_id, image_path) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmtX, "is", $car_id, $url);
                mysqli_stmt_execute($stmtX);
                mysqli_stmt_close($stmtX);
            }
        }
    }

    mysqli_commit($conn);
    echo json_encode(["status" => "success", "message" => "อัปเดตรถเรียบร้อย"]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo json_encode(["status" => "error", "message" => "เกิดข้อผิดพลาด: " . $e->getMessage()]);
}
