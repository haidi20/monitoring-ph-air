#include <Wire.h>              // Library komunikasi I2C, dipakai LCD I2C.
#include <LiquidCrystal_I2C.h> // Library untuk mengontrol LCD 16x2 lewat modul I2C.
#include <OneWire.h>           // Library komunikasi 1-Wire untuk sensor suhu DS18B20.
#include <DallasTemperature.h> // Library pembacaan suhu dari DS18B20 dalam Celcius.
#include <WiFi.h>              // Library koneksi WiFi ESP32.
#include <HTTPClient.h>        // Library untuk mengirim data ke server lewat HTTP GET.
#include <driver/adc.h>        // Library konfigurasi ADC ESP32, dipakai untuk input analog.

/*
  LCD memakai alamat I2C 0x27 dengan ukuran 16 kolom dan 2 baris.
  Jika LCD tidak tampil, alamat I2C kadang bisa 0x3F tergantung modul.
*/
LiquidCrystal_I2C lcd(0x27, 16, 2);

// ================= SENSOR =================
/*
  TURBIDITY_PIN adalah pin analog untuk sensor kekeruhan air.
  Pin 34 pada ESP32 hanya input, cocok untuk membaca sinyal analog.
*/
#define TURBIDITY_PIN 34

/*
  ADC_MAX = 4095 karena ESP32 dikonfigurasi 12-bit.
  Artinya hasil analogRead berada di rentang 0 sampai 4095.
  - 0    kira-kira berarti 0 Volt
  - 4095 kira-kira berarti 3.3 Volt
*/
#define ADC_MAX 4095

/*
  ADC_REF_V = 3.3 adalah tegangan referensi ESP32 dalam Volt.
  Rumus konversi ADC ke Volt:
  tegangan = nilai_adc * (3.3 / 4095)
*/
#define ADC_REF_V 3.3

// ================= SENSOR SUHU DS18B20 =================
/*
  ONE_WIRE_BUS adalah pin data sensor suhu DS18B20.
  Sensor DS18B20 mengirim data digital, bukan analog.
  Hasil akhirnya langsung berupa derajat Celcius.
*/
#define ONE_WIRE_BUS 13
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

// ================= KALIBRASI KEKERUHAN =================
/*
  V_CLEAR dan V_DIRTY adalah nilai tegangan hasil kalibrasi sensor kekeruhan.
  V_CLEAR = tegangan saat air dianggap paling jernih.
  V_DIRTY = tegangan saat air dianggap paling kotor/keruh.

  Program mengubah tegangan sensor menjadi persen kejernihan:
  percent = (volt_turb - V_DIRTY) / (V_CLEAR - V_DIRTY) * 100

  Arti angka percent yang dikirim ke server:
  - 100 berarti sangat jernih menurut kalibrasi ini
  - 0 berarti sangat keruh/kotor menurut kalibrasi ini
  - nilai di tengah menunjukkan tingkat kejernihan relatif
*/
const float V_CLEAR = 1.645;
const float V_DIRTY = 0.762;

// ================= SENSOR pH =================
/*
  pin_ph adalah pin analog sensor pH.
  Sensor pH menghasilkan tegangan analog, lalu program mengubahnya menjadi angka pH.
*/
int pin_ph = 32;

/*
  Nilai kalibrasi pH berikut adalah tegangan saat probe dicelupkan ke cairan buffer.
  Angka ini sangat penting karena menentukan akurasi hasil pH.

  CAL_V4 = 3.300 berarti saat cairan pH 4, sensor terbaca sekitar 3.300 Volt.
  CAL_V7 = 2.585 berarti saat cairan pH 6.86 atau mendekati pH 7, sensor terbaca 2.585 Volt.
  CAL_V9 = 2.150 berarti saat cairan pH 9.18, sensor terbaca sekitar 2.150 Volt.

  Jika probe atau modul pH diganti, angka kalibrasi ini sebaiknya diukur ulang.
*/
float CAL_V4 = 3.300f;
float CAL_V7 = 2.585f;
float CAL_V9 = 2.150f;

