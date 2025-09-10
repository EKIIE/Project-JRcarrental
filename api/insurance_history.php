<?php
session_start(); require("../../db.php");
header("Content-Type: text/html; charset=utf-8");
if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') { http_response_code(403); exit('Forbidden'); }

$car_id = isset($_GET['car_id']) ? (int)$_GET['car_id'] : 0;
$q = mysqli_query($conn, "SELECT insu_date, insu_type, monthly, company, url, receipt, note
                          FROM insurances WHERE car_id={$car_id}
                          ORDER BY insu_date DESC, insurance_id DESC");

if (!$q || mysqli_num_rows($q)===0) { echo '<div class="text-muted">ยังไม่มีประวัติ</div>'; exit; }

echo '<div class="table-responsive"><table class="table table-sm table-striped align-middle">
        <thead><tr>
          <th>วันที่</th><th>ประเภท</th><th>บริษัท</th><th class="text-end">รายเดือน</th><th>หลักฐาน</th><th>หมายเหตุ</th>
        </tr></thead><tbody>';
while ($r = mysqli_fetch_assoc($q)) {
  $link = $r['url'] ? '<a href="'.htmlspecialchars($r['url']).'" target="_blank">link</a>' : '';
  $rcpt = $r['receipt'] ? '<a href="../uploads/insurances/'.htmlspecialchars($r['receipt']).'" target="_blank">ไฟล์</a>' : '';
  echo '<tr>';
  echo '<td>'.htmlspecialchars(date('d/m/Y', strtotime($r['insu_date']))).'</td>';
  echo '<td>'.htmlspecialchars($r['insu_type']).'</td>';
  echo '<td>'.htmlspecialchars($r['company']).'</td>';
  echo '<td class="text-end">'.number_format((float)$r['monthly'],2).'</td>';
  echo '<td>'.$link.($link&&$rcpt?' | ':'').$rcpt.'</td>';
  echo '<td>'.nl2br(htmlspecialchars($r['note'] ?? '')).'</td>';
  echo '</tr>';
}
echo '</tbody></table></div>';
