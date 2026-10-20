FROM composer:2.10.3@sha256:a5f59b9fd2faf31218632be4809dc6491761085e8064c31dc3b84378c48c248b AS composer

FROM dunglas/frankenphp:1.12.7-php8.4-bookworm@sha256:fa9eb2a18e7ee86fcab2e815213e79a42e3ba2a49b3fd2d4223ac6fcb11ab52e

WORKDIR /app

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip poppler-utils util-linux \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN install-php-extensions pdo_mysql

COPY composer.json composer.lock ./
COPY src/ ./src/

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

COPY config/ ./config/
COPY public/ ./public/
COPY database/ ./database/
COPY tests/ ./tests/
COPY examples/ ./examples/
COPY bin/ ./bin/
COPY phinx.php phpunit.xml ./
COPY Caddyfile /etc/frankenphp/Caddyfile
COPY docker/php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN groupadd --gid 10001 app \
    && useradd --uid 10001 --gid app --create-home --shell /usr/sbin/nologin app \
    && mkdir -p /app/storage/bncc /app/.phpunit.cache /config /data \
    && chown -R app:app /app/storage /app/.phpunit.cache /config /data \
    && setcap -r /usr/local/bin/frankenphp

ENV COMPOSER_CACHE_DIR=/tmp/composer-cache
USER 10001:10001

EXPOSE 8080

CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
