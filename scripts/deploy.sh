#!/usr/bin/env bash
# Script de deploy para produção — HEALTH DRIVE / FleetCore
#
# Uso (no servidor de produção, dentro do diretório do projeto):
#   bash scripts/deploy.sh
#
# Garante que o backend e o frontend fiquem sempre sincronizados após o pull,
# evitando o cenário em que o código-fonte é atualizado mas os assets
# compilados (public/build) continuam sendo os antigos.

set -euo pipefail

echo "==> Atualizando código-fonte..."
git pull

echo "==> Instalando dependências PHP..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Instalando dependências e gerando build do frontend..."
npm ci
npm run build

echo "==> Rodando migrations..."
php artisan migrate --force

echo "==> Limpando e reconstruindo caches..."
php artisan optimize:clear
php artisan optimize

echo "==> Deploy concluído."
