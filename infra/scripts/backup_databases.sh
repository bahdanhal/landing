#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/backups}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DATABASES=("bahdan_prod" "ileza_prod" "stackhal_prod")

mkdir -p "$BACKUP_DIR"

for DB in "${DATABASES[@]}"; do
    FILE="$BACKUP_DIR/${DB}_${TIMESTAMP}.sql.gz"
    echo "Creating backup for $DB to $FILE..."
    docker exec -t infra-postgres-1 pg_dump -U "${DB_USER:-bahdan_admin}" "$DB" | gzip > "$FILE"
    echo "Backup completed for $DB"
done

# Keep last 14 days of backups
find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +14 -delete
echo "Old backups pruned."
