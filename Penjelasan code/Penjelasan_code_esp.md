# Penjelasan Kode ESP32 Smart Pet Feeder

Dokumen ini menjelaskan isi file `code_esp/pet_feeder.ino` secara sederhana agar mudah dipahami dan siap dipresentasikan.

---

## 1. Gambaran Umum Sistem

Kode ESP32 ini digunakan untuk mengontrol alat **Smart Pet Feeder**.

ESP32 memiliki beberapa tugas utama:

1. Terhubung ke WiFi.
2. Terhubung ke broker MQTT HiveMQ.
3. Menerima perintah makan manual dari website.
4. Menerima jadwal makan otomatis dari website.
5. Menyimpan jadwal ke memori ESP32.
6. Mengecek waktu saat ini menggunakan NTP.
7. Menggerakkan servo untuk membuka katup makanan.
8. Membaca tombol fisik sebagai kontrol manual langsung.
9. Mengirim status sukses kembali ke website.

Alur sederhananya:

```text
Website / Jadwal / Tombol
        ↓
      ESP32
        ↓
  Servo membuka katup
        ↓
ESP32 kirim status sukses
        ↓
Website mencatat riwayat
```

---

## 2. Library yang Digunakan

```cpp
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <ESP32Servo.h>
#include <Preferences.h>
#include "time.h"
```

Penjelasan:

| Library | Fungsi |
|---|---|
| `WiFi.h` | Menghubungkan ESP32 ke jaringan WiFi. |
| `WiFiClientSecure.h` | Membuat koneksi aman/TLS ke MQTT broker. |
| `PubSubClient.h` | Mengirim dan menerima pesan MQTT. |
| `ESP32Servo.h` | Mengontrol motor servo. |
| `Preferences.h` | Menyimpan data jadwal ke memori ESP32. |
| `time.h` | Mengambil waktu dari internet menggunakan NTP. |

---

## 3. Bagian Konfigurasi

```cpp
const char* ssid = "Kost Pandu Lt2";
const char* password = "pandu2011";
const char* mqtt_server = "8d8aa6ee7d77456cb311e1218d37662d.s1.eu.hivemq.cloud";
const char* mqtt_user = "Marky";
const char* mqtt_password = "Marky123";
const int mqtt_port = 8883;
```

Bagian ini berisi data koneksi:

- `ssid`: nama WiFi.
- `password`: password WiFi.
- `mqtt_server`: alamat broker MQTT HiveMQ.
- `mqtt_user`: username MQTT.
- `mqtt_password`: password MQTT.
- `mqtt_port`: port MQTT secure/TLS, yaitu `8883`.

> Bagian ini adalah pusat konfigurasi koneksi ESP32.

---

## 4. Topic MQTT

```cpp
const char* TOPIC_COMMAND = "marky_petfeeder/command";
const char* TOPIC_SCHEDULE = "marky_petfeeder/schedule";
const char* TOPIC_STATUS = "marky_petfeeder/status";
```

Topic MQTT adalah jalur komunikasi antara website dan ESP32.

| Topic | Arah Komunikasi | Fungsi |
|---|---|---|
| `marky_petfeeder/command` | Website ke ESP32 | Mengirim perintah makan manual. |
| `marky_petfeeder/schedule` | Website ke ESP32 | Mengirim daftar jadwal makan. |
| `marky_petfeeder/status` | ESP32 ke Website | Mengirim laporan bahwa makanan berhasil dikeluarkan. |

Contoh:

Jika user menekan tombol makan di website, website mengirim payload `1` ke topic:

```text
marky_petfeeder/command
```

Lalu ESP32 menerima pesan tersebut dan menjalankan servo.

---

## 5. Pin dan Waktu Debounce

```cpp
const int servoPin = 18;
const int buttonPin = 4;
const unsigned long debounceDelay = 1000;
```

Penjelasan:

| Variabel | Fungsi |
|---|---|
| `servoPin` | Pin ESP32 yang terhubung ke servo. |
| `buttonPin` | Pin ESP32 yang terhubung ke tombol fisik. |
| `debounceDelay` | Jeda 1 detik agar tombol tidak terbaca berkali-kali dalam sekali tekan. |

---

## 6. Objek dan State Program

```cpp
WiFiClientSecure espClient;
PubSubClient client(espClient);
Servo feederServo;
Preferences prefs;
```

Penjelasan objek:

