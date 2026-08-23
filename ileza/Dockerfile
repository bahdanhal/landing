FROM php:8.5-fpm-alpine AS php-base

RUN apk add --no-cache postgresql-dev sqlite-dev \
    && docker-php-ext-install pdo_pgsql pdo_sqlite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

FROM php-base AS app
COPY composer.json composer.lock* ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

COPY .env.example .env
COPY bin ./bin
COPY config ./config
COPY public ./public
COPY src ./src
COPY templates ./templates
COPY translations ./translations
COPY specs ./specs
COPY migrations ./migrations
RUN composer dump-autoload --classmap-authoritative --no-dev \
    && APP_ENV=prod APP_DEBUG=0 php bin/console cache:warmup \
    && mkdir -p var/audit-cache var/audit-logs var/contact-leads var/rate-limits var/market-data var/analytics-data \
    && chown -R www-data:www-data var

USER www-data
EXPOSE 9000

FROM php-base AS test
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --prefer-dist --no-interaction --no-progress --no-scripts
COPY .env.example .env
COPY bin ./bin
COPY config ./config
COPY public ./public
COPY src ./src
COPY templates ./templates
COPY translations ./translations
COPY specs ./specs
COPY migrations ./migrations
COPY tests ./tests
COPY phpunit.xml.dist ./phpunit.xml.dist
COPY phpcs.xml.dist ./phpcs.xml.dist
COPY phpstan.neon.dist ./phpstan.neon.dist
RUN composer dump-autoload --classmap-authoritative
CMD ["vendor/bin/phpunit"]

FROM caddy:2.11-alpine AS web
WORKDIR /app
COPY public ./public
COPY Caddyfile /etc/caddy/Caddyfile
EXPOSE 80
