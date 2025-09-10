<?php
// admin/customers.php
session_start();
require("../db.php");

// ⛔ เฉพาะแอดมิน
if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
  header("Location: ../auth/login.php");
  exit();
}

// ค้นหา (ชื่อ/นามสกุล/username/อีเมล/เบอร์/ที่อยู่)
$search = trim($_GET['search'] ?? '');
$where  = '';
if ($search !== '') {
  $s = mysqli_real_escape_string($conn, $search);
  $where = "WHERE (u.username LIKE '%$s%'
              OR c.firstname LIKE '%$s%'
              OR c.lastname  LIKE '%$s%'
              OR c.email     LIKE '%$s%'
              OR c.phone_number LIKE '%$s%'
              OR c.address   LIKE '%$s%')";
}

// ดึงข้อมูลลูกค้า: users (type=customer) + customers
$sql = "
SELECT u.user_id, u.username,
       c.firstname, c.lastname, c.email, c.phone_number, c.address,
       c.drivers_license, c.passport_license, c.created_at
FROM users u
JOIN customers c ON u.user_id = c.user_id
WHERE u.user_type = 'customer'
" . ($where ? " AND " . substr($where, 6) : "") . "
ORDER BY u.user_id DESC
";
$rs = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>ดูข้อมูลลูกค้า | JR Car Rental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    body{font-family:'Kanit',sans-serif;background:#f4f7f9}
    .dashboard-container{max-width:1200px;margin:auto;padding:20px}
    .page-title{font-weight:600;font-size:2rem}
    .rounded-3{border-radius:1rem!important}
    .shadow-sm{box-shadow:0 .125rem .25rem rgba(0,0,0,.075)!important}
    .doc-thumb{max-width:180px}
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
            <li><a class="dropdown-item" href="manage_booking.php">จัดการการจอง</a></li>
            <li><a class="dropdown-item active" href="customers.php">ดูข้อมูลลูกค้า</a></li>
            <li><a class="dropdown-item" href="../profile.php">แก้ไขข้อมูลส่วนตัว</a></li>
            <li><a class="dropdown-item" href="../auth/logout.php">ออกจากระบบ</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="dashboard-container">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="page-title">ดูข้อมูลลูกค้า</h3>
    <form class="d-flex" method="get">
      <input type="text" class="form-control" name="search" placeholder="ค้นหา (ชื่อ/ผู้ใช้/อีเมล/โทร/ที่อยู่)" value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-outline-secondary ms-2">ค้นหา</button>
    </form>
  </div>

  <div class="card rounded-3 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>ชื่อผู้ใช้</th>
              <th>ชื่อ-นามสกุล</th>
              <th>อีเมล</th>
              <th>เบอร์โทร</th>
              <th>ที่อยู่</th>
              <th class="text-center">เอกสาร</th>
              <th class="text-center">ประวัติการจอง</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($rs && mysqli_num_rows($rs) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($rs)): ?>
              <tr>
                <td><?= (int)$row['user_id'] ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['firstname'].' '.$row['lastname']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['phone_number']) ?></td>
                <td><?= htmlspecialchars($row['address']) ?></td>
                <td class="text-center">
                  <button
                    class="btn btn-info btn-sm text-white"
                    data-bs-toggle="modal"
                    data-bs-target="#docsModal"
                    data-name="<?= htmlspecialchars($row['firstname'].' '.$row['lastname']) ?>"
                    data-idcard="<?= htmlspecialchars($row['passport_license']) ?>"
                    data-license="<?= htmlspecialchars($row['drivers_license']) ?>">
                    ดูเอกสาร
                  </button>
                </td>
                <td class="text-center">
                  <button
                    class="btn btn-primary btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#historyModal"
                    data-userid="<?= (int)$row['user_id'] ?>"
                    data-name="<?= htmlspecialchars($row['firstname'].' '.$row['lastname']) ?>">
                    ดูประวัติ
                  </button>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="text-center py-4">ไม่พบข้อมูลลูกค้า</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal: เอกสารลูกค้า -->
<div class="modal fade" id="docsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content rounded-3">
      <div class="modal-header">
        <h5 class="modal-title">เอกสารลูกค้า — <span id="doc_name" class="fw-light"></span></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <h6>บัตรประชาชน / พาสปอร์ต</h6>
            <div id="doc_idcard" class="border p-2 text-center rounded-3"></div>
          </div>
          <div class="col-md-6">
            <h6>ใบขับขี่</h6>
            <div id="doc_license" class="border p-2 text-center rounded-3"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: ประวัติการจอง -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content rounded-3">
      <div class="modal-header">
        <h5 class="modal-title">ประวัติการจอง — <span id="his_name" class="fw-light"></span></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="his_body" class="text-center text-muted py-5">กำลังโหลด...</div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// เอกสารลูกค้า
document.getElementById('docsModal').addEventListener('show.bs.modal', (ev) => {
  const btn  = ev.relatedTarget;
  const name = btn.getAttribute('data-name') || '';
  const idc  = btn.getAttribute('data-idcard') || '';
  const lic  = btn.getAttribute('data-license') || '';
  document.getElementById('doc_name').textContent = name;

  const render = (file, hostId) => {
    const host = document.getElementById(hostId);
    if (!file) { host.innerHTML = '<div class="text-muted py-5">— ไม่มีไฟล์ —</div>'; return; }
    const path = '../uploads/licenses/' + file;
    if (/\.(jpg|jpeg|png|gif|webp)$/i.test(file)) {
      host.innerHTML = `<img src="${path}" class="img-fluid rounded-3 doc-thumb">`;
    } else if (/\.pdf$/i.test(file)) {
      host.innerHTML = `<a href="${path}" target="_blank" class="btn btn-outline-secondary">เปิดไฟล์ PDF</a>`;
    } else {
      host.innerHTML = `<a href="${path}" target="_blank" class="btn btn-outline-secondary">เปิดไฟล์</a>`;
    }
  };
  render(idc, 'doc_idcard');
  render(lic, 'doc_license');
});

// ประวัติการจอง (AJAX -> admin/api/customer_bookings.php?user_id=)
document.getElementById('historyModal').addEventListener('show.bs.modal', (ev) => {
  const btn = ev.relatedTarget;
  const uid = btn.getAttribute('data-userid');
  const name= btn.getAttribute('data-name') || '';
  document.getElementById('his_name').textContent = name;
  const body = document.getElementById('his_body');
  body.innerHTML = '<div class="text-center text-muted py-5">กำลังโหลด...</div>';

  fetch('../api/customer_bookings.php?user_id='+encodeURIComponent(uid))
    .then(r => r.text())
    .then(html => body.innerHTML = html)
    .catch(() => body.innerHTML = '<div class="text-danger">โหลดข้อมูลไม่สำเร็จ</div>');
});
</script>
</body>
</html>
