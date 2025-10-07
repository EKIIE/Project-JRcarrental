<?php
require("../db.php");
session_start();

if ($_SESSION['user_type'] !== 'admin') {
    echo "Access Denied";
    exit();
}

// ดึงข้อมูลสรุป
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users"))['total'];
$total_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM bookings WHERE booking_status = 'approved'"))['total'];
$total_pending_requests = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM bookings WHERE booking_status = 'pending'"))['total'];
$total_cars = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM cars"))['total'];
$available_cars = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM cars WHERE status = 'available'"))['total'];
$car_statuses = mysqli_query($conn, "
    SELECT status, COUNT(*) AS count 
    FROM cars 
    GROUP BY status
");

$chart_data = [];
while ($row = mysqli_fetch_assoc($car_statuses)) {
    $chart_data[] = [$row['status'], (int)$row['count']];
}

// สถานะรถ
$car_statuses = mysqli_query($conn, "SELECT status, COUNT(*) AS count FROM cars GROUP BY status");
$chart_data_status = [];
while ($row = mysqli_fetch_assoc($car_statuses)) {
    $chart_data_status[] = [$row['status'], (int)$row['count']];
}

// ซ่อมบำรุงแยกตามเดือน
$maintenance_query = mysqli_query($conn, "
    SELECT DATE_FORMAT(maintenance_date, '%M %Y') AS month, SUM(cost) AS total
    FROM maintenance 
    GROUP BY month 
    ORDER BY month
");
$chart_data_maintenance = [];
while ($row = mysqli_fetch_assoc($maintenance_query)) {
    $chart_data_maintenance[] = [$row['month'], (float)$row['total']];
}

// ค่างวด
$installments_query = mysqli_query($conn, "
    SELECT DATE_FORMAT(inst_date, '%Y-%m') AS month, SUM(monthly) AS total
    FROM installments 
    GROUP BY month 
    ORDER BY month
");
$chart_data_installment = [];
while ($row = mysqli_fetch_assoc($installments_query)) {
    $chart_data_installment[] = [$row['month'], (float)$row['total']];
}

// ประกัน
$insurance_query = mysqli_query($conn, "
    SELECT DATE_FORMAT(insu_date, '%Y-%m') AS month, SUM(monthly) AS total
    FROM insurances 
    GROUP BY month 
    ORDER BY month
");
$chart_data_insurance = [];
while ($row = mysqli_fetch_assoc($insurance_query)) {
    $chart_data_insurance[] = [$row['month'], (int)$row['total']];
}
//----------------------------------------------------------------
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : null;

// รายรับ: รวมยอดรายได้จากการเช่า
$income_sql = "
    SELECT DATE_FORMAT(actual_pickup_date, '%Y-%m') AS month, SUM(total_amount) AS total
    FROM rentals
    WHERE rental_status IN ('active','completed')
";

// เพิ่มเงื่อนไขช่วงวันที่
if ($start && $end) {
    $income_sql .= " AND DATE(actual_pickup_date) BETWEEN '$start' AND '$end'";
} elseif ($start) {
    $income_sql .= " AND DATE(actual_pickup_date) >= '$start'";
} elseif ($end) {
    $income_sql .= " AND DATE(actual_pickup_date) <= '$end'";
}

$income_sql .= " GROUP BY month ORDER BY month";

$income_query = mysqli_query($conn, $income_sql);
$income_data = [];
while ($row = mysqli_fetch_assoc($income_query)) {
    $income_data[$row['month']] = (float)$row['total'];
}

// รายจ่าย: รวมจาก maintenance + installments + insurance
$expense_data = [];
$expense_maint = [];
$expense_inst = [];
$expense_insu = [];

// 1. Maintenance
$q_m_sql = "
    SELECT DATE_FORMAT(maintenance_date, '%Y-%m') AS month, SUM(cost) AS total
    FROM maintenance WHERE 1
";
if ($start && $end) {
    $q_m_sql .= " AND DATE(maintenance_date) BETWEEN '$start' AND '$end'";
} elseif ($start) {
    $q_m_sql .= " AND DATE(maintenance_date) >= '$start'";
} elseif ($end) {
    $q_m_sql .= " AND DATE(maintenance_date) <= '$end'";
}
$q_m_sql .= " GROUP BY month ORDER BY month";
$q_m = mysqli_query($conn, $q_m_sql);
while ($row = mysqli_fetch_assoc($q_m)) {
    $expense_maint[$row['month']] = (float)$row['total'];
    $expense_data[$row['month']] = ($expense_data[$row['month']] ?? 0) + (float)$row['total'];
}

// 2. Installments
$q_i_sql = "
    SELECT DATE_FORMAT(inst_date, '%Y-%m') AS month, SUM(monthly) AS total
    FROM installments WHERE 1
";
if ($start && $end) {
    $q_i_sql .= " AND DATE(inst_date) BETWEEN '$start' AND '$end'";
} elseif ($start) {
    $q_i_sql .= " AND DATE(inst_date) >= '$start'";
} elseif ($end) {
    $q_i_sql .= " AND DATE(inst_date) <= '$end'";
}
$q_i_sql .= " GROUP BY month ORDER BY month";
$q_i = mysqli_query($conn, $q_i_sql);
while ($row = mysqli_fetch_assoc($q_i)) {
    $expense_inst[$row['month']] = (float)$row['total'];
    $expense_data[$row['month']] = ($expense_data[$row['month']] ?? 0) + (float)$row['total'];
}

// 3. Insurances
$q_s_sql = "
    SELECT DATE_FORMAT(insu_date, '%Y-%m') AS month, SUM(monthly) AS total
    FROM insurances WHERE 1
";
if ($start && $end) {
    $q_s_sql .= " AND DATE(insu_date) BETWEEN '$start' AND '$end'";
} elseif ($start) {
    $q_s_sql .= " AND DATE(insu_date) >= '$start'";
} elseif ($end) {
    $q_s_sql .= " AND DATE(insu_date) <= '$end'";
}
$q_s_sql .= " GROUP BY month ORDER BY month";
$q_s = mysqli_query($conn, $q_s_sql);
while ($row = mysqli_fetch_assoc($q_s)) {
    $expense_insu[$row['month']] = (float)$row['total'];
    $expense_data[$row['month']] = ($expense_data[$row['month']] ?? 0) + (float)$row['total'];
}

// รวมเดือนทั้งหมด (รายรับ + รายจ่าย)
$allMonths = array_unique(array_merge(
    array_keys($income_data),
    array_keys($expense_maint),
    array_keys($expense_inst),
    array_keys($expense_insu)
));
sort($allMonths);

// เตรียม array สำหรับ JS
$chart_income_expense = [];
foreach ($allMonths as $m) {
    $chart_income_expense[] = [
        $m,
        $income_data[$m] ?? 0,
        $expense_maint[$m] ?? 0,
        $expense_inst[$m] ?? 0,
        $expense_insu[$m] ?? 0
    ];
}

// 1. รถทั้งหมดแยกตามประเภท
$car_type_query = mysqli_query($conn, "
    SELECT t.type_name AS car_type, COUNT(c.car_id) AS total
    FROM cars c
    JOIN car_types t ON c.type_id = t.type_id
    GROUP BY t.type_name
    ORDER BY total DESC
");
$chart_car_type = [];
while ($r = mysqli_fetch_assoc($car_type_query)) {
    $chart_car_type[] = [$r['car_type'], (int)$r['total']];
}

// 2. ค่าใช้จ่ายรายเดือน (รวม maintenance + installment + insurance)
$expense_month_query = mysqli_query($conn, "
    SELECT month,
           COALESCE(SUM(maint), 0) AS maint,
           COALESCE(SUM(inst), 0) AS inst,
           COALESCE(SUM(insu), 0) AS insu
    FROM (
        SELECT DATE_FORMAT(maintenance_date, '%Y-%m') AS month,
               SUM(cost) AS maint,
               0 AS inst,
               0 AS insu
        FROM maintenance
        GROUP BY DATE_FORMAT(maintenance_date, '%Y-%m')
        UNION ALL
        SELECT DATE_FORMAT(inst_date, '%Y-%m') AS month,
               0 AS maint,
               SUM(monthly) AS inst,
               0 AS insu
        FROM installments
        GROUP BY DATE_FORMAT(inst_date, '%Y-%m')
        UNION ALL
        SELECT DATE_FORMAT(insu_date, '%Y-%m') AS month,
               0 AS maint,
               0 AS inst,
               SUM(monthly) AS insu
        FROM insurances
        GROUP BY DATE_FORMAT(insu_date, '%Y-%m')
    ) AS all_expenses
    GROUP BY month
    ORDER BY month
");
$chart_expense_month = [];
while ($r = mysqli_fetch_assoc($expense_month_query)) {
    $chart_expense_month[] = [
        $r['month'],
        (float)$r['maint'],
        (float)($r['inst'] ?? 0),
        (float)($r['insu'] ?? 0)
    ];
}

// 3. ยอดการเช่าเทียบรายเดือน
$rental_month_query = mysqli_query($conn, "
    SELECT DATE_FORMAT(actual_pickup_date, '%Y-%m') AS month, COUNT(*) AS total
    FROM rentals
    WHERE rental_status IN ('active','completed')
    GROUP BY month
    ORDER BY month
");
$chart_rental_month = [];
while ($r = mysqli_fetch_assoc($rental_month_query)) {
    $chart_rental_month[] = [$r['month'], (int)$r['total']];
}

// 4. ยอดการเช่ารายคัน
$rental_per_car = mysqli_query($conn, "
    SELECT c.license_plate, COUNT(r.rental_id) AS total
    FROM rentals r
    JOIN cars c ON r.car_id = c.car_id
    GROUP BY c.license_plate
    ORDER BY total DESC
");
$chart_rental_per_car = [];
while ($r = mysqli_fetch_assoc($rental_per_car)) {
    $chart_rental_per_car[] = [$r['license_plate'], (int)$r['total']];
}

?>


<!DOCTYPE html>
<html lang="th">

<head>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/main.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // ปฏิทินการจอง/เช่า
            var rentCalEl = document.getElementById('calendar_rent');
            if (rentCalEl) {
                var rentCalendar = new FullCalendar.Calendar(rentCalEl, {
                    // plugins: [dayGridPlugin],
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,dayGridWeek'
                    },
                    events: {
                        url: '../api/calendar_rent.php', // API endpoint ที่เราจะสร้าง
                        method: 'GET',
                        failure: function() {
                            console.error('ไม่สามารถโหลด event ได้');
                        }
                    },
                    eventsTimeFormat: { // like '14:30'
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
                    },
                    eventClick: function(info) {
                        info.jsEvent.preventDefault();

                        // clear modal ก่อน
                        document.getElementById('eventTitle').textContent = 'กำลังโหลด...';
                        document.getElementById('eventStart').textContent = '-';
                        document.getElementById('eventEnd').textContent = '-';
                        document.getElementById('eventDesc').textContent = '-';

                        var modal = new bootstrap.Modal(document.getElementById('eventModal'));
                        modal.show();

                        // ยิง ajax ไป API
                        fetch('../api/event_detail.php?id=' + info.event.id)
                            .then(res => res.json())
                            .then(data => {
                                if (data.error) {
                                    document.getElementById('eventTitle').textContent = 'Error';
                                    document.getElementById('eventDesc').textContent = data.error;
                                    return;
                                }
                                // เติมข้อมูล
                                document.getElementById('eventTitle').textContent = data.title || info.event.title;
                                document.getElementById('eventStart').textContent = data.start_date || info.event.startStr;
                                document.getElementById('eventEnd').textContent = data.end_date || info.event.endStr || '-';
                                document.getElementById('eventDesc').textContent = data.description || data.note || '—';
                            })
                            .catch(err => {
                                document.getElementById('eventTitle').textContent = 'Error';
                                document.getElementById('eventDesc').textContent = err;
                            });
                    }

                });
                rentCalendar.render();
                document.querySelector('#rent-tab').addEventListener('shown.bs.tab', function() {
                    rentCalendar.render();
                });
            }
            // ปฏิทินบำรุง/ค่างวด/ประกัน
            var maintCalEl = document.getElementById('calendar_maint');
            if (maintCalEl) {
                var maintCalendar = new FullCalendar.Calendar(maintCalEl, {
                    // plugins: [dayGridPlugin],
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,dayGridWeek'
                    },
                    events: {
                        url: '../api/calendar_maint.php', // API endpoint ที่เราจะสร้าง
                        method: 'GET',
                        failure: function() {
                            console.error('ไม่สามารถโหลด event ได้');
                        }
                    },
                    eventsTimeFormat: { // like '14:30'
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: false
                    },
                    eventClick: function(info) {
                        info.jsEvent.preventDefault();

                        // clear modal ก่อน
                        document.getElementById('eventTitle').textContent = 'กำลังโหลด...';
                        document.getElementById('eventStart').textContent = '-';
                        document.getElementById('eventEnd').textContent = '-';
                        document.getElementById('eventDesc').textContent = '-';

                        var modal = new bootstrap.Modal(document.getElementById('eventModal'));
                        modal.show();

                        // ยิง ajax ไป API
                        fetch('../api/event_detail.php?id=' + info.event.id)
                            .then(res => res.json())
                            .then(data => {
                                if (data.error) {
                                    document.getElementById('eventTitle').textContent = 'Error';
                                    document.getElementById('eventDesc').textContent = data.error;
                                    return;
                                }
                                // เติมข้อมูล
                                document.getElementById('eventTitle').textContent = data.title || info.event.title;
                                document.getElementById('eventStart').textContent = data.start_date || info.event.startStr;
                                document.getElementById('eventEnd').textContent = data.end_date || info.event.endStr || '-';
                                document.getElementById('eventDesc').textContent = data.description || data.note || '—';
                            })
                            .catch(err => {
                                document.getElementById('eventTitle').textContent = 'Error';
                                document.getElementById('eventDesc').textContent = err;
                            });
                    }
                });
                maintCalendar.render();
                document.querySelector('#maint-tab').addEventListener('shown.bs.tab', function() {
                    maintCalendar.render();
                });
            }

        });
    </script>
    <!--Load the AJAX API-->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        // Load the Visualization API and the corechart package.
        google.charts.load('current', {
            packages: ['corechart']
        });

        // Set a callback to run when the Google Visualization API is loaded.
        google.charts.setOnLoadCallback(drawChart);
        const chartDataFromPHP = <?php echo json_encode($chart_data); ?>;
        const chart_data_status = <?= json_encode($chart_data_status) ?>;
        const chart_data_maintenance = <?= json_encode($chart_data_maintenance) ?>;
        const chart_data_installment = <?= json_encode($chart_data_installment) ?>;
        const chart_data_insurance = <?= json_encode($chart_data_insurance) ?>;
        const chart_income_expense = <?= json_encode($chart_income_expense) ?>;
        console.log(chartDataFromPHP);

        // Callback that creates and populates a data table,
        // instantiates the pie chart, passes in the data and
        // draws it.
        function drawChart() {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'สถานะรถ');
            data.addColumn('number', 'จำนวน');

            data.addRows(chartDataFromPHP);

            // รายรับ-รายจ่าย รายเดือน
            var data5 = google.visualization.arrayToDataTable([
                ['เดือน', 'รายรับ', 'Maintenance', 'Installments', 'Insurance'],
                ...chart_income_expense
            ]);

            var chart5 = new google.visualization.ColumnChart(document.getElementById('income_expense_chart'));
            chart5.draw(data5, {
                title: 'รายรับ-รายจ่าย รายเดือน',
                hAxis: {
                    title: 'เดือน'
                },
                vAxis: {
                    title: 'จำนวนเงิน (บาท)'
                },
                colors: ['#28a745', '#ffc107', '#17a2b8', '#dc3545'], // เขียว=รายรับ, แดง=รายจ่าย
                isStacked: false
            });

            // --- รถทั้งหมดแยกตามประเภท ---
            var data6 = google.visualization.arrayToDataTable([
                ['ประเภท', 'จำนวน'], ...<?= json_encode($chart_car_type) ?>
            ]);
            var chart6 = new google.visualization.PieChart(document.getElementById('car_type_chart'));
            chart6.draw(data6, {
                title: 'รถทั้งหมดแยกตามประเภท',
                pieHole: 0.4
            });

            // --- ค่าใช้จ่ายรายเดือน (3 แท่ง) ---
            var data7 = google.visualization.arrayToDataTable([
                ['เดือน', 'Maintenance', 'Installment', 'Insurance'],
                ...<?= json_encode($chart_expense_month) ?>
            ]);
            var chart7 = new google.visualization.ColumnChart(document.getElementById('expense_month_chart'));
            chart7.draw(data7, {
                title: 'ค่าใช้จ่ายรายเดือนแยกตามประเภท',
                colors: ['#ffc107', '#17a2b8', '#dc3545']
            });

            // --- ยอดการเช่าเทียบรายเดือน ---
            var data8 = google.visualization.arrayToDataTable([
                ['เดือน', 'จำนวนการเช่า'],
                ...<?= json_encode($chart_rental_month) ?>
            ]);
            var chart8 = new google.visualization.LineChart(document.getElementById('rental_month_chart'));
            chart8.draw(data8, {
                title: 'ยอดการเช่าเทียบรายเดือน',
                curveType: 'function',
                legend: {
                    position: 'bottom'
                },
                colors: ['#28a745']
            });

            // --- ยอดการเช่ารายคัน ---
            var data9 = google.visualization.arrayToDataTable([
                ['ป้ายทะเบียน', 'จำนวนการเช่า'],
                ...<?= json_encode($chart_rental_per_car) ?>
            ]);
            var chart9 = new google.visualization.BarChart(document.getElementById('rental_per_car_chart'));
            chart9.draw(data9, {
                title: 'ยอดการเช่ารายคัน',
                colors: ['#007bff']
            });

        }
    </script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap');

        body {
            font-family: 'Kanit', sans-serif;
            background-color: #f4f7f9;
        }

        .navbar-brand {
            font-weight: 600;
        }

        .card-icon {
            font-size: 3rem;
            color: #fff;
        }

        .card-title {
            font-weight: 500;
            color: #555;
        }

        .card-value {
            font-weight: 600;
            font-size: 2.5rem;
        }

        .rounded-3 {
            border-radius: 1rem !important;
        }

        .p-4 {
            padding: 1.5rem !important;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }

        .dashboard-title {
            font-weight: 600;
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .shadow-sm {
            box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075) !important;
        }

        #calendar_rent,
        #calendar_maint {
            max-width: 100%;
            height: 600px;
            margin: 20px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 10px;
            /* <<< สำคัญ ไม่งั้นไม่แสดง */
        }
    </style>
</head>

<body>

    <!-- NAVBAR +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ -->
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
                            <li><a class="dropdown-item" href="../staff/staff_dashboard.php">จัดการการจอง</a></li>
                            <li><a class="dropdown-item" href="../profile.php">ข้อมูลส่วนตัว</a></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">ออกจากระบบ</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- MAIN CONTENT +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ -->
    <div class="dashboard-container">
        <h1 class="dashboard-title text-center mt-5 mb-4">แดชบอร์ดผู้ดูแลระบบ</h1>

        <div>
            <!-- NAV-TABS -->
            <ul class="nav nav-tabs mb-3" id="myTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="tab1-tab" data-bs-toggle="tab" data-bs-target="#tab1" type="button">📈 รายรับ-รายจ่าย</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="rent-tab" data-bs-toggle="tab" data-bs-target="#rent-calendar" type="button">📅 การจอง</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="maint-tab" data-bs-toggle="tab" data-bs-target="#maint-calendar" type="button">🛠 บำรุงรักษา</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tab3-tab" data-bs-toggle="tab" data-bs-target="#tab3" type="button">📊 รายงานอื่นๆ</button>
                </li>
            </ul>
            <div class="tab-content" id="myTabsContent">
                <!-- รายรับ/รายจ่าย -->
                <div class="tab-pane fade show active" id="tab1" role="tabpanel">
                    <h5 class="fw-bold mb-3">📈 รายรับ-รายจ่าย รายเดือน</h5>
                    <!-- ตัวกรองวันที่ -->
                    <form method="get" class="d-flex align-items-end gap-3 mb-3">
                        <div>
                            <label for="start" class="form-label mb-1">เริ่มวันที่</label>
                            <input type="date" id="start" name="start" class="form-control"
                                value="<?= htmlspecialchars($_GET['start'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="end" class="form-label mb-1">ถึงวันที่</label>
                            <input type="date" id="end" name="end" class="form-control"
                                value="<?= htmlspecialchars($_GET['end'] ?? '') ?>">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">แสดง</button>
                            <a href="dashboard.php" class="btn btn-secondary">รีเซ็ต</a>
                        </div>
                    </form>
                    <div id="income_expense_chart" style="width:100%; height:500px;"></div>
                </div>

                <!-- ปฏิทิน -->
                <!-- ปฏิทินการจอง/เช่า -->
                <div class="tab-pane fade" id="rent-calendar" role="tabpanel">
                    <div id="calendar_rent"></div>
                </div>
                <!-- ปฏิทินบำรุง/ค่างวด/ประกัน -->
                <div class="tab-pane fade" id="maint-calendar" role="tabpanel">
                    <div id="calendar_maint"></div>
                </div>

                <!-- รายงานอื่นๆ -->
                <div class="tab-pane fade" id="tab3" role="tabpanel">
                    <div class="row">
                        <!-- รถทั้งหมดแยกตามประเภท -->
                        <div class="col-md-6 mt-5">
                            <h5 class="fw-bold mb-3">🚘 รถทั้งหมด</h5>
                            <div id="car_type_chart" style="height: 400px;"></div>
                        </div>

                        <!-- ค่าใช้จ่ายรายเดือน -->
                        <div class="col-md-6 mt-5">
                            <h5 class="fw-bold mb-3">💸 ค่าใช้จ่ายรายเดือน</h5>
                            <div id="expense_month_chart" style="height: 400px;"></div>
                        </div>

                        <!-- ยอดการเช่าเทียบรายเดือน -->
                        <div class="col-md-6 mt-5">
                            <h5 class="fw-bold mb-3">📅 ยอดการเช่ารายเดือน</h5>
                            <div id="rental_month_chart" style="height: 400px;"></div>
                        </div>

                        <!-- ยอดการเช่ารายคัน -->
                        <div class="col-md-6 mt-5">
                            <h5 class="fw-bold mb-3">🏷️ ยอดการเช่ารายคัน</h5>
                            <div id="rental_per_car_chart" style="height: 400px;"></div>
                        </div>

                    </div>
                </div>

            </div>
        </div>


        <div class="row g-4">
            <!-- ซ้าย: เมนูจัดการ -->
            <div class="col-12 col-lg-6">
                <h3 class="fw-bold mb-3">Manage </h3>
                <div class="list-group">
                    <a href="../staff/staff_dashboard.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
                        <h5 class="mb-1 fw-bold">จัดการรายการจอง</h5>
                        <p class="mb-1">ดูรายการจองทั้งหมด, อนุมัติ, หรือปฏิเสธคำขอจอง</p>
                    </a>
                    <a href="manage_staff.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
                        <h5 class="mb-1 fw-bold">จัดการพนักงาน</h5>
                        <p class="mb-1">เพิ่มหรือแก้ไขข้อมูลของพนักงาน</p>
                    </a>
                    <a href="manage_customer.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
                        <h5 class="mb-1 fw-bold">ข้อมูลลูกค้า</h5>
                        <p class="mb-1">ข้อมูลลูกค้า</p>
                    </a>
                </div>
            </div>

            <!-- ขวา: Maintenance / ค่าใช้จ่ายรถ -->
            <div class="col-12 col-lg-6">
                <h3 class="fw-bold mb-3">Maintenance </h3>
                <div class="list-group">
                    <a href="manage_cars.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
                        <h5 class="mb-1 fw-bold">จัดการรถยนต์</h5>
                        <p class="mb-1">เพิ่ม, แก้ไข, หรือลบข้อมูลรถยนต์ในระบบ</p>
                    </a>
                    <a href="car_expenses.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
                        <h5 class="mb-1 fw-bold">บันทึกเกี่ยวกับรถ</h5>
                        <p class="mb-1">ประวัติซ่อมบำรุง ค่าใช้จ่าย และกำหนดนัดรอบถัดไป</p>
                    </a>
                    <a href="rental_overview.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
                        <h5 class="mb-1 fw-bold">ข้อมูลการเช่า</h5>
                        <p class="mb-1">ประวัติการเช่ารถ ข้อมูลเช็คสถาพใบเสร็จและสลิป</p>
                    </a>
                </div>
            </div>
        </div>

        <!-- Modal แสดงรายละเอียด Event -->
        <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content rounded-3 shadow">
                    <div class="modal-header">
                        <h5 class="modal-title">รายละเอียดกิจกรรม</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-bordered mb-0">
                            <tr>
                                <th>หัวข้อ</th>
                                <td id="eventTitle"></td>
                            </tr>
                            <tr>
                                <th>เริ่มต้น</th>
                                <td id="eventStart"></td>
                            </tr>
                            <tr>
                                <th>สิ้นสุด</th>
                                <td id="eventEnd"></td>
                            </tr>
                            <tr>
                                <th>รายละเอียด</th>
                                <td id="eventDesc"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    </div>
                </div>
            </div>
        </div>


        <div class="mt-5 text-center">
            <a href="../auth/logout.php" class="btn btn-danger btn-lg rounded-pill px-5 shadow">ออกจากระบบ</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.19/index.global.min.js"></script>

</body>

</html>