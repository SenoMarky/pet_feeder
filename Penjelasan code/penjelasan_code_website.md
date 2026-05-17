# Penjelasan Kode Website Smart Pet Feeder

Dokumen ini menjelaskan bagaimana kode website Smart Pet Feeder bekerja, bagaimana website terhubung dengan ESP32 melalui HiveMQ, dan bagaimana data akhirnya disimpan ke database.

---

## 1. Gambaran Umum Sistem

Project ini terdiri dari tiga bagian utama:

1. **ESP32**
   - Menggerakkan servo untuk mengeluarkan makanan.
   - Mengirim status berhasil ke HiveMQ setelah servo bergerak.
   - Menerima perintah manual dan jadwal dari website melalui MQTT.

2. **HiveMQ Cloud**
   - Berfungsi sebagai broker MQTT.
   - Menjadi perantara komunikasi antara website dan ESP32.

3. **Website PHP + MySQL**
   - Menampilkan dashboard.
   - Mengirim perintah manual ke ESP32.
   - Mengirim jadwal otomatis ke ESP32.
   - Menerima status dari ESP32 melalui subscriber.
   - Menyimpan riwayat pemberian makan ke database.

Alur besarnya:

```text
Website PHP  ⇄  HiveMQ MQTT  ⇄  ESP32
     ↓                         ↑
  MySQL Database         Servo Pet Feeder
```

---

## 2. Alur Saat User Memberi Makan Manual

Ketika user menekan tombol **Keluarkan Makanan** di dashboard:

```text
User klik tombol di website
        ↓
index.php mengirim form ke feed_action.php
        ↓
feed_action.php publish payload "1" ke HiveMQ
        ↓
Topic: marky_petfeeder/command
        ↓
ESP32 menerima pesan MQTT
        ↓
ESP32 menggerakkan servo
        ↓
ESP32 publish SUCCESS_MANUAL ke HiveMQ
        ↓
Topic: marky_petfeeder/status
        ↓
mqtt_subscriber.php menerima status
        ↓
Data disimpan ke tabel feeding_logs
        ↓
index.php mengambil data terbaru lewat dashboard_data.php
```

Jadi website tidak langsung mencatat log saat tombol ditekan. Log baru dicatat ketika ESP32 sudah mengirim konfirmasi sukses.

---

## 3. Alur Saat Jadwal Otomatis Ditambahkan

Ketika user menambah jadwal makan:

```text
User memilih jam di dashboard
        ↓
index.php mengirim form ke schedule_action.php
        ↓
schedule_action.php menyimpan jadwal ke database
        ↓
schedule_action.php mengambil semua jadwal dari database
        ↓
Jadwal digabung menjadi string, contoh: 07:00,12:00,18:00
        ↓
Jadwal dikirim ke HiveMQ
        ↓
Topic: marky_petfeeder/schedule
        ↓
ESP32 menerima jadwal baru
        ↓
ESP32 menyimpan jadwal ke memori internal
        ↓
ESP32 memberi makan otomatis saat waktu cocok
        ↓
ESP32 publish SUCCESS_AUTO
        ↓
mqtt_subscriber.php menyimpan log ke database
```

Dengan alur ini, jadwal tersimpan di dua tempat:

1. **Database MySQL**, agar bisa ditampilkan di website.
2. **Memori ESP32**, agar alat tetap bisa menjalankan jadwal.

---

## 4. Alur Saat Tombol Fisik di ESP32 Ditekan

Jika tombol fisik pada alat ditekan:

```text
User menekan tombol fisik
        ↓
ESP32 membaca tombol
        ↓
ESP32 menggerakkan servo
        ↓
ESP32 publish SUCCESS_BUTTON ke HiveMQ
        ↓
mqtt_subscriber.php menerima status
        ↓
Log disimpan ke database
        ↓
Dashboard menampilkan riwayat terbaru
```

Artinya, walaupun perintah berasal dari tombol fisik, website tetap bisa mencatat riwayatnya selama `mqtt_subscriber.php` berjalan.

---

## 5. Topic MQTT yang Digunakan

| Topic | Arah | Fungsi |
|---|---|---|
| `marky_petfeeder/command` | Website ke ESP32 | Mengirim perintah makan manual. |
| `marky_petfeeder/schedule` | Website ke ESP32 | Mengirim daftar jadwal makan. |
| `marky_petfeeder/status` | ESP32 ke Website | Mengirim konfirmasi bahwa makanan berhasil dikeluarkan. |

Payload yang digunakan:

