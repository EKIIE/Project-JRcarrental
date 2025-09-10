<?php
// admin/api/customer_bookings.php
session_start();
require("../../db.php");

if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
  http_response_code(403); exit('forbidden');
}

$user_id = (int)($_GET['user_id'] ?? 0);
if ($user_id <= 0) { echo '<div class="text-danger">ไม่พบ user_id</div>'; exit; }

$q = mysqli_query($conn, "
SELECT b.booking_id, b.start_date, b.end_date, b.location, b.booking_status,
       c.license_plate
FROM bookings b
JOIN cars c ON b.car_id = c.car_id
WHERE b.user_id = $user_id
ORDER BY b.created_at DESC
");

if (!$q || mysqli_num_rows($q) === 0) {
  echo '<div class="text-muted">— ไม่มีประวัติการจอง —</div>'; exit;
}
?>
<div class="table-responsive">
  <table class="table table-sm table-bordered align-middle">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>ทะเบียน</th>
        <th>รับรถ</th>
        <th>คืนรถ</th>
        <th>สถานที่รับ</th>
        <th>สถานะ</th>
      </tr>
    </thead>
    <tbody>
      <?php while($r = mysqli_fetch_assoc($q)): ?>
        <tr>
          <td><?= (int)$r['booking_id'] ?></td>
          <td><?= htmlspecialchars($r['license_plate']) ?></td>
          <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['start_date']))) ?></td>
          <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['end_date']))) ?></td>
          <td><?= htmlspecialchars($r['location']) ?></td>
          <td>
            <?php
              $st = $r['booking_status'];
              $badge = [
                'pending'=>'secondary',
                'confirmed'=>'primary',
                'approved'=>'success',
                'cancelled'=>'danger',
                'completed'=>'dark'
              ][$st] ?? 'secondary';
            ?>
            <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($st) ?></span>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
