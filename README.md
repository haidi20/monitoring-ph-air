# Monitoring PH Air

Aplikasi monitoring kualitas air (pH, suhu, kekeruhan) berbasis Laravel 12 dengan perangkat IoT ESP32. Data sensor dikirim ESP32 ke endpoint ingest, ditampilkan pada dashboard web, dan firmware dapat di-update secara nirkabel (OTA).

## Arsitektur

- **Backend**: Laravel 12 (PHP 8.2+), MySQL.
- **Frontend**: Blade + Vite.
- **IoT**: ESP32 (`iot/running/running.ino`), build via `iot/build.py` tanpa Arduino IDE.
- **Deploy**: VPS + GitHub Actions (`.github/workflows/deploy-vps-uc.yml`), SSL via Let's Encrypt.

## Setup Lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run dev
php artisan serve
```

Import struktur database dari `database/Databases_Haidi_Nurhadinata20260103190732.sql` bila tersedia.

## Konfigurasi

Atur di `.env`:

| Variabel | Keterangan |
|---|---|
| `MONITORING_API_KEY` | API key untuk endpoint ingest IoT |
| `MONITORING_DASHBOARD_USERNAME` | Username login dashboard |
| `MONITORING_DASHBOARD_PASSWORD` | Password login dashboard |
| `TELEGRAM_BOT_TOKEN` | Token bot Telegram (opsional, untuk alert) |
| `TELEGRAM_CHAT_ID` | Chat ID tujuan alert (opsional) |

Ambang batas alert per sensor dikonfigurasi di `config/monitoring.php`. Jika Telegram dikonfigurasi, setiap nilai di luar ambang batas akan memicu notifikasi.

## Endpoint Utama

| Endpoint | Metode | Keterangan |
|---|---|---|
| `/server.php` | POST | Ingest data sensor (butuh `apikey` + prefix sensor: `phair`, `suhu`, `kekeruhan`) |
| `/api/monitoring` | GET | Data monitoring terbaru dalam JSON |
| `/` | GET | Dashboard (login diperlukan) |
| `/detail/{sensor}` | GET | Detail grafik per sensor |

## Firmware ESP32 & OTA

1. Build + flash pertama via USB (sekali saja):
   ```bash
   python3 iot/build.py --upload
   ```
2. Update berikutnya via WiFi:
   ```bash
   python3 iot/build.py --ota
   ```

Detail lengkap: `iot/plan_ota.md` dan `iot/cara_running.md`.

## Deploy VPS

Setup awal dan update didukung script di `deploy/`:

- `deploy/setup-vps.sh` — instalasi server (PHP, MySQL, aplikasi).
- `deploy/setup-ssl.sh` — HTTPS via Let's Encrypt.
- `deploy/deploy-update.sh` — update aplikasi di server.

Pipeline `.github/workflows/deploy-vps-uc.yml` menjalankan deploy otomatis.

## Testing

```bash
php artisan test
```
