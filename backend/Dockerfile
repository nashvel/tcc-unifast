# TCC UniFAST API — Laravel 13 on FrankenPHP (PHP 8.4).
#
# PHP 8.4 is required, not optional: composer.lock pins Symfony 8.1 components
# that declare php >=8.4.1.
FROM dunglas/frankenphp:1-php8.4

# gd    -> phpoffice/phpspreadsheet (via maatwebsite/excel) and barryvdh/laravel-dompdf
# zip   -> phpspreadsheet xlsx read/write
# intl  -> phpspreadsheet formatting
# Others are standard Laravel runtime requirements.
RUN install-php-extensions \
        gd \
        zip \
        intl \
        exif \
        bcmath \
        pdo_mysql \
        opcache

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install dependencies first so the layer caches independently of app code.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .

# Runs package:discover via post-autoload-dump.
RUN composer dump-autoload --optimize --no-dev --no-interaction

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Railway overrides PORT at runtime; this is the local default.
ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["entrypoint"]
