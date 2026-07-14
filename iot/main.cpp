#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <driver/adc.h>

LiquidCrystal_I2C lcd(0x27, 16, 2);

// ================= SENSOR =================
#define TURBIDITY_PIN 34
#define ADC_MAX 4095
#define ADC_REF_V 3.3

// DS18B20
#define ONE_WIRE_BUS 13
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

// Turbidity Calibration
const float V_CLEAR = 1.645;
const float V_DIRTY = 0.762;

// ================= pH =================
int pin_ph = 32;
// tetap simpan nilai pH4 dan pH7 yang kamu punya
float CAL_V4 = 3.300f;    // tegangan saat buffer pH 4.0
float CAL_V7 = 2.585f;    // tegangan saat buffer pH 6.86 (pH7 ~6.86)
/*
  untuk pH9.18 kamu laporkan 2.000 - 2.170 V; kita ambil midpoint sebagai starting calibration:
  jika nanti kamu ingin lebih akurat, ukur beberapa kali di buffer pH9.18 dan ganti angka ini
*/
float CAL_V9 = 2.150f;    // <-- midpoint(2.000..2.170) = 2.085 V

// koefisien untuk pH = a*V^2 + b*V + c (diisi di setup)
double a_coef = 0.0, b_coef = 0.0, c_coef = 0.0;
bool useQuadratic = false;

// ================= WIFI ===================
const char* ssid = "wifi-iot";
const char* password = "password-iot";
const char* server_url = "http://labrobotika.go-web.my.id/server.php?apikey=";
const char* apikey = "e2907e0d85c49fcbff5ac006c696f6f9";

unsigned long lastSend = 0;
unsigned long lastLCD = 0;

// ================= WIFI CONNECT ===================
void wifiReconnect() {
  if (WiFi.status() == WL_CONNECTED) return;

  WiFi.disconnect();
  WiFi.begin(ssid, password);

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 5000) delay(200);

  if (WiFi.status() == WL_CONNECTED) Serial.println("WiFi Reconnected!");
}

// ================= SEND SERVER ===================
void kirimData(float suhu, float kekeruhan, float ph) {
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;

  String url = String(server_url) + apikey +
               "&suhu=" + suhu +
               "&kekeruhan=" + kekeruhan +
               "&phair=" + ph;

  http.begin(url);
  int code = http.GET();

  if (code == HTTP_CODE_OK) Serial.println("Server OK");
  else Serial.println("HTTP Error: " + String(code));

  http.end();
}

// ================= Kalkulasi koefisien kuadrat dari 3 titik ===================
// solve a*V^2 + b*V + c = pH  for 3 known (V,pH): (CAL_V4,4.0), (CAL_V7,6.86), (CAL_V9,9.18)
void computeQuadraticFrom3Points() {
  // matrix A (3x4) for augmented system
  double A[3][4];
  double V[3] = { (double)CAL_V4, (double)CAL_V7, (double)CAL_V9 };
  double P[3] = { 4.0, 6.86, 9.18 };

  for (int i=0;i<3;i++){
    A[i][0] = V[i]*V[i];
    A[i][1] = V[i];
    A[i][2] = 1.0;
    A[i][3] = P[i];
  }

  // Gaussian elimination
  for (int i=0;i<3;i++){
    // find pivot
    int pivot = i;
    for (int r=i+1;r<3;r++) if (fabs(A[r][i]) > fabs(A[pivot][i])) pivot = r;
    // swap if needed
    if (pivot != i) {
      for (int c=0;c<4;c++) {
        double t = A[i][c];
        A[i][c] = A[pivot][c];
        A[pivot][c] = t;
      }
    }
    // normalize row i
    double div = A[i][i];
    if (fabs(div) < 1e-12) continue;
    for (int c=i;c<4;c++) A[i][c] /= div;
    // eliminate rows below
    for (int r=i+1;r<3;r++){
      double f = A[r][i];
      for (int c=i;c<4;c++) A[r][c] -= f * A[i][c];
    }
  }

  // back substitution
  double x[3];
  for (int i=2;i>=0;i--){
    double s = A[i][3];
    for (int c=i+1;c<3;c++) s -= A[i][c] * x[c];
    double denom = A[i][i];
    if (fabs(denom) < 1e-12) x[i] = 0;
    else x[i] = s / denom;
  }

  a_coef = x[0];
  b_coef = x[1];
  c_coef = x[2];
  useQuadratic = true;

  Serial.println("Computed quadratic coefficients:");
  Serial.printf("a=%.9f b=%.9f c=%.9f\n", a_coef, b_coef, c_coef);
}

