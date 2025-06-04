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

WORKDIR /var/www
COPY . /var/www

# Install Composer + dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader --prefer-dist

#######################################################################
# Stage 2 – production: Secure Apache + PHP + App Code               #
#######################################################################
FROM php:8.3-apache AS production

ARG DEBIAN_FRONTEND=noninteractive
ARG UID=1000
ARG GID=33
ARG USER_NAME=isl
ARG GROUP_NAME=www-data

# Install runtime dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-0 inotify-tools \
    && docker-php-ext-install pdo_sqlite \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false libsqlite3-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Enable pre-built inotify extension
COPY --from=builder /tmp/inotify.so /tmp/inotify.so
RUN PHP_INI_PATH=$(php -i | grep "Scan this dir for additional .ini files" | cut -d ">" -f2 | xargs) \
    && mv /tmp/inotify.so $(php -r "echo ini_get('extension_dir');")/inotify.so \
    && echo "extension=inotify.so" > "$PHP_INI_PATH/inotify.ini"

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

# Copy application code
COPY --from=builder /var/www /var/www

# Production entrypoint
COPY docker/common/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && \
    chmod +x /usr/local/bin/entrypoint.sh

# Set permissions
RUN chown -R ${USER_NAME}:${GROUP_NAME} /var/www && \
    find /var/www -type d -exec chmod 755 {} \; && \
    find /var/www -type f -exec chmod 644 {} \; && \
    chmod -R 775 /var/www/storage /var/www/bootstrap/cache

WORKDIR /var/www
EXPOSE 80
USER ${USER_NAME}
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]

#######################################################################
# Stage 3 – development: Production base + Dev tools                 #
#######################################################################
FROM production AS development

# Install development tools and Node.js
USER root
RUN apt-get update && \
    apt-get install -y --no-install-recommends git unzip curl gnupg && \
    curl -sL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false curl gnupg && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy Composer from builder
COPY --from=builder /usr/local/bin/composer /usr/local/bin/composer

# Development entrypoint
COPY docker/development/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && \
    chmod +x /usr/local/bin/entrypoint.sh

USER ${USER_NAME}
