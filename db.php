<?php
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'eco_travel';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
	die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
