<?php
require("../db.php");
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $username = mysqli_real_escape_string($conn, $_POST["username"]);
  $password = $_POST["password"];

  // ✅ ดึงข้อมูลผู้ใช้จาก username เท่านั้น
  $sql = "SELECT * FROM users WHERE username = '$username' LIMIT 1";
  $result = mysqli_query($conn, $sql);

  if ($row = mysqli_fetch_assoc($result)) {
    // ✅ ตรวจสอบ password hash
    if (password_verify($password, $row["password"])) {
      
      // ----------- สร้าง session พื้นฐาน -----------
      $_SESSION["user_id"] = $row["user_id"];
      $_SESSION["username"] = $row["username"];
      $_SESSION["user_type"] = $row["user_type"];

      // ----------- ดึงข้อมูลเพิ่มเติมตาม user type -----------
      $user_id = (int)$row["user_id"];

      if ($row["user_type"] == "customer") {
        $cus_sql = "SELECT * FROM customers WHERE user_id = $user_id";
        $cus_result = mysqli_query($conn, $cus_sql);
        if ($cus_row = mysqli_fetch_assoc($cus_result)) {
          $_SESSION["customer_id"] = $cus_row["customer_id"];
          $_SESSION["full_name"] = $cus_row["firstname"] . " " . $cus_row["lastname"];
          $_SESSION["phone"] = $cus_row["phone_number"];
          $_SESSION["profile_picture"] = $cus_row["profile_picture"] ?? null;
        }
      } elseif ($row["user_type"] == "staff") {
        $emp_sql = "SELECT * FROM employees WHERE user_id = $user_id";
        $emp_result = mysqli_query($conn, $emp_sql);
        if ($emp_row = mysqli_fetch_assoc($emp_result)) {
          $_SESSION["employee_id"] = $emp_row["employee_id"];
          $_SESSION["full_name"] = $emp_row["firstname"] . " " . $emp_row["lastname"];
          $_SESSION["position"] = $emp_row["position"];
          $_SESSION["profile_picture"] = $emp_row["profile_picture"] ?? null;
        }
      }

      // ----------- Redirect ไปหน้า role ที่ถูกต้อง -----------
      if ($row["user_type"] == "admin") {
        header("Location: ../admin/dashboard.php");
      } elseif ($row["user_type"] == "staff") {
        header("Location: ../staff/staff_dashboard.php");
      } else {
        header("Location: ../index.php");
      }
      exit();
    } else {
      // ❌ password ผิด
      $_SESSION['error'] = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
      header("Location: login.php");
      exit();
    }
  } else {
    // ❌ ไม่พบบัญชีผู้ใช้
    $_SESSION['error'] = "ไม่พบบัญชีผู้ใช้";
    header("Location: login.php");
    exit();
  }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | JR Car Rental</title>
  <link rel="stylesheet" href="../css/login.css">
</head>

<body>

  <div class="login-container">
    <form action="" method="POST">
      <h2>เข้าสู่ระบบ</h2>
      <img src="../img/JRlogo.jpg" alt="JR Logo" width="120" height="120">
      <p></p>
      <a href="../index.php">⌞ JR Car Rental ⌝</a>
      <p></p>

      <input type="text" id="username" name="username" placeholder="Username" required>
      <input type="password" id="password" name="password" placeholder="Password" required>
      <p>────୨ৎ────</p>

      <div class="iidiv">
        <button type="submit">Login</button>
        <a href="register.php">Register</a>
      </div>
    </form>
  </div>

  <!-- SweetAlert2 -->
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
  <?php unset($_SESSION['success']); endif; ?>

  <?php if (isset($_SESSION['error'])): ?>
    <script>
      Swal.fire({
        icon: 'error',
        title: 'เกิดข้อผิดพลาด',
        text: '<?= $_SESSION['error'] ?>',
        confirmButtonText: 'ตกลง'
      });
    </script>
  <?php unset($_SESSION['error']); endif; ?>

</body>
</html>
