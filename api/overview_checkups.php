<?php
session_start();
require("../db.php");
if (empty($_SESSION['user_type']) || $_SESSION['user_type']!=='admin') { http_response_code(403); exit; }

$rental_id = (int)($_GET['rental_id'] ?? 0);
if ($rental_id<=0) { echo "ไม่พบข้อมูล"; exit; }

$q = mysqli_query($conn, "SELECT c.*, e.firstname, e.lastname
                          FROM checkups c 
                          LEFT JOIN employees e ON c.employee_id = e.employee_id
                          WHERE c.rental_id = $rental_id
                          ORDER BY c.checkup_date ASC");
if (!$q || mysqli_num_rows($q)==0) { echo '<div class="text-muted">— ไม่มีบันทึก —</div>'; exit; }

echo '<div class="table-responsive"><table class="table table-sm align-middle">';
echo '<thead class="table-light"><tr>
        <th>#</th><th>ประเภท</th><th>วันที่</th>
        <th>รายละเอียด</th><th>ค่าปรับ</th><th>ผู้ตรวจ</th>
      </tr></thead><tbody>';
$i=1;
while($r = mysqli_fetch_assoc($q)){
  echo '<tr>';
  echo '<td>'.($i++).'</td>';
  echo '<td>'.htmlspecialchars($r['checkup_type']).'</td>';
  echo '<td>'.date('d/m/Y H:i', strtotime($r['checkup_date'])).'</td>';
  $issues=[];
  if($r['fuel_notfull'])   $issues[]='น้ำมันไม่เต็ม';
  if($r['tire_damaged'])   $issues[]='ยางเสียหาย';
  if($r['key_lost'])       $issues[]='กุญแจหาย';
  if($r['smell_detected']) $issues[]='มีกลิ่น/ขนสัตว์';
  if($r['late_return'])    $issues[]='คืนช้า';
  $detail = (!empty($issues)?('• '.implode(', ', $issues).'<br>'):'').nl2br(htmlspecialchars($r['damage_notes']));
  echo '<td>'.$detail.'</td>';
  echo '<td>'.number_format((float)$r['penalty_fee'],2).'</td>';
  $who = trim(($r['firstname']??'').' '.($r['lastname']??''));
  echo '<td>'.($who?:'-').'</td>';
  echo '</tr>';
}
echo '</tbody></table></div>';
