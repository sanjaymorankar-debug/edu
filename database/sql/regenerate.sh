#!/usr/bin/env bash
# Regenerates 01-schema.sql, 02-reference-data.sql and 03-demo-data.sql from
# a scratch PostgreSQL database built by the app's own migrations + seeders.
#
# Usage (from the repo root; standard libpq env vars pick the server):
#   PGHOST=127.0.0.1 PGPORT=5432 PGUSER=postgres PGPASSWORD=... \
#     database/sql/regenerate.sh edu_sqlgen
#
# The database named must be EMPTY or disposable: it is rebuilt with
# `php artisan migrate:fresh --seed`. Needs php, psql and pg_dump (same major
# version as the server or newer).
set -euo pipefail

DB="${1:?usage: $0 <scratch-database-name>}"
OUT="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$OUT/../.." && pwd)"
REV="$(git -C "$ROOT" rev-parse --short HEAD)"

REFERENCE_TABLES=(roles permissions role_has_permissions states districts
  complaint_categories school_rating_components teacher_rating_components)

export DB_CONNECTION=pgsql DB_HOST="${PGHOST:-127.0.0.1}" DB_PORT="${PGPORT:-5432}" \
  DB_DATABASE="$DB" DB_USERNAME="${PGUSER:-postgres}" DB_PASSWORD="${PGPASSWORD:-}"

(cd "$ROOT" && php artisan migrate:fresh --seed --force --no-ansi >/dev/null)

# The exact SQL `php artisan migrate` runs on pgsql — what `migrate --pretend`
# prints — collected with DB::pretend(), one block per migration. (A
# pg_dump --schema-only would be equivalent, but PostgreSQL re-words CHECK
# constraints when it dumps them, so the imported schema would not be
# byte-identical to a migrated one.)
migration_ddl() {
  (cd "$ROOT" && php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$migrator = $app->make('migrator');
$db = $app->make('db');
$print = function (array $queries) {
    foreach ($queries as $q) {
        echo rtrim($q['query'], "; \n").";\n";
    }
};

$print($db->connection()->pretend(fn () => $app->make('migration.repository')->createRepository()));

foreach ($migrator->getMigrationFiles(database_path('migrations')) as $name => $path) {
    $migration = require $path;
    echo "\n-- {$name}\n";
    $print($db->connection($migration->getConnection())->pretend(fn () => $migration->up()));
}
PHP
  )
}

# pg_dump >= 16.10 wraps dumps in \restrict/\unrestrict, which only psql
# understands; drop them (and the version banner) so the files also import
# through pgAdmin or a provider's web SQL console.
clean() { grep -vE '^\\(un)?restrict |^-- Dumped (from|by) '; }

dump() { pg_dump -d "$DB" --no-owner --no-privileges --no-comments "$@" | clean; }

# Every table except migrations and the reference tables that holds rows.
mapfile -t DEMO_TABLES < <(psql -d "$DB" -AtX -c "
  SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
  WHERE n.nspname = 'public' AND c.relkind = 'r'
    AND c.relname NOT IN ('migrations', $(printf "'%s'," "${REFERENCE_TABLES[@]}" | sed 's/,$//'))
  ORDER BY 1" | while read -r t; do
    [ "$(psql -d "$DB" -AtX -c "SELECT EXISTS (SELECT 1 FROM public.\"$t\")")" = t ] && echo "$t"
  done)

tflags() { for t in "$@"; do printf -- '--table=public.%s ' "$t"; done; }

TABLE_COUNT=$(psql -d "$DB" -AtX -c "SELECT count(*) FROM pg_tables WHERE schemaname = 'public'")
MIGRATION_COUNT=$(psql -d "$DB" -AtX -c "SELECT count(*) FROM migrations")

{
cat <<EOF
-- =====================================================================
-- EDU (edutest.agtci.com) — 01-schema.sql            PostgreSQL 13+
-- Full database schema: $TABLE_COUNT tables (incl. Laravel \`migrations\`).
-- Generated @ $REV by database/sql/regenerate.sh: the exact SQL
-- \`php artisan migrate\` runs on PostgreSQL for all $MIGRATION_COUNT migrations (what
-- \`migrate --pretend\` prints). Enables the \`citext\` extension (trusted;
-- the database owner can create it).
--
-- HOW TO IMPORT: into an EMPTY database you've already created (there is
-- no CREATE DATABASE here) — see database/sql/README.md for psql, pgAdmin
-- and provider-console steps. Then import 02-reference-data.sql (and
-- optionally 03-demo-data.sql).
--
-- The \`migrations\` table is filled at the end, so a later
-- \`php artisan migrate --force\` is a no-op, not an error.
-- DO NOT import into a database that already has these tables.
-- =====================================================================

EOF
echo "-- Same schema the app uses (config/database.php: search_path = public)."
echo "SET search_path TO public;"
echo
migration_ddl
echo
echo "-- Mark all $MIGRATION_COUNT migrations as run (batch 1)."
dump --data-only --inserts --rows-per-insert=1000 --table=public.migrations
} > "$OUT/01-schema.sql"

{
cat <<EOF
-- =====================================================================
-- EDU — 02-reference-data.sql                         PostgreSQL 13+
-- Reference data every environment needs (from the app's own seeders @
-- $REV): roles, permissions + role grants (spatie), states, districts,
-- complaint categories, school and teacher rating components.
-- Import AFTER 01-schema.sql. ON CONFLICT DO NOTHING, so re-importing is
-- harmless. Creates NO user accounts — see 03.
-- =====================================================================

EOF
# shellcheck disable=SC2046
dump --data-only --column-inserts --rows-per-insert=1000 --on-conflict-do-nothing $(tflags "${REFERENCE_TABLES[@]}")
} > "$OUT/02-reference-data.sql"

USER_COUNT=$(psql -d "$DB" -AtX -c "SELECT count(*) FROM users")
SCHOOL_COUNT=$(psql -d "$DB" -AtX -c "SELECT count(*) FROM schools")
COMPLAINT_COUNT=$(psql -d "$DB" -AtX -c "SELECT count(*) FROM complaints")
{
cat <<EOF
-- =====================================================================
-- EDU — 03-demo-data.sql   (OPTIONAL — TEST/STAGING ONLY)   PostgreSQL 13+
-- The synthetic demo data from DatabaseSeeder (@ $REV):
-- $USER_COUNT users incl. every account in TEST_ACCOUNTS.md, $SCHOOL_COUNT schools,
-- $COMPLAINT_COUNT complaints, feedback, scores. All passwords = Password123!
-- (public, in this repo). NEVER import into a real production database.
-- Import AFTER 01 and 02, into an otherwise empty database. Rows are in
-- foreign-key order and each id sequence is advanced past the imported ids.
-- =====================================================================

EOF
# shellcheck disable=SC2046
dump --data-only --column-inserts --rows-per-insert=1000 $(tflags "${DEMO_TABLES[@]}")
} > "$OUT/03-demo-data.sql"

echo "Wrote $OUT/0{1,2,3}-*.sql from $DB @ $REV"