/*
  Koefisien a, b, c dipakai untuk rumus kuadrat:
  pH = a * V^2 + b * V + c

  V adalah tegangan dari sensor pH.
  Rumus kuadrat dipakai karena hubungan tegangan dan pH tidak selalu lurus sempurna.
*/
double a_coef = 0.0, b_coef = 0.0, c_coef = 0.0;

/*
  useQuadratic menandakan apakah kalibrasi 3 titik sudah berhasil dihitung.
  true  = gunakan rumus kuadrat dari 3 titik kalibrasi.
  false = gunakan rumus linear sederhana pH 4 dan pH 7.
*/
bool useQuadratic = false;

// ================= WIFI DAN SERVER ===================
/*
  ssid dan password adalah nama WiFi dan password yang dipakai ESP32.
  server_url adalah endpoint Laravel untuk menerima data sensor.
  apikey dipakai server untuk mengenali project yang aktif.
*/
const char* ssid = "Proo";
const char* password = "samarinda";
const char* server_url = "http://43.156.137.225/server.php?apikey=";
const char* apikey = "e2907e0d85c49fcbff5ac006c696f6f9";

/*
  lastSend dan lastLCD menyimpan waktu terakhir aksi dilakukan.
  millis() menghasilkan jumlah milidetik sejak ESP32 menyala.
  Dengan cara ini program bisa berjalan realtime tanpa delay panjang.
*/
unsigned long lastSend = 0;
unsigned long lastLCD = 0;

// ================= WIFI CONNECT ===================
/*
  Method wifiReconnect()
  Tujuan:
  - Mengecek apakah ESP32 masih terhubung ke WiFi.
  - Jika putus, ESP32 mencoba konek ulang.

  Angka penting:
  - 5000 berarti batas waktu mencoba konek ulang adalah 5000 ms = 5 detik.
  - delay(200) berarti pengecekan status WiFi dilakukan tiap 200 ms.
*/
void wifiReconnect() {
  // Jika WiFi masih tersambung, fungsi langsung selesai.
  if (WiFi.status() == WL_CONNECTED) return;

  // Putuskan koneksi lama, lalu mulai koneksi baru memakai ssid dan password.
  WiFi.disconnect();
  WiFi.begin(ssid, password);

  // Simpan waktu mulai reconnect agar bisa diberi batas maksimal 5 detik.
  unsigned long start = millis();

  // Selama belum konek dan belum lewat 5 detik, tunggu sebentar lalu cek lagi.
  while (WiFi.status() != WL_CONNECTED && millis() - start < 5000) delay(200);

  // Jika berhasil terkoneksi, tampilkan informasi di Serial Monitor.
  if (WiFi.status() == WL_CONNECTED) Serial.println("WiFi Reconnected!");
}

// ================= SEND SERVER ===================
/*
  Method kirimData(float suhu, float kekeruhan, float ph)
  Tujuan:
  - Mengirim hasil sensor ke server Laravel.

  Parameter:
  - suhu      = angka suhu air dalam Celcius, contoh 26.50.
  - kekeruhan = angka kejernihan/kekeruhan dalam persen, contoh 100.00.
  - ph        = angka pH air, contoh 7.44.

  Data dikirim lewat HTTP GET dengan format URL:
  server.php dengan parameter apikey, suhu, kekeruhan, dan phair.
*/
void kirimData(float suhu, float kekeruhan, float ph) {
  // Jika WiFi belum tersambung, data tidak dikirim agar tidak error.
  if (WiFi.status() != WL_CONNECTED) return;

  HTTPClient http;

  // Gabungkan alamat server, apikey, dan nilai sensor menjadi satu URL.
  String url = String(server_url) + apikey +
               "&suhu=" + suhu +
               "&kekeruhan=" + kekeruhan +
               "&phair=" + ph;

  // Mulai request HTTP ke URL yang sudah dibuat.
  http.begin(url);

  // Jalankan request GET dan simpan kode balasan server.
  // HTTP_CODE_OK bernilai 200, artinya server menerima request dengan sukses.
  int code = http.GET();

  if (code == HTTP_CODE_OK) Serial.println("Server OK");
  else Serial.println("HTTP Error: " + String(code));

  // Tutup koneksi HTTP agar memori tidak boros.
  http.end();
}

