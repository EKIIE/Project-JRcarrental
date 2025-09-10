<?php
require("../db.php");
session_start();

if ($_SESSION['user_type'] !== 'staff') {
    echo "Access Denied";
    exit();
}

// ดึงรายการจองที่รอส่งลูกค้า
// $pending = mysqli_query($conn, "SELECT * FROM bookings WHERE booking_status = 'pending' ORDER BY created_at DESC");
$pending = mysqli_query(
    $conn,
    "SELECT b.*, c.license_plate
    FROM bookings b
    JOIN cars c ON b.car_id = c.car_id
    WHERE b.booking_status IN ('pending', 'waiting')
    ORDER BY b.created_at DESC"
);

// ดึงรายการจองที่ถึงวันคืนรถแล้ว
// $today = date('Y-m-d');
// $returning = mysqli_query($conn,
//     "SELECT * FROM bookings 
//     WHERE booking_status = 
//     end_date = '$today' ORDER BY created_at DESC");

$active_rentals = mysqli_query(
    $conn,
    "SELECT r.*, c.license_plate, b.location
    FROM rentals r
    JOIN cars c ON r.car_id = c.car_id
    JOIN bookings b ON r.booking_id = b.booking_id
    WHERE r.rental_status = 'ongoing'
    ORDER BY r.actual_pickup_date DESC"
);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Staff Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="container py-4">
    <!-- ใช้ฟอนต์ Kanit และ Bootstrap 5 -->
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background-color: #fdfaf6;
        }

        .card-style {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
        }
    </style>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded-3 shadow-sm mb-4 px-3">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">JR Car Rental</a>
            <div class="d-flex">
                <a href="../notifications.php" class="btn btn-outline-light btn-sm me-2">🔔 แจ้งเตือน</a>
                <a href="../auth/logout.php" class="btn btn-danger btn-sm">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <div class="container card-style">
        <h2 class="mb-4">แดชบอร์ดพนักงาน</h2>

        <div class="mt-4">
            <h4>🚗 รายการรอส่งรถให้ลูกค้า</h4>
            <ul class="list-group shadow-sm rounded-3">
                <?php while ($row = mysqli_fetch_assoc($pending)): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <?= htmlspecialchars($row['license_plate']) ?> |
                            <?= date('j M Y', strtotime($row['start_date'])) ?> |
                            <?= htmlspecialchars($row['location']) ?>
                        </div>
                        <div>
                            <!-- ปุ่มดูข้อมูลลูกค้า -->
                            <a href="#" class="btn btn-info btn-sm me-2"
                                data-bs-toggle="modal"
                                data-bs-target="#customerModal<?= $row['booking_id'] ?>">
                                ยืนยันผู้เช่า
                            </a>

                            <!-- ปุ่มทำสัญญา -->
                            <?php if ($row['booking_status'] === 'waiting'): ?>
                                <a href="create_contract.php?booking_id=<?= $row['booking_id'] ?>"
                                    class="btn btn-success btn-sm me-2">ทำสัญญา</a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm me-2" disabled>รอยืนยัน</button>
                            <?php endif; ?>

                            <!-- ปุ่มยกเลิกจอง -->
                            <form action="cancel_booking.php" method="post" class="d-inline"
                                onsubmit="return confirm('ยืนยันยกเลิกการจองนี้หรือไม่?');">
                                <input type="hidden" name="booking_id" value="<?= (int)$row['booking_id'] ?>">
                                <input type="hidden" name="car_id" value="<?= (int)$row['car_id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm">ยกเลิกจอง</button>
                            </form>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>

        <div class="mt-4">
            <h4>🔁 รถที่กำลังเช่าอยู่</h4>
            <ul class="list-group shadow-sm rounded-3">
                <?php while ($r = mysqli_fetch_assoc($active_rentals)): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <?= htmlspecialchars($r['license_plate']) ?> |
                            เริ่มเช่า: <?= date('j M Y', strtotime($r['actual_pickup_date'])) ?> |
                            รับคืน: <?= $r['actual_return_date'] ? date('j M Y', strtotime($r['actual_return_date'])) : '-' ?>
                        </div>
                        <div>
                            <a href="contract_view.php?booking_id=<?= $r['booking_id'] ?>" class="btn btn-outline-secondary btn-sm">ดูสัญญา</a>
                            <a href="checkup_form.php?rental_id=<?= $r['rental_id'] ?>" class="btn btn-warning btn-sm">
                                รับรถคืน
                            </a>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
    </div>

    <div class="mt-5">
        <a href="../notifications.php" class="btn btn-secondary">ดูการแจ้งเตือน</a>
        <a href="../auth/logout.php" class="btn btn-danger float-end">ออกจากระบบ</a>
    </div>

    <!-- Modal Renter -->
    <?php
    mysqli_data_seek($pending, 0); // รีเซ็ต pointer
    while ($row = mysqli_fetch_assoc($pending)):
        $user_id = $row['user_id'];
        $customer = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM customers WHERE user_id = $user_id"));
    ?>
        <div class="modal fade" id="customerModal<?= $row['booking_id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">ข้อมูลลูกค้า</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <p><strong>ชื่อ-นามสกุล:</strong> <?= $customer['firstname'] . ' ' . $customer['lastname'] ?></p>
                                <p><strong>เบอร์โทร:</strong> <?= $customer['phone_number'] ?></p>
                                <p><strong>อีเมล:</strong> <?= $customer['email'] ?></p>
                                <p><strong>สถานที่รับรถ:</strong> <?= $row['location'] ?></p>
                                <p><strong>วันรับรถ:</strong> <?= date('j M Y', strtotime($row['start_date'])) ?></p>
                            </div>

                            <div class="col-md-4">
                                <p class="mb-1"><strong>สลิป: </strong> ชำระแล้ว</p>
                                <?php if (!empty($row['payment_proof'])): ?>
                                    <img src="../uploads/slips/<?= htmlspecialchars($row['payment_proof']) ?>"
                                        class="img-thumbnail" style="max-width: 180px;">
                                <?php else: ?>
                                    <span class="text-muted">— ไม่มีสลิป —</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <hr>
                        <p><strong>บัตรประชาชน และ ใบขับขี่</strong></p>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <img src="../uploads/licenses/<?= $customer['passport_license'] ?>" class="img-fluid rounded border" alt="บัตรประชาชน">
                            </div>
                            <div class="col-md-6 mb-2">
                                <img src="../uploads/licenses/<?= $customer['drivers_license'] ?>" class="img-fluid rounded border" alt="ใบขับขี่">
                            </div>
                        </div>
                        <hr>
                        <form method="post" action="confirm_delivery.php" class="text-end mt-3">
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
    <!-- End Modal Renter -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Toast แจ้งเตือนการจองใหม่ -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
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

    <script>
        let lastSeen = Number(localStorage.getItem('bk_last_seen')) || Math.floor(Date.now() / 1000);

        async function checkNewBookings() {
            try {
                const res = await fetch('../api/new_bookings.php?since=' + lastSeen);
                const data = await res.json();
                if (data.server_time) localStorage.setItem('bk_last_seen', data.server_time);

                if (data.count && data.count > 0) {
                    const el = document.getElementById('newBookingToast');
                    const toast = new bootstrap.Toast(el);
                    toast.show();
                }
                lastSeen = data.server_time || lastSeen;
            } catch (e) {
                console.error("check error", e);
            }
        }

        // เช็คทุก 15 วิ
        setInterval(checkNewBookings, 15000);
        checkNewBookings();
    </script>
    <?php if (isset($_GET['cancelled'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                <?php if ($_GET['cancelled'] == '1'): ?>
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: 'ยกเลิกการจองเรียบร้อยแล้ว',
                        timer: 3000,
                        showConfirmButton: false
                    });
                <?php else: ?>
                    Swal.fire({
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