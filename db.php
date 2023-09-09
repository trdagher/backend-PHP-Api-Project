<?php

$host = 'db4free.net';
$user = 'tonydbtest';
$password = 'aimTony333123';
$database = 'tonydbtestdb';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>