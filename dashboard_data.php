<?php
require 'config.php';
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');

$response = [
    'success' => true,
    'server_time' => date('H:i:s'),
    'logs' => [],
    'schedules' => [],
];

try {
    $logs = $conn->query("SELECT id, method, status, created_at FROM feeding_logs ORDER BY created_at DESC LIMIT 10");
    while ($row = $logs->fetch_assoc()) {
        $response['logs'][] = [
            'id' => (int) $row['id'],
            'method' => $row['method'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'time_label' => date('H:i:s (d M)', strtotime($row['created_at'])),
        ];
    }

    $schedules = $conn->query("SELECT id, feed_time FROM feeding_schedules ORDER BY feed_time ASC");
    while ($row = $schedules->fetch_assoc()) {
        $response['schedules'][] = [
            'id' => (int) $row['id'],
            'feed_time' => $row['feed_time'],
            'time_label' => date('H:i', strtotime($row['feed_time'])),
        ];
    }
} catch (Throwable $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'Gagal mengambil data dashboard.',
    ];
}

echo json_encode($response);
