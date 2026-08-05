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

# Se qualquer passo abaixo falhar, garante que o cache de bootstrap/rotas não
# fique em estado intermediário (código novo parcialmente aplicado com cache
# antigo ainda ativo) — isso já causou uma indisponibilidade de login em
# produção.
trap 'echo "==> Deploy falhou; limpando caches para evitar estado inconsistente..."; php artisan optimize:clear || true' ERR

echo "==> Verificando árvore de trabalho..."
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "ERRO: há alterações não commitadas em arquivos rastreados. Resolva antes de continuar:"
    git status --short --untracked-files=no
    exit 1
fi

echo "==> Atualizando código-fonte..."
git pull --ff-only

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

echo "==> Reiniciando workers de fila..."
php artisan queue:restart

echo "==> Deploy concluído."
