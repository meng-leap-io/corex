FROM certbot/certbot:v2.9.0

RUN apk add --no-cache bash curl jq

COPY docker/nginx/certbot/renew.sh /opt/renew.sh
COPY docker/nginx/certbot/cli.ini /etc/letsencrypt/cli.ini

RUN chmod +x /opt/renew.sh && \
    mkdir -p /var/www/certbot /etc/letsencrypt/renewal-hooks/post

COPY docker/nginx/certbot/hooks/ /etc/letsencrypt/renewal-hooks/
RUN chmod +x /etc/letsencrypt/renewal-hooks/*/*.sh 2>/dev/null || true

VOLUME ["/etc/letsencrypt", "/var/www/certbot", "/var/log/letsencrypt"]

ENTRYPOINT []
CMD ["/opt/renew.sh"]
