#!/bin/sh
set -e

BACKUP_DIR="/backups"
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_DATABASE:-pos_system}"
DB_USER="${DB_USERNAME:-giovanni}"
PGPASSWORD="${DB_PASSWORD}"
RETENTION_DAYS=7

mkdir -p "$BACKUP_DIR"

FILENAME="backup_$(date +%Y%m%d_%H%M%S).sql.gz"
FILEPATH="$BACKUP_DIR/$FILENAME"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Démarrage sauvegarde → $FILENAME"

export PGPASSWORD="$DB_PASSWORD"
pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" "$DB_NAME" | gzip > "$FILEPATH"

SIZE=$(du -sh "$FILEPATH" | cut -f1)
echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✅ Sauvegarde OK — $FILENAME ($SIZE)"

# Suppression des sauvegardes de plus de RETENTION_DAYS jours
find "$BACKUP_DIR" -name "backup_*.sql.gz" -mtime "+$RETENTION_DAYS" -delete
REMAINING=$(ls "$BACKUP_DIR"/backup_*.sql.gz 2>/dev/null | wc -l)
echo "[$(date '+%Y-%m-%d %H:%M:%S')] 📦 $REMAINING sauvegarde(s) conservée(s)"
