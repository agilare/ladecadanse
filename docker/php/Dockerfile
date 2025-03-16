FROM php:8.3-apache
RUN \
    # Install Debian packages
    apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y --no-install-recommends automake git libpng-dev libzip-dev unzip wget zlib1g-dev zip && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* && \

    # Install PHP extensions
    docker-php-ext-install mysqli && \
    docker-php-ext-install gd && \
    docker-php-ext-install zip && \

    # Install Xdebug according to wizard instruction (https://xdebug.org/wizard)
    wget https://xdebug.org/files/xdebug-3.4.2.tgz && \
    tar -xvzf xdebug-3.4.2.tgz && \
    cd xdebug-3.4.2 && \
    phpize && \
    ./configure && \
    make && \
    cp modules/xdebug.so /usr/local/lib/php/extensions/no-debug-non-zts-20230831/ && \
    echo zend_extension=xdebug > /usr/local/etc/php/conf.d/99-xdebug.ini && \
    echo xdebug.mode=debug >> /usr/local/etc/php/conf.d/99-xdebug.ini && \
    echo xdebug.client_host=host.docker.internal >> /usr/local/etc/php/conf.d/99-xdebug.ini && \
    echo xdebug.client_port=9000 >> /usr/local/etc/php/conf.d/99-xdebug.ini
