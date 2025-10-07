<?php
session_start();
require("../db.php");

if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/* ---------- helpers ---------- */
function ensure_dir($d)
{
    if (!is_dir($d)) mkdir($d, 0775, true);
}
function upload_one($file, $dir, $prefix = 'file_')
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = $prefix . bin2hex(random_bytes(6)) . '.' . $ext;
    ensure_dir($dir);
    return move_uploaded_file($file['tmp_name'], $dir . $name) ? $name : '';
}

/* ---------- create (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    $type     = $_POST['form_type'];
    $car_id   = (int)($_POST['car_id'] ?? 0);

    if ($car_id <= 0) {
        header("Location: car_expenses.php");
        exit();
    }

    if ($type === 'maintenance') {
        $maintenance_date = trim($_POST['maintenance_date'] ?? '');
        $description      = trim($_POST['description'] ?? '');
        $cost             = (float)($_POST['cost'] ?? 0);
        $next_dueDate     = trim($_POST['next_dueDate'] ?? '');

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO maintenance (car_id, maintenance_date, description, cost, next_dueDate)
       VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "issds", $car_id, $maintenance_date, $description, $cost, $next_dueDate);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif ($type === 'insurance') {
        $insu_type = trim($_POST['insu_type'] ?? '');
        $insu_date = trim($_POST['insu_date'] ?? '');
        $monthly   = (float)($_POST['monthly'] ?? 0);
        $company   = trim($_POST['company'] ?? '');
        $url       = trim($_POST['url'] ?? '');
        $note      = trim($_POST['note'] ?? '');
        $receipt   = upload_one($_FILES['receipt'] ?? null, "../uploads/insurances/", "insu_");

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO insurances (car_id, insu_type, insu_date, monthly, company, url, receipt, note)
       VALUES (?,?,?,?,?,?,?,?)"
        );
        mysqli_stmt_bind_param($stmt, "issdssss", $car_id, $insu_type, $insu_date, $monthly, $company, $url, $receipt, $note);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif ($type === 'installment') {
        $inst_date = trim($_POST['inst_date'] ?? '');
        $monthly   = (float)($_POST['monthly'] ?? 0);
        $company   = trim($_POST['company'] ?? '');
        $url       = trim($_POST['url'] ?? '');
        $note      = trim($_POST['note'] ?? '');
        $receipt   = upload_one($_FILES['receipt'] ?? null, "../uploads/installments/", "inst_");

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO installments (car_id, inst_date, monthly, company, url, receipt, note)
       VALUES (?,?,?,?,?,?,?)"
        );
        mysqli_stmt_bind_param($stmt, "isdssss", $car_id, $inst_date, $monthly, $company, $url, $receipt, $note);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    header("Location: car_expenses.php");
    exit();
}

/* ---------- fetch cars ---------- */
$search = trim($_GET['search'] ?? '');
$where = '';
if ($search !== '') {
    $q = mysqli_real_escape_string($conn, $search);
    $where = "WHERE brand LIKE '%{$q}%' OR model LIKE '%{$q}%' OR license_plate LIKE '%{$q}%'";
}
$cars = mysqli_query($conn, "SELECT car_id, brand, model, license_plate, status, image_path FROM cars {$where} ORDER BY car_id DESC");
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <title>Maintenance Hub | JR Car Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Kanit', sans-serif;
            background: #f4f7f9
        }

        .rounded-3 {
            border-radius: 1rem !important
        }

        .shadow-sm {
            box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075) !important
        }

        .page {
            max-width: 1200px;
            margin: auto;
            padding: 20px
        }

        .thumb {
            width: 88px;
            height: 60px;
            object-fit: cover;
            border-radius: .5rem
        }
    </style>
</head>