// ================= KALKULASI KOEFISIEN pH ===================
/*
  Method computeQuadraticFrom3Points()
  Tujuan:
  - Menghitung koefisien a, b, c dari 3 titik kalibrasi pH.
  - Titik yang dipakai:
    1. CAL_V4 menghasilkan pH 4.00
    2. CAL_V7 menghasilkan pH 6.86
    3. CAL_V9 menghasilkan pH 9.18

  perlu 3 titik:
  - Rumus kuadrat punya 3 variabel: a, b, c.
  - Untuk menemukan 3 variabel, dibutuhkan 3 data kalibrasi.

  Output method ini:
  - a_coef, b_coef, c_coef akan berisi angka hasil perhitungan.
  - useQuadratic menjadi true agar pembacaan pH memakai rumus kuadrat.

  ADC adalah singkatan dari Analog to Digital Converter.

  alur ph:
  1. analogRead(pin_ph)
   Membaca sensor pH sebagai angka ADC

  2. analogRead(pin_ph) * (ADC_REF_V / ADC_MAX)
    Mengubah ADC menjadi Volt

  3. computeQuadraticFrom3Points()
    Menghitung rumus kalibrasi pH dari 3 titik referensi

  4. voltToPH_cal(volt_ph)
    Mengubah Volt menjadi nilai pH memakai rumus kalibrasi
*/
void computeQuadraticFrom3Points() {
  /*
    METHOD INI ADALAH PROSES RUMUS KALIBRASI pH 3 TITIK.

    Tujuan utama:
    - Mencari nilai koefisien a, b, dan c.
    - Koefisien ini dipakai pada rumus kuadrat:

      pH = a * V^2 + b * V + c

    - Sensor pH membaca tegangan Volt, bukan langsung angka pH.
    - Kita punya 3 data kalibrasi dari cairan buffer:
      1. Tegangan CAL_V4 harus menghasilkan pH 4.00.
      2. Tegangan CAL_V7 harus menghasilkan pH 6.86.
      3. Tegangan CAL_V9 harus menghasilkan pH 9.18.
    - Dari 3 data itu, program mencari rumus yang paling pas agar tegangan
      sensor bisa dikonversi menjadi angka pH.

    rumus kuadrat
    - Rumus kuadrat punya 3 angka yang dicari: a, b, c.
    - Karena kita punya 3 titik kalibrasi, maka 3 angka itu bisa dihitung.
    - Rumus kuadrat biasanya lebih fleksibel daripada rumus garis lurus 2 titik.

    Matrix A berukuran 3x4 dipakai untuk menyusun sistem persamaan linear.
    Tiap baris bentuknya:

      [V^2, V, 1, pH]

    Contoh konsep persamaannya:

      a*V4^2 + b*V4 + c = 4.00
      a*V7^2 + b*V7 + c = 6.86
      a*V9^2 + b*V9 + c = 9.18

    Angka 1 pada kolom ketiga adalah pasangan untuk koefisien c,
    karena c adalah konstanta yang tidak dikali tegangan.
  */
  double A[3][4];

  /*
    Array V menyimpan 3 tegangan hasil kalibrasi.
    Urutannya harus sama dengan array P.

    V[0] = CAL_V4, tegangan saat pH 4.00.
    V[1] = CAL_V7, tegangan saat pH 6.86.
    V[2] = CAL_V9, tegangan saat pH 9.18.
  */
  double V[3] = { (double)CAL_V4, (double)CAL_V7, (double)CAL_V9 };

  /*
    Array P menyimpan nilai pH target dari masing-masing tegangan.
    P[0] berpasangan dengan V[0], P[1] dengan V[1], dan P[2] dengan V[2].
  */
  double P[3] = { 4.0, 6.86, 9.18 };

  /*
    Proses ini mengisi matrix A dari 3 titik kalibrasi.

    Untuk setiap titik kalibrasi:
    - Kolom 0 diisi V^2, karena rumus punya bagian a * V^2.
    - Kolom 1 diisi V, karena rumus punya bagian b * V.
    - Kolom 2 diisi 1, karena rumus punya konstanta c.
    - Kolom 3 diisi pH target, yaitu hasil yang harus dicapai.
  */
  for (int i=0;i<3;i++){
    A[i][0] = V[i]*V[i]; // Kolom V^2 untuk mencari koefisien a.
    A[i][1] = V[i];      // Kolom V untuk mencari koefisien b.
    A[i][2] = 1.0;       // Kolom konstanta untuk mencari koefisien c.
    A[i][3] = P[i];      // Kolom hasil pH target.
  }

  /*
    Gaussian elimination adalah metode matematika untuk menyelesaikan
    persamaan linear.

    Tujuannya:
    - Mengubah matrix menjadi bentuk segitiga atas.
    - Setelah bentuknya segitiga, nilai a, b, c bisa dihitung dari bawah
      menggunakan proses back substitution.

    Gambaran sederhananya:
    - Baris pertama dipakai untuk menghilangkan nilai di bawah kolom pertama.
    - Baris kedua dipakai untuk menghilangkan nilai di bawah kolom kedua.
    - Setelah itu persamaan menjadi lebih mudah diselesaikan.
  */
  for (int i=0;i<3;i++){
    /*
      Pivot adalah angka utama pada kolom yang sedang diproses.

      - Agar pembagian lebih stabil.
      - Agar tidak mudah error jika angka pivot terlalu kecil.
      - Ini membantu mengurangi kesalahan pembulatan angka desimal.
    */
    int pivot = i;
    for (int r=i+1;r<3;r++) if (fabs(A[r][i]) > fabs(A[pivot][i])) pivot = r;

    /*
      Jika pivot terbesar ada di baris lain, barisnya ditukar.
      Pertukaran baris tidak mengubah arti persamaan, hanya mengubah urutan
      agar perhitungan lebih aman.
    */
    if (pivot != i) {
      for (int c=0;c<4;c++) {
        double t = A[i][c];
        A[i][c] = A[pivot][c];
        A[pivot][c] = t;
      }
    }

    /*
      Normalisasi baris pivot.

      Maksudnya:
      - Nilai utama pada baris ini dibuat menjadi 1.
      - Caranya seluruh angka pada baris dibagi dengan nilai pivot.

      Alasan proses ini diperlukan:
      - Supaya proses menghilangkan angka di baris bawah lebih mudah.
    */
    double div = A[i][i];

    /*
      1e-12 adalah angka yang sangat kecil.
      Jika pembagi terlalu dekat dengan nol, pembagian bisa menghasilkan
      error besar. Karena itu baris dilewati jika pembaginya terlalu kecil.
    */
    if (fabs(div) < 1e-12) continue;
    for (int c=i;c<4;c++) A[i][c] /= div;

    /*
      Eliminasi baris di bawah pivot.

      Tujuannya:
      - Membuat nilai pada kolom yang sama di baris bawah menjadi 0.
      - Ini yang membuat matrix berubah menjadi bentuk segitiga atas.

      f adalah faktor pengurang. Baris bawah dikurangi dengan baris pivot
      yang sudah dikalikan faktor tersebut.
    */
    for (int r=i+1;r<3;r++){
      double f = A[r][i];
      for (int c=i;c<4;c++) A[r][c] -= f * A[i][c];
    }
  }

  /*
    Back substitution adalah proses menghitung nilai akhir dari bawah ke atas.

    Alasan perhitungan dimulai dari bawah:
    - Setelah Gaussian elimination, baris paling bawah biasanya hanya punya
      satu variabel utama yang belum diketahui.
    - Setelah variabel bawah ketemu, nilainya dipakai untuk menghitung
      variabel di baris atasnya.

    x[0] = a, yaitu koefisien untuk V^2.
    x[1] = b, yaitu koefisien untuk V.
    x[2] = c, yaitu konstanta.
  */
  double x[3];
  for (int i=2;i>=0;i--){
    /*
      s dimulai dari nilai pH target pada kolom terakhir.
      Lalu dikurangi bagian variabel yang sudah diketahui dari proses bawah.
    */
    double s = A[i][3];
    for (int c=i+1;c<3;c++) s -= A[i][c] * x[c];

    /*
      denom adalah angka pembagi untuk variabel yang sedang dicari.
      Jika terlalu kecil, isi 0 agar tidak terjadi pembagian dengan nol.
    */
    double denom = A[i][i];
    if (fabs(denom) < 1e-12) x[i] = 0;
    else x[i] = s / denom;
  }

  /*
    Simpan hasil akhir koefisien.

    Setelah ini rumus:
      pH = a_coef * V^2 + b_coef * V + c_coef

    sudah bisa dipakai oleh method voltToPH_cal().
  */
  a_coef = x[0];
  b_coef = x[1];
  c_coef = x[2];

  /*
    useQuadratic dibuat true karena koefisien kuadrat sudah dihitung.
    Artinya pembacaan pH berikutnya akan memakai rumus kuadrat, bukan rumus
    linear fallback.
  */
  useQuadratic = true;

  /*
    Tampilkan koefisien di Serial Monitor untuk pengecekan.
    Angka ini berguna jika ingin melihat hasil rumus kalibrasi yang dibuat
    dari 3 titik buffer pH.
  */
  Serial.println("Computed quadratic coefficients:");
  Serial.printf("a=%.9f b=%.9f c=%.9f\n", a_coef, b_coef, c_coef);
}

