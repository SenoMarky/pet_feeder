<?php
// feed_action.php
require 'config.php';
require 'vendor/autoload.php';
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = (new ConnectionSettings())->setUsername($mqtt_user)->setPassword($mqtt_password)->setUseTls(true)->setTlsSelfSignedAllowed(true);
        $mqtt = new MqttClient($mqtt_server, $mqtt_port, 'PHP_Manual_' . rand(100, 999));
        $mqtt->connect($settings, true);
        
        $mqtt->publish('marky_petfeeder/command', '1', 0); 
        $mqtt->disconnect();

        header("Location: index.php?msg=Sinyal terkirim! Menunggu konfirmasi alat...");
    } catch (Exception $e) {
        header("Location: index.php?msg=Gagal koneksi ke server MQTT.");
    }
}
?>