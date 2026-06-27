#!/usr/bin/env bash
set -euo pipefail

echo "=== Creating .env file ==="
cat > /var/www/.env << ENVFILE
APP_NAME="${APP_NAME:-SaaS Subscriptions}"
APP_ENV="${APP_ENV:-production}"
APP_KEY="${APP_KEY:-}"
APP_DEBUG="${APP_DEBUG:-false}"
APP_URL="${APP_URL:-http://localhost}"

DB_CONNECTION=pgsql
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT:-5432}
DB_DATABASE=${DB_DATABASE}
DB_PGSQL_DATABASE=${DB_PGSQL_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}
DB_MIGRATE_USERNAME=${DB_MIGRATE_USERNAME}
DB_MIGRATE_PASSWORD=${DB_MIGRATE_PASSWORD}
DB_SSLMODE=${DB_SSLMODE:-require}

SESSION_DRIVER=${SESSION_DRIVER:-database}
CACHE_STORE=${CACHE_STORE:-database}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}

CRON_SECRET=${CRON_SECRET}
SANCTUM_EXPIRATION=${SANCTUM_EXPIRATION:-1440}
BCRYPT_ROUNDS=${BCRYPT_ROUNDS:-12}
ENVFILE

echo "=== Generating app key if missing ==="
php artisan key:generate --force

echo "=== Creating PostgreSQL roles ==="
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

echo "=== Running migrations ==="
php artisan migrate --force --database=pgsql_migrate

echo "=== Seeding ==="
php artisan db:seed --force

echo "=== Caching ==="
php artisan config:cache
php artisan route:clear

echo "=== Done ==="