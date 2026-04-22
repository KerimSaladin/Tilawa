#!/bin/bash
set -e

PG_DATA=/var/lib/postgresql/data
DB_NAME=${DB_NAME:-tilawa}
DB_USER=${DB_USER:-tilawa}
DB_PASS=${DB_PASS:-tilawa123}

# Init PostgreSQL if first run
if [ ! -f "$PG_DATA/PG_VERSION" ]; then
    su postgres -c "initdb -D $PG_DATA"
fi

# Start PostgreSQL
su postgres -c "pg_ctl start -D $PG_DATA -l /var/log/postgresql.log -w"

# Create DB and user if not exists
su postgres -c "psql -tc \"SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'\" | grep -q 1 || psql -c \"CREATE USER $DB_USER WITH PASSWORD '$DB_PASS';\""
su postgres -c "psql -tc \"SELECT 1 FROM pg_database WHERE datname='$DB_NAME'\" | grep -q 1 || psql -c \"CREATE DATABASE $DB_NAME OWNER $DB_USER;\""

# Run schema if tables don't exist
TABLE_COUNT=$(su postgres -c "psql -d $DB_NAME -tc \"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public';\"" | tr -d ' ')
if [ "$TABLE_COUNT" = "0" ]; then
    su postgres -c "psql -d $DB_NAME -f /var/www/html/database/pg_schema.sql"
    echo "Schema loaded."
fi

# Start Apache in foreground
apache2-foreground
