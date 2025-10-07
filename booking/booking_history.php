<?php
include("../db.php");
session_start();

if (!isset($_SESSION['user_id'])) {
  header("Location: auth/login.php");
  exit();
}

$user_id = (int)$_SESSION['user_id'];

// โชว์เฉพาะการจองที่ยังเกี่ยวข้อง: pending/confirmed (ตัด cancelled) // NEW
$sql = "SELECT 
          b.*,
          c.brand, c.model, c.image_path, c.daily_rate,
          r.rental_id, r.emp_deliver,
          e.firstname AS emp_fname, e.lastname AS emp_lname,
          e.phone_number AS emp_phone, e.email AS emp_email
        FROM bookings b
        JOIN cars c ON b.car_id = c.car_id
        LEFT JOIN rentals r ON r.rental_id = (
          SELECT r2.rental_id 
          FROM rentals r2 
          WHERE r2.booking_id = b.booking_id 
          ORDER BY r2.rental_id DESC 
          LIMIT 1
        )
        LEFT JOIN employees e ON e.employee_id = r.emp_deliver
        WHERE b.user_id = ?
          AND b.booking_status IN ('pending','confirmed')
        ORDER BY b.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
  die("Prepare failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <title>ประวัติการจอง</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap');

    body {
      font-family: 'Kanit', sans-serif;
      background-color: #fdfaf6;
      color: #3a2c2c;
      margin: 0;
      padding: 0;
    }

    h2 {
      font-weight: 600;
      color: #3a2c2c;
    }

    .navbar {
      font-weight: 500;
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

    .dropdown-item:hover {
      color: #a87346 !important;
    }

    .dropdown-menu {
      border-radius: 0.75rem;
      border-color: #f3e2cc;
    }

    .booking-card {
      border: 1px solid #f1e3d3;
      border-radius: 1rem;
      background-color: #fffaf4;
      overflow: hidden;
      transition: all 0.2s ease;
    }

    .booking-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    }

    .booking-img {
      height: 200px;
      object-fit: cover;
      border-bottom: 1px solid #f1e3d3;
    }

    .badge.bg-warning {
      background-color: #ffedcc !important;
      color: #a87346;
    }

    .badge.bg-success {
      background-color: #cbe8cc !important;
      color: #226d2e;
    }

    .btn-primary {
      background-color: #9c7b5b;
      border: none;
      border-radius: 0.5rem;
    }

    .btn-primary:hover {
      background-color: #7e634a;
    }

    .btn-outline-danger {
      border-radius: 0.5rem;
    }

    .modal-content {
      border-radius: 1rem;
    }

    @media (max-width: 768px) {
      .booking-img {
        height: 160px;
      }

      h2 {
        font-size: 1.3rem;
      }

      .container {
        padding: 1rem;
      }
    }
  </style>


</head>

<body>

  <!-- NAVBAR +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ -->

  <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
    <div class="container">
      <a class="navbar-brand" href="../index.php">JR Car Rental</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarContent">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><a class="nav-link" href="../index.php">หน้าหลัก</a></li>

          <?php if (!isset($_SESSION['user_type'])): ?>
            <li class="nav-item"><a class="nav-link" href="../auth/register.php">สมัครสมาชิก</a></li>
            <li class="nav-item"><a class="nav-link" href="../auth/login.php">เข้าสู่ระบบ</a></li>

          <?php elseif ($_SESSION['user_type'] == 'customer'): ?>
            <li class="nav-item"><a class="nav-link" href="booking_history.php">ประวัติการจอง</a></li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">บัญชีของฉัน</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="../profile.php">ข้อมูลส่วนตัว</a></li>
                <li><a class="dropdown-item" href="../auth/logout.php">ออกจากระบบ</a></li>
              </ul>
            </li>

          <?php elseif ($_SESSION['user_type'] == 'staff'): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">เมนูพนักงาน</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="../staff/checkup.php">ตรวจสอบรถ</a></li>
                <li><a class="dropdown-item" href="../staff/return_car.php">รับคืนรถ</a></li>
                <li><a class="dropdown-item" href="../profile.php">ข้อมูลส่วนตัว</a></li>
                <li><a class="dropdown-item" href="../auth/logout.php">ออกจากระบบ</a></li>
              </ul>
            </li>

          <?php elseif ($_SESSION['user_type'] == 'admin'): ?>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">ผู้ดูแลระบบ</a>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="../admin/dashboard.php">แดชบอร์ด</a></li>
                <li><a class="dropdown-item" href="../admin/manage_staff.php">จัดการพนักงาน</a></li>
                <li><a class="dropdown-item" href="../admin/manage_cars.php">จัดการรถ</a></li>
                <li><a class="dropdown-item" href="../admin/manage_bookings.php">จัดการการจอง</a></li>
                <li><a class="dropdown-item" href="../profile.php">ข้อมูลส่วนตัว</a></li>
                <li><a class="dropdown-item" href="../auth/logout.php">ออกจากระบบ</a></li>
              </ul>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>

  <!-- NAVBAR +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ -->

  <div class="container py-5">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0">ประวัติการจองของคุณ</h2>
      <!-- <span class="badge badge-soft rounded-pill px-3 py-2">เฉพาะรายการที่กำลังใช้งาน / รอดำเนินการ</span> -->
    </div>

    <?php if (mysqli_num_rows($result) == 0): ?>
      <div class="alert alert-info">ยังไม่มีการจอง</div>
    <?php else: ?>
      <div class="row row-cols-1 row-cols-md-2 g-4">
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
          <?php
          $d1 = new DateTime($row['start_date']);
          $d2 = new DateTime($row['end_date']);
          $days = max(1, $d1->diff($d2)->days);
          $rentTotal = (float)$row['daily_rate'] * $days;

          $status = $row['booking_status'] ?? 'pending';
          $badge = match ($status) {
            'pending'   => 'warning',
            'confirmed' => 'success',
            default     => 'secondary',
          };

          $carImg = '../uploads/cars/' . $row['image_path'];
          $empFull = trim(($row['emp_fname'] ?? '') . ' ' . ($row['emp_lname'] ?? ''));
          $hasEmp  = ($status === 'confirmed' && !empty($row['emp_deliver']));
          $modalId = 'empModal' . $row['booking_id'];
          ?>
          <div class="col">
            <div class="booking-card h-100">
              <img src="<?= htmlspecialchars($carImg) ?>" class="booking-img w-100" alt="">
              <div class="p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h5 class="mb-1"><?= htmlspecialchars($row['brand'] . ' ' . $row['model']) ?></h5>
                  <span class="badge bg-<?= $badge ?> text-capitalize"><?= htmlspecialchars($status) ?></span>
                </div>

                <ul class="list-mini">
                  <li><strong>วันที่เริ่มเช่า:</strong> <?= $d1->format('d/m/Y') ?></li>
                  <li><strong>วันที่คืน:</strong> <?= $d2->format('d/m/Y') ?></li>
                  <li>
                    <strong>ค่าเช่า <?= $days ?> วัน:</strong>
                    <?= number_format($rentTotal, 2) ?> บาท
                    <small class="text-muted">(<?= number_format($row['daily_rate']) ?> บาท/วัน)</small>
                  </li>
                  <li><strong>มัดจำ:</strong> <?= number_format($row['total_price'], 2) ?> บาท</li>
                </ul>

                <button class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">
                  ดูข้อมูลพนักงาน
                </button>

                <?php if ($status === 'pending'): ?>
                  <button class="btn btn-outline-danger btn-sm mt-3 cancel-booking"
                    data-id="<?= $row['booking_id'] ?>">
                    ยกเลิกการจอง
                  </button>
                <?php endif; ?>

                <div class="text-muted small mt-3">จองเมื่อ: <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></div>
              </div>
            </div>
          </div>

          <!-- Modal ข้อมูลพนักงาน -->
          <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">ข้อมูลพนักงานที่ส่งรถ</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <?php if (!empty($row['emp_deliver'])): ?>
                    <div class="mb-2"><strong>ชื่อ-นามสกุล:</strong> <?= htmlspecialchars($empFull) ?></div>
                    <div class="mb-2"><strong>เบอร์โทร:</strong> <?= htmlspecialchars($row['emp_phone'] ?? '-') ?></div>
                    <div class="mb-2"><strong>อีเมล:</strong> <?= htmlspecialchars($row['emp_email'] ?? '-') ?></div>
                    <hr>
                    <div class="small text-muted">
                      หากต้องการเปลี่ยนเวลานัดรับ-ส่งรถ กรุณาติดต่อพนักงานโดยตรง หรือฝ่ายบริการลูกค้า
                    </div>
                  <?php else: ?>
                    <div class="alert alert-warning text-center">
                      ยังไม่มีพนักงานยืนยันการจอง
                    </div>
                  <?php endif; ?>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-light" data-bs-dismiss="modal">ปิด</button>
                </div>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php include("../includes/footer.php"); ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      <?php if (!empty($_GET['new'])): ?>
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: 'บันทึกการจองเรียบร้อย',
          showConfirmButton: false,
          timer: 2000
        });
      <?php endif; ?>
      // });
      // document.addEventListener("DOMContentLoaded", () => {
      document.querySelectorAll(".cancel-booking").forEach(btn => {
        btn.addEventListener("click", () => {
          const id = btn.getAttribute("data-id");
          Swal.fire({
            title: "ต้องการยกเลิกการจอง?",
            text: "หากยกเลิกแล้วจะไม่สามารถคืนค่ามัดจำได้",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "ใช่, ยกเลิกเลย",
            cancelButtonText: "ไม่"
          }).then(result => {
            if (result.isConfirmed) {
              fetch("cancel_booking.php", {
                  method: "POST",
                  headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                  },
                  body: "booking_id=" + id
                })
                .then(res => res.json())
                .then(data => {
                  if (data.success) {
                    Swal.fire("สำเร็จ", data.msg, "success").then(() => {
                      location.reload();
                    });
                  } else {
                    Swal.fire("ผิดพลาด", data.msg, "error");
                  }
                })
                .catch(err => console.error(err));
            }
          });
        });
      });
    });
  </script>

</body>

</html>