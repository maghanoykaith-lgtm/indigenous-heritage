# Production image: PHP 8.3 + Apache. Used by Render (render.yaml) or any Docker host.
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev ca-certificates \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd exif opcache \
    && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite headers

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-ihms.ini"
COPY docker/apache.conf /etc/apache2/conf-enabled/zz-ihms.conf
COPY docker/entrypoint.sh /usr/local/bin/ihms-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/ihms-entrypoint && chmod +x /usr/local/bin/ihms-entrypoint

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html

ENV APP_ENV=production \
    PORT=10000

EXPOSE 10000
ENTRYPOINT ["ihms-entrypoint"]
CMD ["apache2-foreground"]