// convert volt -> pH using quadratic fit (if available) otherwise fallback to 2-point linear using V4,V7
float voltToPH_cal(float V) {
  if (useQuadratic) {
    double ph = a_coef*V*V + b_coef*V + c_coef;
    return (float)ph;
  } else {
    // fallback linear (pH4-pH7)
    float ph = 4.0f + (V - CAL_V4) * (6.86f - 4.0f) / (CAL_V7 - CAL_V4);
    return ph;
  }
}

// ================= SETUP ===================
void setup() {
  Serial.begin(9600);
  lcd.init(); lcd.backlight();

  analogSetPinAttenuation(TURBIDITY_PIN, ADC_11db);
  analogSetPinAttenuation(pin_ph, ADC_11db);
  adc1_config_width(ADC_WIDTH_12Bit);



  sensors.begin();
  WiFi.begin(ssid, password);

  // compute quadratic calibration from provided 3 points
  computeQuadraticFrom3Points();

  lcd.setCursor(0,0); lcd.print("Monitoring Ready");
  delay(1200);
}

// ================= LOOP (REALTIME) ===================
void loop() {

  wifiReconnect();

  // ===== Turbidity =====
  int adc_turb = analogRead(TURBIDITY_PIN);
  float volt_turb = adc_turb * (ADC_REF_V / ADC_MAX);

  float percent = (volt_turb - V_DIRTY) / (V_CLEAR - V_DIRTY) * 100.0;
  percent = constrain(percent, 0, 100);

  String kondisi;
  if (percent > 85) kondisi = "SgtJernih";
  else if (percent > 75) kondisi = "Jernih";
  else if (percent > 40) kondisi = "Keruh";
  else if (percent > 20) kondisi = "SgtKeruh";
  else kondisi = "Kotor";

  // ===== Suhu =====
  sensors.requestTemperatures();
  float suhu = sensors.getTempCByIndex(0);

  // ===== pH realtime no-sampling =====
  float volt_ph = (analogRead(pin_ph) * (ADC_REF_V / ADC_MAX));

  // use calibrated mapping
  float nilai_ph = voltToPH_cal(volt_ph);

  // ===== SERIAL =====
  Serial.println("============ DEBUG ============");
  Serial.printf("Turbidity: %.3fV | %.1f%% | %s\n", volt_turb, percent, kondisi.c_str());
  Serial.printf("Suhu: %.2f C\n", suhu);
  Serial.printf("pHVolt: %.3f V | pH: %.2f\n", volt_ph, nilai_ph);
  Serial.println("================================\n");

  // ===== LCD Every 700ms =====
  if (millis() - lastLCD > 700) {
    lcd.clear();
    lcd.setCursor(0, 0); lcd.printf("S:%.1fC pH:%.2f", suhu, nilai_ph);
    lcd.setCursor(0, 1); lcd.printf("%.0f%% %s", percent, kondisi.c_str());
    lastLCD = millis();
  }

  // ===== SEND SERVER Set 7 detik (kamu bisa ubah) =====
  if (millis() - lastSend > 7000) {
    kirimData(suhu, percent, nilai_ph);
    lastSend = millis();
  }
}
