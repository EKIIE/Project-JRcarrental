<?php
require("../db.php");
session_start();

// อนุญาตทั้ง staff และ admin
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['staff', 'admin'])) {
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
    "SELECT r.*, c.license_plate, b.location, b.end_date
    FROM rentals r
    JOIN cars c ON r.car_id = c.car_id
    JOIN bookings b ON r.booking_id = b.booking_id
    WHERE r.rental_status = 'ongoing'
    ORDER BY r.actual_pickup_date DESC"
);

$where = [];
if (!empty($_GET['month'])) {
    $month = (int)$_GET['month'];
    $where[] = "MONTH(b.start_date) = $month";
}
if (!empty($_GET['plate'])) {
    $plate = mysqli_real_escape_string($conn, $_GET['plate']);
    $where[] = "c.license_plate LIKE '%$plate%'";
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$history = mysqli_query(
    $conn,
    "SELECT r.*, 
            b.start_date, b.end_date, b.location,
            c.license_plate, c.brand, c.model,
            cus.firstname AS cus_fname, cus.lastname AS cus_lname,
            e1.firstname AS deliver_fname, e1.lastname AS deliver_lname,
            e2.firstname AS return_fname, e2.lastname AS return_lname
     FROM rentals r
     JOIN bookings b ON r.booking_id = b.booking_id
     JOIN cars c ON r.car_id = c.car_id
     JOIN customers cus ON r.user_id = cus.user_id
     LEFT JOIN employees e1 ON r.emp_deliver = e1.employee_id
     LEFT JOIN employees e2 ON r.emp_returner = e2.employee_id
     $where_sql
     ORDER BY r.created_at DESC"
);

// *** เพิ่มโค้ดตรวจสอบ error ตรงนี้ ***
if (!$history) {
    echo "<h3 class='text-danger'>SQL Query Error!</h3>";
    // ใช้ die() เพื่อหยุดการทำงานและแสดงข้อผิดพลาดของ MySQL
    die("Database Error: " . mysqli_error($conn) . " on query: " . 
        "SELECT r.*, b.start_date, b.end_date, b.location, c.license_plate, c.brand, c.model, cus.firstname AS cus_fname, cus.lastname AS cus_lname, e1.firstname AS deliver_fname, e1.lastname AS deliver_lname, e2.firstname AS return_fname, e2.lastname AS return_lname FROM rentals r JOIN bookings b ON r.booking_id = b.booking_id JOIN cars c ON r.car_id = c.car_id JOIN customers cus ON r.user_id = cus.user_id LEFT JOIN employees e1 ON r.emp_deliver = e1.employee_id LEFT JOIN employees e2 ON r.emp_returner = e2.employee_id {$where_sql} ORDER BY r.created_at DESC");
}

$history_data = [];
while ($h = mysqli_fetch_assoc($history)) {
    $history_data[] = $h;
}

function mapLocation($code)
{
    return match ($code) {
        'v1' => 'หน้าร้าน',
        'v2' => 'สนามบินเชียงใหม่',
        'v3' => 'ปั๊มข้างสนามบิน',
        default => $code // ถ้าไม่ตรง ให้โชว์ค่าดิบไป
    };
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Staff Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <!-- <body class="container py-4"> -->
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4 px-3">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">JR Car Rental</a>
            <div class="d-flex">
                <a href="../profile.php" class="btn btn-outline-light btn-sm me-2">โปรไฟล์</a>
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
                            <?= htmlspecialchars(mapLocation($row['location'])) ?>
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
                                <button class="btn btn-success btn-sm me-2"
                                    data-bs-toggle="modal"
                                    data-bs-target="#contractModal<?= $row['booking_id'] ?>">
                                    ทำสัญญา
                                </button>
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
                            กำหนดคืน: <?= date('j M Y', strtotime($r['end_date'])) ?> |
                            <?= htmlspecialchars(mapLocation($r['location'])) ?>
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


        <div class="mt-5">
            <!-- <a href="../notifications.php" class="btn btn-secondary">ดูการแจ้งเตือน</a> -->
            <!-- <a href="../auth/logout.php" class="btn btn-danger float-end">ออกจากระบบ</a> -->
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#historyModal">
                📜 ดูประวัติการเช่า
            </button>
        </div>
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
                                <p><strong>สถานที่รับรถ:</strong> <?= htmlspecialchars(mapLocation($row['location'])) ?></p>
                                <p><strong>วันรับรถ:</strong> <?= date('j M Y', strtotime($row['start_date'])) ?></p>
                                <p><strong>วันคืนรถ:</strong> <?= date('j M Y', strtotime($row['end_date'])) ?></p>
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

        <!-- Modal Confirm -->
        <div class="modal fade" id="contractModal<?= $row['booking_id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">ยืนยันข้อมูลสัญญา</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="create_contract.php">
                            <input type="hidden" name="booking_id" value="<?= (int)$row['booking_id'] ?>">
                            <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
                            <input type="hidden" name="car_id" value="<?= (int)$row['car_id'] ?>">

                            <div class="mb-3">
                                <label class="form-label">ชื่อผู้เช่า</label>
                                <input type="text" class="form-control" value="<?= $customer['firstname'] . ' ' . $customer['lastname'] ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">วันรับรถ</label>
                                <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($row['start_date'])) ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">วันคืนรถ</label>
                                <input type="text" class="form-control" value="<?= date('d/m/Y', strtotime($row['end_date'])) ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">สถานที่รับรถ</label>
                                <select name="location" class="form-select" required>
                                    <option value="<?= htmlspecialchars(mapLocation($row['location'])) ?>" selected><?= htmlspecialchars(mapLocation($row['location'])) ?></option>
                                    <option value="v1">หน้าร้าน</option>
                                    <option value="v2">สนามบินเชียงใหม่</option>
                                    <option value="v3">ปั๊มปตทข้างสนามบิน</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">หมายเหตุ (ถ้ามี)</label>
                                <textarea class="form-control" name="notes"></textarea>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-success">ยืนยันและบันทึกสัญญา</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Modal Confirm -->

        <!-- Modal History -->
        <div class="modal fade" id="historyModal" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">ประวัติรายการเช่า</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">

                        <!-- ฟิลเตอร์ -->
                        <form method="get" class="row g-2 mb-3">
                            <div class="col-md-3">
                                <select name="month" class="form-select">
                                    <option value="">-- ทั้งหมด --</option>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= ($_GET['month'] ?? '') == $m ? 'selected' : '' ?>>
                                            <?= date("F", mktime(0, 0, 0, $m, 1)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="plate" class="form-control" placeholder="ทะเบียนรถ" value="<?= htmlspecialchars($_GET['plate'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary">ค้นหา</button>
                            </div>
                        </form>

                        <!-- ตาราง -->
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ทะเบียน</th>
                                    <th>ผู้เช่า</th>
                                    <th>วันรับ</th>
                                    <th>วันคืน</th>
                                    <th>สถานะ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history_data as $h): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($h['license_plate']) ?></td>
                                        <td><?= htmlspecialchars($h['cus_fname'] . ' ' . $h['cus_lname']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($h['start_date'])) ?></td>
                                        <td><?= date('d/m/Y', strtotime($h['end_date'])) ?></td>
                                        <td><?= htmlspecialchars($h['rental_status']) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm" onclick="openDetail('<?= $h['rental_id'] ?>')">
                                                รายละเอียด
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
        <!-- End Modal History -->

        <!-- Modal History Detail -->
        <?php foreach ($history_data as $h): ?>
            <div class="modal fade" id="histDetail<?= $h['rental_id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">รายละเอียดการเช่า</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>ลูกค้า:</strong> <?= $h['cus_fname'] . ' ' . $h['cus_lname'] ?></p>
                            <p><strong>ทะเบียนรถ:</strong> <?= $h['license_plate'] ?> (<?= $h['brand'] . ' ' . $h['model'] ?>)</p>
                            <p><strong>วันรับ:</strong> <?= $h['start_date'] ?></p>
                            <p><strong>วันคืน:</strong> <?= $h['end_date'] ?></p>
                            <p><strong>สถานที่:</strong> <?= htmlspecialchars(mapLocation($h['location'])) ?></p>
                            <p><strong>พนักงานส่ง:</strong> <?= $h['deliver_fname'] . ' ' . $h['deliver_lname'] ?></p>
                            <p><strong>พนักงานรับคืน:</strong> <?= $h['return_fname'] . ' ' . $h['return_lname'] ?></p>
                            <p><strong>สถานะการเช่า:</strong> <?= $h['rental_status'] ?></p>
                            <p><strong>ราคารวม:</strong> <?= number_format($h['total_amount']) ?> บาท</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <!-- End Modal History Detail -->

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
    <script>
        function calcPrice(bid, dailyRate) {
            const start = document.getElementById('start_date' + bid);
            const end = document.getElementById('end_date' + bid);
            const box = document.getElementById('priceCalc' + bid);

            function update() {
                if (start.value && end.value) {
                    const d1 = new Date(start.value);
                    const d2 = new Date(end.value);
                    let days = Math.max(1, Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24)));
                    let rentTotal = dailyRate * days;
                    let deposit = Math.ceil(rentTotal * 0.2);
                    box.textContent = `ค่าเช่า ${days} วัน = ${rentTotal.toLocaleString()} บาท | มัดจำ: ${deposit.toLocaleString()} บาท`;
                }
            }
            start.addEventListener('change', update);
            end.addEventListener('change', update);
            update();
        }
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

    <?php if (!empty($_GET['month']) || !empty($_GET['plate'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var modal = new bootstrap.Modal(document.getElementById('historyModal'));
                modal.show();
            });
        </script>
    <?php endif; ?>
    <script>
        function openDetail(id) {
            var modal = new bootstrap.Modal(document.getElementById('histDetail' + id));
            modal.show();
        }
    </script>

</body>

</html>