<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">JR Car Rental</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">หน้าหลัก</a>
                    </li>
                    <!-- ผู้ดูแลระบบ -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            ผู้ดูแลระบบ
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="dashboard.php">แดชบอร์ด</a></li>
                            <li><a class="dropdown-item" href="manage_staff.php">จัดการพนักงาน</a></li>
                            <li><a class="dropdown-item" href="manage_cars.php">จัดการรถ</a></li>
                            <li><a class="dropdown-item" href="manage_bookings.php">จัดการการจอง</a></li>
                            <li><a class="dropdown-item" href="../profile.php">ข้อมูลส่วนตัว</a></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">ออกจากระบบ</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- NAVBAR -->

    <div class="page">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="fw-semibold">ศูนย์รวมค่าใช้จ่าย & บำรุงรักษารถ</h3>
            <form class="d-flex" method="get">
                <input class="form-control" name="search" placeholder="ค้นหา (ยี่ห้อ/รุ่น/ทะเบียน)" value="<?= htmlspecialchars($search) ?>">
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
                                <th>ทะเบียน</th>
                                <th>รูปรถ</th>
                                <th>สถานะ</th>
                                <th class="text-center">บันทึกการเข้าศูนย์</th>
                                <th class="text-center">ค่าประกัน / พ.ร.บ.</th>
                                <th class="text-center">ค่างวดรถ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($cars && mysqli_num_rows($cars) > 0): while ($c = mysqli_fetch_assoc($cars)): ?>
                                    <tr>
                                        <td><?= (int)$c['car_id'] ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($c['license_plate']) ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($c['brand'] . ' ' . $c['model']) ?></small>
                                        </td>
                                        <td><img class="thumb shadow-sm" src="../uploads/cars/<?= htmlspecialchars($c['image_path']) ?>" alt=""></td>
                                        <td>
                                            <?php if ($c['status'] === 'available'): ?>
                                                <span class="badge bg-success">ว่าง</span>
                                            <?php elseif ($c['status'] === 'rented'): ?>
                                                <span class="badge bg-warning text-dark">ถูกเช่า</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">ซ่อมบำรุง</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- เข้าศูนย์ -->
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button class="btn btn-outline-primary btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalMaintenance"
                                                    data-carid="<?= (int)$c['car_id'] ?>"
                                                    data-label="<?= htmlspecialchars($c['license_plate'] . ' | ' . $c['brand'] . ' ' . $c['model']) ?>"
                                                    data-mode="history">
                                                    ประวัติ
                                                </button>
                                                <button class="btn btn-primary btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalMaintenance"
                                                    data-carid="<?= (int)$c['car_id'] ?>"
                                                    data-label="<?= htmlspecialchars($c['license_plate'] . ' | ' . $c['brand'] . ' ' . $c['model']) ?>"
                                                    data-mode="create">
                                                    เพิ่ม
                                                </button>
                                            </div>
                                        </td>


                                        <!-- ประกัน/พ.ร.บ. -->
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button class="btn btn-outline-info btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalInsurance"
                                                    data-carid="<?= (int)$c['car_id'] ?>"
                                                    data-label="<?= htmlspecialchars($c['license_plate'] . ' | ' . $c['brand'] . ' ' . $c['model']) ?>"
                                                    data-mode="history">
                                                    ประวัติ
                                                </button>
                                                <button class="btn btn-info btn-sm text-white"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalInsurance"
                                                    data-carid="<?= (int)$c['car_id'] ?>"
                                                    data-label="<?= htmlspecialchars($c['license_plate'] . ' | ' . $c['brand'] . ' ' . $c['model']) ?>"
                                                    data-mode="create">
                                                    เพิ่ม
                                                </button>
                                            </div>
                                        </td>


                                        <!-- ค่างวด -->
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button class="btn btn-outline-success btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalInstallment"
                                                    data-carid="<?= (int)$c['car_id'] ?>"
                                                    data-label="<?= htmlspecialchars($c['license_plate'] . ' | ' . $c['brand'] . ' ' . $c['model']) ?>"
                                                    data-mode="history">
                                                    ประวัติ
                                                </button>
                                                <button class="btn btn-success btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalInstallment"
                                                    data-carid="<?= (int)$c['car_id'] ?>"
                                                    data-label="<?= htmlspecialchars($c['license_plate'] . ' | ' . $c['brand'] . ' ' . $c['model']) ?>"
                                                    data-mode="create">
                                                    เพิ่ม
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">ไม่พบรถในระบบ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== MODALS ========== -->

    <!-- Maintenance Modal -->
    <div class="modal fade" id="modalMaintenance" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content rounded-3">
                <div class="modal-header">
                    <h5 class="modal-title">เข้าศูนย์ — <span id="m_car_label" class="text-muted"></span></h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <ul class="nav nav-tabs px-3 pt-2" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#m_tab_history" type="button">ประวัติ</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#m_tab_create" type="button">เพิ่มใหม่</button></li>
                </ul>

                <div class="tab-content">
                    <!-- ประวัติ -->
                    <div class="tab-pane fade show active p-3" id="m_tab_history">
                        <div id="m_history_body" class="text-center text-muted py-5">กำลังโหลด...</div>
                    </div>
                    <!-- เพิ่มใหม่ -->
                    <div class="tab-pane fade p-3" id="m_tab_create">
                        <form method="post" class="mt-2">
                            <input type="hidden" name="form_type" value="maintenance">
                            <input type="hidden" name="car_id" id="m_car_id">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">วันที่ซ่อม</label>
                                    <input type="date" name="maintenance_date" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ค่าใช้จ่าย (บาท)</label>
                                    <input type="number" step="0.01" min="0" name="cost" value="0" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">นัดครั้งถัดไป</label>
                                    <input type="date" name="next_dueDate" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">รายละเอียด</label>
                                    <textarea name="description" rows="2" class="form-control" placeholder="เช่น เปลี่ยนน้ำมันเครื่อง/ผ้าเบรค ฯลฯ"></textarea>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">ยกเลิก</button>
                                <button class="btn btn-primary">บันทึก</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>


    <!-- Insurance Modal -->
    <div class="modal fade" id="modalInsurance" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content rounded-3">
                <div class="modal-header">
                    <h5 class="modal-title">ประกัน / พ.ร.บ. — <span id="i_car_label" class="text-muted"></span></h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <!-- tabs -->
                <ul class="nav nav-tabs px-3 pt-2" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#i_tab_history" type="button">ประวัติ</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#i_tab_create" type="button">เพิ่มใหม่</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- HISTORY -->
                    <div class="tab-pane fade show active p-3" id="i_tab_history">
                        <div id="i_history_body" class="text-center text-muted py-5">กำลังโหลด...</div>
                    </div>

                    <!-- CREATE -->
                    <div class="tab-pane fade p-3" id="i_tab_create">
                        <form method="post" enctype="multipart/form-data" class="mt-2">
                            <input type="hidden" name="form_type" value="insurance">
                            <input type="hidden" name="car_id" id="i_car_id">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">ประเภท</label>
                                    <input name="insu_type" class="form-control" placeholder="เช่น ชั้น 1 / พ.ร.บ." required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">วันที่</label>
                                    <input type="date" name="insu_date" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">รายเดือน (บาท)</label>
                                    <input type="number" step="0.01" min="0" name="monthly" value="0" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">บริษัท</label>
                                    <input name="company" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ลิงก์กรมธรรม์ (ถ้ามี)</label>
                                    <input name="url" class="form-control" placeholder="https://">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">แนบหลักฐาน (PDF/รูป)</label>
                                    <input type="file" name="receipt" class="form-control" accept=".pdf,image/*">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">หมายเหตุ</label>
                                    <textarea name="note" rows="2" class="form-control"></textarea>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">ยกเลิก</button>
                                <button class="btn btn-info text-white">บันทึก</button>
                            </div>
                        </form>
                    </div>
                </div><!-- /.tab-content -->
            </div>
        </div>
    </div>

    <!-- Installment Modal -->
    <div class="modal fade" id="modalInstallment" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content rounded-3">
                <div class="modal-header">
                    <h5 class="modal-title">ค่างวดรถ — <span id="p_car_label" class="text-muted"></span></h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <!-- tabs -->
                <ul class="nav nav-tabs px-3 pt-2" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#p_tab_history" type="button">ประวัติ</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#p_tab_create" type="button">เพิ่มใหม่</button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- HISTORY -->
                    <div class="tab-pane fade show active p-3" id="p_tab_history">
                        <div id="p_history_body" class="text-center text-muted py-5">กำลังโหลด...</div>
                    </div>

                    <!-- CREATE -->
                    <div class="tab-pane fade p-3" id="p_tab_create">
                        <form method="post" enctype="multipart/form-data" class="mt-2">
                            <input type="hidden" name="form_type" value="installment">
                            <input type="hidden" name="car_id" id="p_car_id">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">วันที่ชำระ</label>
                                    <input type="date" name="inst_date" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ยอดเดือนนี้ (บาท)</label>
                                    <input type="number" step="0.01" min="0" name="monthly" value="0" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">บริษัทไฟแนนซ์</label>
                                    <input name="company" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ลิงก์ (ถ้ามี)</label>
                                    <input name="url" class="form-control" placeholder="https://">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">แนบหลักฐาน (PDF/รูป)</label>
                                    <input type="file" name="receipt" class="form-control" accept=".pdf,image/*">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">หมายเหตุ</label>
                                    <textarea name="note" rows="2" class="form-control"></textarea>
                                </div>
                            </div>
                            <div class="text-end mt-3">
                                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">ยกเลิก</button>
                                <button class="btn btn-success">บันทึก</button>
                            </div>
                        </form>
                    </div>
                </div><!-- /.tab-content -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            /**
             * รวมงานทั้งหมดเวลาเปิดโมดัล:
             * - ใส่ car_id ลง hidden input และโชว์ label (เช่น “ทะเบียน XX”)
             * - สลับแท็บเริ่มต้น (history/create) จาก data-mode
             * - โหลดประวัติด้วย AJAX เข้า pane ที่กำหนด
             *
             * ปุ่มที่เรียกโมดัลควรมี data-carid, data-label และ (ถ้าต้องการ) data-mode="create" หรือ "history"
             */
            function mountHistoryModal(modalId, {
                labelSpanId,
                hiddenInputId,
                historyPaneId,
                apiUrl
            }) {
                const modal = document.getElementById(modalId);
                if (!modal) return;

                modal.addEventListener('show.bs.modal', (ev) => {
                    const btn = ev.relatedTarget || {};
                    const carId = btn.getAttribute?.('data-carid') || '';
                    const label = btn.getAttribute?.('data-label') || '';
                    const mode = btn.getAttribute?.('data-mode') || 'history';

                    // 1) inject ค่า
                    const hid = document.getElementById(hiddenInputId);
                    if (hid) hid.value = carId;
                    const lab = document.getElementById(labelSpanId);
                    if (lab) lab.textContent = label;

                    // 2) สลับแท็บเริ่มต้น
                    const targetSelector = mode === 'create' ? '[data-bs-target$="_create"]' :
                        '[data-bs-target$="_history"]';
                    const startTabBtn = modal.querySelector(targetSelector);
                    if (startTabBtn) new bootstrap.Tab(startTabBtn).show();

                    // 3) โหลดประวัติ
                    const host = document.getElementById(historyPaneId);
                    if (!host) return;
                    host.innerHTML = '<div class="text-center text-muted py-5">กำลังโหลด...</div>';

                    fetch(`${apiUrl}?car_id=${encodeURIComponent(carId)}`)
                        .then(r => r.text())
                        .then(html => {
                            host.innerHTML = html;
                        })
                        .catch(() => {
                            host.innerHTML = '<div class="text-danger">โหลดข้อมูลไม่สำเร็จ</div>';
                        });
                });
            }

            // ติดตั้งให้ครบทั้ง 3 โมดัล (แก้ path api ให้ตรงโปรเจ็กต์ของปัง)
            mountHistoryModal('modalMaintenance', {
                labelSpanId: 'm_car_label',
                hiddenInputId: 'm_car_id',
                historyPaneId: 'm_history_body',
                apiUrl: '../api/maintenance_history.php'
            });

            mountHistoryModal('modalInsurance', {
                labelSpanId: 'i_car_label',
                hiddenInputId: 'i_car_id',
                historyPaneId: 'i_history_body',
                apiUrl: '../api/insurance_history.php'
            });

            mountHistoryModal('modalInstallment', {
                labelSpanId: 'p_car_label',
                hiddenInputId: 'p_car_id',
                historyPaneId: 'p_history_body',
                apiUrl: '../api/installment_history.php'
            });
        })();
    </script>


</body>

</html>