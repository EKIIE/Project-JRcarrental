<?php
session_start();
require("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
$lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
$email = mysqli_real_escape_string($conn, $_POST['email']);
$phone_number = mysqli_real_escape_string($conn, $_POST['phone_number']);
$address = mysqli_real_escape_string($conn, $_POST['address']);

$sql = "UPDATE users SET 
            firstname = '$firstname',
            lastname = '$lastname',
            email = '$email',
            phone_number = '$phone_number',
            address = '$address'
        WHERE user_id = '$user_id'";

if (mysqli_query($conn, $sql)) {
    $_SESSION['success'] = "อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้ว";
} else {
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
}

header("Location: profile.php");
exit();
