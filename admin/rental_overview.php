<?php
session_start();
require("../db.php");

// admin only
if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
  echo "<script>alert('Access Denied'); window.location='../home/index.php';</script>";
  exit;
}

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function baht($n)
{
  return number_format((float)$n, 2);
}

// ---------- ฟิลเตอร์ ----------
$q         = trim($_GET['q'] ?? '');          // username / ทะเบียน / booking_id / rental_id
$date_from = trim($_GET['from'] ?? '');       // yyyy-mm-dd
$date_to   = trim($_GET['to'] ?? '');         // yyyy-mm-dd
$status    = trim($_GET['status'] ?? '');     // booking | rental สถานะรวม (optional)

// สร้าง where แบบ dynamic
$where = "1";
$params = [];
$types  = "";

// ค้นหาชื่อผู้ใช้ / ทะเบียน / id
if ($q !== "") {
  // ถ้าเป็นตัวเลขล้วน จะจับเป็น id ได้ด้วย
  $where .= " AND (u.username LIKE CONCAT('%', ?, '%')
                   OR c.license_plate LIKE CONCAT('%', ?, '%')
                   OR b.booking_id = ?
                   OR r.rental_id = ?)";
  $types  .= "ssii";
  $params[] = $q;
  $params[] = $q;
  $params[] = ctype_digit($q) ? (int)$q : 0;
  $params[] = ctype_digit($q) ? (int)$q : 0;
}

// ช่วงวัน (อิง start_date ของ booking)
if ($date_from !== "") {
  $where .= " AND DATE(b.start_date) >= ?";
  $types  .= "s";
  $params[] = $date_from;
}
if ($date_to !== "") {
  $where .= " AND DATE(b.start_date) <= ?";
  $types  .= "s";
  $params[] = $date_to;
}

// สถานะรวม: รองรับทั้ง booking_status และ rental_status แบบง่าย ๆ
if ($status !== "") {
  $where .= " AND (b.booking_status = ? OR r.rental_status = ?)";
  $types  .= "ss";
  $params[] = $status;
  $params[] = $status;
}

// ---------- ดึงรายการ (ล่าสุดก่อน) ----------
$sql = "
SELECT
  b.booking_id, b.user_id, b.car_id, b.start_date, b.end_date,
  b.location, b.total_price, b.booking_status,
  u.username,
  c.license_plate, c.brand, c.model, c.image_path,
  r.rental_id, r.rental_status, r.actual_pickup_date, r.actual_return_date,
  r.total_amount, r.contract_file
FROM bookings b
JOIN users u ON b.user_id = u.user_id
JOIN cars  c ON b.car_id  = c.car_id
LEFT JOIN rentals r ON r.booking_id = b.booking_id
WHERE $where
ORDER BY COALESCE(r.updated_at, r.created_at, b.created_at) DESC, b.booking_id DESC
LIMIT 200
";
$st = mysqli_prepare($conn, $sql);
if ($types !== "") {
  mysqli_stmt_bind_param($st, $types, ...$params);
}
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);
$rows = [];
while ($row = mysqli_fetch_assoc($res)) {
  $rows[] = $row;
}
mysqli_stmt_close($st);
?>
<!doctype html>
<html lang="th">

