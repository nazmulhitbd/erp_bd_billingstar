<?php
$dbhost = '127.0.0.1';
$dbport = 3306;
$dbname = 'billing_star';
$dbuser = 'billing_star';
$dbpass = 'Bs-708284dd101d94ca864d-X67';
$dbcharset = 'latin1';
$processtime = 600;
$host = $servername = $dbhost;
$username = $dbuser;
$password = $dbpass;
$database = $dbname;
$ptime = $processtime;
mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();
if (!$conn || !@$conn->real_connect($dbhost, $dbuser, $dbpass, $dbname, $dbport)) {
    throw new RuntimeException('Database connection failed: ' . (mysqli_connect_error() ?: 'unable to initialize MySQL client'));
}
if (!$conn->set_charset($dbcharset)) {
    throw new RuntimeException('Could not set database connection charset.');
}
$link = $conn;
$db = true;
