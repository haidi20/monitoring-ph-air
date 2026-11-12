# AGENTS.md - Monitoring PH Air

Aturan ini berlaku untuk seluruh isi repo. Aturan global di
`~/.codex/AGENTS.md` tetap berlaku dan tidak digantikan file ini.

## Ringkasan project

Aplikasi monitoring kualitas air (pH, suhu, kekeruhan) berbasis Laravel 12
dan perangkat ESP32. ESP32 mengirim pembacaan sensor lewat HTTP GET ke
`/server.php`, data disimpan di MySQL, lalu ditampilkan di dashboard Blade
plus Chart.js.

- Backend: Laravel 12, PHP 8.2+ (produksi dan mesin dev memakai 8.3).
- Database: MySQL di produksi, SQLite in-memory saat test.
- Frontend: Blade + Vite, aset lama di `public/legacy-assets/`.
- IoT: ESP32, build tanpa Arduino IDE lewat `iot/build.py`.
- Produksi: `https://skripsi.haidinata.my.id`.

## Peta file penting

- `app/Http/Controllers/MonitoringController.php` - satu-satunya controller:
  ingest, login/logout, dashboard, detail, API, alert Telegram.
- `public/server.php` - jalur ingest yang benar-benar dipakai produksi.
- `config/monitoring.php` - api key, kredensial dashboard, threshold alert.
- `config/vps.php` - host/user/app dir VPS dari env, password hanya dari env.
- `iot/running/running.ino` - firmware sumber kebenaran.
- `iot/build.py` - build/flash/OTA firmware.
- `deploy/*.sh` - provisioning dan update server.
- `tests/Feature/MonitoringTest.php` - satu-satunya test fitur yang relevan.

## Dua jalur ingest (penting)

Nginx mengeksekusi `public/server.php` langsung untuk request `/server.php`,
jadi di produksi controller `ingest()` TIDAK dijalankan.

- `public/server.php`: hanya validasi apikey lalu insert ke `data_input`.
  Tidak memanggil `dispatchThresholdAlert()`, jadi alert Telegram tidak jalan
  di produksi saat ini.
- `MonitoringController::ingest()`: dipakai route Laravel dan seluruh test,
  sudah memanggil alert Telegram.

Setiap perubahan perilaku ingest WAJIB diterapkan di kedua tempat, lalu
diverifikasi dengan `php artisan test`. Jangan menganggap route Laravel
mewakili produksi.

## Kontrak endpoint IoT

- `/server.php` menerima GET atau POST dengan `apikey` dan prefix sensor.
- Prefix yang dikenali: `phair`, `suhu`, `kekeruhan`.
- Respons selalu JSON `out_1` sampai `out_10` bernilai `"0"` walau hanya
  sebagian sensor dikirim. Firmware bergantung pada bentuk ini.
- Prefix yang tidak dikirim dilewati, bukan diisi nol. Firmware sengaja tidak
  mengirim `phair` ketika sensor pH dianggap tidak valid atau tidak tercelup.
- Apikey salah atau kosong dibalas `Gagal`, bukan error HTTP.

## Skema database

- Tabel `data_*` (`data_input`, `data_project`, `data_sensor`,
  `data_sensor_project`, dan lainnya) TIDAK dibuat oleh migration.
  Sumbernya dump `database/Databases_Haidi_Nurhadinata20260103190732.sql`
  untuk produksi, dan `Schema::create` di `setUp()` test untuk SQLite.
- Migration yang ada hanya tabel bawaan Laravel (users, cache, jobs).
- Jangan menambah migration untuk tabel `data_*` tanpa persetujuan user,
  karena production memakai dump dan `migrate --force` akan bentrok.
- Kolom `data_input.nilai` bertipe teks. Cast ke angka sebelum operasi
  matematika atau agregasi.
- Waktu disimpan dalam zona aplikasi `Asia/Makassar` (`APP_TIMEZONE`),
  sedangkan server VPS berjalan di UTC.

## Aturan dashboard dan API

- Dashboard, detail, dan `/api/monitoring` hanya memakai data hari ini
  (`todayRange()`), supaya data historis lama tidak tercampur.
- Deret grafik diagregasi rata-rata per menit lewat
  `aggregateTodayPerMinute()`; nilai "realtime" memakai pembacaan mentah
  paling akhir hari ini.
- Query harus jalan di MySQL dan SQLite: pakai `DATE_FORMAT` untuk MySQL dan
  `strftime` untuk SQLite seperti pola yang sudah ada.