<head>
  <meta charset="utf-8">
  <title>ดูข้อมูลการเช่ารถ | JR Car Rental</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Kanit', sans-serif;
      background: #f4f7f9;
    }

    .container-narrow {
      max-width: 1200px;
    }

    .rounded-3 {
      border-radius: 1rem !important;
    }

    .thumb {
      width: 70px;
      height: auto;
      border-radius: .4rem;
    }

    .badge-state {
      text-transform: capitalize
    }
  </style>
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
      <a class="navbar-brand" href="dashboard.php">JR Car Rental</a>
      <div class="collapse navbar-collapse"></div>
      <a class="btn btn-outline-light btn-sm" href="dashboard.php">ย้อนกลับ</a>
    </div>
  </nav>

  <div class="container container-narrow my-4">

    <!-- ฟอร์มค้นหา -->
    <div class="card rounded-3 shadow-sm mb-3">
      <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
          <div class="col-md-4">
            <label class="form-label">ค้นหา (ชื่อผู้ใช้ / ทะเบียน / Booking ID / Rental ID)</label>
            <input type="text" class="form-control" name="q" value="<?= h($q) ?>" placeholder="เช่น worakarn / กข-1234 / 101 / 5001">
          </div>
          <div class="col-md-2">
            <label class="form-label">จากวันที่</label>
            <input type="date" class="form-control" name="from" value="<?= h($date_from) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">ถึงวันที่</label>
            <input type="date" class="form-control" name="to" value="<?= h($date_to) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">สถานะ</label>
            <select class="form-select" name="status">
              <option value="">— ทั้งหมด —</option>
              <?php foreach (['pending', 'confirmed', 'cancelled', 'active', 'completed', 'overdue', 'waiting_review', 'approved'] as $st): ?>
                <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <button class="btn btn-primary w-100">ค้นหา</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ตารางรวม ประวัติการเช่า -->
    <div class="card rounded-3 shadow-sm">
      <div class="card-header"><strong>ประวัติการเช่ารถทั้งหมด (ล่าสุดก่อน)</strong></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Booking</th>
                <th>Rental</th>
                <th>รูปรถ</th>
                <th>ทะเบียน</th>
                <th>ลูกค้า</th>
                <th>ช่วงเช่า</th>
                <th>สถานะ</th>
                <th class="text-end">ยอด (฿)</th>
                <th class="text-center">จัดการ</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr>
                  <td colspan="9" class="text-center text-muted py-4">— ไม่พบรายการ —</td>
                </tr>
                <?php else: foreach ($rows as $r): ?>
                  <tr>
                    <td>#<?= (int)$r['booking_id'] ?></td>
                    <td><?= $r['rental_id'] ? '#' . (int)$r['rental_id'] : '-' ?></td>
                    <td><img class="thumb" src="../uploads/cars/<?= h($r['image_path']) ?>" alt=""></td>
                    <td><?= h($r['license_plate']) ?></td>
                    <td><?= h($r['username']) ?></td>
                    <td>
                      <div><?= date('d/m/Y', strtotime($r['start_date'])) ?></div>
                      <div class="text-muted small">ถึง <?= date('d/m/Y', strtotime($r['end_date'])) ?></div>
                    </td>
                    <td>
                      <span class="badge bg-secondary badge-state"><?= h($r['booking_status']) ?></span>
                      <?php if ($r['rental_id']): ?>
                        <div><span class="badge <?= $r['rental_status'] === 'active' ? 'bg-warning text-dark' : ($r['rental_status'] === 'completed' ? 'bg-success' : 'bg-danger') ?> badge-state">
                            <?= h($r['rental_status']) ?></span></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <?php
                      $amount = $r['rental_id'] ? ($r['total_amount'] ?? $r['total_price']) : $r['total_price'];
                      echo baht($amount);
                      ?>
                    </td>
                    <td class="text-center">
                      <?php if ($r['contract_file']): ?>
                        <a class="btn btn-outline-secondary btn-sm mb-1" target="_blank"
                          href="../uploads/contracts/<?= h($r['contract_file']) ?>">สัญญา</a>
                      <?php endif; ?>
                      <?php if ($r['rental_id']): ?>
                        <button class="btn btn-info btn-sm text-white mb-1"
                          data-bs-toggle="modal" data-bs-target="#modalCheckups"
                          data-rental="<?= (int)$r['rental_id'] ?>">ตรวจสภาพ</button>
                        <button class="btn btn-success btn-sm mb-1"
                          data-bs-toggle="modal" data-bs-target="#modalPayments"
                          data-rental="<?= (int)$r['rental_id'] ?>">ชำระเงิน</button>
                      <?php else: ?>
                        <span class="text-muted small">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
              <?php endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

  <!-- ==== Modals (ใช้ API เดิม) ==== -->
  <div class="modal fade" id="modalCheckups" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content rounded-3">
        <div class="modal-header">
          <h5 class="modal-title">ประวัติตรวจสภาพรถ</h5><button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="checkupsBody">กำลังโหลด...</div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalPayments" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content rounded-3">
        <div class="modal-header">
          <h5 class="modal-title">การชำระเงิน</h5><button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="paymentsBody">กำลังโหลด...</div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // โหลด Checkups (AJAX)
    document.getElementById('modalCheckups').addEventListener('show.bs.modal', ev => {
      const rid = ev.relatedTarget?.getAttribute('data-rental');
      if (!rid) return;
      fetch('../api/rental_overview_checkups.php?rental_id=' + encodeURIComponent(rid))
        .then(r => r.text()).then(html => {
          document.getElementById('checkupsBody').innerHTML = html;
        })
        .catch(() => {
          document.getElementById('checkupsBody').innerHTML = '<div class="text-danger">โหลดข้อมูลไม่สำเร็จ</div>';
        });
    });
    // โหลด Payments (AJAX)
    document.getElementById('modalPayments').addEventListener('show.bs.modal', ev => {
      const rid = ev.relatedTarget?.getAttribute('data-rental');
      if (!rid) return;
      fetch('../api/overview_payments.php?rental_id=' + encodeURIComponent(rid))
        .then(r => r.text()).then(html => {
          document.getElementById('paymentsBody').innerHTML = html;
        })
        .catch(() => {
          document.getElementById('paymentsBody').innerHTML = '<div class="text-danger">โหลดข้อมูลไม่สำเร็จ</div>';
        });
    });
  </script>
</body>

</html>