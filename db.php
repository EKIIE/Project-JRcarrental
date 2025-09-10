
<?php
//ฟั
error_reporting(E_ALL);
ini_set('display_errors', 1);

function connect_db(){
    $host_id = $_SERVER['SERVER_ADDR'];
    // echo "<p style='color: blue;'>Server IP: $host_id</p>";


    if($host_id == "127.0.0.1" or $host_id == "::1"){
            //localhost
        $servername = "127.0.0.1"; 
        $username = "root";
        $password = "";
        $dbname = "jr_car_rental";
    }else{
            //server
        $servername = "localhost"; 
        $username = "cistrain_ekiie";
        $password = "m053213700";
        $dbname = "cistrain_ekiie";
        echo "<p style='color: red;'>Using: $username@$servername</p>";
    }
    $conn = mysqli_connect($servername, $username, $password, $dbname);

    if(!$conn){
        die("Connection failed: " . mysqli_connect_error());
    } else {
        // echo "<p style='color:green;'>Connected to: " . $dbname . "</p>";
    }

    mysqli_set_charset($conn, "utf8");
    return $conn;
}

$conn = connect_db(); // เพิ่มบรรทัดนี้ให้เรียกอัตโนมัติ
?>
<!-- ######################################### -->
<?php
/*
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "car_rental";
$conn = mysqli_connect($host, $user, $pass, $dbname);
mysqli_set_charset($conn, "utf8");

if (!$conn) {
  die("Connection failed: " . mysqli_connect_error());
}
  */
?>
