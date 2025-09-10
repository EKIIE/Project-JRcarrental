<?php
session_start();
require("../db.php");
if (empty($_SESSION['user_type']) || $_SESSION['user_type']!=='admin') { http_response_code(403); exit; }

$rental_id = (int)($_GET['rental_id'] ?? 0);
if ($rental_id<=0) { echo "ไม่พบข้อมูล"; exit; }

$q = mysqli_query($conn, "SELECT * FROM payments WHERE rental_id = $rental_id ORDER BY payment_date ASC");
if (!$q || mysqli_num_rows($q)==0) { echo '<div class="text-muted">— ไม่มีการชำระเงิน —</div>'; exit; }

echo '<div class="table-responsive"><table class="table table-sm align-middle">';
echo '<thead class="table-light"><tr>
        <th>#</th><th>วันที่</th><th>จำนวนเงิน</th>
        <th>ช่องทาง</th><th>สถานะ</th><th>ใบเสร็จ</th>
      </tr></thead><tbody>';
$i=1;
while($r = mysqli_fetch_assoc($q)){
  echo '<tr>';
  echo '<td>'.($i++).'</td>';
  echo '<td>'.date('d/m/Y H:i', strtotime($r['payment_date'])).'</td>';
  echo '<td>'.number_format((float)$r['amount'],2).'</td>';
  echo '<td>'.htmlspecialchars($r['payment_method']).'</td>';
  echo '<td><span class="badge '.($r['status']==='completed'?'bg-success':'bg-secondary').'">'.htmlspecialchars($r['status']).'</span></td>';
  // ถ้ามีไฟล์ใบเสร็จ (invoice_pdf) ให้กดดาวน์โหลดได้
  if (!empty($r['invoice_pdf'])) {
    echo '<td><a target="_blank" class="btn btn-outline-primary btn-sm" href="../uploads/invoices/'.htmlspecialchars($r['invoice_pdf']).'">เปิดใบเสร็จ</a></td>';
  } else {
    echo '<td class="text-muted">—</td>';
  }
  echo '</tr>';
}
echo '</tbody></table></div>';
