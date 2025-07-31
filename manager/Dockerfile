ARG PHP_VERSION=8.3
ARG NODE_VERSION=lts

###
# Git Clone
###
FROM --platform=$BUILDPLATFORM alpine:3 AS clone
WORKDIR /app

ARG BEDITA_MANAGER_REFSPEC=v5.12.1
RUN apk add --no-cache git \
    && git clone --depth=1 --branch="${BEDITA_MANAGER_REFSPEC}" https://github.com/bedita/manager.git .

###
# NPM install & build
###
FROM --platform=$BUILDPLATFORM node:${NODE_VERSION} AS npm
WORKDIR /app

# Install dependencies:
COPY --from=clone /app/package.json /app/yarn.lock /app/
RUN yarn install

# Build JS and CSS:
COPY --from=clone /app/ /app/
RUN yarn build

###
# Composer install
###
FROM --platform=$BUILDPLATFORM chialab/php:${PHP_VERSION}-fpm-alpine AS composer
WORKDIR /app

# Install dependencies:
COPY --from=clone /app/composer.json /app/
RUN composer install --no-dev --prefer-dist --no-interaction

# Add sources and built assets, and dump Composer autoloader:
COPY --from=clone /app/ /app/
RUN composer dump-autoload --classmap-authoritative --no-cache

###
# Caddy final image
###
FROM caddy:2-alpine AS web
COPY ./deploy/Caddyfile /etc/caddy/Caddyfile
COPY --from=npm /app/webroot /app/webroot/

###
# PHP final image
###
FROM chialab/php:${PHP_VERSION}-fpm-alpine AS app
WORKDIR /app

# Copy PHP configuration files.
COPY ./deploy/php-conf.ini /usr/local/etc/php/conf.d/manager.ini
COPY ./deploy/phpfpm-conf.ini /usr/local/etc/php-fpm.d/zzz-manager.conf

# Setup user,
RUN chown www-data:www-data /app
USER www-data
ENV DEBUG=0 \
    LOG_DEBUG_URL="console:///?stream=php://stdout" \
    LOG_ERROR_URL="console:///?stream=php://stdout"

# Copy application and run post-install commands
COPY --chown=www-data:www-data ./deploy/app_local.php /app/config/
COPY --chown=www-data:www-data --from=composer /app/ /app/
COPY --chown=www-data:www-data --from=npm /app/webroot /app/webroot/
RUN composer run post-install-cmd --no-interaction \
    && rm config/.env
