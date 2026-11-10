#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/monitoring-ph-air}"
BRANCH="${BRANCH:-main}"

info() { printf '\033[1;34m[INFO]\033[0m %s\n' "$*"; }
die() { printf '\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }

[[ ${EUID} -eq 0 ]] || die "Jalankan sebagai root: sudo APP_DIR=${APP_DIR} bash deploy/deploy-update.sh"
[[ -f "${APP_DIR}/artisan" ]] || die "Aplikasi belum di-setup di ${APP_DIR}."

info "1/5 Pull kode terbaru"
git -C "${APP_DIR}" fetch origin
git -C "${APP_DIR}" checkout "${BRANCH}"
git -C "${APP_DIR}" reset --hard "origin/${BRANCH}"
cd "${APP_DIR}"

info "2/5 Update dependency dan build aset"
composer install --no-dev --optimize-autoloader --no-interaction
if [[ -f package-lock.json ]]; then
    npm ci
else
    npm install
fi
npm run build

info "3/5 Migrasi database"
php artisan migrate --force

info "4/5 Refresh cache produksi"
chown -R www-data:www-data storage bootstrap/cache
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

info "5/5 Health check"
HTTP_CODE=$(curl -sS -o /tmp/monitoring-health.html -w "%{http_code}" http://127.0.0.1/ || true)
[[ "${HTTP_CODE}" == "200" ]] || die "Health check /up mengembalikan HTTP ${HTTP_CODE}."
info "Deploy selesai."
