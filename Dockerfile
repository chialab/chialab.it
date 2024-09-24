ARG PHP_VERSION=8.3
ARG BASE_IMAGE=chialab/php
ARG NODE_VERSION=lts
ARG AWS_DEFAULT_REGION=eu-south-1

###
# Composer install
###
FROM --platform=$BUILDPLATFORM ${BASE_IMAGE}:${PHP_VERSION}-fpm-alpine AS composer
WORKDIR /app/

# Install dependencies
COPY --chown=www-data:www-data ./composer.json ./composer.lock /app/
COPY --chown=www-data:www-data ./plugins/Chialab/composer.json /app/plugins/Chialab/
COPY --chown=www-data:www-data ./plugins/OpenSource/composer.json /app/plugins/OpenSource/
RUN composer install --no-dev --prefer-dist --no-interaction

# Add sources and dump Composer autoloader
COPY --chown=www-data:www-data ./ /app/
RUN composer dump-autoload --optimize --no-cache

###
# NPM install & build
###
FROM --platform=$BUILDPLATFORM node:${NODE_VERSION} AS npm
WORKDIR /app/

# Install dependencies
COPY ./package.json ./yarn.lock /app/
COPY ./packages/cdk/package.json /app/packages/cdk/package.json
RUN --mount=type=secret,id=npm,required=true,target=.npmrc yarn install

# Build JS app
COPY ./ /app/
RUN yarn build

###
# Caddy final image
###
FROM caddy:2-alpine AS web

COPY ./deploy/Caddyfile ./deploy/Caddyfile.redirects /etc/caddy/
COPY --from=npm /app/webroot/ /app/webroot/
COPY --from=npm /app/plugins/Chialab/webroot/ /app/webroot/chialab/
COPY --from=npm /app/plugins/Illustratorium/webroot/ /app/webroot/illustratorium/
COPY --from=npm /app/plugins/OpenSource/webroot/ /app/webroot/open_source/

###
# PHP final image
###
FROM ${BASE_IMAGE}:${PHP_VERSION}-fpm-alpine AS app
WORKDIR /app

# Copy PHP configuration files
ARG AWS_DEFAULT_REGION
ADD --chown=www-data:www-data https://truststore.pki.rds.amazonaws.com/${AWS_DEFAULT_REGION}/${AWS_DEFAULT_REGION}-bundle.pem /etc/ssl/certs/rds.${AWS_DEFAULT_REGION}.amazonaws.com-ca.pem
COPY ./deploy/php-conf.ini /usr/local/etc/php/conf.d/chialab.ini
COPY ./deploy/phpfpm-conf.ini /usr/local/etc/php-fpm.d/zzz-chialab.conf

# Setup user and environment variables
RUN chown www-data:www-data /app
USER www-data
ENV DEBUG=0 \
    AWS_DEFAULT_REGION="${AWS_DEFAULT_REGION}" \
    LOG_DEBUG_URL="console:///?stream=php://stdout" \
    LOG_ERROR_URL="console:///?stream=php://stdout" \
    DATABASE_SSL_CA_PATH="/etc/ssl/certs/rds.${AWS_DEFAULT_REGION}.amazonaws.com-ca.pem"

# Copy application
COPY --chown=www-data:www-data ./deploy/app_local.php /app/config/app_local.php
COPY --chown=www-data:www-data --from=composer /app/ /app/
COPY --chown=www-data:www-data --from=npm /app/webroot/ /app/webroot/
COPY --chown=www-data:www-data --from=npm /app/plugins/Chialab/webroot/ /app/webroot/chialab/
COPY --chown=www-data:www-data --from=npm /app/plugins/OpenSource/webroot/ /app/webroot/open_source/
COPY --from=npm /app/plugins/Chialab/webroot/build/entrypoints.json /app/plugins/Chialab/webroot/build/entrypoints.json
COPY --from=npm /app/plugins/Illustratorium/webroot/build/entrypoints.json /app/plugins/Illustratorium/webroot/build/entrypoints.json
COPY --from=npm /app/plugins/OpenSource/webroot/build/entrypoints.json /app/plugins/OpenSource/webroot/build/entrypoints.json
RUN composer run post-install-cmd --no-interaction
