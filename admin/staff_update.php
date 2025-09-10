<?php
require("../db.php");
session_start();

// ตรวจสอบสิทธิ์ admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST["user_id"]);
    $username = mysqli_real_escape_string($conn, $_POST["username"]);
    $email = mysqli_real_escape_string($conn, $_POST["email"]);
    $firstname = mysqli_real_escape_string($conn, $_POST["firstname"]);
    $lastname = mysqli_real_escape_string($conn, $_POST["lastname"]);
    $phone = mysqli_real_escape_string($conn, $_POST["phone_number"]);
    $address = mysqli_real_escape_string($conn, $_POST["address"]);


    $sql = "UPDATE employees SET 
            email='$email',
            firstname='$firstname',
            lastname='$lastname',
            phone_number='$phone',
            address='$address'
        WHERE user_id='$id'";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "แก้ไขข้อมูลพนักงานเรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาดในการแก้ไข: " . mysqli_error($conn);
    }

    header("Location: manage_staff.php");
    exit();
}
