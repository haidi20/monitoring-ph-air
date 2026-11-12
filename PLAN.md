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
- [ ] 8. Commit, push, deploy ke VPS UC
- [ ] 9. Verifikasi produksi via `/api/monitoring`
