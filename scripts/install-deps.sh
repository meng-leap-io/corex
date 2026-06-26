#!/usr/bin/env bash
set -euo pipefail

# Corex.dev Dependency Installation Script
# Usage: ./scripts/install-deps.sh [--dev] [--python] [--php] [--frontend] [--all]
#
# Options:
#   --all       Install all dependencies (default)
#   --php       Install only PHP/Composer dependencies
#   --python    Install only Python dependencies
#   --frontend  Install only frontend/npm dependencies
#   --dev       Include dev dependencies (default for local)

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COLOR_RESET='\033[0m'
COLOR_GREEN='\033[0;32m'
COLOR_YELLOW='\033[1;33m'
COLOR_RED='\033[0;31m'
COLOR_CYAN='\033[0;36m'
COLOR_BOLD='\033[1m'

log_info()  { echo -e "${COLOR_GREEN}[INFO]${COLOR_RESET}  $*"; }
log_warn()  { echo -e "${COLOR_YELLOW}[WARN]${COLOR_RESET}  $*"; }
log_error() { echo -e "${COLOR_RED}[ERROR]${COLOR_RESET} $*"; }
log_step()  { echo -e "\n${COLOR_CYAN}══ $* ══${COLOR_RESET}\n"; }

# ── Detect installed tools ─────────────────────────────────────────────────

check_tool() {
    if command -v "$1" &>/dev/null; then
        log_info "Found $1: $($1 --version 2>&1 | head -1)"
        return 0
    else
        log_warn "$1 is not installed"
        return 1
    fi
}

detect_os() {
    case "$(uname -s)" in
        Linux*)  echo "linux" ;;
        Darwin*) echo "macos" ;;
        MINGW*|MSYS*) echo "windows" ;;
        *)       echo "unknown" ;;
    esac
}

# ── Installation functions ─────────────────────────────────────────────────

install_system_deps() {
    log_step "System dependencies"

    local os
    os=$(detect_os)

    case "$os" in
        linux)
            if command -v apt-get &>/dev/null; then
                log_info "Detected apt-based system"
                sudo apt-get update -qq
                sudo apt-get install -y -qq \
                    curl wget git unzip zip \
                    ca-certificates gnupg lsb-release \
                    libpq-dev libzip-dev libonig-dev \
                    openssl ssl-cert 2>/dev/null || true
            elif command -v yum &>/dev/null; then
                log_info "Detected yum-based system"
                sudo yum install -y curl wget git unzip zip \
                    openssl ca-certificates 2>/dev/null || true
            fi
            ;;
        macos)
            if command -v brew &>/dev/null; then
                log_info "Detected Homebrew"
                brew install curl wget git openssl 2>/dev/null || true
            fi
            ;;
        *)
            log_warn "Unknown OS. Install system packages manually."
            ;;
    esac
}

install_php() {
    log_step "PHP / Composer dependencies"

    cd "$ROOT_DIR/backend"

    if [[ ! -f "composer.json" ]]; then
        log_error "composer.json not found in backend/"
        return 1
    fi

    check_tool php
    check_tool composer

    local args=("install" "--no-progress" "--prefer-dist" "--optimize-autoloader")

    if [[ "${DEV:-true}" == "true" ]]; then
        log_info "Installing with dev dependencies"
    else
        args+=("--no-dev")
        log_info "Installing without dev dependencies"
    fi

    composer "${args[@]}"
    log_info "PHP dependencies installed successfully"
}