| Objek | Fungsi |
|---|---|
| `espClient` | Client koneksi aman untuk MQTT TLS. |
| `client` | Objek MQTT untuk publish dan subscribe. |
| `feederServo` | Objek untuk mengontrol servo. |
| `prefs` | Objek untuk menyimpan jadwal ke memori ESP32. |

State program:

```cpp
String jadwalMakan = "";
String sumberPerintah = "";
bool mintaMakan = false;
int lastFedMinute = -1;
unsigned long lastButtonPress = 0;
```

| Variabel | Fungsi |
|---|---|
| `jadwalMakan` | Menyimpan daftar jadwal dari website, contoh `07:00,12:00`. |
| `sumberPerintah` | Menyimpan asal perintah makan: `MANUAL`, `AUTO`, atau `BUTTON`. |
| `mintaMakan` | Penanda apakah servo perlu dijalankan. |
| `lastFedMinute` | Mencegah alat memberi makan berkali-kali pada menit yang sama. |
| `lastButtonPress` | Menyimpan waktu terakhir tombol ditekan untuk debounce. |

---

## 7. Fungsi `bukaKatup()`

```cpp
void bukaKatup() {
  Serial.println("[AKSI] Membuka katup...");
  feederServo.write(45);
  delay(350);
  feederServo.write(0);
}
```

Fungsi ini bertugas menggerakkan servo.

Alurnya:

1. Servo bergerak ke sudut `45` derajat.
2. Katup makanan terbuka.
3. Program menunggu selama `350 ms`.
4. Servo kembali ke sudut `0` derajat.
5. Katup makanan tertutup kembali.

Fungsi ini adalah bagian utama yang membuat makanan keluar secara fisik.

---

## 8. Fungsi `mintaBeriMakan()`

```cpp
void mintaBeriMakan(String sumber) {
  mintaMakan = true;
  sumberPerintah = sumber;
}
```

Fungsi ini digunakan untuk membuat antrean permintaan makan.

Parameter `sumber` menunjukkan asal perintah:

| Nilai | Arti |
|---|---|
| `MANUAL` | Perintah dari website. |
| `AUTO` | Perintah dari jadwal otomatis. |
| `BUTTON` | Perintah dari tombol fisik. |

Contoh:

```cpp
mintaBeriMakan("MANUAL");
```

Artinya ESP32 diminta memberi makan karena ada perintah manual dari website.

---

## 9. Fungsi `kirimStatusSukses()`

```cpp
void kirimStatusSukses() {
  if (sumberPerintah == "MANUAL") client.publish(TOPIC_STATUS, "SUCCESS_MANUAL");
  else if (sumberPerintah == "AUTO") client.publish(TOPIC_STATUS, "SUCCESS_AUTO");
  else if (sumberPerintah == "BUTTON") client.publish(TOPIC_STATUS, "SUCCESS_BUTTON");
}
```

Fungsi ini mengirim laporan ke website setelah servo selesai bergerak.

Status yang dikirim:

| Sumber Perintah | Status yang Dikirim |
|---|---|
| `MANUAL` | `SUCCESS_MANUAL` |
| `AUTO` | `SUCCESS_AUTO` |
| `BUTTON` | `SUCCESS_BUTTON` |

Status dikirim ke topic:

```text
marky_petfeeder/status
```

Dengan cara ini, website tahu bahwa makanan benar-benar sudah dikeluarkan oleh alat.

---

## 10. Fungsi `prosesMakan()`

```cpp
void prosesMakan() {
  if (!mintaMakan) return;

  bukaKatup();
  kirimStatusSukses();
  mintaMakan = false;
  sumberPerintah = "";
}
```

Fungsi ini menjalankan proses pemberian makan.

Alurnya:

1. Mengecek apakah ada permintaan makan.
2. Jika tidak ada, fungsi langsung berhenti.
3. Jika ada, servo membuka katup.
4. ESP32 mengirim status sukses ke website.
5. Flag `mintaMakan` dikembalikan menjadi `false`.
6. `sumberPerintah` dikosongkan kembali.

Fungsi ini membuat proses makan hanya berjalan ketika memang ada perintah.

---

## 11. Fungsi `simpanJadwal()`

```cpp
void simpanJadwal(String jadwal) {
  jadwalMakan = jadwal;
  prefs.begin("feeder_data", false);
  prefs.putString("list_jam", jadwalMakan);
  prefs.end();
  Serial.println("[SYNC] Jadwal diperbarui: " + jadwalMakan);
}
```

Fungsi ini menyimpan jadwal dari website ke memori ESP32.

Alurnya:

