#!/usr/bin/env bash
# Provisionamento de infraestrutura para produção — HEALTH DRIVE / FleetCore
#
# Automatiza os passos de sistema que antes eram manuais: Nginx, HTTPS (Certbot),
# Supervisor (queue worker) e crontab (scheduler). Roda UMA VEZ, como root,
# em um Ubuntu Server 24.04 LTS já com PHP-FPM, Composer, Node, MySQL e Redis
# instalados e com o projeto clonado em PROJECT_DIR.
#
# Uso:
#   sudo DOMAIN=api.healthdrive.com.br \
#        PROJECT_DIR=/var/www/healthdrive \
#        CERTBOT_EMAIL=admin@healthdrive.com.br \
#        bash scripts/provision-server.sh
#
# Variáveis:
#   DOMAIN        (obrigatória) domínio público da API, ex: api.healthdrive.com.br
#   PROJECT_DIR   (opcional, default /var/www/healthdrive) caminho do projeto no servidor
#   CERTBOT_EMAIL (opcional) e-mail para registro Let's Encrypt; se ausente, HTTPS é pulado
#   PHP_FPM_SOCK  (opcional, default detectado automaticamente) socket do PHP-FPM
#   QUEUE_PROCS   (opcional, default 2) número de processos do queue worker

set -euo pipefail

if [ "$EUID" -ne 0 ]; then
  echo "Este script precisa rodar como root (use sudo)." >&2
  exit 1
fi

: "${DOMAIN:?Defina DOMAIN=seu-dominio.com.br antes de rodar o script}"
PROJECT_DIR="${PROJECT_DIR:-/var/www/healthdrive}"
QUEUE_PROCS="${QUEUE_PROCS:-2}"

if [ ! -f "$PROJECT_DIR/artisan" ]; then
  echo "Não encontrei $PROJECT_DIR/artisan — verifique PROJECT_DIR." >&2
  exit 1
fi

if [ -z "${PHP_FPM_SOCK:-}" ]; then
  PHP_FPM_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n1 || true)"
  if [ -z "$PHP_FPM_SOCK" ]; then
    echo "Não encontrei socket do PHP-FPM em /run/php — defina PHP_FPM_SOCK manualmente." >&2
    exit 1
  fi
fi

echo "==> Instalando Nginx, Supervisor e Certbot (se ausentes)..."
apt-get update -y
apt-get install -y nginx supervisor certbot python3-certbot-nginx

echo "==> Configurando Nginx para $DOMAIN (PHP-FPM socket: $PHP_FPM_SOCK)..."
NGINX_CONF="/etc/nginx/sites-available/healthdrive"
cat > "$NGINX_CONF" <<EOF
server {
    listen 80;
    server_name $DOMAIN;
    root $PROJECT_DIR/public;

    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$PHP_FPM_SOCK;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/healthdrive
if [ -f /etc/nginx/sites-enabled/default ]; then
  rm -f /etc/nginx/sites-enabled/default
fi
nginx -t
systemctl reload nginx

if [ -n "${CERTBOT_EMAIL:-}" ]; then
  echo "==> Emitindo certificado HTTPS via Certbot para $DOMAIN..."
  certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$CERTBOT_EMAIL" --redirect
else
  echo "==> CERTBOT_EMAIL não definido — pulando emissão de HTTPS."
  echo "    Rode depois manualmente: certbot --nginx -d $DOMAIN"
fi

echo "==> Configurando Supervisor para o queue worker..."
SUPERVISOR_CONF="/etc/supervisor/conf.d/healthdrive-worker.conf"
cat > "$SUPERVISOR_CONF" <<EOF
[program:healthdrive-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $PROJECT_DIR/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=$QUEUE_PROCS
redirect_stderr=true
stdout_logfile=$PROJECT_DIR/storage/logs/worker.log
stopwaitsecs=3600
EOF

supervisorctl reread
supervisorctl update
supervisorctl start healthdrive-worker:* 2>/dev/null || true

echo "==> Configurando crontab do scheduler (www-data)..."
CRON_LINE="* * * * * cd $PROJECT_DIR && php artisan schedule:run >> /dev/null 2>&1"
( crontab -u www-data -l 2>/dev/null | grep -vF "$PROJECT_DIR && php artisan schedule:run" ; echo "$CRON_LINE" ) | crontab -u www-data -

echo "==> Ajustando dono/permissões finais do projeto..."
chown -R www-data:www-data "$PROJECT_DIR"
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

echo "==> Provisionamento concluído."
echo "    Nginx: /etc/nginx/sites-available/healthdrive"
echo "    Supervisor: $SUPERVISOR_CONF (status: supervisorctl status)"
echo "    Cron: crontab -u www-data -l"
