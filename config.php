<?php
$db_host = 'localhost';
$db_user = 'root'; 
$db_pass = '';     
$db_name = 'db_petfeeder';

// Koneksi ke MySQL
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// Konfigurasi HiveMQ Cloud
$mqtt_server   = 'URL_SERVER_MQTT';
$mqtt_port     = 8883; 
$mqtt_user     = 'USERNAME_MQTT'; 
$mqtt_password = 'MQTT_PASSWORD';
?>