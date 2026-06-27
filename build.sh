#!/usr/bin/env bash
set -euo pipefail

echo "=== Installing PHP dependencies ==="
composer install --no-dev --optimize-autoloader --no-interaction

echo "=== Generating app key ==="
php artisan key:generate --force

echo "=== Creating PostgreSQL roles (migrate_user + app_user) ==="
# Render gives us a superuser DATABASE_URL — use it to create the two roles
psql "$DATABASE_URL" <<-SQL
    DO \$\$
    BEGIN
        IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = '${DB_MIGRATE_USERNAME}') THEN
            CREATE ROLE ${DB_MIGRATE_USERNAME} LOGIN PASSWORD '${DB_MIGRATE_PASSWORD}'
                NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;
        END IF;

        IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = '${DB_USERNAME}') THEN
            CREATE ROLE ${DB_USERNAME} LOGIN PASSWORD '${DB_PASSWORD}'
                NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS;
        END IF;
    END
    \$\$;

    GRANT CONNECT ON DATABASE ${DB_DATABASE} TO ${DB_MIGRATE_USERNAME};
    GRANT CONNECT ON DATABASE ${DB_DATABASE} TO ${DB_USERNAME};

    GRANT USAGE, CREATE ON SCHEMA public TO ${DB_MIGRATE_USERNAME};
    GRANT USAGE ON SCHEMA public TO ${DB_USERNAME};

    ALTER DEFAULT PRIVILEGES FOR ROLE ${DB_MIGRATE_USERNAME} IN SCHEMA public
        GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO ${DB_USERNAME};
    ALTER DEFAULT PRIVILEGES FOR ROLE ${DB_MIGRATE_USERNAME} IN SCHEMA public
        GRANT USAGE, SELECT ON SEQUENCES TO ${DB_USERNAME};
SQL

echo "=== Running migrations (via migrate_user) ==="
php artisan migrate --force --database=pgsql_migrate

echo "=== Seeding default data ==="
php artisan db:seed --force

echo "=== Caching config & routes ==="
php artisan config:cache
php artisan route:cache
php artisan event:cache

echo "=== Build complete ==="