/*
  Method voltToPH_cal(float V)
  Tujuan:
  - Mengubah tegangan sensor pH menjadi angka pH.

  Parameter:
  - V = tegangan hasil sensor pH dalam Volt.

  Return:
  - angka pH, contoh 7.44.

  Arti angka pH:
  - pH < 7  berarti air cenderung asam.
  - pH = 7  berarti netral.
  - pH > 7  berarti air cenderung basa.


*/
float voltToPH_cal(float V) {
  if (useQuadratic) {
    // RUMUS: Gunakan rumus kuadrat dari 3 titik kalibrasi pH.
    // Rumusnya: pH = a * V^2 + b * V + c.
    double ph = a_coef*V*V + b_coef*V + c_coef;
    return (float)ph;
  } else {
    /*
      Fallback linear dipakai kalau koefisien kuadrat belum tersedia.
      Rumus ini hanya memakai 2 titik kalibrasi:
      - Titik 1: tegangan CAL_V4 menghasilkan pH 4.00.
      - Titik 2: tegangan CAL_V7 menghasilkan pH 6.86.

      RUMUS: Kalibrasi linear / interpolasi garis lurus.
      Bentuk umum rumus garis dari 2 titik adalah:

      y = y1 + (x - x1) * (y2 - y1) / (x2 - x1)

      Pada sensor pH:
      - x  = V, yaitu tegangan sensor yang sedang dibaca.
      - x1 = CAL_V4, yaitu tegangan saat larutan pH 4.00.
      - y1 = 4.00, yaitu nilai pH pada titik pertama.
      - x2 = CAL_V7, yaitu tegangan saat larutan pH 6.86.
      - y2 = 6.86, yaitu nilai pH pada titik kedua.

      Maka rumusnya menjadi:

      pH = 4.00 + (V - CAL_V4) * (6.86 - 4.00) / (CAL_V7 - CAL_V4)
    */
    float ph = 4.0f + (V - CAL_V4) * (6.86f - 4.0f) / (CAL_V7 - CAL_V4);
    return ph;
  }
}

