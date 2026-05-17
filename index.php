<?php
require 'config.php';
date_default_timezone_set('Asia/Jakarta');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard Smart Pet Feeder untuk kontrol manual, jadwal otomatis, dan monitoring riwayat pemberian makan secara real-time.">
    <title>Smart Pet Feeder Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <header class="header">
        <div>
            <h1>🐾 Smart Pet Feeder Dashboard</h1>
            <p>Selamat Datang Di Dashboard Pet Feeder</p>
        </div>
        <div class="status-pill" id="connectionStatus">Menghubungkan...</div>
    </header>

    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-info"><strong>Info:</strong> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <section class="stats-grid" aria-label="Ringkasan dashboard">
        <div class="stat-card">
            <span>Total Jadwal</span>
            <strong id="scheduleCount">0</strong>
        </div>
        <div class="stat-card">
            <span>Log Terakhir</span>
            <strong id="lastFeedTime">-</strong>
        </div>
        <div class="stat-card">
            <span>Update Server</span>
            <strong id="serverTime">-</strong>
        </div>
    </section>

    <main class="grid">
        <section class="col">
            <div class="card">
                <h2>Beri Makan Manual</h2>
                <p>Klik tombol di bawah ini untuk memberikan pakan saat ini juga.</p>
                <form action="feed_action.php" method="POST">
                    <button id="manualFeedButton" type="submit" class="btn">Keluarkan Makanan</button>
                </form>
            </div>

            <div class="card">
                <div class="card-title-row">
                    <h2>Jadwal Otomatis</h2>
                    <small>Tersimpan di alat</small>
                </div>
                <p>Atur jadwal pemberian pakan.</p>
                <form action="schedule_action.php" method="POST" class="schedule-form">
                    <input type="hidden" name="action" value="add">
                    <input id="feedTimeInput" type="time" name="feed_time" required>
                    <button id="addScheduleButton" type="submit" class="btn btn-inline">Tambah</button>
                </form>

                <table>
                    <thead>
                        <tr><th>Jam (WIB)</th><th class="center">Aksi</th></tr>
                    </thead>
                    <tbody id="schedulesBody">
                        <tr><td colspan="2" class="empty-state">Memuat jadwal...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="col">
            <div class="card">
                <div class="card-title-row">
                    <h2>Riwayat</h2>
                    <small>Closed-loop</small>
                </div>
                <p>Data riwayat pemberian pakan.</p>
                <table>
                    <thead>
                        <tr><th>Metode</th><th>Waktu</th></tr>
                    </thead>
                    <tbody id="logsBody">
                        <tr><td colspan="2" class="empty-state">Memuat riwayat...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<script>
const schedulesBody = document.getElementById('schedulesBody');
const logsBody = document.getElementById('logsBody');
const connectionStatus = document.getElementById('connectionStatus');
const scheduleCount = document.getElementById('scheduleCount');
const lastFeedTime = document.getElementById('lastFeedTime');
const serverTime = document.getElementById('serverTime');
let lastLogId = null;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    }[char]));
}

function methodLabel(method) {
    if (method.includes('Web')) return `🔵 ${escapeHtml(method)}`;
    if (method.includes('Jadwal')) return `🟢 ${escapeHtml(method)}`;
    return `🟠 ${escapeHtml(method)}`;
}

function renderSchedules(schedules) {
    scheduleCount.textContent = schedules.length;
    if (!schedules.length) {
        schedulesBody.innerHTML = '<tr><td colspan="2" class="empty-state">Belum ada jadwal.</td></tr>';
        return;
    }

    schedulesBody.innerHTML = schedules.map(schedule => `
        <tr>
            <td><strong>${escapeHtml(schedule.time_label)}</strong></td>
            <td class="center">
                <a id="deleteSchedule${schedule.id}" href="schedule_action.php?delete_id=${schedule.id}" class="btn-danger">Hapus</a>
            </td>
        </tr>
    `).join('');
}

function renderLogs(logs) {
    if (!logs.length) {
        lastFeedTime.textContent = '-';
        logsBody.innerHTML = '<tr><td colspan="2" class="empty-state">Belum ada riwayat berhasil.</td></tr>';
        return;
    }

    lastFeedTime.textContent = logs[0].time_label;
    const newestLogId = logs[0].id;
    const shouldHighlight = lastLogId !== null && newestLogId !== lastLogId;
    lastLogId = newestLogId;

    logsBody.innerHTML = logs.map((log, index) => `
        <tr class="${shouldHighlight && index === 0 ? 'new-row' : ''}">
            <td>${methodLabel(log.method)}</td>
            <td>${escapeHtml(log.time_label)}</td>
        </tr>
    `).join('');
}

async function loadDashboardData() {
    try {
        const response = await fetch('dashboard_data.php?ts=' + Date.now(), { cache: 'no-store' });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const data = await response.json();
        if (!data.success) throw new Error(data.message || 'Gagal mengambil data');

        renderSchedules(data.schedules);
        renderLogs(data.logs);
        serverTime.textContent = data.server_time;
        connectionStatus.textContent = 'Live Update Aktif';
        connectionStatus.classList.remove('offline');
    } catch (error) {
        connectionStatus.textContent = 'Koneksi Data Gagal';
        connectionStatus.classList.add('offline');
        console.error(error);
    }
}

loadDashboardData();
setInterval(loadDashboardData, 3000);
</script>

</body>
</html>
