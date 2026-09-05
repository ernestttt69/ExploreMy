#!/bin/sh
set -eu

# Render's mounted secret may only be readable by root. Give Apache a private
# runtime copy outside the public directory before Laravel caches its settings.
if [ -n "${MYSQL_ATTR_SSL_CA:-}" ]; then
    if [ ! -r "$MYSQL_ATTR_SSL_CA" ]; then
        echo "Database CA certificate is missing or unreadable. Check MYSQL_ATTR_SSL_CA and the Render secret file." >&2
        exit 1
    fi
    install -d -m 0750 -o root -g www-data /run/exploremy
    install -m 0640 -o root -g www-data "$MYSQL_ATTR_SSL_CA" /run/exploremy/mysql-ca.pem
    export MYSQL_ATTR_SSL_CA=/run/exploremy/mysql-ca.pem
    php -r 'if (openssl_x509_read(file_get_contents(getenv("MYSQL_ATTR_SSL_CA"))) === false) { fwrite(STDERR, "Database CA certificate is not valid PEM.\n"); exit(1); }'
fi

php artisan config:cache
# Check database access with the same account that serves website requests.
runuser -u www-data -- php artisan migrate --force
exec apache2-foreground
