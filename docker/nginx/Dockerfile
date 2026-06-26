FROM nginx:1.27-alpine AS base

RUN apk add --no-cache curl && \
    rm -rf /var/cache/apk/*

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/ /etc/nginx/conf.d/
COPY docker/nginx/includes/ /etc/nginx/includes/

RUN mkdir -p /etc/nginx/ssl /var/cache/nginx /var/run /var/www/certbot && \
    chown -R nginx:nginx /etc/nginx/ssl /var/cache/nginx /var/run /var/www/certbot && \
    chmod -R 755 /etc/nginx/ssl && \
    chmod 755 /var/www/certbot

# Expose ports: HTTP, HTTPS, and HTTP/3 (QUIC)
EXPOSE 80 443

HEALTHCHECK --interval=15s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:80/health || curl -kf https://localhost:443/health || exit 1

USER nginx
CMD ["nginx", "-g", "daemon off;"]
