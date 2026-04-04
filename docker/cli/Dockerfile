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

# Copy the library source
COPY . /opt/php-api-builder

# Install dependencies
RUN cd /opt/php-api-builder && composer install --no-dev --no-interaction --optimize-autoloader

# Create a global symlink for the CLI
RUN ln -s /opt/php-api-builder/bin/api /usr/local/bin/api

WORKDIR /app

ENTRYPOINT ["php", "/usr/local/bin/api"]
CMD ["--help"]