| Payload | Pengirim | Arti |
|---|---|---|
| `1` | Website | Perintah makan manual. |
| `07:00,12:00` | Website | Daftar jadwal makan. |
| `SUCCESS_MANUAL` | ESP32 | Servo berhasil bergerak dari perintah website. |
| `SUCCESS_AUTO` | ESP32 | Servo berhasil bergerak dari jadwal otomatis. |
| `SUCCESS_BUTTON` | ESP32 | Servo berhasil bergerak dari tombol fisik. |

---

## 6. Database yang Digunakan

Website menggunakan database:

```php
$db_name = 'db_petfeeder';
```

Dari kode yang ada, terdapat dua tabel utama.

### A. Tabel `feeding_schedules`

Tabel ini menyimpan jadwal makan.

Kolom yang digunakan:

| Kolom | Fungsi |
|---|---|
| `id` | ID jadwal. |
| `feed_time` | Jam makan, contoh `07:00:00`. |

Digunakan oleh:

- `schedule_action.php`
- `dashboard_data.php`

### B. Tabel `feeding_logs`

Tabel ini menyimpan riwayat pemberian makan.

Kolom yang digunakan:

| Kolom | Fungsi |
|---|---|
| `id` | ID log. |
| `method` | Metode pemberian makan. |
| `status` | Status proses, contoh `SUCCESS`. |
| `created_at` | Waktu log dibuat. |

Digunakan oleh:

- `mqtt_subscriber.php`
- `dashboard_data.php`

---

## 7. Penjelasan Setiap File Website

---

## 7.1 `config.php`

File ini adalah pusat konfigurasi website.

Fungsinya:

1. Menyimpan konfigurasi database.
2. Membuat koneksi ke MySQL.
3. Menyimpan konfigurasi MQTT HiveMQ.

Isi pentingnya:

```php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'db_petfeeder';
```

Bagian ini menentukan database yang digunakan.

```php
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
```

Kode ini membuat koneksi ke MySQL. Variabel `$conn` dipakai oleh file lain untuk melakukan query ke database.

Konfigurasi MQTT:

```php
$mqtt_server   = '8d8aa6ee7d77456cb311e1218d37662d.s1.eu.hivemq.cloud';
$mqtt_port     = 8883;
$mqtt_user     = 'Marky';
$mqtt_password = 'Marky123';
$mqtt_topic    = 'marky_petfeeder/command';
```

Bagian ini digunakan saat website ingin terhubung ke HiveMQ.

Kesimpulan:

> `config.php` adalah file dasar yang dipanggil oleh hampir semua file PHP lain karena berisi koneksi database dan konfigurasi MQTT.

---

## 7.2 `index.php`

File ini adalah halaman utama dashboard Smart Pet Feeder.

Fungsinya:

1. Menampilkan judul dashboard.
2. Menampilkan status koneksi data.
3. Menampilkan total jadwal.
4. Menampilkan log terakhir.
5. Menyediakan tombol makan manual.
6. Menyediakan form tambah jadwal.
7. Menampilkan daftar jadwal.
8. Menampilkan riwayat pemberian makan.
9. Mengambil data terbaru secara otomatis menggunakan JavaScript.

Bagian manual feeding:

```html
<form action="feed_action.php" method="POST">
    <button id="manualFeedButton" type="submit" class="btn">Keluarkan Makanan</button>
</form>
```

Saat tombol ditekan, form dikirim ke `feed_action.php`.

Bagian tambah jadwal:

```html
<form action="schedule_action.php" method="POST" class="schedule-form">
    <input type="hidden" name="action" value="add">
    <input id="feedTimeInput" type="time" name="feed_time" required>
    <button id="addScheduleButton" type="submit" class="btn btn-inline">Tambah</button>
</form>
```

Saat user memilih jam dan menekan tambah, data dikirim ke `schedule_action.php`.

Bagian JavaScript live update:

```js
const response = await fetch('dashboard_data.php?ts=' + Date.now(), { cache: 'no-store' });
```

Kode ini mengambil data terbaru dari `dashboard_data.php`.

Refresh otomatis:

```js
setInterval(loadDashboardData, 3000);
```

Artinya dashboard memperbarui data setiap 3 detik.

Kesimpulan:

> `index.php` adalah tampilan utama yang menghubungkan user dengan fitur manual feeding, jadwal otomatis, dan riwayat pemberian makan.

---

## 7.3 `feed_action.php`

File ini menangani tombol makan manual dari website.

Fungsinya:

1. Menerima request `POST` dari tombol di `index.php`.
2. Membuat koneksi ke HiveMQ.
3. Mengirim payload `1` ke topic command.
4. Mengarahkan user kembali ke dashboard.

Bagian penting:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
```

Kode ini memastikan file hanya memproses request dari form POST.

Membuat koneksi MQTT:

```php
$settings = (new ConnectionSettings())
    ->setUsername($mqtt_user)
    ->setPassword($mqtt_password)
    ->setUseTls(true)
    ->setTlsSelfSignedAllowed(true);
