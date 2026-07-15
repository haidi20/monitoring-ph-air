# Cara Running Program IoT ESP32 Lewat Arduino IDE

Catatan ini menjelaskan cara menjalankan file `main.cpp` ke perangkat ESP32 menggunakan Arduino IDE lewat kabel USB.

## 1. Perangkat yang Dibutuhkan

- Board ESP32.
- Kabel USB data, bukan kabel charge-only.
- Laptop/komputer dengan Arduino IDE.
- Sensor sesuai rangkaian program:
  - DS18B20 untuk suhu air.
  - Sensor kekeruhan air pada pin analog `34`.
  - Sensor pH air pada pin analog `32`.
  - LCD I2C alamat `0x27`, ukuran `16x2`.
- WiFi dengan nama dan password sesuai isi program:
  - SSID: `wifi-iot`
  - Password: `password-iot`

## 2. Install Arduino IDE

1. Download Arduino IDE dari website resmi Arduino.
2. Install dan buka Arduino IDE.
3. Sambungkan ESP32 ke komputer menggunakan kabel USB.

## 3. Tambahkan Board ESP32 di Arduino IDE

1. Buka menu `File > Preferences`.
2. Pada bagian `Additional Boards Manager URLs`, tambahkan URL berikut:

```text
https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
```

3. Klik `OK`.
4. Buka menu `Tools > Board > Boards Manager`.
5. Cari `esp32`.
6. Install package `esp32 by Espressif Systems`.

## 4. Pilih Board dan Port

1. Buka menu `Tools > Board`.
2. Pilih board ESP32 yang sesuai. Umumnya bisa pakai:
   - `ESP32 Dev Module`
3. Buka menu `Tools > Port`.
4. Pilih port USB ESP32, contoh:
   - Windows: `COM3`, `COM4`, atau sejenisnya.
   - Jika port tidak muncul, install driver USB sesuai chip board, biasanya `CH340` atau `CP210x`.

## 5. Install Library yang Dibutuhkan

Program `main.cpp` memakai beberapa library. Install lewat `Tools > Manage Libraries`:

- `LiquidCrystal I2C`
- `OneWire`
- `DallasTemperature`

Library berikut biasanya sudah tersedia dari core ESP32:

- `WiFi`
- `HTTPClient`
- `driver/adc`

## 6. Cara Membuka File `main.cpp` di Arduino IDE

Arduino IDE biasanya memakai file `.ino`, sedangkan project ini memakai `main.cpp`.

Cara paling mudah:

1. Buat folder baru, misalnya:

```text
monitoring_ph_air_esp32
```

2. Di dalam folder itu buat file:

```text
monitoring_ph_air_esp32.ino
```

3. Copy seluruh isi file berikut:

```text
D:\projects\monitoring-ph-air\iot\main.cpp
```

4. Paste ke file `.ino` yang baru dibuat.
5. Buka file `.ino` tersebut dengan Arduino IDE.

Catatan penting:

- Nama folder dan nama file `.ino` harus sama.
- Contoh benar:
  - Folder: `monitoring_ph_air_esp32`
  - File: `monitoring_ph_air_esp32.ino`

## 7. Cek Pengaturan Sebelum Upload

Di Arduino IDE, pastikan pengaturan berikut:

- `Tools > Board`: `ESP32 Dev Module`
- `Tools > Port`: port USB ESP32 yang aktif.
- `Tools > Upload Speed`: bisa pakai default, misalnya `921600` atau `115200`.
- `Tools > CPU Frequency`: default aman, biasanya `240MHz`.

## 8. Upload Program ke ESP32

1. Klik tombol `Verify` atau ikon centang untuk compile.
2. Jika compile berhasil, klik tombol `Upload` atau ikon panah kanan.
3. Tunggu sampai muncul pesan upload selesai.
4. Jika muncul tulisan `Connecting...` terus:
   - Tekan dan tahan tombol `BOOT` di ESP32.
   - Saat upload mulai berjalan, lepaskan tombol `BOOT`.

