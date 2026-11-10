#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/monitoring-ph-air}"
BRANCH="${BRANCH:-main}"
PHP_VERSION="${PHP_VERSION:-8.3}"
DB_NAME="${DB_NAME:-monitoring_ph_air}"
DB_USER="${DB_USER:-monitoring}"
DB_PASSWORD="${DB_PASSWORD:-}"
REPO_URL="${REPO_URL:-https://github.com/haidi20/monitoring-ph-air.git}"
APP_URL="${APP_URL:-skripsi.43.156.137.225.nip.io}"
FORCE_IMPORT_SQL="${FORCE_IMPORT_SQL:-false}"

info() { printf '\033[1;34m[INFO]\033[0m %s\n' "$*"; }
die() { printf '\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }

[[ ${EUID} -eq 0 ]] || die "Jalankan sebagai root: sudo bash deploy/setup-vps.sh"
[[ -n ${APP_URL} ]] || die "APP_URL wajib diisi."
[[ ${APP_URL} != http://* && ${APP_URL} != https://* ]] || die "Gunakan hostname tanpa http:// atau https://."

if [[ -z ${DB_PASSWORD} ]]; then
    DB_PASSWORD="$(openssl rand -base64 24 | tr -dc 'A-Za-z0-9' | head -c 24)"
    info "Password database dibuat otomatis."
fi

info "1/10 Install paket sistem"
export DEBIAN_FRONTEND=noninteractive
rm -f /etc/apt/sources.list.d/ondrej-ubuntu-php-noble.list /etc/apt/sources.list.d/ondrej-ubuntu-php-noble.sources
apt-get update -qq
apt-get install -y -qq ca-certificates curl git unzip software-properties-common gnupg

php_major_minor="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
if [[ "${php_major_minor}" != "${PHP_VERSION}" ]]; then
    apt-get install -y -qq \
      "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" \
      "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" \
      "php${PHP_VERSION}-zip" "php${PHP_VERSION}-gd" "php${PHP_VERSION}-intl" \
      "php${PHP_VERSION}-bcmath" "php${PHP_VERSION}-opcache" "php${PHP_VERSION}-redis"
fi
apt-get install -y -qq mysql-server redis-server nginx
systemctl enable --now mysql redis-server nginx

php -v
php -m | grep -q pdo_mysql || apt-get install -y -qq "php${PHP_VERSION}-mysql"

info "2/10 Install Composer dan Node.js"
if [[ ! -x /usr/local/bin/composer ]]; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi
if ! command -v node >/dev/null 2>&1; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y -qq nodejs
fi

info "3/10 Setup database"
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
mysql -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

info "4/10 Clone repository"
if [[ -d "${APP_DIR}/.git" ]]; then
    git -C "${APP_DIR}" fetch origin
    git -C "${APP_DIR}" checkout "${BRANCH}"
    git -C "${APP_DIR}" reset --hard "origin/${BRANCH}"
else
    git clone --branch "${BRANCH}" "${REPO_URL}" "${APP_DIR}"
fi
cd "${APP_DIR}"

info "5/10 Install dependency"
composer install --no-dev --optimize-autoloader --no-interaction
if [[ -f package-lock.json ]]; then
    npm ci
else
    npm install
fi
npm run build

info "6/10 Konfigurasi environment produksi"
if [[ ! -f .env ]]; then
    cp .env.example .env
fi
sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
sed -i "s|^APP_URL=.*|APP_URL=http://${APP_URL}|" .env
sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sed -i "s|^DB_PORT=.*|DB_PORT=3306|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
php artisan key:generate --force

info "7/10 Import data monitoring"
if [[ "${FORCE_IMPORT_SQL}" == "true" ]] || ! mysql -NBe "SHOW TABLES LIKE 'data_project'" "${DB_NAME}" | grep -q data_project; then
    raw_input='Y'
    if [[ "${FORCE_IMPORT_SQL}" != "true" ]]; then
        read -rp "Database monitoring belum ada. Import database/Databases_Haidi_Nurhadinata20260103190732.sql? [Y/n]: " raw_input
    fi
    [[ ${raw_input,,} != n ]] || die "Struktur data monitoring belum tersedia."
    mysql "${DB_NAME}" < database/Databases_Haidi_Nurhadinata20260103190732.sql
fi

info "8/10 Migrasi dan build cache Laravel"
php artisan migrate --force
chown -R www-data:www-data storage bootstrap/cache
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

info "9/10 Konfigurasi Nginx"
NGINX_CONF="/etc/nginx/sites-available/monitoring-ph-air"
cat > "${NGINX_CONF}" <<EOF
server {
    listen 80;
    server_name ${APP_URL} _;
    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 20M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
EOF
ln -sf "${NGINX_CONF}" /etc/nginx/sites-enabled/monitoring-ph-air
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

info "10/10 Firewall dan health check"
ufw allow OpenSSH >/dev/null 2>&1 || true
ufw allow "Nginx Full" >/dev/null 2>&1 || true
yes | ufw enable >/dev/null 2>&1 || true
HTTP_CODE=$(curl -sS -o /tmp/monitoring-health.html -w "%{http_code}" http://127.0.0.1/ || true)
[[ "${HTTP_CODE}" == "200" ]] || die "Health check /up mengembalikan HTTP ${HTTP_CODE}."

cat <<RINGKASAN

Deployment selesai.

  App dir : ${APP_DIR}
  URL     : http://${APP_URL}/
  Host    : ${APP_URL}
  DB name : ${DB_NAME}
  DB user : ${DB_USER}
  DB pass : ${DB_PASSWORD}

Simpan kredensial database di password manager.
Untuk update berikutnya: sudo APP_DIR=${APP_DIR} BRANCH=${BRANCH} bash deploy/deploy-update.sh
RINGKASAN
