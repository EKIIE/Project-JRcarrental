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
    FROM maintenance GROUP BY month ORDER BY maintenance_date
");
$chart_data_maintenance = [];
while ($row = mysqli_fetch_assoc($maintenance_query)) {
    $chart_data_maintenance[] = [$row['month'], (float)$row['total']];
}

// ค่างวด
$installments_query = mysqli_query($conn, "
    SELECT company, SUM(monthly) AS total
    FROM installments GROUP BY company
");
$chart_data_installment = [];
while ($row = mysqli_fetch_assoc($installments_query)) {
    $chart_data_installment[] = [$row['company'], (float)$row['total']];
}

// ประกัน
$insurance_query = mysqli_query($conn, "
    SELECT company, COUNT(*) AS count
    FROM insurances GROUP BY company
");
$chart_data_insurance = [];
while ($row = mysqli_fetch_assoc($insurance_query)) {
    $chart_data_insurance[] = [$row['company'], (int)$row['count']];
}
//----------------------------------------------------------------
// รายรับ: รวมยอดรายได้จากการเช่า
$income_query = mysqli_query($conn, "
    SELECT DATE_FORMAT(actual_pickup_date, '%Y-%m') AS month, SUM(total_amount) AS total
    FROM rentals
    WHERE rental_status IN ('active','completed')
    GROUP BY month
    ORDER BY month
");
$income_data = [];
while ($row = mysqli_fetch_assoc($income_query)) {
    $income_data[$row['month']] = (float)$row['total'];
}

// รายจ่าย: รวมจาก maintenance + installments + insurance
// $expense_data = [];
// 1. Maintenance
$expense_maint = [];
$q_m = mysqli_query($conn, "
    SELECT DATE_FORMAT(maintenance_date, '%Y-%m') AS month, SUM(cost) AS total
    FROM maintenance GROUP BY month
");
while ($row = mysqli_fetch_assoc($q_m)) {
    $expense_data[$row['month']] = ($expense_data[$row['month']] ?? 0) + (float)$row['total'];
}

// 2. Installments
$expense_inst = [];
$q_i = mysqli_query($conn, "
    SELECT DATE_FORMAT(inst_date, '%Y-%m') AS month, SUM(monthly) AS total
    FROM installments GROUP BY month
");
while ($row = mysqli_fetch_assoc($q_i)) {
    $expense_data[$row['month']] = ($expense_data[$row['month']] ?? 0) + (float)$row['total'];
}

// 3. Insurances
$expense_insu = [];
$q_s = mysqli_query($conn, "
    SELECT DATE_FORMAT(insu_date, '%Y-%m') AS month, SUM(monthly) AS total
    FROM insurances GROUP BY month
");
while ($row = mysqli_fetch_assoc($q_s)) {
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

            var options = {
                title: 'สถานะรถในระบบ',
                width: 400,
                height: 300
            };

            // 1. Car Status Chart
            var data1 = google.visualization.arrayToDataTable([
                ['สถานะรถ', 'จำนวน'], ...chart_data_status
            ]);
            var chart1 = new google.visualization.PieChart(document.getElementById('car_status_chart'));
            chart1.draw(data1, {
                title: 'สถานะรถในระบบ'
            });

            // 2. Maintenance Cost Chart
            var data2 = google.visualization.arrayToDataTable([
                ['เดือน', 'ค่าใช้จ่าย'], ...chart_data_maintenance
            ]);
            var chart2 = new google.visualization.ColumnChart(document.getElementById('maintenance_chart'));
            chart2.draw(data2, {
                title: 'ค่าใช้จ่ายซ่อมบำรุงรายเดือน'
            });

            // 3. Installment Chart
            var data3 = google.visualization.arrayToDataTable([
                ['บริษัท', 'ยอดรวมค่างวด'], ...chart_data_installment
            ]);
            var chart3 = new google.visualization.BarChart(document.getElementById('installment_chart'));
            chart3.draw(data3, {
                title: 'ยอดค่างวดต่อบริษัท'
            });

            // 4. Insurance Chart
            var data4 = google.visualization.arrayToDataTable([
                ['บริษัท', 'จำนวนกรมธรรม์'], ...chart_data_insurance
            ]);
            var chart4 = new google.visualization.ColumnChart(document.getElementById('insurance_chart'));
            chart4.draw(data4, {
                title: 'จำนวนรถที่มีประกันแยกตามบริษัท'
            });

            // รายรับ-รายจ่าย รายเดือน
            var data5 = google.visualization.arrayToDataTable([
                ['เดือน', 'รายรับ', 'Maintenance', 'Installments', 'Insurance'],
                ...chart_income_expense
            ]);

            var chart5 = new google.visualization.ColumnChart(document.getElementById('income_expense_chart'));
            chart5.draw(data5, {
                title: 'รายรับ-รายจ่าย รายเดือน',
                hAxis: { title: 'เดือน' },
                vAxis: { title: 'จำนวนเงิน (บาท)' },
                colors: ['#28a745', '#ffc107', '#17a2b8', '#dc3545'], // เขียว=รายรับ, แดง=รายจ่าย
                isStacked: false
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
                            <li><a class="dropdown-item" href="manage_bookings.php">จัดการการจอง</a></li>
                            <li><a class="dropdown-item" href="../profile.php">แก้ไขข้อมูลส่วนตัว</a></li>
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
                        <!-- สถานะรถ -->
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-3">📊 สถานะรถในระบบ</h5>
                            <div id="car_status_chart"></div>
                        </div>

                        <!-- ค่าใช้จ่ายซ่อมบำรุง -->
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-3">🛠 ค่าใช้จ่ายซ่อมบำรุงรายเดือน</h5>
                            <div id="maintenance_chart"></div>
                        </div>

                        <!-- ค่างวด -->
                        <div class="col-md-6 mt-5">
                            <h5 class="fw-bold mb-3">📄 ค่างวดรถ (Installments)</h5>
                            <div id="installment_chart"></div>
                        </div>

                        <!-- ประกันรถ -->
                        <div class="col-md-6 mt-5">
                            <h5 class="fw-bold mb-3">🛡 บริษัทประกันที่ใช้งาน</h5>
                            <div id="insurance_chart"></div>
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
                    <a href="manage_booking.php" class="list-group-item list-group-item-action py-3 rounded-3 mb-2 shadow-sm">
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