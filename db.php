
<?php
//ฟั
error_reporting(E_ALL);
ini_set('display_errors', 1);

function connect_db()
{
    $host_id = $_SERVER['SERVER_ADDR'];


    if ($host_id == "127.0.0.1" or $host_id == "::1") {
        //localhost
        $servername = "127.0.0.1";
        $username = "root";
        $password = "";
        $dbname = "jr_car_rental";
    } else {
        //server
        $servername = "localhost";
        $username = "root";
        $password = "rooT2244_";
        $dbname = "jr_carrental";
    }
    $conn = mysqli_connect($servername, $username, $password, $dbname);

    if (!$conn) {
        // แทนที่จะ die() ให้ส่งข้อความ JSON error กลับไปแทน
        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['brand'])) {
            header('Content-Type: application/json; charset=utf-8');
            // ส่งข้อผิดพลาดกลับไปในรูปแบบที่ AJAX คาดหวัง
            echo json_encode(["status" => "error", "message" => "DATABASE_CONNECTION_ERROR"]);
            exit();
        } else {
            // สำหรับการโหลดหน้าปกติ ให้แสดง error (หรือซ่อน error)
            die("Connection failed: " . mysqli_connect_error());
        }
    } else {
        // echo "Connected successfully"; // ทดสอบการเชื่อมต่อฐานข้อมูล
    }

    mysqli_set_charset($conn, "utf8");
    return $conn;
}

$conn = connect_db(); // เพิ่มบรรทัดนี้ให้เรียกอัตโนมัติ
?>
