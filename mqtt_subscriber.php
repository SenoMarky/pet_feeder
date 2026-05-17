<?php
// mqtt_subscriber.php
require __DIR__ . '/config.php';
require __DIR__ . '/vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

date_default_timezone_set('Asia/Jakarta');
set_time_limit(0); 

try {
    $settings = (new ConnectionSettings())->setUsername($mqtt_user)->setPassword($mqtt_password)->setUseTls(true)->setTlsSelfSignedAllowed(true)->setKeepAliveInterval(60);
    $mqtt = new MqttClient($mqtt_server, $mqtt_port, 'PHP_Listener_' . rand(100, 999));
    $mqtt->connect($settings, true);
    
    echo "========================================\n";
    echo "DAEMON SUBSCRIBER AKTIF\n";
    echo "Menunggu laporan gerakan dari ESP32...\n";
    echo "========================================\n";

    $mqtt->subscribe('marky_petfeeder/status', function ($topic, $message, $retained, $matchedWildcards) {
        global $conn;
        echo "[" . date('H:i:s') . "] Menerima sinyal konfirmasi: " . $message . PHP_EOL;

        $method = "";
        if ($message === "SUCCESS_MANUAL") $method = "MANUAL (Web)";
        else if ($message === "SUCCESS_AUTO") $method = "OTOMATIS (Jadwal Alat)";
        else if ($message === "SUCCESS_BUTTON") $method = "TOMBOL FISIK";

        if ($method !== "") {
            $query = "INSERT INTO feeding_logs (`method`, `status`) VALUES (?, 'SUCCESS')";
            if ($stmt = $conn->prepare($query)) {
                $stmt->bind_param("s", $method);
                if ($stmt->execute()) {
                    echo "   -> Berhasil mencatat log ke MySQL!" . PHP_EOL;
                } else {
                    echo "   -> Gagal mengeksekusi statement: " . $stmt->error . PHP_EOL;
                }
                $stmt->close();
            } else {
                echo "   -> Gagal mempersiapkan statement: " . $conn->error . PHP_EOL;
            }
        }
    }, 0);

    $mqtt->loop(true);

} catch (\Throwable $e) {
    echo "Koneksi terputus: " . $e->getMessage() . PHP_EOL;
}
?>