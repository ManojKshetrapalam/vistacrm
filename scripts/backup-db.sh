#!/usr/bin/env bash

# Vistabound CRM - Automated MySQL Database Backup Script
# Usage: ./scripts/backup-db.sh

set -euo pipefail

# Configuration
BACKUP_DIR="${BACKUP_DIR:-/var/backups/vistabound-crm}"
DB_NAME="${DB_DATABASE:-vistabound_crm}"
DB_USER="${DB_USERNAME:-root}"
DB_PASS="${DB_PASSWORD:-}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/${DB_NAME}_backup_${TIMESTAMP}.sql.gz"
LOG_FILE="${BACKUP_DIR}/backup.log"

mkdir -p "${BACKUP_DIR}"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting backup of database '${DB_NAME}'..." | tee -a "${LOG_FILE}"

# Execute mysqldump with gzip compression
if [ -z "${DB_PASS}" ]; then
  mysqldump -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    "${DB_NAME}" | gzip -9 > "${BACKUP_FILE}"
else
  mysqldump -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}" -p"${DB_PASS}" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    "${DB_NAME}" | gzip -9 > "${BACKUP_FILE}"
fi

FILE_SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Backup created: ${BACKUP_FILE} (${FILE_SIZE})" | tee -a "${LOG_FILE}"

# Retention Policy: Delete backups older than 30 days
find "${BACKUP_DIR}" -type f -name "${DB_NAME}_backup_*.sql.gz" -mtime +30 -exec rm -f {} \;
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Pruned backups older than 30 days." | tee -a "${LOG_FILE}"
