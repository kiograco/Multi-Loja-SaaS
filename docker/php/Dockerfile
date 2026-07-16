FROM php:8.3-cli-alpine

# System deps
RUN apk add --no-cache \
        postgresql-dev \
        autoconf \
        g++ \
        make \
        linux-headers \
    && docker-php-ext-install pdo pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make linux-headers

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies first (better layer caching)
COPY composer.json ./
RUN composer install --no-interaction --no-scripts --prefer-dist || true

COPY . .
RUN composer install --no-interaction --prefer-dist

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
