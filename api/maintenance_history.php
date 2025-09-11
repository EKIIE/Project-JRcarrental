<?php
session_start(); require("../db.php");
header("Content-Type: text/html; charset=utf-8");

if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') { http_response_code(403); exit('Forbidden'); }

$car_id = isset($_GET['car_id']) ? (int)$_GET['car_id'] : 0;
if ($car_id <= 0) { echo '<div class="text-danger">car_id ไม่ถูกต้อง</div>'; exit; }

$q = mysqli_query($conn, "SELECT maintenance_date, description, cost, next_dueDate
                          FROM maintenance WHERE car_id={$car_id}
                          ORDER BY maintenance_date DESC, maintenance_id DESC");

if (!$q || mysqli_num_rows($q)===0) {
  echo '<div class="text-muted">ยังไม่มีประวัติ</div>'; exit;
}
echo '<div class="table-responsive"><table class="table table-sm table-striped align-middle">
        <thead><tr>
          <th>วันที่ซ่อม</th><th>รายละเอียด</th><th class="text-end">ค่าใช้จ่าย</th><th>นัดครั้งถัดไป</th>
        </tr></thead><tbody>';
while ($r = mysqli_fetch_assoc($q)) {
  echo '<tr>';
  echo '<td>'.htmlspecialchars(date('d/m/Y', strtotime($r['maintenance_date']))).'</td>';
  echo '<td>'.nl2br(htmlspecialchars($r['description'] ?? '')).'</td>';
  echo '<td class="text-end">'.number_format((float)$r['cost'],2).'</td>';
  echo '<td>'.($r['next_dueDate'] ? htmlspecialchars(date('d/m/Y', strtotime($r['next_dueDate']))) : '-').'</td>';
  echo '</tr>';
}
echo '</tbody></table></div>';
