#!/usr/bin/env bash
set -euo pipefail

# Corex.dev Database Initialization Script
# Usage: ./scripts/init-db.sh [--reset] [--seed]
#
# Options:
#   --reset    Drop all tables before migrating
#   --seed     Seed the database after migration
#   --fresh    Shortcut for --reset --seed

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../backend" && pwd)"
COLOR_RESET='\033[0m'
COLOR_GREEN='\033[0;32m'
COLOR_YELLOW='\033[1;33m'
COLOR_RED='\033[0;31m'
COLOR_CYAN='\033[0;36m'

log_info()  { echo -e "${COLOR_GREEN}[INFO]${COLOR_RESET}  $*"; }
log_warn()  { echo -e "${COLOR_YELLOW}[WARN]${COLOR_RESET}  $*"; }
log_error() { echo -e "${COLOR_RED}[ERROR]${COLOR_RESET} $*"; }
log_step()  { echo -e "\n${COLOR_CYAN}══ $* ══${COLOR_RESET}\n"; }

check_prerequisites() {
    log_step "Checking prerequisites"

    if ! command -v php &>/dev/null; then
        log_error "PHP is not installed. Install PHP 8.3+ and try again."
        exit 1
    fi

    PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;")
    if [[ "$(echo "$PHP_VERSION < 8.3" | bc -l 2>/dev/null || echo 1)" == "1" ]]; then
        log_warn "PHP $PHP_VERSION detected. PHP 8.3+ is recommended."
    else
        log_info "PHP $PHP_VERSION detected."
    fi

    if [[ ! -f "$APP_DIR/.env" ]]; then
        log_error ".env file not found at $APP_DIR/.env"
        log_info "Run: cp .env.example backend/.env && php artisan key:generate"
        exit 1
    fi

    log_info "Prerequisites OK"
}

validate_env() {
    log_step "Validating environment"

    local required_vars=(
        "DB_CONNECTION" "DB_HOST" "DB_PORT" "DB_DATABASE"
        "DB_USERNAME" "DB_PASSWORD"
    )

    local missing=0
    for var in "${required_vars[@]}"; do
        if ! grep -q "^${var}=" "$APP_DIR/.env" 2>/dev/null; then
            log_error "Missing environment variable: $var"
            missing=1
        fi
    done

    if [[ $missing -eq 1 ]]; then
        log_error "Fix missing variables in $APP_DIR/.env"
        exit 1
    fi

    log_info "Environment validated"
}

install_deps() {
    log_step "Installing Composer dependencies"
    cd "$APP_DIR"

    if [[ ! -f "vendor/autoload.php" ]]; then
        composer install --no-progress --prefer-dist --optimize-autoloader
        log_info "Dependencies installed"
    else
        log_info "Dependencies already installed"
    fi
}

generate_key() {
    log_step "Generating application key"

    cd "$APP_DIR"
    APP_KEY=$(grep "^APP_KEY=" .env | cut -d= -f2)

    if [[ -z "$APP_KEY" || "$APP_KEY" == "base64:" ]]; then
        php artisan key:generate --force
        log_info "APP_KEY generated"
    else
        log_info "APP_KEY already set"
    fi
}

check_connection() {
    log_step "Checking database connection"

    cd "$APP_DIR"
    if php artisan db:show --no-interaction 2>/dev/null; then
        log_info "Database connection OK"
    else
        log_warn "Could not connect to database. Make sure PostgreSQL is running."
        log_info "Expected: $(grep ^DB_HOST= .env | cut -d= -f2):$(grep ^DB_PORT= .env | cut -d= -f2)"
        log_info "Database: $(grep ^DB_DATABASE= .env | cut -d= -f2)"
        log_info "User:     $(grep ^DB_USERNAME= .env | cut -d= -f2)"
    fi
}

run_migrations() {
    log_step "Running migrations"

    cd "$APP_DIR"

    if [[ "${RESET:-false}" == "true" ]]; then
        log_warn "Rolling back all migrations..."
        php artisan migrate:fresh --force --seed --no-interaction
        log_info "Database reset and re-migrated"
    else
        php artisan migrate --force --no-interaction
        log_info "Migrations complete"
    fi
}

seed_database() {
    log_step "Seeding database"

    cd "$APP_DIR"

    if [[ "${SEED:-false}" == "true" ]]; then
        php artisan db:seed --force --no-interaction
        log_info "Database seeded"
    else
        log_info "Skipping seed (use --seed to populate data)"
    fi
}

optimize() {
    log_step "Optimizing Laravel"

    cd "$APP_DIR"
    php artisan optimize --no-interaction 2>/dev/null || true
    php artisan view:cache --no-interaction 2>/dev/null || true
    php artisan route:cache --no-interaction 2>/dev/null || true
    php artisan config:cache --no-interaction 2>/dev/null || true
    log_info "Optimization complete"
}

main() {
    echo -e "${COLOR_CYAN}"
    echo "╔══════════════════════════════════════════╗"
    echo "║      Corex.dev Database Setup Script     ║"
    echo "╚══════════════════════════════════════════╝"
    echo -e "${COLOR_RESET}"

    RESET=false
    SEED=false

    for arg in "$@"; do
        case "$arg" in
            --reset) RESET=true ;;
            --seed)  SEED=true ;;
            --fresh) RESET=true; SEED=true ;;
            --help)
                echo "Usage: $0 [--reset] [--seed] [--fresh]"
                echo ""
                echo "Options:"
                echo "  --reset    Drop all tables before migrating"
                echo "  --seed     Seed the database after migration"
                echo "  --fresh    Shortcut for --reset --seed"
                echo "  --help     Show this help"
                exit 0
                ;;
        esac
    done

    check_prerequisites
    validate_env
    install_deps
    generate_key
    check_connection
    run_migrations
    seed_database
    optimize

    log_step "Done!"
    log_info "Application is ready at \${APP_URL}"
    log_info "Login with: admin@corex.dev / admin123"
}

main "$@"
