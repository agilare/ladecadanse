FROM php:7.4-apache
RUN apt-get update && apt-get install -y zlib1g-dev libpng-dev zip
RUN docker-php-ext-install mysqli && \
    docker-php-ext-install gd