## 9. Buka Serial Monitor

1. Klik menu `Tools > Serial Monitor`.
2. Set baud rate ke:

```text
9600
```

3. Jika program berjalan, Serial Monitor akan menampilkan debug seperti:

```text
============ DEBUG ============
Turbidity: 1.645V | 100.0% | SgtJernih
Suhu: 26.50 C
pHVolt: 2.585 V | pH: 6.86
================================
```

Arti angka:

- `Turbidity` dalam Volt adalah tegangan sensor kekeruhan.
- `%` adalah hasil kekeruhan/kejernihan yang dikirim ke server.
- `Suhu` adalah suhu air dalam Celcius.
- `pHVolt` adalah tegangan sensor pH.
- `pH` adalah hasil pH setelah dikalibrasi.

## 10. Data yang Dikirim ke Server

Program akan mengirim data ke server setiap `7000 ms` atau 7 detik.

Endpoint server yang dipakai:

```text
http://43.156.137.225/server.php
```

Parameter yang dikirim:

- `apikey`: kode project di server.
- `suhu`: nilai suhu air.
- `kekeruhan`: nilai kekeruhan/kejernihan dalam persen.
- `phair`: nilai pH air.

Contoh URL yang dikirim ESP32:

```text
http://43.156.137.225/server.php?apikey=e2907e0d85c49fcbff5ac006c696f6f9&suhu=26.50&kekeruhan=100.00&phair=7.44
```

Jika berhasil, Serial Monitor akan menampilkan:

```text
Server OK
```

## 11. Troubleshooting

### Port USB Tidak Muncul

- Pastikan kabel USB adalah kabel data.
- Coba ganti port USB.
- Install driver `CH340` atau `CP210x` sesuai board ESP32.

### Upload Gagal atau Stuck di `Connecting...`

- Tekan tombol `BOOT` saat proses upload.
- Coba turunkan `Upload Speed` ke `115200`.
- Pastikan port yang dipilih benar.

### LCD Tidak Menampilkan Teks

- Cek kabel SDA dan SCL.
- Cek alamat LCD. Program memakai alamat `0x27`.
- Jika modul LCD memakai alamat lain, ubah kode:

```cpp
LiquidCrystal_I2C lcd(0x27, 16, 2);
```

Menjadi misalnya:

```cpp
LiquidCrystal_I2C lcd(0x3F, 16, 2);
```

### Sensor Suhu Menampilkan `-127`

- Biasanya sensor DS18B20 tidak terbaca.
- Cek kabel data sensor di pin `13`.
- Pastikan memakai resistor pull-up jika rangkaian DS18B20 membutuhkannya.

### Data Tidak Masuk ke Server

- Pastikan WiFi `wifi-iot` aktif dan password benar.
- Pastikan ESP32 mendapat koneksi internet.
- Cek Serial Monitor apakah muncul `Server OK` atau `HTTP Error`.
- Pastikan API key di program sama dengan API key project di server.

## 12. Catatan Pin Program

Pin yang dipakai di `main.cpp`:

| Komponen | Pin ESP32 | Keterangan |
| --- | --- | --- |
| Sensor kekeruhan | `34` | Input analog |
| Sensor pH | `32` | Input analog |
| DS18B20 | `13` | Data digital OneWire |
| LCD I2C SDA/SCL | Sesuai board ESP32 | Umumnya SDA `21`, SCL `22` |

## 13. Catatan Kalibrasi

Kalibrasi yang dipakai program:

```cpp
const float V_CLEAR = 1.645;
const float V_DIRTY = 0.762;
float CAL_V4 = 3.300f;
float CAL_V7 = 2.585f;
float CAL_V9 = 2.150f;
```

Keterangan:

- `V_CLEAR` dan `V_DIRTY` untuk sensor kekeruhan.
- `CAL_V4`, `CAL_V7`, dan `CAL_V9` untuk sensor pH.
- Jika hasil sensor tidak akurat, ukur ulang nilai tegangan sensor lalu update angka kalibrasi tersebut.
