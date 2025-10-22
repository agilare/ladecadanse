FROM php:8.3-apache
RUN \
    # Install Debian packages
    apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y --no-install-recommends automake git libpng-dev libzip-dev unzip wget zlib1g-dev zip && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \
    # Install PHP extensions
    docker-php-ext-install pdo pdo_mysql mysqli gd zip && \
    # Install Xdebug via PECL
    pecl install xdebug-3.4.2 && \
    docker-php-ext-enable xdebug && \
    printf "xdebug.mode=debug\nxdebug.client_host=host.docker.internal\nxdebug.client_port=9000\n" > /usr/local/etc/php/conf.d/99-xdebug.ini
