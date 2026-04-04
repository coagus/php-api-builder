FROM php:8.4-cli

# System dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    libpq-dev \
    sqlite3 \
    libsqlite3-dev \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    zip

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install the library globally
RUN composer global require coagus/php-api-builder --no-interaction

# Add global composer bin to PATH
ENV PATH="/root/.composer/vendor/bin:${PATH}"

WORKDIR /app

ENTRYPOINT ["php", "api"]
CMD ["--help"]
