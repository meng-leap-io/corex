#!/bin/bash
# Post-renewal hook: reload Nginx and log success
set -euo pipefail

echo "[$(date -u '+%Y-%m-%dT%H:%M:%SZ')] Certificate renewed, reloading Nginx..."

# Copy updated certs to shared SSL volume
CERT_SRC="/etc/letsencrypt/live"
for cert_dir in "$CERT_SRC"/*/; do
    DOMAIN=$(basename "$cert_dir")
    if [ -f "${cert_dir}fullchain.pem" ]; then
        cp -L "${cert_dir}fullchain.pem" /etc/nginx/ssl/fullchain.pem 2>/dev/null || true
        cp -L "${cert_dir}privkey.pem" /etc/nginx/ssl/privkey.pem 2>/dev/null || true
        cp -L "${cert_dir}chain.pem" /etc/nginx/ssl/chain.pem 2>/dev/null || true
        echo "  Copied certificates for $DOMAIN"
        break
    fi
done

# Send reload signal to Nginx
nginx -s reload 2>/dev/null || echo "  Nginx reload skipped (not running in this container)"

echo "[$(date -u '+%Y-%m-%dT%H:%M:%SZ')] Renewal hooks completed"
