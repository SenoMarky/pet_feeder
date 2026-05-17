#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <ESP32Servo.h>
#include <Preferences.h>
#include "time.h"

// ===================== KREDENSIAL =====================
const char* ssid          = "SSID_WIFI";
const char* password      = "PASSWORD_WIFI";

const char* mqtt_server   = "URL_SERVER_MQTT";
const int   mqtt_port     = 8883;
const char* mqtt_user     = "USERNAME_MQTT";
const char* mqtt_password = "MQTT_PASSWORD";

// ===================== TOPIK MQTT =====================
const char* TOPIC_COMMAND  = "marky_petfeeder/command";
const char* TOPIC_SCHEDULE = "marky_petfeeder/schedule";
const char* TOPIC_STATUS   = "marky_petfeeder/status";

// ===================== PIN HARDWARE =====================
const int servoPin = 18;
const int buttonPin = 4;

// ===================== KONFIGURASI =====================
const int servoOpenAngle = 45;
const int servoCloseAngle = 0;
const int servoOpenDurationMs = 350;

const unsigned long debounceDelay = 1000;
const long gmtOffsetSeconds = 25200; // GMT+7 / WIB
const int daylightOffsetSeconds = 0;

// ===================== OBJEK GLOBAL =====================
WiFiClientSecure espClient;
PubSubClient client(espClient);
Servo feederServo;
Preferences prefs;

// ===================== STATE APLIKASI =====================
String jadwalMakan = "";
String sumberPerintah = "";

bool mintaMakan = false;
int lastFedMinute = -1;
unsigned long lastButtonPress = 0;

// =====================================================
//  FUNGSI SERVO
// =====================================================
void bukaKatup() {
  Serial.println("[AKSI] Membuka katup makanan...");

  feederServo.write(servoOpenAngle);
  delay(servoOpenDurationMs);
  feederServo.write(servoCloseAngle);
}

// =====================================================
//  FUNGSI JADWAL
// =====================================================
void simpanJadwalKeMemori(const String& jadwalBaru) {
  jadwalMakan = jadwalBaru;

  prefs.begin("feeder_data", false);
  prefs.putString("list_jam", jadwalMakan);
  prefs.end();

  Serial.println("[SYNC] Jadwal diperbarui: " + jadwalMakan);
}

void muatJadwalDariMemori() {
  prefs.begin("feeder_data", true);
  jadwalMakan = prefs.getString("list_jam", "");
  prefs.end();

  Serial.println("[INIT] Jadwal tersimpan: " + jadwalMakan);
}

void checkLocalSchedule() {
  struct tm timeinfo;

  if (!getLocalTime(&timeinfo)) {
    return;
  }

  char currentTime[6];
  sprintf(currentTime, "%02d:%02d", timeinfo.tm_hour, timeinfo.tm_min);

  bool jadwalCocok = jadwalMakan.indexOf(currentTime) >= 0;
  bool belumMakanDiMenitIni = lastFedMinute != timeinfo.tm_min;

  if (jadwalCocok && belumMakanDiMenitIni) {
    Serial.printf("[JADWAL] Waktu cocok (%s)!\n", currentTime);

    mintaMakan = true;
    sumberPerintah = "AUTO";
    lastFedMinute = timeinfo.tm_min;
  }
}

// =====================================================
//  FUNGSI MQTT
// =====================================================
void publishStatusSukses() {
  if (sumberPerintah == "MANUAL") {
    client.publish(TOPIC_STATUS, "SUCCESS_MANUAL");
  } else if (sumberPerintah == "AUTO") {
    client.publish(TOPIC_STATUS, "SUCCESS_AUTO");
  } else if (sumberPerintah == "BUTTON") {
    client.publish(TOPIC_STATUS, "SUCCESS_BUTTON");
  }
}

void callback(char* topic, byte* payload, unsigned int length) {
  String currentTopic(topic);
  String message((char*) payload, length);

  if (currentTopic == TOPIC_COMMAND && message == "1") {
    Serial.println("[MANUAL] Perintah masuk dari website...");

    mintaMakan = true;
    sumberPerintah = "MANUAL";
    return;
  }

  if (currentTopic == TOPIC_SCHEDULE) {
    simpanJadwalKeMemori(message);
    return;
  }
}

void reconnectMqtt() {
  while (!client.connected()) {
    Serial.print("Konek ke HiveMQ...");

    String clientId = "ESP32Feeder-" + String(random(0xffff), HEX);

    if (client.connect(clientId.c_str(), mqtt_user, mqtt_password)) {
      Serial.println(" BERHASIL!");

      client.subscribe(TOPIC_COMMAND);
      client.subscribe(TOPIC_SCHEDULE);
    } else {
      Serial.println(" GAGAL, coba lagi 5 detik...");
      delay(5000);
    }
  }
}

// =====================================================
//  FUNGSI KONEKSI DAN INPUT
// =====================================================
void connectWifi() {
  Serial.print("Menghubungkan WiFi");
  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    Serial.print(".");
    delay(500);
  }

  Serial.println(" BERHASIL!");
  Serial.println("IP ESP32: " + WiFi.localIP().toString());
}

void setupTime() {
  configTime(gmtOffsetSeconds, daylightOffsetSeconds, "pool.ntp.org");
  Serial.println("[INIT] Sinkronisasi waktu NTP dimulai...");
}

void checkPhysicalButton() {
  bool tombolDitekan = digitalRead(buttonPin) == LOW;
  bool debounceAman = millis() - lastButtonPress > debounceDelay;

  if (tombolDitekan && debounceAman) {
    Serial.println("[TOMBOL] Tombol fisik ditekan!");

    mintaMakan = true;
    sumberPerintah = "BUTTON";
    lastButtonPress = millis();
  }
}

void prosesPermintaanMakan() {
  if (!mintaMakan) {
    return;
  }

  bukaKatup();
  publishStatusSukses();

  mintaMakan = false;
  sumberPerintah = "";
}

// =====================================================
//  SETUP
// =====================================================
void setup() {
  Serial.begin(115200);

  feederServo.attach(servoPin);
  feederServo.write(servoCloseAngle);

  pinMode(buttonPin, INPUT_PULLUP);

  muatJadwalDariMemori();
  connectWifi();
  setupTime();

  espClient.setInsecure();
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(callback);
}

// =====================================================
//  LOOP UTAMA
// =====================================================
void loop() {
  if (!client.connected()) {
    reconnectMqtt();
  }

  client.loop();

  checkLocalSchedule();
  checkPhysicalButton();
  prosesPermintaanMakan();
}