- Sensor tanpa data hari ini tampil `-` dengan deret kosong.
- Polling dashboard dan halaman detail 10 detik. Jangan menaikkan frekuensi
  polling tanpa mengukur ukuran payload.

## Aturan firmware ESP32

- `iot/running/running.ino` adalah sumber kebenaran; `iot/build.py`
  meng-compile direktori `iot/running`.
- `iot/main.cpp` adalah salinan lama yang tidak dipakai build. Kalau perilaku
  firmware diubah, samakan juga supaya tidak menyesatkan.
- `SEND_INTERVAL_MS = 3000` (3 detik). Jadwal kirim memakai
  `lastSend += SEND_INTERVAL_MS`, bukan reset ke `millis()`, agar periode
  tidak melar karena durasi kerja sensor dan HTTP.
- Suhu dibaca non-blocking: `sensors.setWaitForConversion(false)` di
  `setup()`, hasil dibaca di loop lalu langsung minta konversi berikutnya.
- Koneksi HTTPS dipakai ulang (`WiFiClientSecure` dan `HTTPClient` global,
  `http.setReuse(true)`). Tutup socket hanya saat request gagal.
- `arduino-cli` tidak tersedia di mesin dev Windows. Jangan mengklaim build
  atau flash berhasil tanpa menjalankannya; sampaikan bahwa itu belum diuji.
- Pin, kalibrasi pH, dan ambang deteksi sensor lepas punya komentar panjang
  di firmware. Baca dulu sebelum mengubah angka kalibrasi.

## Testing

```bash
php artisan test
```

- Semua test wajib lulus sebelum melaporkan tugas selesai.
- Test memakai SQLite in-memory dan membuat tabel `data_*` manual di
  `setUp()`. Query MySQL-specific akan gagal di test.
- Tambahkan test baru di `tests/Feature/MonitoringTest.php` untuk perubahan
  perilaku ingest, filter rentang waktu, atau agregasi grafik.

## Deploy

- VPS UC: `172.232.96.174`, aplikasi di `/var/www/monitoring-ph-air`,
  user `root`. Host dan path boleh hardcode di `config/vps.php`, tetapi
  password hanya boleh dibaca dari `VPS_PASSWORD` di `.env`.
- Script: `deploy/setup-vps.sh` (awal), `deploy/deploy-update.sh` (update),
  `deploy/setup-ssl.sh` (HTTPS).
- Workflow `.github/workflows/deploy-vps-uc.yml` butuh runner label
  `self-hosted, klinik-vps` yang bisa offline sehingga run tertahan di queue.
  Deploy manual lewat SSH boleh, tetapi hanya kalau user meminta deploy.
- Setelah update manual: `git reset --hard origin/main`, lalu
  `php artisan optimize:clear`, `config:cache`, `route:cache`, `view:cache`,
  dan `chown -R www-data:www-data storage bootstrap/cache`.
- Health check bawaan workflow memanggil `http://127.0.0.1/` yang dilayani
  default_server nginx, bukan aplikasi ini. Jangan menganggapnya bukti
  aplikasi sehat; verifikasi lewat `https://skripsi.haidinata.my.id`.

## Keamanan dan data produksi

- Jangan pernah menulis password, token, atau isi `.env` ke file yang
  di-track git, dokumentasi, atau pesan commit. `.env` sudah di-ignore.
- `documents/ssh.md` hanya berisi rujukan lokasi kredensial, bukan nilainya.
- Jangan menyuntik data uji ke database produksi. Kalau terlanjur, hapus
  baris tersebut dan laporkan ke user.
- Jangan menjalankan seeder atau `migrate:fresh` di produksi.

## Gaya kerja

- Task multi-langkah wajib punya `PLAN.md` di root, satu langkah satu scope,
  dan dicentang `- [x]` begitu selesai.
- JANGAN menjalankan `git add`, `git commit`, `git push`, atau membuat branch
  tanpa perintah eksplisit dari user pada pesan terakhir. Tinggalkan
  perubahan di working tree dan laporkan file yang berubah.
- Komentar kode, dokumentasi, dan pesan commit memakai Bahasa Indonesia.
- Format pesan commit: `[Feat] ringkasan`, `[Fix] ringkasan`,
  `[Chore] ringkasan`, atau `[Refactor] ringkasan`.
- Ikuti gaya kode yang sudah ada: satu controller, query builder lewat
  `DB::table`, dan docblock Bahasa Indonesia di atas method.
- Perubahan kecil dan fokus. Jangan refactor di luar scope permintaan.