// ================= SETUP ===================
/*
  Method setup()
  Tujuan:
  - Dijalankan satu kali saat ESP32 pertama menyala atau reset.
  - Menyiapkan Serial Monitor, LCD, ADC, sensor suhu, WiFi, dan kalibrasi pH.
*/
void setup() {
  // Serial 9600 berarti komunikasi Serial Monitor berjalan di 9600 baud.
  Serial.begin(9600);

  // Inisialisasi LCD dan nyalakan lampu belakangnya.
  lcd.init();
  lcd.backlight();

  /*
    ADC_11db memperluas rentang pembacaan ADC ESP32 agar bisa membaca tegangan mendekati 3.3V.
    Ini penting untuk sensor kekeruhan dan pH karena keluarannya berupa tegangan analog.
  */
  analogSetPinAttenuation(TURBIDITY_PIN, ADC_11db);
  analogSetPinAttenuation(pin_ph, ADC_11db);

  // RUMUS/SETTING: 12 bit membuat analogRead menghasilkan angka 0 sampai 4095.
  // analogReadResolution(12) lebih cocok untuk Arduino IDE ESP32 core versi baru.
  analogReadResolution(12);

  // Mulai sensor suhu DS18B20.
  sensors.begin();

  // Mulai koneksi WiFi.
  WiFi.begin(ssid, password);

  // RUMUS: Hitung koefisien a, b, c untuk rumus kalibrasi pH.
  // Proses ini membuat ESP32 bisa mengubah tegangan sensor menjadi angka pH.
  computeQuadraticFrom3Points();

  // Tampilkan status awal pada LCD selama 1.2 detik.
  lcd.setCursor(0,0);
  lcd.print("Monitoring Ready");
  delay(1200);
}

