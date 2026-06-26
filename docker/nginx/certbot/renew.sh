#!/bin/bash
# Corex.dev Certbot Auto-Renewal Script
set -euo pipefail

DOMAINS="${DOMAINS:-corex.dev,console.corex.dev,api.corex.dev}"
EMAIL="${EMAIL:-admin@corex.dev}"
WEBROOT="/var/www/certbot"
LOG_DIR="/var/log/letsencrypt"
FIRST_RUN="${FIRST_RUN:-false}"

mkdir -p "$WEBROOT" "$LOG_DIR"

log() {
    echo "[$(date -u '+%Y-%m-%dT%H:%M:%SZ')] $*" | tee -a "$LOG_DIR/renew.log"
}

issue_certificates() {
    log "Issuing certificates for domains: $DOMAINS"

    IFS=',' read -ra DOMAIN_LIST <<< "$DOMAINS"

    for DOMAIN in "${DOMAIN_LIST[@]}"; do
        DOMAIN=$(echo "$DOMAIN" | xargs)
        certbot certonly \
            --webroot \
            --webroot-path "$WEBROOT" \
            --email "$EMAIL" \
            --agree-tos \
            --non-interactive \
            --domain "$DOMAIN" \
            --expand \
            2>&1 | tee -a "$LOG_DIR/issue.log"
        log "Certificate issued for $DOMAIN"
    done
}

renew_certificates() {
    log "Checking certificate renewals..."

    certbot renew \
        --non-interactive \
        --agree-tos \
        --deploy-hook "nginx -s reload" \
        2>&1 | tee -a "$LOG_DIR/renew.log"

    log "Renewal check complete"
}

check_expiry() {
    log "Checking certificate expiry dates..."

    for cert_dir in /etc/letsencrypt/live/*/; do
        if [ -f "${cert_dir}cert.pem" ]; then
            EXPIRY=$(openssl x509 -enddate -noout -in "${cert_dir}cert.pem" | cut -d= -f2)
            DAYS_LEFT=$(( ($(date -d "$EXPIRY" +%s) - $(date +%s)) / 86400 ))
            DOMAIN=$(basename "$cert_dir")
            log "Certificate $DOMAIN expires in $DAYS_LEFT days ($EXPIRY)"

            if [ "$DAYS_LEFT" -lt 30 ]; then
                log "WARNING: Certificate $DOMAIN expires in less than 30 days!"
            fi
        fi
    done
}

copy_to_nginx() {
    log "Copying certificates to Nginx SSL directory..."

    mkdir -p /etc/nginx/ssl
    CERT_SRC="/etc/letsencrypt/live"

    for cert_dir in "$CERT_SRC"/*/; do
        DOMAIN=$(basename "$cert_dir")
        if [ -f "${cert_dir}fullchain.pem" ]; then
            cp -L "${cert_dir}fullchain.pem" "/etc/nginx/ssl/fullchain.pem" 2>/dev/null || true
            cp -L "${cert_dir}privkey.pem" "/etc/nginx/ssl/privkey.pem" 2>/dev/null || true
            cp -L "${cert_dir}chain.pem" "/etc/nginx/ssl/chain.pem" 2>/dev/null || true
            log "Copied certificates for $DOMAIN to nginx SSL directory"
            break
        fi
    done
}

case "${1:-renew}" in
    issue)
        issue_certificates
        copy_to_nginx
        ;;
    renew)
        renew_certificates
        copy_to_nginx
        ;;
    check)
        check_expiry
        ;;
    all)
        issue_certificates
        copy_to_nginx
        ;;
    *)
        renew_certificates
        copy_to_nginx
        ;;
esac

log "Certbot operation completed"
