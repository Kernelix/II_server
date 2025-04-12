FROM php:8.3-fpm

# Install dependencies and PHP extensions in a single RUN command to reduce layers
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install xdebug redis \
    && docker-php-ext-enable xdebug redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN apt-get update && apt-get install -y redis-tools
# Copy PHP configuration files
COPY ./conf/php.ini /usr/local/etc/php/conf.d/
COPY ./conf/xdebug.ini /usr/local/etc/php/conf.d/
RUN mv /usr/local/etc/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Установка PHP-CS-Fixer и PHP_CodeSniffer
RUN composer global require friendsofphp/php-cs-fixer squizlabs/php_codesniffer \
    && ln -s /root/.composer/vendor/bin/php-cs-fixer /usr/local/bin/php-cs-fixer \
    && ln -s /root/.composer/vendor/bin/phpcs /usr/local/bin/phpcs \
    && ln -s /root/.composer/vendor/bin/phpcbf /usr/local/bin/phpcbf

RUN git config --global --add safe.directory /var/www

WORKDIR /var/www
