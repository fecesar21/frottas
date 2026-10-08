#!/usr/bin/env bash
# Backup automatizado — HEALTH DRIVE / FleetCore
#
# Realiza dois backups em sequência:
#   1. Banco de dados MySQL  → dump comprimido com gzip
#   2. Diretório do sistema  → tar.gz excluindo node_modules, vendor e public/build
#
# Credenciais lidas diretamente do .env do projeto (sem senhas no script).
# Retenção configurável: por padrão mantém os últimos 14 dias de cada tipo.
#
# Uso manual:
#   sudo -u www-data bash /var/www/frottas/scripts/backup.sh
#
# Instalação do cron (rodar como root, uma vez):
#   sudo bash /var/www/frottas/scripts/backup.sh --install-cron
#
# Variáveis de ambiente (todas opcionais, têm defaults):
#   PROJECT_DIR   — caminho do projeto       (default: /var/www/frottas)
#   BACKUP_DIR    — destino dos backups       (default: /var/backups/healthdrive)
#   RETAIN_DAYS   — dias de retenção          (default: 14)
#   CRON_HOUR     — hora do cron diário       (default: 2)
#   CRON_MINUTE   — minuto do cron diário     (default: 30)

set -euo pipefail

# Independe do diretório de quem chamou (ex.: sudo -u www-data a partir de
# /home/fcesarc, que o www-data não lê — o find falharia ao voltar para ele).
cd /

# ──────────────────────────────────────────────
# Configuração
# ──────────────────────────────────────────────
PROJECT_DIR="${PROJECT_DIR:-/var/www/frottas}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/healthdrive}"
RETAIN_DAYS="${RETAIN_DAYS:-14}"
LOG_FILE="${BACKUP_DIR}/backup.log"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"

# ──────────────────────────────────────────────
# Instalação do cron (modo especial)
# ──────────────────────────────────────────────
if [[ "${1:-}" == "--install-cron" ]]; then
  if [ "$EUID" -ne 0 ]; then
    echo "Use sudo para instalar o cron." >&2
    exit 1
  fi

  CRON_HOUR="${CRON_HOUR:-2}"
  CRON_MINUTE="${CRON_MINUTE:-30}"
  CRON_LINE="${CRON_MINUTE} ${CRON_HOUR} * * * PROJECT_DIR=${PROJECT_DIR} BACKUP_DIR=${BACKUP_DIR} RETAIN_DAYS=${RETAIN_DAYS} bash ${PROJECT_DIR}/scripts/backup.sh >> ${BACKUP_DIR}/cron.log 2>&1"

  # Garante que o diretório de backup existe e pertence ao www-data
  mkdir -p "${BACKUP_DIR}"
  chown -R www-data:www-data "${BACKUP_DIR}"
  chmod 750 "${BACKUP_DIR}"

  # Adiciona ao crontab do www-data (idempotente — remove linha anterior se existir)
  ( crontab -u www-data -l 2>/dev/null \
      | grep -vF "scripts/backup.sh" \
    ; echo "${CRON_LINE}" \
  ) | crontab -u www-data -

  echo "Cron instalado para www-data:"
  echo "  ${CRON_LINE}"
  echo ""
  echo "Verifique com: crontab -u www-data -l"
  exit 0
fi

# ──────────────────────────────────────────────
# Funções auxiliares
# ──────────────────────────────────────────────
log() {
  local msg="[$(date '+%Y-%m-%d %H:%M:%S')] $*"
  echo "${msg}"
  echo "${msg}" >> "${LOG_FILE}"
}

die() {
  log "ERRO: $*"
  exit 1
}

# Lê um valor do .env do projeto (ignora linhas comentadas e espaços extras)
env_val() {
  local key="$1"
  grep -E "^${key}=" "${PROJECT_DIR}/.env" \
    | head -n1 \
    | sed 's/^[^=]*=//; s/^"//; s/"$//; s/^'"'"'//; s/'"'"'$//' \
    || true
}

# ──────────────────────────────────────────────
# Pré-condições
# ──────────────────────────────────────────────
[ -f "${PROJECT_DIR}/.env" ] || die ".env não encontrado em ${PROJECT_DIR}"
[ -f "${PROJECT_DIR}/artisan" ] || die "artisan não encontrado — verifique PROJECT_DIR"

mkdir -p "${BACKUP_DIR}/db" "${BACKUP_DIR}/files"
touch "${LOG_FILE}"

# Com set -e o script morreria sem registrar nada; loga a falha e remove o
# arquivo parcial do dump para não deixar um backup vazio parecendo válido.
trap 'log "ERRO: comando falhou (linha ${LINENO}) — backup incompleto"; [ -n "${DUMP_PARCIAL:-}" ] && rm -f "${DUMP_PARCIAL}"' ERR

