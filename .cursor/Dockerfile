FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    unzip \
    zip \
    php8.3-cli \
    php8.3-common \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-curl \
    php8.3-zip \
    php8.3-mysql \
    php8.3-sqlite3 \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --2 --install-dir=/usr/local/bin --filename=composer \
    && rm -f /tmp/composer-setup.php \
    && php -v \
    && composer --version
