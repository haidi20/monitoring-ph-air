#!/usr/bin/env bash
set -euo pipefail

DOMAIN="${APP_URL:-skripsi.haidinata.my.id}"
SSL_EMAIL="${SSL_EMAIL:-}"

info() { printf '\033[1;34m[INFO]\033[0m %s\n' "$*"; }
die() { printf '\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }

[[ ${EUID} -eq 0 ]] || die "Jalankan sebagai root: sudo APP_URL=${DOMAIN} bash deploy/setup-ssl.sh"
[[ -n ${DOMAIN} ]] || die "APP_URL wajib diisi."

info "Install Certbot dan plugin Nginx"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq certbot python3-certbot-nginx

CERTBOT_ARGS=(--nginx --non-interactive --agree-tos --redirect -d "${DOMAIN}")
if [[ -n ${SSL_EMAIL} ]]; then
    CERTBOT_ARGS+=(--email "${SSL_EMAIL}")
else
    CERTBOT_ARGS+=(--register-unsafely-without-email)
fi

info "Terbitkan sertifikat untuk ${DOMAIN}"
certbot "${CERTBOT_ARGS[@]}"
systemctl reload nginx

info "Verifikasi HTTPS"
HTTP_CODE=$(curl -sS -o /tmp/monitoring-https.html -w "%{http_code}" --max-time 15 "https://${DOMAIN}/" || true)
[[ "${HTTP_CODE}" == "200" || "${HTTP_CODE}" == "302" ]] || die "HTTPS check mengembalikan HTTP ${HTTP_CODE}."
info "HTTPS aktif: https://${DOMAIN}/"
