# syntax=docker/dockerfile:1

#######################################################################
# Stage 1 – builder: PHP extensions + Composer + vendor/ install      #
#######################################################################
FROM php:8.3-apache AS builder

ARG DEBIAN_FRONTEND=noninteractive

# Install system dependencies and build tools
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl libsqlite3-dev inotify-tools \
        build-essential autoconf libinotifytools0-dev \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Build inotify from source
RUN cd /tmp && \
    git clone https://github.com/arnaud-lb/php-inotify.git && \
    cd php-inotify && \
    phpize && \
    ./configure && \
    make && \
    make install && \
    cp modules/inotify.so /tmp/inotify.so

# Create inotify config file
RUN PHP_INI_PATH=$(php -i | grep "Scan this dir for additional .ini files" | sed -n 's/.*=> \(.*\)/\1/p' | head -n 1) \
    && echo "extension=inotify.so" > "$PHP_INI_PATH/inotify.ini"

# Verify inotify installation in builder
RUN php -m | grep inotify

WORKDIR /var/www

# Copy application code with correct permissions
COPY --chown=www-data:www-data . /var/www

# Install Composer + dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader --prefer-dist

# Set writable permissions only for required directories
RUN chmod -R 775 storage bootstrap/cache

#######################################################################
# Stage 2 – production: Secure Apache + PHP + App Code + Node.js     #
#######################################################################
FROM php:8.3-apache AS production

ARG DEBIAN_FRONTEND=noninteractive
ARG UID=1000
ARG GID=33
ARG USER_NAME=isl
ARG GROUP_NAME=www-data

# Install runtime dependencies and Node.js
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        libsqlite3-0 inotify-tools libinotifytools0 \
        curl gnupg && \
    curl -sL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false && \
    apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Enable pre-built inotify extension
COPY --from=builder /tmp/inotify.so /usr/local/lib/php/extensions/no-debug-non-zts-20230831/inotify.so
COPY --from=builder /usr/local/etc/php/conf.d/inotify.ini /usr/local/etc/php/conf.d/inotify.ini

# Verify installations
RUN php -m | grep inotify && node -v

# Create restricted user
RUN if ! getent group ${GID} >/dev/null; then \
        addgroup --gid ${GID} ${GROUP_NAME}; \
    else \
        existing_group=$(getent group ${GID} | cut -d: -f1); \
        if [ "$existing_group" != "${GROUP_NAME}" ]; then \
            groupmod -n ${GROUP_NAME} ${existing_group}; \
        fi; \
    fi && \
    mkdir -p /home/${USER_NAME} && \
    adduser --uid ${UID} --gid ${GID} --system --disabled-password --home /home/${USER_NAME} ${USER_NAME} && \
    chown -R ${USER_NAME}:${GROUP_NAME} /home/${USER_NAME} && \
    chsh -s /sbin/nologin ${USER_NAME}

# Apache configuration
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Copy application code with correct ownership
COPY --from=builder --chown=${USER_NAME}:${GROUP_NAME} /var/www /var/www

# Production entrypoint
COPY docker/common/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && \
    chmod +x /usr/local/bin/entrypoint.sh

# Set writable permissions only for required directories
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

WORKDIR /var/www
EXPOSE 80
USER ${USER_NAME}
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]

#######################################################################
# Stage 3 – development: Production base + Dev tools                 #
#######################################################################
FROM production AS development

# Install additional development tools
USER root
RUN apt-get update && \
    apt-get install -y --no-install-recommends git unzip && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy Composer from builder
COPY --from=builder /usr/local/bin/composer /usr/local/bin/composer

# Development entrypoint
COPY docker/development/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && \
    chmod +x /usr/local/bin/entrypoint.sh

USER ${USER_NAME}
