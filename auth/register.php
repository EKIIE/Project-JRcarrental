<?php
require("../db.php");
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = $_POST["username"];
  $password = $_POST["password"];
  $confirm_password = $_POST["confirm_password"];

  if ($password !== $confirm_password) {
    $_SESSION['error'] = "รหัสผ่านไม่ตรงกัน";
  } else {
    $email = $_POST["email"];
    $firstname = $_POST["firstname"];
    $lastname = $_POST["lastname"];
    $phone_number = $_POST["phone_number"];
    $address = $_POST["address"];
    $user_type = 'customer';

    $upload_dir = "../uploads/licenses/";
    $driver_license_file = $_FILES["drivers_license"];
    $passport_license_file = $_FILES["passport_license"];

    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];

    function uploadFile($file, $upload_dir, $fieldname)
    {
      global $allowed_types;
      if ($file['error'] === 0 && in_array($file['type'], $allowed_types)) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid($fieldname . "_") . '.' . $ext;
        $destination = $upload_dir . $filename;
        if (!is_dir($upload_dir)) {
          mkdir($upload_dir, 0777, true);
        }
        if (move_uploaded_file($file['tmp_name'], $destination)) {
          return $filename;
        }
      }
      return false;
    }

    $driver_license_path = uploadFile($driver_license_file, $upload_dir, "license");
    $passport_license_path = uploadFile($passport_license_file, $upload_dir, "passport");

    if (!$driver_license_path || !$passport_license_path) {
      $_SESSION['error'] = "อัปโหลดไฟล์ล้มเหลว หรือไฟล์ไม่ถูกต้อง (รองรับ PDF, JPG, PNG)";
    } else {
      $check = mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'");
      if (mysqli_num_rows($check) > 0) {
        $_SESSION['error'] = "ชื่อผู้ใช้นี้ถูกใช้ไปแล้ว";
      } else {
        // Step 1: insert into users
        $user_sql = "INSERT INTO users (username, password, user_type) VALUES ('$username', '$password', '$user_type')";
        if (mysqli_query($conn, $user_sql)) {
          $user_id = mysqli_insert_id($conn);

          // Step 2: insert into customers
          $cus_sql = "INSERT INTO customers (user_id, email, firstname, lastname, phone_number, address, drivers_license, passport_license)
                      VALUES ('$user_id', '$email', '$firstname', '$lastname', '$phone_number', '$address', '$driver_license_path', '$passport_license_path')";

          if (mysqli_query($conn, $cus_sql)) {
            $_SESSION['success'] = "สมัครสมาชิกเรียบร้อยแล้ว";
            header("Location: login.php");
            exit();
          } else {
            $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลลูกค้า";
          }
        } else {
          $_SESSION['error'] = "เกิดข้อผิดพลาดในการบันทึกบัญชีผู้ใช้";
        }
      }
    }
  }
}
?>



<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register</title>
  <link rel="stylesheet" href="../css/login.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body>

  <div class="login-container">
    <form action="" method="POST" enctype="multipart/form-data">
      <!-- <h2>⋆ ˚ ࿔ Register 𝜗𝜚 ˚ ⋆</h2> -->
      <h2> Register </h2>
      <img src="../img/JRlogo.jpg" alt="" width="120" height="120">
      <p></p>
      <a href="../index.php">⌞ JR Car Rental ⌝</a>
      <p></p>

      <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

      <input type="text" name="username" placeholder="Username" required>
      <div class="password-wrapper">
        <input type="password" id="password" name="password" placeholder="Password" autocomplete="off" required>
        <i class="fa-solid fa-eye toggle-btn" onclick="togglePassword('password', this)"></i>
      </div>
      <div class="password-wrapper">
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required oninput="checkPasswordMatch()">
        <span id="match-status" class="match-status"></span>
        <i class="fa-solid fa-eye toggle-btn" onclick="togglePassword('confirm_password', this)"></i>
      </div>
      <input type="email" name="email" placeholder="Email" required>
      <input type="text" name="firstname" placeholder="First Name" required>
      <input type="text" name="lastname" placeholder="Last Name" required>
      <input type="tel" name="phone_number" placeholder="Phone Number"
        pattern="[0-9]{9,10}"
        title="กรอกตัวเลข 9-10 หลัก"
        required>
      <input type="text" name="address" placeholder="Address" required>
      <br><br>

      <div class="file-upload">

        <label>แนบสำเนาใบขับขี่</label><br>
        <p>(ไฟล์รูปภาพเท่านั้น)</p>
        <input type="file" name="drivers_license" accept="image/jpeg,image/jpg,image/png" required>
        <br><br>

        <label>แนบสำเนาบัตรประชาชนหรือพาสปอร์ต</label><br>
        <p>(ไฟล์รูปภาพเท่านั้น)</p>
        <input type="file" name="passport_license" accept="image/jpeg,image/jpg,image/png" required>
        <!-- <br><br> -->

      </div>

      <input type="hidden" name="user_type" value="customer">

      <label>────୨ৎ────</label>
      <div class="iidiv">
        <a href="login.php">Login</a>
        <button type="submit" id="submitBtn">Register</button>
      </div>
    </form>
  </div>

  <script src="auth.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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