install_python() {
    log_step "Python dependencies"

    cd "$ROOT_DIR/ai-gateway"

    if [[ ! -f "requirements.txt" ]]; then
        log_error "requirements.txt not found in ai-gateway/"
        return 1
    fi

    check_tool python3

    local venv_dir=".venv"

    if [[ ! -d "$venv_dir" ]]; then
        log_info "Creating Python virtual environment..."
        python3 -m venv "$venv_dir"
        log_info "Virtual environment created at $venv_dir"
    else
        log_info "Virtual environment already exists"
    fi

    source "$venv_dir/bin/activate"

    pip install --upgrade pip setuptools wheel --quiet
    pip install -r requirements.txt --quiet

    if [[ "${DEV:-true}" == "true" ]]; then
        pip install pytest pytest-cov pytest-asyncio black ruff mypy --quiet
        log_info "Dev dependencies installed"
    fi

    log_info "Python dependencies installed successfully"
    log_info "Activate with: source $venv_dir/bin/activate"
}

install_frontend() {
    log_step "Frontend dependencies"

    cd "$ROOT_DIR/backend"

    if [[ ! -f "package.json" ]]; then
        log_warn "package.json not found in backend/ — skipping frontend"
        return 0
    fi

    check_tool node
    check_tool npm

    local args=("ci")

    if [[ "${DEV:-true}" != "true" ]]; then
        args=("ci" "--only=production")
    fi

    npm "${args[@]}"
    log_info "Frontend dependencies installed"
}

install_docker_images() {
    log_step "Pulling Docker images"

    if ! command -v docker &>/dev/null; then
        log_warn "Docker not found — skipping image pulls"
        return 0
    fi

    local images=(
        "postgres:16-alpine"
        "redis:7-alpine"
        "nginx:1.27-alpine"
        "php:8.3-fpm-alpine"
    )

    for img in "${images[@]}"; do
        log_info "Pulling $img..."
        docker pull "$img" --quiet 2>/dev/null || log_warn "Failed to pull $img"
    done

    log_info "Docker images pulled"
}

# ── Main ───────────────────────────────────────────────────────────────────

main() {
    echo -e "${COLOR_BOLD}${COLOR_CYAN}"
    echo "╔══════════════════════════════════════════╗"
    echo "║   Corex.dev Dependency Installer v1.0    ║"
    echo "╚══════════════════════════════════════════╝"
    echo -e "${COLOR_RESET}"

    DEV=true
    INSTALL_PHP=false
    INSTALL_PYTHON=false
    INSTALL_FRONTEND=false
    INSTALL_ALL=true
    INSTALL_DOCKER=false

    if [[ $# -eq 0 ]]; then
        set -- --all
    fi

    for arg in "$@"; do
        case "$arg" in
            --php)      INSTALL_PHP=true; INSTALL_ALL=false ;;
            --python)   INSTALL_PYTHON=true; INSTALL_ALL=false ;;
            --frontend) INSTALL_FRONTEND=true; INSTALL_ALL=false ;;
            --docker)   INSTALL_DOCKER=true ;;
            --all)      INSTALL_ALL=true ;;
            --dev)      DEV=true ;;
            --no-dev)   DEV=false ;;
            --prod)     DEV=false; INSTALL_ALL=true ;;
            --help|-h)
                echo "Usage: $0 [OPTIONS]"
                echo ""
                echo "Options:"
                echo "  --all       Install all dependencies (default)"
                echo "  --php       PHP/Composer only"
                echo "  --python    Python only"
                echo "  --frontend  Frontend/npm only"
                echo "  --docker    Pull Docker images"
                echo "  --dev       Include dev dependencies (default)"
                echo "  --no-dev    Skip dev dependencies"
                echo "  --prod      Alias for --all --no-dev"
                echo "  --help      Show this help"
                exit 0
                ;;
        esac
    done

    install_system_deps

    if [[ "$INSTALL_ALL" == "true" ]]; then
        install_php
        install_python
        install_frontend
    else
        [[ "$INSTALL_PHP" == "true" ]] && install_php
        [[ "$INSTALL_PYTHON" == "true" ]] && install_python
        [[ "$INSTALL_FRONTEND" == "true" ]] && install_frontend
    fi

    [[ "$INSTALL_DOCKER" == "true" ]] && install_docker_images

    log_step "All done!"
    log_info "Run ./scripts/init-db.sh --seed to set up the database"
}

main "$@"