1. Jadwal baru diterima dari website.
2. Jadwal disimpan ke variabel `jadwalMakan`.
3. Jadwal disimpan ke memori ESP32 menggunakan `Preferences`.
4. Jadwal tetap ada walaupun ESP32 restart.

Contoh jadwal yang diterima:

```text
07:00,12:00,18:00
```

---

## 12. Fungsi `cekJadwal()`

```cpp
void cekJadwal() {
  struct tm timeinfo;
  if (!getLocalTime(&timeinfo)) return;

  char jamSekarang[6];
  sprintf(jamSekarang, "%02d:%02d", timeinfo.tm_hour, timeinfo.tm_min);

  if (jadwalMakan.indexOf(jamSekarang) >= 0 && lastFedMinute != timeinfo.tm_min) {
    Serial.printf("[JADWAL] Waktu cocok (%s)!\n", jamSekarang);
    lastFedMinute = timeinfo.tm_min;
    mintaBeriMakan("AUTO");
  }
}
```

Fungsi ini mengecek apakah waktu sekarang cocok dengan jadwal makan.

Alurnya:

1. ESP32 mengambil waktu sekarang dari NTP.
2. Waktu diubah menjadi format `HH:MM`.
3. Program mengecek apakah waktu tersebut ada di daftar `jadwalMakan`.
4. Jika cocok, ESP32 meminta proses makan otomatis.
5. `lastFedMinute` digunakan agar makan tidak terjadi berulang kali dalam menit yang sama.

Contoh:

Jika `jadwalMakan` berisi:

```text
07:00,12:00,18:00
```

Dan waktu sekarang adalah:

```text
12:00
```

Maka ESP32 akan menjalankan servo dan mengirim status `SUCCESS_AUTO`.

---

## 13. Fungsi `callback()`

```cpp
void callback(char* topic, byte* payload, unsigned int length) {
  String pesan((char*) payload, length);
  String topik(topic);

  if (topik == TOPIC_COMMAND && pesan == "1") {
    Serial.println("[MANUAL] Perintah masuk dari web...");
    mintaBeriMakan("MANUAL");
  } else if (topik == TOPIC_SCHEDULE) {
    simpanJadwal(pesan);
  }
}
```

Fungsi ini otomatis berjalan ketika ESP32 menerima pesan MQTT.

Ada dua jenis pesan yang diproses:

### A. Perintah Manual

Jika topic adalah:

```text
marky_petfeeder/command
```

Dan isi pesan adalah:

```text
1
```

Maka ESP32 menjalankan:

```cpp
mintaBeriMakan("MANUAL");
```

### B. Update Jadwal

Jika topic adalah:

```text
marky_petfeeder/schedule
```

Maka isi pesan dianggap sebagai daftar jadwal baru dan disimpan menggunakan fungsi `simpanJadwal()`.

---

## 14. Fungsi `reconnectMqtt()`

```cpp
void reconnectMqtt() {
  while (!client.connected()) {
    Serial.print("Konek ke HiveMQ...");
    String clientId = "ESP32Feeder-" + String(random(0xffff), HEX);

    if (client.connect(clientId.c_str(), mqtt_user, mqtt_password)) {
      Serial.println(" BERHASIL!");
      client.subscribe(TOPIC_COMMAND);
      client.subscribe(TOPIC_SCHEDULE);
    } else {
      delay(5000);
    }
  }
}
```

Fungsi ini memastikan ESP32 selalu terhubung ke broker MQTT.

Alurnya:

1. Jika belum terkoneksi MQTT, ESP32 mencoba konek ke HiveMQ.
2. ESP32 membuat `clientId` acak agar tidak bentrok dengan client lain.
3. Jika koneksi berhasil, ESP32 subscribe ke dua topic:
   - `marky_petfeeder/command`
   - `marky_petfeeder/schedule`
4. Jika gagal, ESP32 menunggu 5 detik lalu mencoba lagi.

---

## 15. Fungsi `cekTombol()`

```cpp
void cekTombol() {
  if (digitalRead(buttonPin) == LOW && millis() - lastButtonPress > debounceDelay) {
    Serial.println("[TOMBOL] Ditekan!");
    lastButtonPress = millis();
    mintaBeriMakan("BUTTON");
  }
}
```

Fungsi ini membaca tombol fisik.

Karena tombol menggunakan `INPUT_PULLUP`, maka:

| Kondisi Tombol | Nilai yang Dibaca |
|---|---|
| Tidak ditekan | `HIGH` |
| Ditekan | `LOW` |

Jika tombol ditekan dan sudah melewati jeda debounce, ESP32 menjalankan:

