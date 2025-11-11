<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ngcdf_boardroom";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Throwable $e) {
    error_log("DB connect error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}
?>