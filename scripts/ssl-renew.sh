#!/bin/bash
# Corex.dev SSL Certificate Renewal Script
# Usage: ./scripts/ssl-renew.sh [issue|renew|check|force]
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_DIR"

LOG_FILE="storage/logs/ssl-renew.log"
mkdir -p "$(dirname "$LOG_FILE")"

log() {
    echo "[$(date -u '+%Y-%m-%dT%H:%M:%SZ')] $*" | tee -a "$LOG_FILE"
}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

check_prerequisites() {
    log "Checking prerequisites..."

    if ! command -v docker &>/dev/null; then
        echo -e "${RED}Error: docker is not installed${NC}"
        exit 1
    fi

    if ! docker compose version &>/dev/null; then
        echo -e "${YELLOW}Warning: docker compose plugin not found, trying docker-compose${NC}"
    fi

    log "Prerequisites OK"
}

ensure_directories() {
    mkdir -p docker/nginx/ssl
    mkdir -p docker/nginx/certbot/logs
    log "Directories created"
}

issue_initial() {
    log "Issuing initial Let's Encrypt certificates (staging test first)..."

    echo -e "${YELLOW}Step 1: Testing with staging environment...${NC}"

    docker compose run --rm --entrypoint "" certbot \
        certbot certonly --webroot \
        --webroot-path /var/www/certbot \
        --staging \
        --email admin@corex.dev \
        --agree-tos \
        --non-interactive \
        -d corex.dev -d console.corex.dev -d api.corex.dev \
        2>&1 | tee -a "$LOG_FILE"

    if [ $? -ne 0 ]; then
        echo -e "${RED}Staging test failed. Check the logs.${NC}"
        exit 1
    fi

    echo -e "${GREEN}Staging test passed!${NC}"

    echo -e "${YELLOW}Step 2: Issuing production certificates...${NC}"

    docker compose run --rm --entrypoint "" certbot \
        certbot certonly --webroot \
        --webroot-path /var/www/certbot \
        --email admin@corex.dev \
        --agree-tos \
        --non-interactive \
        --force-renewal \
        -d corex.dev -d console.corex.dev -d api.corex.dev \
        2>&1 | tee -a "$LOG_FILE"

    if [ $? -ne 0 ]; then
        echo -e "${RED}Certificate issuance failed. Check the logs.${NC}"
        exit 1
    fi

    echo -e "${GREEN}Certificates issued successfully!${NC}"
}

renew_certificates() {
    log "Renewing certificates..."

    docker compose run --rm --entrypoint "" certbot \
        certbot renew \
        --non-interactive \
        --agree-tos \
        2>&1 | tee -a "$LOG_FILE"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}Certificates renewed successfully${NC}"
    else
        echo -e "${RED}Renewal failed${NC}"
        exit 1
    fi
}

force_renew() {
    log "Force renewing certificates..."

    docker compose run --rm --entrypoint "" certbot \
        certbot renew \
        --non-interactive \
        --agree-tos \
        --force-renewal \
        2>&1 | tee -a "$LOG_FILE"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}Certificates force-renewed successfully${NC}"
    else
        echo -e "${RED}Force renewal failed${NC}"
        exit 1
    fi
}

check_expiry() {
    log "Checking certificate expiry..."

    docker compose run --rm --entrypoint "" certbot \
        certbot certificates \
        2>&1 | tee -a "$LOG_FILE"

    echo ""
    echo "=== Certificate Expiry Details ==="
    for cert_dir in docker/nginx/ssl/live/*/ 2>/dev/null; do
        if [ -f "${cert_dir}cert.pem" ]; then
            openssl x509 -enddate -noout -in "${cert_dir}cert.pem"
        fi
    done
}

reload_nginx() {
    log "Reloading Nginx to pick up new certificates..."

    docker compose exec nginx nginx -s reload 2>&1 | tee -a "$LOG_FILE" || true

    if docker compose ps nginx --format '{{.Status}}' | grep -q healthy; then
        echo -e "${GREEN}Nginx reloaded successfully${NC}"
    else
        echo -e "${YELLOW}Nginx reload sent (check container status)${NC}"
    fi
}

setup_cron() {
    log "Setting up cron job for automatic renewal..."

    CRON_JOB="0 3 * * * cd $PROJECT_DIR && ./scripts/ssl-renew.sh renew >> $LOG_FILE 2>&1"

    if crontab -l 2>/dev/null | grep -q "ssl-renew.sh"; then
        echo -e "${YELLOW}Cron job already exists, updating...${NC}"
        (crontab -l 2>/dev/null | grep -v "ssl-renew.sh"; echo "$CRON_JOB") | crontab -
    else
        (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    fi

    echo -e "${GREEN}Cron job installed: runs daily at 3 AM${NC}"
}

print_status() {
    echo ""
    echo "========================================"
    echo "  Corex.dev SSL Certificate Status"
    echo "========================================"
    echo ""

    if [ -f docker/nginx/ssl/fullchain.pem ]; then
        echo "Certificate: $(openssl x509 -subject -noout -in docker/nginx/ssl/fullchain.pem 2>/dev/null)"
        echo "Issuer: $(openssl x509 -issuer -noout -in docker/nginx/ssl/fullchain.pem 2>/dev/null)"
        echo "Valid from: $(openssl x509 -startdate -noout -in docker/nginx/ssl/fullchain.pem 2>/dev/null)"
        echo "Expires: $(openssl x509 -enddate -noout -in docker/nginx/ssl/fullchain.pem 2>/dev/null)"
        echo "SHA256 Fingerprint: $(openssl x509 -fingerprint -sha256 -noout -in docker/nginx/ssl/fullchain.pem 2>/dev/null)"
    else
        echo -e "${RED}No certificate found at docker/nginx/ssl/fullchain.pem${NC}"
        echo "Run './scripts/ssl-renew.sh issue' to obtain one"
    fi

    echo ""
    echo "Cron job: $(crontab -l 2>/dev/null | grep ssl-renew || echo 'Not installed (run setup-cron)')"
    echo ""
}

# Main
case "${1:-status}" in
    issue)
        check_prerequisites
        ensure_directories
        issue_initial
        reload_nginx
        setup_cron
        ;;
    renew)
        check_prerequisites
        renew_certificates
        reload_nginx
        ;;
    force)
        check_prerequisites
        force_renew
        reload_nginx
        ;;
    check)
        check_prerequisites
        check_expiry
        ;;
    setup-cron)
        setup_cron
        ;;
    status)
        print_status
        ;;
    *)
        echo "Usage: $0 {issue|renew|force|check|setup-cron|status}"
        echo ""
        echo "  issue       Issue initial Let's Encrypt certificates (staging test first)"
        echo "  renew       Check and renew certificates if needed"
        echo "  force       Force renewal of all certificates"
        echo "  check       Show certificate expiry dates"
        echo "  setup-cron  Install cron job for auto-renewal"
        echo "  status      Show current certificate status"
        exit 1
        ;;
esac