```cpp
mintaBeriMakan("BUTTON");
```

Setelah servo bergerak, ESP32 akan mengirim status:

```text
SUCCESS_BUTTON
```

---

## 16. Fungsi `setup()`

```cpp
void setup() {
  Serial.begin(115200);

  feederServo.attach(servoPin);
  feederServo.write(0);
  pinMode(buttonPin, INPUT_PULLUP);

  prefs.begin("feeder_data", true);
  jadwalMakan = prefs.getString("list_jam", "");
  prefs.end();

  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) delay(500);

  configTime(25200, 0, "pool.ntp.org");
  espClient.setInsecure();
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(callback);
}
```

Fungsi `setup()` hanya berjalan sekali saat ESP32 pertama menyala.

Tugasnya:

1. Mengaktifkan Serial Monitor.
2. Menghubungkan servo ke pin `18`.
3. Menutup posisi awal servo ke sudut `0`.
4. Mengatur tombol fisik sebagai `INPUT_PULLUP`.
5. Mengambil jadwal yang tersimpan di memori ESP32.
6. Menghubungkan ESP32 ke WiFi.
7. Mengatur waktu menggunakan NTP dengan zona WIB.
8. Mengaktifkan koneksi TLS MQTT.
9. Menentukan server MQTT dan fungsi callback.

---

## 17. Fungsi `loop()`

```cpp
void loop() {
  if (!client.connected()) reconnectMqtt();

  client.loop();
  cekJadwal();
  cekTombol();
  prosesMakan();
}
```

Fungsi `loop()` berjalan terus-menerus selama ESP32 hidup.

Urutan kerjanya:

1. Mengecek koneksi MQTT.
2. Jika MQTT terputus, panggil `reconnectMqtt()`.
3. `client.loop()` memproses pesan MQTT masuk.
4. `cekJadwal()` mengecek apakah sudah masuk waktu makan otomatis.
5. `cekTombol()` mengecek apakah tombol fisik ditekan.
6. `prosesMakan()` menjalankan servo jika ada permintaan makan.

Bagian ini adalah pusat alur kerja ESP32.

---

## 18. Alur Kerja Manual dari Website

```text
User klik tombol di website
        ↓
Website publish payload "1"
        ↓
Topic: marky_petfeeder/command
        ↓
ESP32 menerima pesan di callback()
        ↓
mintaBeriMakan("MANUAL")
        ↓
prosesMakan()
        ↓
Servo membuka katup
        ↓
ESP32 publish SUCCESS_MANUAL
        ↓
Website mencatat riwayat
```

---

## 19. Alur Kerja Jadwal Otomatis

```text
User menambah jadwal di website
        ↓
Website publish daftar jadwal
        ↓
Topic: marky_petfeeder/schedule
        ↓
ESP32 menerima jadwal di callback()
        ↓
simpanJadwal()
        ↓
Jadwal disimpan ke Preferences
        ↓
cekJadwal() membandingkan waktu sekarang
        ↓
Jika cocok, mintaBeriMakan("AUTO")
        ↓
Servo membuka katup
        ↓
ESP32 publish SUCCESS_AUTO
```

---

## 20. Alur Kerja Tombol Fisik

```text
User menekan tombol fisik
        ↓
ESP32 membaca buttonPin LOW
        ↓
cekTombol()
        ↓
mintaBeriMakan("BUTTON")
        ↓
prosesMakan()
        ↓
Servo membuka katup
        ↓
ESP32 publish SUCCESS_BUTTON
```

---

## 21. Kesimpulan untuk Presentasi

Kode ESP32 ini bekerja sebagai pengendali utama alat Smart Pet Feeder.

Inti sistemnya adalah:

- Website hanya mengirim perintah dan jadwal.
- ESP32 menerima perintah melalui MQTT.
- ESP32 yang benar-benar menggerakkan servo.
- ESP32 mengirim status sukses setelah servo bergerak.
- Website mencatat riwayat berdasarkan status dari ESP32.

Dengan cara ini, sistem menjadi **closed-loop**, karena riwayat makan hanya dicatat ketika ESP32 sudah mengirim konfirmasi bahwa alat berhasil bekerja.

Kalimat singkat untuk presentasi:

> ESP32 berperan sebagai eksekutor utama. Website mengirim perintah melalui MQTT, ESP32 menjalankan servo untuk mengeluarkan makanan, lalu ESP32 mengirimkan status sukses kembali agar website dapat mencatat riwayat pemberian makan.
