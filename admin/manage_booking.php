<?php
require("../db.php");
session_start();

/* ---------- ตรวจสอบสิทธิ์เฉพาะ admin ---------- */
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/* ---------- ดึงรายการจองที่รอส่งลูกค้า (pending/confirmed) ---------- */
$pending = mysqli_query(
    $conn,
    "SELECT b.*, c.license_plate
     FROM bookings b
     JOIN cars c ON b.car_id = c.car_id
     WHERE b.booking_status IN ('pending','confirmed')
     ORDER BY b.created_at DESC"
);

/* ---------- ดึงรายการที่กำลังเช่าอยู่ ---------- */
$active_rentals = mysqli_query(
    $conn,
    "SELECT r.*, c.license_plate, b.location
     FROM rentals r
     JOIN cars c ON r.car_id = c.car_id
     JOIN bookings b ON r.booking_id = b.booking_id
     WHERE r.rental_status = 'active'
     ORDER BY r.actual_pickup_date DESC"
);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <title>จัดการการจอง | JR Car Rental (Admin)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap / Fonts / Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

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

        .btn-primary {
            background: var(--jr-brown);
            border-color: var(--jr-brown);
        }

        .btn-primary:hover {
            background: var(--jr-brown-2);
            border-color: var(--jr-brown-2);
        }

        .card-section .card-header {
            background: var(--jr-cream);
            border-bottom: 1px solid rgba(0, 0, 0, .05);
        }

        .card-section .card-header h5 {
            margin: 0;
            font-weight: 600;
        }

        .list-group-item {
            border: 0;
            border-bottom: 1px solid rgba(0, 0, 0, .06);
        }

        .list-group-flush>.list-group-item:last-child {
            border-bottom: 0;
        }

        .modal-content {
            border-radius: 1rem;
        }

        .modal-header,
        .modal-footer {
            border-color: rgba(0, 0, 0, .06);
        }

        .doc-box img {
            max-width: 180px;
        }

        .id-box img {
            width: 100%;
            max-height: 360px;
            object-fit: cover;
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
                            <li><a class="dropdown-item" href="manage_cars.php">จัดการรถ</a></li>
                            <li><a class="dropdown-item active" href="manage_bookings.php">จัดการการจอง</a></li>
                            <li><a class="dropdown-item" href="../profile.php">ข้อมูลส่วนตัว</a></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">ออกจากระบบ</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h3 class="page-title">จัดการการจอง</h3>
                <div class="subtext">ตรวจสลิป/ยืนยันผู้เช่า, ทำสัญญา, ยกเลิก, และรับรถคืน</div>
            </div>
            <div class="d-flex gap-2">
                <a href="../notifications.php" class="btn btn-outline-secondary rounded-3"><i class="fa-regular fa-bell me-1"></i> การแจ้งเตือน</a>
            </div>
        </div>

        <!-- รอส่งรถให้ลูกค้า -->
        <div class="card rounded-3 shadow-sm card-section mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">🚗 รายการรอส่งรถให้ลูกค้า</h5>
                <span class="text-muted small">รวม <?= number_format($pending ? mysqli_num_rows($pending) : 0) ?> รายการ</span>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if ($pending && mysqli_num_rows($pending) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($pending)): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <span class="fw-semibold"><?= htmlspecialchars($row['license_plate']) ?></span>
                                    <span class="text-muted">| รับ: <?= date('j M Y', strtotime($row['start_date'])) ?></span>
                                    <span class="text-muted">| จุดรับ: <?= htmlspecialchars($row['location']) ?></span>
                                    <?php if ($row['booking_status'] === 'pending'): ?>
                                        <span class="badge bg-secondary ms-2">รออนุมัติ</span>
                                    <?php elseif ($row['booking_status'] === 'confirmed'): ?>
                                        <span class="badge bg-success ms-2">ยืนยันแล้ว</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-nowrap">
                                    <!-- ดู/ยืนยันผู้เช่า -->
                                    <a href="#" class="btn btn-info btn-sm me-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#customerModal<?= (int)$row['booking_id'] ?>">
                                        ยืนยันผู้เช่า
                                    </a>
                                    <!-- ทำสัญญา -->
                                    <?php if ($row['booking_status'] === 'confirmed'): ?>
                                        <a class="btn btn-success btn-sm me-2"
                                            href="../staff/create_contract.php?booking_id=<?= (int)$row['booking_id'] ?>">
                                            ทำสัญญา
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm me-2" disabled>รอยืนยัน</button>
                                    <?php endif; ?>
                                    <!-- ยกเลิกการจอง -->
                                    <form action="../staff/cancel_booking.php" method="post" class="d-inline"
                                        onsubmit="return confirm('ยืนยันยกเลิกการจองนี้หรือไม่?');">
                                        <input type="hidden" name="booking_id" value="<?= (int)$row['booking_id'] ?>">
                                        <input type="hidden" name="car_id" value="<?= (int)$row['car_id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">ยกเลิกจอง</button>
                                    </form>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center py-4 text-muted">ไม่มีรายการ</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- รถที่กำลังเช่าอยู่ -->
        <div class="card rounded-3 shadow-sm card-section mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">🔁 รถที่กำลังเช่าอยู่</h5>
                <span class="text-muted small">กำลังเช่า <?= number_format($active_rentals ? mysqli_num_rows($active_rentals) : 0) ?> คัน</span>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if ($active_rentals && mysqli_num_rows($active_rentals) > 0): ?>
                        <?php while ($r = mysqli_fetch_assoc($active_rentals)): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="me-3">
                                    <span class="fw-semibold"><?= htmlspecialchars($r['license_plate']) ?></span>
                                    <span class="text-muted">| เริ่มเช่า: <?= date('j M Y', strtotime($r['actual_pickup_date'])) ?></span>
                                    <span class="text-muted">| รับคืน: <?= $r['actual_return_date'] ? date('j M Y', strtotime($r['actual_return_date'])) : '-' ?></span>
                                </div>
                                <div class="text-nowrap">
                                    <a href="../staff/checkup_form.php?rental_id=<?= (int)$r['rental_id'] ?>" class="btn btn-warning btn-sm">รับรถคืน</a>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center py-4 text-muted">ไม่มีรายการ</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="text-end">
            <a href="../auth/logout.php" class="btn btn-danger rounded-3">ออกจากระบบ</a>
        </div>
    </div>

    <!-- ========= Modals: ข้อมูลลูกค้า ========= -->
    <?php if ($pending && mysqli_num_rows($pending) > 0): ?>
        <?php mysqli_data_seek($pending, 0); ?>
        <?php while ($row = mysqli_fetch_assoc($pending)):
            $user_id  = (int)$row['user_id'];
            $cRes     = mysqli_query($conn, "SELECT * FROM customers WHERE user_id = {$user_id}");
            $customer = $cRes ? mysqli_fetch_assoc($cRes) : [];
            $fullname = trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? ''));
        ?>
            <div class="modal fade" id="customerModal<?= (int)$row['booking_id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                ข้อมูลลูกค้า <span class="fw-light">— <?= htmlspecialchars($fullname) ?></span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3 align-items-start">
                                <div class="col-md-8">
                                    <p class="mb-1"><strong>ชื่อ-นามสกุล:</strong> <?= htmlspecialchars($fullname) ?></p>
                                    <p class="mb-1"><strong>เบอร์โทร:</strong> <?= htmlspecialchars($customer['phone_number'] ?? '-') ?></p>
                                    <p class="mb-1"><strong>อีเมล:</strong> <?= htmlspecialchars($customer['email'] ?? '-') ?></p>
                                    <p class="mb-1"><strong>สถานที่รับรถ:</strong> <?= htmlspecialchars($row['location']) ?></p>
                                    <p class="mb-1"><strong>วันรับรถ:</strong> <?= date('j M Y', strtotime($row['start_date'])) ?></p>
                                </div>
                                <div class="col-md-4 doc-box">
                                    <p class="mb-1"><strong>สลิป:</strong> <?= !empty($row['payment_status']) ? htmlspecialchars($row['payment_status']) : '—' ?></p>
                                    <?php if (!empty($row['payment_proof'])): ?>
                                        <img src="../uploads/slips/<?= htmlspecialchars($row['payment_proof']) ?>" class="img-thumbnail" alt="สลิปชำระเงิน">
                                    <?php else: ?>
                                        <span class="text-muted">— ไม่มีสลิป —</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <hr>
                            <p class="mb-2"><strong>บัตรประชาชน และ ใบขับขี่</strong></p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <?php if (!empty($customer['passport_license'])): ?>
                                        <img src="../uploads/licenses/<?= htmlspecialchars($customer['passport_license']) ?>" class="id-box img-fluid rounded border" alt="บัตรประชาชน/พาสปอร์ต">
                                    <?php else: ?>
                                        <div class="text-muted">— ไม่มีไฟล์ —</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <?php if (!empty($customer['drivers_license'])): ?>
                                        <img src="../uploads/licenses/<?= htmlspecialchars($customer['drivers_license']) ?>" class="id-box img-fluid rounded border" alt="ใบขับขี่">
                                    <?php else: ?>
                                        <div class="text-muted">— ไม่มีไฟล์ —</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <hr>
                            <form method="post" action="../staff/confirm_delivery.php" class="text-end mt-3">
                                <input type="hidden" name="booking_id" value="<?= (int)$row['booking_id'] ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
                                <input type="hidden" name="car_id" value="<?= (int)$row['car_id'] ?>">
                                <button type="submit" class="btn btn-success">เตรียมส่งรถ</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>


    <!-- ========= Toast แจ้งเตือนการจองใหม่ ========= -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
        <div id="newBookingToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">มีการจองใหม่</strong>
                <small>เดี๋ยวนี้</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                มีรายการรอตรวจสลิปใหม่ — ไปที่ “รอตรวจสอบสลิป” เพื่อดูรายละเอียด
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // poll การจองใหม่ เหมือนหน้า staff
        let lastSeen = Number(localStorage.getItem('bk_last_seen')) || Math.floor(Date.now() / 1000);
        async function checkNewBookings() {
            try {
                const res = await fetch('../api/new_bookings.php?since=' + lastSeen);
                const data = await res.json();
                if (data.server_time) localStorage.setItem('bk_last_seen', data.server_time);
                if (data.count && data.count > 0) new bootstrap.Toast(document.getElementById('newBookingToast')).show();
                lastSeen = data.server_time || lastSeen;
            } catch (e) {
                console.error(e);
            }
        }
        setInterval(checkNewBookings, 15000);
        checkNewBookings();
    </script>

    <?php if (isset($_GET['cancelled'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                <?php if ($_GET['cancelled'] == '1'): ?>
                    Swal && Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: 'ยกเลิกการจองเรียบร้อยแล้ว',
                        timer: 3000,
                        showConfirmButton: false
                    });
                <?php else: ?>
                    Swal && Swal.fire({
                        icon: 'error',
                        title: 'ไม่สำเร็จ',
                        text: 'ยกเลิกการจองไม่สำเร็จ หรือสถานะไม่อนุญาตให้ยกเลิก',
                        confirmButtonText: 'ตกลง'
                    });
                <?php endif; ?>
            });
        </script>
    <?php endif; ?>

</body>

</html>