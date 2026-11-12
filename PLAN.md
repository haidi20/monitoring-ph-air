# PLAN.md — Kirim data lebih cepat + grafik teragregasi

Tujuan: periode kirim ESP32 turun ke ~3 detik tanpa blocking, dan grafik
dashboard tetap menampilkan seluruh hari (bukan cuma 4 menit terakhir).

## Checklist

- [x] 1. Baca struktur firmware (`running.ino`) dan view dashboard
- [x] 2. Firmware: interval kirim 3000 ms dengan jadwal tetap (anti drift)
- [x] 3. Firmware: pembacaan suhu non-blocking (`setWaitForConversion(false)`)
- [x] 4. Firmware: reuse koneksi HTTPS antar kiriman
- [x] 5. Backend: agregasi grafik per menit untuk seluruh hari ini
- [x] 6. Test: agregasi per menit + rentang hari
- [x] 7. Jalankan `php artisan test` (13 passed, 44 assertions)
- [x] 8. Commit, push, deploy ke VPS UC
- [x] 9. Verifikasi produksi via `/api/monitoring`

## Hasil

- Firmware `iot/running/running.ino` (dipakai `build.py`) dan salinan
  `iot/main.cpp` sudah memakai `SEND_INTERVAL_MS = 3000`, jadwal tetap,
  suhu non-blocking, dan reuse koneksi TLS.
- API produksi memakai agregasi per menit: suhu/kekeruhan 68 bucket,
  pH 6 bucket, payload 6,7 KB untuk satu hari penuh.
- Firmware belum di-flash; perangkat terakhir kirim 11:23 WITA dan
  sekarang offline, jadi kecepatan 3 detik baru berlaku setelah OTA/flash.
