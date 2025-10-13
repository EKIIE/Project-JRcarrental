<?php
session_start();
// ไม่ต้อง autoload ถ้าไม่ได้ใช้ lib
date_default_timezone_set('Asia/Bangkok');

// --- รับค่าจากฟอร์ม (cast + trim) ---
$car_id   = isset($_POST['car_id'])   ? (int)$_POST['car_id'] : 0;
$rate     = isset($_POST['rate'])     ? (float)$_POST['rate'] : 0;
// $deposit  = isset($_POST['deposit'])  ? (float)$_POST['deposit'] : 0;
$start    = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
$end      = isset($_POST['end_date'])   ? trim($_POST['end_date'])   : '';
$pickup   = isset($_POST['pickup_time']) ? trim($_POST['pickup_time']) : '';
$location = isset($_POST['location'])   ? trim($_POST['location'])   : '';
$note     = isset($_POST['note'])       ? trim($_POST['note'])       : '';

// --- ตรวจวันที่ + คำนวณวันเช่า (อย่างน้อย 1 วัน) ---
try {
    $d1 = new DateTime($start);
    $d2 = new DateTime($end);
} catch (Exception $e) {
    die('รูปแบบวันที่ไม่ถูกต้อง');
}
$diffDays = (int)$d1->diff($d2)->days;
$days = max(1, $diffDays);

// --- ยอดที่ต้องจ่ายตอนนี้ = มัดจำเท่านั้น (ห้ามใช้ค่าจาก POST) ---
$rent_total = $rate * $days; // ค่าเช่ารวม
$bd_paynow = 0.20 * $rent_total; // มัดจำ 20%
$pay_now = (float)$deposit;

// --- กันยอดเป็น 0 ---
if ($bd_paynow <= 0) {
    die('จำนวนเงินมัดจำต้องมากกว่า 0');
}

// --- เก็บลง session สำหรับ confirm_payment ---
$_SESSION['temp_booking'] = [
    'car_id'      => $car_id,
    'rate'        => $rate,
    'deposit'     => $deposit,
    'start_date'  => $d1->format('Y-m-d'),
    'end_date'    => $d2->format('Y-m-d'),
    'pickup_time' => $pickup,
    'location'    => $location,
    'note'        => $note,
    'days'        => $days,
    'total_price' => $bd_paynow,           // ชำระตอนนี้ (มัดจำ)
    // 'rent_total'  => $rate * $days       // ค่าเช่ารวม (ชำระตอนคืนรถ)
    'rent_total'  => $rent_total     // ค่าเช่ารวม (ชำระตอนคืนรถ)
];

// --- ตั้งหมดอายุ 10 นาที ---
$_SESSION['expires_at'] = time() + 600;
$server_now = time();
$expires_at = $_SESSION['expires_at'];
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>ชำระเงิน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap');

        body {
            font-family: 'Kanit', sans-serif;
            background-color: #fdfaf6;
            color: #3a2c2c;
            margin: 0;
            padding: 0;
        }

        h2 {
            font-weight: 600;
            color: #3a2c2c;
        }

        p {
            color: #5c4b3a;
        }

        /* QR Section */
        .text-center {
            max-width: 600px;
            margin: auto;
            background: #fffaf4;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        img {
            border-radius: 0.75rem;
            border: 1px solid #f1e3d3;
        }

        /* Timer */
        #timer {
            font-size: 1.25rem;
            font-weight: 600;
            color: #b35d2e;
        }

        /* Form */
        form {
            background-color: #fff;
            border-radius: 1rem;
            box-shadow: 0 1px 6px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
        }

        .form-control {
            border-radius: 0.5rem;
            border-color: #e5d7c8;
        }

        .form-control:focus {
            border-color: #c7a987;
            box-shadow: 0 0 0 0.15rem rgba(212, 180, 153, 0.3);
        }

        /* Buttons */
        .btn-success {
            background-color: #9c7b5b;
            border: none;
            border-radius: 0.6rem;
            font-weight: 500;
        }

        .btn-success:hover {
            background-color: #7e634a;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .text-center {
                padding: 1.25rem;
            }

            h2 {
                font-size: 1.3rem;
            }

            img {
                width: 240px !important;
                height: auto !important;
            }
        }
    </style>

</head>

<body class="text-center py-5">
    <h2>สแกนเพื่อชำระเงิน</h2>
    <div class="mb-2">เวลาที่เหลือ: <span id="timer">10:00</span></div>
    
    <img src="generate_qr.php" width="300" class="mb-3" alt="PromptPay QR">
    
    <p><strong><?= number_format($bd_paynow, 2) ?> บาท</strong></p>
    <input type="hidden" id="amount" value="<?= $rent_total ?>">
    <input type="hidden" id="pickup_time" value="<?= htmlspecialchars($pickup) ?>">

    <!-- SLIP -->
    <form method="post" action="confirm_payment.php" class="mt-3 container" style="max-width:520px" enctype="multipart/form-data">
        <div class="mb-3 text-start">
            <label for="slip" class="form-label">แนบสลิปการโอน</label>
            <input type="file" name="slip" id="slip" class="form-control" accept="image/*" required>
            <!-- <div class="mt-2">
                <img id="slipPreview" src="" alt="" style="max-width:100%; display:none; border-radius:8px;">
            </div> -->
        </div>
        <button type="submit" id="confirmBtn" class="btn btn-success w-100">ยืนยัน</button>
    </form>
    <!-- SLIP -->

    <script>
        const serverNow = <?= $server_now ?> * 1000;
        const expiresAt = <?= $expires_at ?> * 1000;
        const bookingPage = "../booking.php";

        let skew = Date.now() - serverNow;
        const timerEl = document.getElementById('timer');
        const btn = document.getElementById('confirmBtn');
        const slipInput = document.getElementById('slip');

        function tick() {
            const now = Date.now() - skew;
            let msLeft = expiresAt - now;
            if (msLeft <= 0) {
                timerEl.textContent = "หมดเวลา";
                btn.disabled = true;
                slipInput.disabled = true;
                setTimeout(() => {
                    window.location.href = bookingPage + "?expired=1";
                }, 1000);
                return;
            }
            const s = Math.floor(msLeft / 1000);
            const mm = String(Math.floor(s / 60)).padStart(2, '0');
            const ss = String(s % 60).padStart(2, '0');
            timerEl.textContent = `${mm}:${ss}`;
            setTimeout(tick, 250);
        }
        tick();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // กันลืมแนบสลิป: ถ้าไม่เลือกไฟล์ให้เตือนด้วย SweetAlert2
        const form = document.querySelector('form[action="confirm_payment.php"]');
        form.addEventListener('submit', function(e) {
            if (!slipInput.files.length) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณาแนบสลิป',
                    text: 'ต้องแนบสลิปการโอนก่อนกดยืนยัน',
                    confirmButtonText: 'ตกลง'
                });
            }
        });
    </script>
    <script>
        // แสดง preview สลิป
        slipInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('slipPreview');
                    img.src = e.target.result;
                    img.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>

</body>

</html>