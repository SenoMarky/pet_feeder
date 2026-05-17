#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <ESP32Servo.h>
#include <Preferences.h>
#include "time.h"

// ===================== KONFIGURASI =====================
const char* ssid = "Kost Pandu Lt2";
const char* password = "pandu2011";
const char* mqtt_server = "8d8aa6ee7d77456cb311e1218d37662d.s1.eu.hivemq.cloud";
const char* mqtt_user = "Marky";
const char* mqtt_password = "Marky123";
const int mqtt_port = 8883;

const char* TOPIC_COMMAND = "marky_petfeeder/command";
const char* TOPIC_SCHEDULE = "marky_petfeeder/schedule";
const char* TOPIC_STATUS = "marky_petfeeder/status";

const int servoPin = 18;
const int buttonPin = 4;
const unsigned long debounceDelay = 1000;

// ===================== OBJEK & STATE =====================
WiFiClientSecure espClient;
PubSubClient client(espClient);
Servo feederServo;
Preferences prefs;

String jadwalMakan = "";
String sumberPerintah = "";
bool mintaMakan = false;
int lastFedMinute = -1;
unsigned long lastButtonPress = 0;

// ===================== AKSI MAKAN =====================
void bukaKatup() {
  Serial.println("[AKSI] Membuka katup...");
  feederServo.write(45);
  delay(350);
  feederServo.write(0);
}

void mintaBeriMakan(String sumber) {
  mintaMakan = true;
  sumberPerintah = sumber;
}

void kirimStatusSukses() {
  if (sumberPerintah == "MANUAL") client.publish(TOPIC_STATUS, "SUCCESS_MANUAL");
  else if (sumberPerintah == "AUTO") client.publish(TOPIC_STATUS, "SUCCESS_AUTO");
  else if (sumberPerintah == "BUTTON") client.publish(TOPIC_STATUS, "SUCCESS_BUTTON");
}

void prosesMakan() {
  if (!mintaMakan) return;

  bukaKatup();
  kirimStatusSukses();
  mintaMakan = false;
  sumberPerintah = "";
}

// ===================== JADWAL =====================
void simpanJadwal(String jadwal) {
  jadwalMakan = jadwal;
  prefs.begin("feeder_data", false);
  prefs.putString("list_jam", jadwalMakan);
  prefs.end();
  Serial.println("[SYNC] Jadwal diperbarui: " + jadwalMakan);
}

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

// ===================== MQTT =====================
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

// ===================== INPUT =====================
void cekTombol() {
  if (digitalRead(buttonPin) == LOW && millis() - lastButtonPress > debounceDelay) {
    Serial.println("[TOMBOL] Ditekan!");
    lastButtonPress = millis();
    mintaBeriMakan("BUTTON");
  }
}

// ===================== SETUP & LOOP =====================
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

void loop() {
  if (!client.connected()) reconnectMqtt();

  client.loop();
  cekJadwal();
  cekTombol();
  prosesMakan();
}