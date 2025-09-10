<?php
session_start();
require("db.php");

if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$upload_dir = "uploads/";
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
  $file = $_FILES['profile_picture'];
  $file_type = $file['type'];

  if (in_array($file_type, $allowed_types)) {
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid("profile_") . "." . $ext;
    $destination = $upload_dir . $filename;

    if (!is_dir($upload_dir)) {
      mkdir($upload_dir, 0777, true);
    }

    if (move_uploaded_file($file['tmp_name'], $destination)) {
      // ลบรูปเก่า (ถ้ามีและไม่ใช่ default)
      $res = mysqli_query($conn, "SELECT profile_picture FROM users WHERE user_id = $user_id");
      $old = mysqli_fetch_assoc($res);
      if (
        $old &&
        $old['profile_picture'] &&
        file_exists($upload_dir . $old['profile_picture']) &&
        $old['profile_picture'] !== 'default_profile.png'
      ) {
        unlink($upload_dir . $old['profile_picture']);
      }

      // อัปเดตโปรไฟล์ใหม่
      $sql = "UPDATE users SET profile_picture = '$filename' WHERE user_id = $user_id";
      if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "เปลี่ยนรูปโปรไฟล์เรียบร้อยแล้ว";
      } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการอัปเดตรูปโปรไฟล์";
      }
    } else {
      $_SESSION['error'] = "ไม่สามารถบันทึกรูปภาพได้";
    }
  } else {
    $_SESSION['error'] = "ไฟล์ต้องเป็น JPG, PNG หรือ WEBP เท่านั้น";
  }
} else {
  $_SESSION['error'] = "กรุณาเลือกรูปภาพ";
}

header("Location: profile.php");
exit();
