<?php
error_reporting(0);
ini_set('display_errors', 0);

$db_host = 'localhost';
$db_user = 'axendell_admin';
$db_pass = '0@s;mx7=)97hHD$@';
$db_name = 'axendell_admin';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    http_response_code(503);
    die("Servis geçici olarak kullanılamıyor.");
}

$conn->set_charset("utf8mb4");
