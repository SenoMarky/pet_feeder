<?php
// config.php
$db_host = 'localhost';
$db_user = 'root'; // Sesuaikan dengan user database kamu
$db_pass = '';     // Sesuaikan dengan password database kamu
$db_name = 'db_petfeeder';

// Koneksi ke MySQL
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// Konfigurasi HiveMQ Cloud
$mqtt_server   = '8d8aa6ee7d77456cb311e1218d37662d.s1.eu.hivemq.cloud';
$mqtt_port     = 8883; // Port wajib TLS
$mqtt_user     = 'Marky'; 
$mqtt_password = 'Marky123';
$mqtt_topic    = 'marky_petfeeder/command';
?>