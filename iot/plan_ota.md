# Perencanaan OTA — Update Firmware Tanpa Kabel USB

## Ringkasan

## St proteg Fase
## Fase 1 — Implementasi ArduinoOTA (diterapkan sekarang)
## Fase 2 — Flash pertama via USB (sekali)
## Fase 3 — Update firmware berikutnya via WiFi
## Fase 4 — Troubleshooting OTA

## Ringkasan

Setelah fase pertama selesai, update firmware ESP32 tidak butuh kabel USB
lagi, selama ESP32 dan komputer build berada di jaringan WiFi yang sama.

Skema kerja:

1. Build firmware di Mac pakai `python3 iot/build.py`.
2. Flash pertama tetap via USB untuk sekali saja.
3. Update berikutnya dikirim lewat WiFi dengan ArduinoOTA.

## Fase 1 — Implementasi ArduinoOTA (diterapkan sekarang)

Yang ditambahkan pada file `iot/running/running.ino`:

1. `#include <ArduinoOTA.h>` di blok include.
2. Inisialisasi `ArduinoOTA` di dalam `setup()` setelah koneksi WiFi aktif.
3. Panggil `ArduinoOTA.handle()` di awal `loop()` agar update bisa masuk
   kapan pun selama perangkat menyala.

Informasi koneksi OTA yang dipakai:

| Item | Nilai |
|---|---|
| Hostname | `monitoring-ph-air` |
| Password OTA | `esp32air` |
| Port | `3232` (default ArduinoOTA) |

## Fase 2 — Flash pertama via USB (sekali)

Jalankan sekali, setelah konfigurasi ini aktif di `running.ino`:

```bash
python3 iot/build.py --upload
```

Script akan meminta port USB. Pada macOS biasanya:

```text
/dev/cu.usbserial-XXXX
```

Setelah fase ini, USB tidak diperlukan lagi untuk update firmware.

## Fase 3 — Update firmware berikutnya via WiFi

Pastikan:

1. Mac dan ESP32 berada di WiFi yang sama.
2. ESP32 sudah online, dapat dilihat dari log Serial Monitor atau LCD.
3. Firmware lama sudah memiliki `ArduinoOTA` aktif.

Dua cara update via WiFi:

### Cara A — lewat `python3 iot/build.py`

```bash
python3 iot/build.py --ota
```

Script akan men-scan host `monitoring-ph-air.local` dan mengirim firmware
hasil build lewat protokol OTA Arduino.

### Cara B — pakai `arduino-cli` langsung

```bash
arduino-cli upload \
  --input-dir iot/build \
  --fqbn esp32:esp32:esp32 \
  -p /dev/cu.usbserial-XXXX \
  iot/running
```

Catatan: `arduino-cli upload` dengan `--input-dir` tetap membutuhkan
tipe koneksi `serial`; under the hood ArduinoOTA di CLI ditangani oleh
`espota.py` yang ada di dalam core ESP32/Arduino. Cara paling stabil
untuk push via OTA adalah menjalankan `iot/build.py --ota`, yang
membungkus `espota.py` dengan detail host dan password.

## Fase 4 — Troubleshooting OTA

| Gejala | Sebab umum | Solusi |
|---|---|---|
| `monitoring-ph-air.local` tidak ditemukan | mDNS belum resolve | pakai IP address ESP32 langsung |
| `Authentication failed` | password OTA salah | pastikan `OTA_PASSWORD` sama di firmware dan build script |
| Firmware terupload, ESP32 restart, tapi program tidak jalan | partisi penuh atau program terlalu besar | kurangi fitur atau reset ke pabrik |
| OTA tidak bisa diakses setelah beberapa hari | ESP32 reboot / IP berubah | masukkan ke jaringan dengan DHCP static / mDNS refresh |

## Pemeliharaan

- Jika SSID atau password WiFi berubah, update terlebih dahulu di
  `running.ino`, lalu flash pertama harus diulang via USB sekali lagi.
- Jika password OTA ingin di-rotate, perbarui di firmware dan di
  `build.py` secara bersamaan.
