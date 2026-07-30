#!/usr/bin/env bash
# Script de instalação inicial para produção — HEALTH DRIVE / FleetCore
#
# Uso (no servidor de produção, dentro do diretório do projeto, após o
# provisionamento de PHP, Composer, Node, MySQL, Redis e Nginx):
#   bash scripts/install.sh
#
# Cobre apenas os passos que rodam UMA VEZ na primeira instalação
# (criação do .env, key:generate, permissões). Para atualizações
# subsequentes, use scripts/deploy.sh.

set -euo pipefail

if [ -f .env ]; then
  echo "==> .env já existe — pulando criação (edite manualmente se necessário)."
else
  echo "==> Criando .env a partir de .env.example..."
  cp .env.example .env
  echo "==> IMPORTANTE: edite o .env agora com os dados de produção (DB_*, APP_URL,"
  echo "    SANCTUM_STATEFUL_DOMAINS, CORS_ALLOWED_ORIGINS, CACHE_STORE=redis,"
  echo "    QUEUE_CONNECTION=redis) antes de continuar."
  read -rp "Pressione ENTER após configurar o .env para prosseguir..."
fi

echo "==> Instalando dependências PHP..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Gerando APP_KEY (se ainda não definida)..."
php artisan key:generate --force

echo "==> Instalando dependências e gerando build do frontend..."
npm ci
npm run build

echo "==> Rodando migrations..."
php artisan migrate --force

echo "==> Criando link de storage..."
php artisan storage:link

echo "==> Otimizando aplicação..."
php artisan optimize

echo "==> Ajustando permissões de storage e cache..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

echo "==> Instalação concluída."
echo "==> Próximos passos manuais: configurar Nginx, Certbot, Supervisor (queue:work)"
echo "    e o crontab do scheduler (schedule:run) — veja o guia de deploy."