log "===== Início do backup (timestamp: ${TIMESTAMP}) ====="

# ──────────────────────────────────────────────
# 1. Backup do banco de dados MySQL
# ──────────────────────────────────────────────
DB_CONNECTION="$(env_val DB_CONNECTION)"

if [[ "${DB_CONNECTION}" == "mysql" ]]; then
  DB_HOST="$(env_val DB_HOST)"
  DB_PORT="$(env_val DB_PORT)"
  DB_DATABASE="$(env_val DB_DATABASE)"
  DB_USERNAME="$(env_val DB_USERNAME)"
  DB_PASSWORD="$(env_val DB_PASSWORD)"

  DB_FILE="${BACKUP_DIR}/db/${DB_DATABASE}_${TIMESTAMP}.sql.gz"

  log "Iniciando dump MySQL: ${DB_DATABASE}@${DB_HOST}:${DB_PORT} → ${DB_FILE}"

  # Sem --set-gtid-purged: o servidor é MariaDB, que não reconhece a opção.
  DUMP_PARCIAL="${DB_FILE}"
  MYSQL_PWD="${DB_PASSWORD}" mysqldump \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --user="${DB_USERNAME}" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    "${DB_DATABASE}" \
    | gzip -9 > "${DB_FILE}"
  DUMP_PARCIAL=""

  DB_SIZE="$(du -sh "${DB_FILE}" | cut -f1)"
  log "Dump concluído: ${DB_FILE} (${DB_SIZE})"

elif [[ "${DB_CONNECTION}" == "sqlite" ]]; then
  DB_PATH="$(env_val DB_DATABASE)"
  # Aceita caminhos relativos ao projeto
  [[ "${DB_PATH}" != /* ]] && DB_PATH="${PROJECT_DIR}/${DB_PATH}"

  DB_FILE="${BACKUP_DIR}/db/sqlite_${TIMESTAMP}.sqlite.gz"

  log "Iniciando cópia SQLite: ${DB_PATH} → ${DB_FILE}"
  gzip -9 -c "${DB_PATH}" > "${DB_FILE}"
  DB_SIZE="$(du -sh "${DB_FILE}" | cut -f1)"
  log "Cópia concluída: ${DB_FILE} (${DB_SIZE})"

else
  log "AVISO: DB_CONNECTION='${DB_CONNECTION}' não suportado neste script — pulando backup de banco."
fi

# ──────────────────────────────────────────────
# 2. Backup do diretório do sistema
# ──────────────────────────────────────────────
FILES_ARCHIVE="${BACKUP_DIR}/files/frottas_${TIMESTAMP}.tar.gz"

log "Iniciando backup de arquivos: ${PROJECT_DIR} → ${FILES_ARCHIVE}"

tar -czf "${FILES_ARCHIVE}" \
  --exclude="${PROJECT_DIR}/node_modules" \
  --exclude="${PROJECT_DIR}/vendor" \
  --exclude="${PROJECT_DIR}/public/build" \
  --exclude="${PROJECT_DIR}/storage/logs/*.log" \
  --exclude="${PROJECT_DIR}/.git" \
  -C "$(dirname "${PROJECT_DIR}")" \
  "$(basename "${PROJECT_DIR}")"

FILES_SIZE="$(du -sh "${FILES_ARCHIVE}" | cut -f1)"
log "Backup de arquivos concluído: ${FILES_ARCHIVE} (${FILES_SIZE})"

# ──────────────────────────────────────────────
# 3. Limpeza de backups antigos (retenção)
# ──────────────────────────────────────────────
log "Removendo backups com mais de ${RETAIN_DAYS} dias..."

find "${BACKUP_DIR}/db"    -maxdepth 1 -type f -mtime "+${RETAIN_DAYS}" -delete
find "${BACKUP_DIR}/files" -maxdepth 1 -type f -mtime "+${RETAIN_DAYS}" -delete

# Conta o que sobrou
DB_COUNT="$(find "${BACKUP_DIR}/db"    -maxdepth 1 -type f | wc -l)"
FILES_COUNT="$(find "${BACKUP_DIR}/files" -maxdepth 1 -type f | wc -l)"
log "Backups retidos → DB: ${DB_COUNT} arquivo(s) | Arquivos: ${FILES_COUNT} arquivo(s)"

# ──────────────────────────────────────────────
# 4. Sumário final
# ──────────────────────────────────────────────
log "===== Backup finalizado com sucesso ====="
