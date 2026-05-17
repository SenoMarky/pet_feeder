<?php
require 'config.php';
require 'vendor/autoload.php';
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

function syncSchedulesToESP($conn, $mqtt_server, $mqtt_port, $mqtt_user, $mqtt_password) {
    $result = $conn->query("SELECT feed_time FROM feeding_schedules ORDER BY feed_time ASC");
    $arr_jam = [];
    while ($row = $result->fetch_assoc()) {
        $arr_jam[] = date('H:i', strtotime($row['feed_time']));
    }
    $string_jadwal = implode(',', $arr_jam); // Menjadi string misal: "07:00,12:00"

    try {
        $settings = (new ConnectionSettings())->setUsername($mqtt_user)->setPassword($mqtt_password)->setUseTls(true)->setTlsSelfSignedAllowed(true);
        $mqtt = new MqttClient($mqtt_server, $mqtt_port, 'PHP_Sync_' . rand(100, 999));
        $mqtt->connect($settings, true);
        
        $mqtt->publish('marky_petfeeder/schedule', $string_jadwal, 0); // Sinkronisasi jadwal
        $mqtt->disconnect();
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $time = $_POST['feed_time'];
    $stmt = $conn->prepare("INSERT INTO feeding_schedules (feed_time) VALUES (?)");
    $stmt->bind_param("s", $time);
    $stmt->execute();
    
    syncSchedulesToESP($conn, $mqtt_server, $mqtt_port, $mqtt_user, $mqtt_password);
    header("Location: index.php?msg=Jadwal ditambah dan disinkronisasi");
}

if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM feeding_schedules WHERE id = $id");
    
    syncSchedulesToESP($conn, $mqtt_server, $mqtt_port, $mqtt_user, $mqtt_password);
    header("Location: index.php?msg=Jadwal dihapus dan disinkronisasi");
}
?>