// ================= LOOP REALTIME ===================
/*
  Method loop()
  Tujuan:
  - Dijalankan berulang-ulang selama ESP32 menyala.
  - Membaca sensor, menghitung angka sensor, menampilkan ke LCD, dan mengirim ke server.

  Alur utama:
  1. Pastikan WiFi tersambung.
  2. Baca sensor kekeruhan.
  3. Baca sensor suhu.
  4. Baca sensor pH.
  5. Tampilkan hasil ke Serial Monitor.
  6. Update LCD tiap 700 ms.
  7. Kirim data ke server tiap 7000 ms atau 7 detik.
*/
void loop() {

  // Pastikan WiFi tetap tersambung. Jika putus, coba konek ulang.
  wifiReconnect();

  // ===== KEKERUHAN AIR =====
  /*
    analogRead(TURBIDITY_PIN) menghasilkan angka ADC 0 sampai 4095.
    Semakin besar/kecil nilai tergantung karakter sensor dan kalibrasi tegangan.
  */
  int adc_turb = analogRead(TURBIDITY_PIN);

  // RUMUS: Ubah nilai ADC kekeruhan menjadi tegangan Volt.
  // Rumusnya: tegangan = nilai_adc * (3.3 / 4095).
  float volt_turb = adc_turb * (ADC_REF_V / ADC_MAX);

  /*
    Ubah tegangan menjadi persen kejernihan berdasarkan V_CLEAR dan V_DIRTY.
    Hasil percent dikirim ke server sebagai field kekeruhan.

    Contoh arti angka:
    - 100.00 = sangat jernih
    - 80.00  = jernih
    - 50.00  = keruh sedang
    - 10.00  = sangat keruh/kotor
  */
  // RUMUS: Kalibrasi kekeruhan dari tegangan menjadi persen.
  // Rumusnya: persen = (tegangan_sensor - tegangan_air_kotor) / (tegangan_air_jernih - tegangan_air_kotor) * 100.
  float percent = (volt_turb - V_DIRTY) / (V_CLEAR - V_DIRTY) * 100.0;

  // Batasi hasil agar tidak kurang dari 0 dan tidak lebih dari 100.
  percent = constrain(percent, 0, 100);

  /*
    Buat label kondisi dari nilai percent.
    Label ini hanya ditampilkan di LCD/Serial, sedangkan server menerima angka percent.
  */
  String kondisi;
  if (percent > 85) kondisi = "SgtJernih";      // 85-100: sangat jernih.
  else if (percent > 75) kondisi = "Jernih";    // 75-85: jernih.
  else if (percent > 40) kondisi = "Keruh";     // 40-75: keruh.
  else if (percent > 20) kondisi = "SgtKeruh";  // 20-40: sangat keruh.
  else kondisi = "Kotor";                       // 0-20: kotor.

  // ===== SUHU AIR =====
  // Minta sensor DS18B20 melakukan pembacaan suhu terbaru.
  sensors.requestTemperatures();

  /*
    Ambil suhu sensor pertama dalam Celcius.
    Hasil suhu dikirim ke server sebagai field suhu.

    Contoh arti angka:
    - 26.50 berarti suhu air 26.50 derajat Celcius.
    - Jika hasil -127, biasanya sensor DS18B20 tidak terbaca atau kabel bermasalah.
  */
  float suhu = sensors.getTempCByIndex(0);

  // ===== pH AIR =====
  // Baca tegangan analog sensor pH lalu konversi dari ADC ke Volt.
  float volt_ph = (analogRead(pin_ph) * (ADC_REF_V / ADC_MAX));

  /*
    Ubah tegangan pH menjadi nilai pH memakai rumus kalibrasi.
    Hasil nilai_ph dikirim ke server sebagai field phair.
  */
  float nilai_ph = voltToPH_cal(volt_ph);

  // ===== SERIAL MONITOR =====
  /*
    Bagian ini membantu debugging saat ESP32 disambungkan ke komputer.
    Nilai yang tampil:
    - volt_turb = tegangan sensor kekeruhan.
    - percent   = persen kejernihan/kekeruhan.
    - kondisi   = label kondisi air.
    - suhu      = suhu air dalam Celcius.
    - volt_ph   = tegangan sensor pH.
    - nilai_ph  = hasil pH setelah kalibrasi.
  */
  Serial.println("============ DEBUG ============");
  Serial.printf("Turbidity: %.3fV | %.1f%% | %s\n", volt_turb, percent, kondisi.c_str());
  Serial.printf("Suhu: %.2f C\n", suhu);
  Serial.printf("pHVolt: %.3f V | pH: %.2f\n", volt_ph, nilai_ph);
  Serial.println("================================\n");

  // ===== LCD SETIAP 700 ms =====
  /*
    LCD diperbarui tiap 700 ms agar tampilan realtime tetapi tidak terlalu sering berkedip.
    700 ms = 0.7 detik.
  */
  if (millis() - lastLCD > 700) {
    lcd.clear();

    // Baris pertama LCD: suhu dan pH.
    lcd.setCursor(0, 0);
    lcd.printf("S:%.1fC pH:%.2f", suhu, nilai_ph);

    // Baris kedua LCD: persen kejernihan/kekeruhan dan label kondisi.
    lcd.setCursor(0, 1);
    lcd.printf("%.0f%% %s", percent, kondisi.c_str());

    // Simpan waktu update LCD terakhir.
    lastLCD = millis();
  }

  // ===== KIRIM SERVER SETIAP 7 DETIK =====
  /*
    Data dikirim tiap 7000 ms = 7 detik.
    Angka ini bisa diubah:
    - lebih kecil: data lebih realtime, tapi server lebih sering menerima request.
    - lebih besar: server lebih ringan, tapi data lebih jarang diperbarui.
  */
  if (millis() - lastSend > 7000) {
    // Kirim suhu, percent kekeruhan, dan nilai pH ke server Laravel.
    kirimData(suhu, percent, nilai_ph);

    // Simpan waktu kirim terakhir.
    lastSend = millis();
  }
}
