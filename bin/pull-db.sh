#!/usr/bin/env bash
#
# Copy the production database to the local Sail MySQL database.
# Run this on the Mac itself (not inside Sail): it needs your SSH keys.
# Read-only on the server: it only runs mysqldump there.

set -euo pipefail

SSH_HOST="oof"
REMOTE_DIR="~/oof-o6o894df.on-forge.com/current"
SKIP_TABLES=(sessions cache cache_locks jobs job_batches failed_jobs password_reset_tokens)

cd "$(dirname "$0")/.."

if [[ ! -f .env ]]; then
    echo "No local .env found." >&2
    exit 1
fi

env_value() {
    local value
    value=$(grep -E "^$1=" .env | tail -n1 | cut -d= -f2-)
    value="${value%\"}"; value="${value#\"}"
    value="${value%\'}"; value="${value#\'}"
    printf '%s' "$value"
}

LOCAL_DB=$(env_value DB_DATABASE)
LOCAL_USER=$(env_value DB_USERNAME)
LOCAL_PASS=$(env_value DB_PASSWORD)

if [[ -z "$LOCAL_DB" || -z "$LOCAL_USER" ]]; then
    echo "DB_DATABASE / DB_USERNAME missing from local .env." >&2
    exit 1
fi

echo "This will REPLACE all data in local database '$LOCAL_DB' with a copy of production."
read -r -p "Continue? [y/N] " answer
if [[ ! "$answer" =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
fi

START=$(date +%s)
DUMP_FILE=$(mktemp -t oof-dump.XXXXXX)
trap 'rm -f "$DUMP_FILE"' EXIT

echo "Dumping production database..."
# Credentials are read on the server and never leave it. The remote script is
# passed on stdin, so the only thing sent back is the gzipped dump.
ssh "$SSH_HOST" bash -s -- "${SKIP_TABLES[@]}" > "$DUMP_FILE" <<REMOTE
set -euo pipefail
cd $REMOTE_DIR
env_value() {
    local value
    value=\$(grep -E "^\$1=" .env | tail -n1 | cut -d= -f2-)
    value="\${value%\"}"; value="\${value#\"}"
    value="\${value%\'}"; value="\${value#\'}"
    printf '%s' "\$value"
}
DB=\$(env_value DB_DATABASE)
USER=\$(env_value DB_USERNAME)
PASS=\$(env_value DB_PASSWORD)
HOST=\$(env_value DB_HOST)
PORT=\$(env_value DB_PORT)
ignore=()
for table in "\$@"; do
    ignore+=("--ignore-table=\$DB.\$table")
done
MYSQL_PWD="\$PASS" mysqldump --single-transaction --no-tablespaces \
    -h "\${HOST:-127.0.0.1}" -P "\${PORT:-3306}" -u "\$USER" "\${ignore[@]}" "\$DB" | gzip -c
REMOTE

DUMP_SIZE=$(wc -c < "$DUMP_FILE" | tr -d ' ')
if [[ "$DUMP_SIZE" -lt 100 ]] || ! gzip -t "$DUMP_FILE"; then
    echo "Dump looks empty or corrupt; local database left untouched." >&2
    exit 1
fi

echo "Importing into local Sail database '$LOCAL_DB'..."
# mysqldump emits DROP TABLE IF EXISTS per table, so no database drop is needed.
gzip -dc "$DUMP_FILE" | ./vendor/bin/sail exec -T -e MYSQL_PWD="$LOCAL_PASS" mysql \
    mysql -u "$LOCAL_USER" "$LOCAL_DB"

ELAPSED=$(( $(date +%s) - START ))
echo
echo "Done in ${ELAPSED}s."
echo "  Source:   $SSH_HOST:$REMOTE_DIR (skipped: ${SKIP_TABLES[*]})"
echo "  Target:   local database '$LOCAL_DB'"
echo "  Dump:     $(( DUMP_SIZE / 1024 )) KB compressed"
echo "Run './vendor/bin/sail artisan migrate' if local migrations are ahead of production."