```

Kode ini menyiapkan koneksi MQTT dengan username, password, dan TLS.

Publish perintah manual:

```php
$mqtt->publish('marky_petfeeder/command', '1', 0);
```

Artinya website mengirim pesan `1` ke ESP32 melalui HiveMQ.

Kesimpulan:

> `feed_action.php` adalah pengirim perintah manual dari website ke ESP32.

---

## 7.4 `schedule_action.php`

File ini menangani tambah dan hapus jadwal.

Fungsinya:

1. Menyimpan jadwal baru ke database.
2. Menghapus jadwal dari database.
3. Mengambil semua jadwal dari database.
4. Mengirim ulang seluruh jadwal ke ESP32 melalui MQTT.

Fungsi utama di file ini:

```php
function syncSchedulesToESP($conn, $mqtt_server, $mqtt_port, $mqtt_user, $mqtt_password)
```

Fungsi ini digunakan untuk menyinkronkan jadwal dari database ke ESP32.

Mengambil semua jadwal:

```php
$result = $conn->query("SELECT feed_time FROM feeding_schedules ORDER BY feed_time ASC");
```

Mengubah jadwal menjadi format `HH:MM`:

```php
$arr_jam[] = date('H:i', strtotime($row['feed_time']));
```

Menggabungkan semua jadwal menjadi string:

```php
$string_jadwal = implode(',', $arr_jam);
```

Contoh hasilnya:

```text
07:00,12:00,18:00
```

Mengirim jadwal ke ESP32:

```php
$mqtt->publish('marky_petfeeder/schedule', $string_jadwal, 0);
```

Tambah jadwal:

```php
$stmt = $conn->prepare("INSERT INTO feeding_schedules (feed_time) VALUES (?)");
$stmt->bind_param("s", $time);
$stmt->execute();
```

Hapus jadwal:

```php
$conn->query("DELETE FROM feeding_schedules WHERE id = $id");
```

Kesimpulan:

> `schedule_action.php` bertugas mengelola jadwal di database dan memastikan ESP32 menerima jadwal terbaru.

---

## 7.5 `mqtt_subscriber.php`

File ini adalah listener atau daemon MQTT.

Fungsinya:

1. Terhubung ke HiveMQ.
2. Subscribe ke topic status.
3. Menunggu pesan sukses dari ESP32.
4. Menerjemahkan pesan status menjadi metode pemberian makan.
5. Menyimpan log ke database.

File ini harus dijalankan terus melalui terminal:

```bash
php mqtt_subscriber.php
```

Subscribe ke topic status:

```php
$mqtt->subscribe('marky_petfeeder/status', function ($topic, $message, $retained, $matchedWildcards) {
```

Artinya PHP akan terus menunggu pesan dari ESP32 pada topic:

```text
marky_petfeeder/status
```

Mapping status:

```php
if ($message === "SUCCESS_MANUAL") $method = "MANUAL (Web)";
else if ($message === "SUCCESS_AUTO") $method = "OTOMATIS (Jadwal Alat)";
else if ($message === "SUCCESS_BUTTON") $method = "TOMBOL FISIK";
```

Pesan dari ESP32 diterjemahkan menjadi metode yang mudah dibaca.

Menyimpan ke database:

```php
$query = "INSERT INTO feeding_logs (`method`, `status`) VALUES (?, 'SUCCESS')";
```

Data disimpan ke tabel `feeding_logs`.

Kesimpulan:

> `mqtt_subscriber.php` adalah jembatan dari ESP32 ke database. File ini menerima status dari HiveMQ lalu menyimpan riwayat pemberian makan.

---

## 7.6 `dashboard_data.php`

File ini adalah API sederhana untuk dashboard.

Fungsinya:

1. Mengambil data log terbaru dari database.
2. Mengambil data jadwal dari database.
3. Mengirim data ke `index.php` dalam format JSON.

Header JSON:

```php
header('Content-Type: application/json; charset=utf-8');
```

Artinya output file ini adalah JSON, bukan halaman HTML.

Struktur response:

```php
$response = [
    'success' => true,
    'server_time' => date('H:i:s'),
    'logs' => [],
    'schedules' => [],
];
```

Mengambil log terbaru:

```php
$logs = $conn->query("SELECT id, method, status, created_at FROM feeding_logs ORDER BY created_at DESC LIMIT 10");
```

Mengambil maksimal 10 riwayat terbaru dari tabel `feeding_logs`.

Mengambil jadwal:

```php
$schedules = $conn->query("SELECT id, feed_time FROM feeding_schedules ORDER BY feed_time ASC");
```

Mengambil semua jadwal dari tabel `feeding_schedules`.

Output dikirim dengan:

```php
echo json_encode($response);
```

Kesimpulan:

> `dashboard_data.php` menyediakan data terbaru untuk dashboard tanpa perlu reload halaman.

---

## 7.7 `style.css`

File ini mengatur tampilan dashboard.

Fungsinya:

1. Mengatur warna tema.
2. Mengatur layout dashboard.
3. Mengatur tampilan card, tombol, tabel, dan alert.
4. Membuat tampilan lebih modern dan responsif.

Contoh bagian warna utama:

```css
:root {
    --vanilla: #f6df9f;
    --broken-white: #fffaf0;
    --cocoa: #4b2f1d;
    --success: #7aa95c;
    --danger: #d96b5f;
}
```

Tampilan dashboard dibuat dengan card:

```css
.card {
    padding: 25px;
    margin-bottom: 18px;
    border-radius: var(--radius);
}
```

Website juga responsif untuk layar kecil:

```css
@media (max-width: 820px) {
    .grid { grid-template-columns: 1fr; }
}
```

Kesimpulan:

> `style.css` membuat dashboard terlihat rapi, nyaman dilihat, dan bisa menyesuaikan ukuran layar.

---

## 8. Penjelasan Hubungan ESP32, HiveMQ, Website, dan Database

Hubungan antar komponen dapat dijelaskan seperti ini:

```text
[Website Dashboard]
        |
        | publish command / schedule
        v
[HiveMQ Broker]
        |
        | diterima oleh ESP32
        v
[ESP32 Pet Feeder]
        |
        | servo bergerak
        v
[ESP32 publish status sukses]
        |
        v
[HiveMQ Broker]
        |
        | diterima mqtt_subscriber.php
        v
[Website PHP Subscriber]
        |
        | INSERT log
        v
[MySQL Database]
        |
        | SELECT data dashboard
        v
[index.php menampilkan data]
```

---

## 9. Kenapa Disebut Closed-Loop?

Sistem ini bisa disebut **closed-loop** karena website tidak mencatat log hanya berdasarkan tombol ditekan.

Website mencatat log setelah menerima konfirmasi dari ESP32.

Contoh:

```text
Tombol website ditekan
        ↓
Perintah dikirim ke ESP32
        ↓
ESP32 menjalankan servo
        ↓
ESP32 mengirim SUCCESS_MANUAL
        ↓
Baru masuk database sebagai log sukses
```

Dengan begitu, riwayat di website lebih valid karena berasal dari feedback alat.

---

## 10. Peran Setiap File Secara Singkat

| File | Peran Utama |
|---|---|
| `config.php` | Menyimpan konfigurasi database dan MQTT. |
| `index.php` | Halaman dashboard utama. |
| `feed_action.php` | Mengirim perintah makan manual ke ESP32. |
| `schedule_action.php` | Menambah, menghapus, dan mengirim jadwal ke ESP32. |
| `mqtt_subscriber.php` | Menerima status dari ESP32 dan menyimpan log ke database. |
| `dashboard_data.php` | Menyediakan data JSON untuk live update dashboard. |
| `style.css` | Mengatur tampilan dashboard. |
| `code_esp/pet_feeder.ino` | Kode ESP32 untuk servo, tombol, MQTT, dan jadwal. |

---

## 11. Kalimat Singkat untuk Presentasi

> Website Smart Pet Feeder berfungsi sebagai dashboard kontrol dan monitoring. Ketika user memberi perintah atau mengatur jadwal, website mengirim data ke ESP32 melalui HiveMQ. ESP32 mengeksekusi perintah dengan menggerakkan servo, lalu mengirim status sukses kembali ke HiveMQ. File `mqtt_subscriber.php` menerima status tersebut dan menyimpannya ke database, kemudian dashboard mengambil data terbaru melalui `dashboard_data.php` agar riwayat tampil secara real-time.

---

## 12. Kesimpulan

Website ini memiliki pembagian tugas yang jelas:

- `index.php` untuk tampilan.
- `feed_action.php` untuk perintah manual.
- `schedule_action.php` untuk jadwal.
- `mqtt_subscriber.php` untuk menerima status dari ESP32.
- `dashboard_data.php` untuk menyediakan data dashboard.
- `config.php` untuk koneksi database dan MQTT.
- `style.css` untuk tampilan.

Alur utama sistem adalah:

```text
Website mengirim perintah → HiveMQ → ESP32 → Servo bergerak → ESP32 kirim status → HiveMQ → Subscriber PHP → Database → Dashboard tampilkan data
```

Dengan struktur seperti ini, sistem mudah dijelaskan karena setiap file memiliki tugas masing-masing dan semua komunikasi antar perangkat dilakukan melalui MQTT.
