<?php
$host = "sql308.infinityfree.com";
$user = "if0_41314799";
$pass = "klayn12345";
$db   = "if0_41314799_db_sam";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>