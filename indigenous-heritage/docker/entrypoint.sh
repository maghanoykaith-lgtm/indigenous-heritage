#!/bin/sh
set -e

# Render (and most container hosts) tell the app which port to listen on.
PORT="${PORT:-10000}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Bring the database schema up to date. Free databases can take a moment to
# wake up, so retry before giving up; the site then shows a 503 page until
# the database becomes reachable.
attempt=1
until php /var/www/html/database/migrate.php; do
    if [ "$attempt" -ge 5 ]; then
        echo "[entrypoint] Migration failed after $attempt attempts; starting web server anyway." >&2
        break
    fi
    attempt=$((attempt + 1))
    echo "[entrypoint] Retrying migration in 10 seconds (attempt $attempt of 5)..." >&2
    sleep 10
done

exec "$@"
