# Base stage with common dependencies
FROM php:8.4-apache AS base

# Install system dependencies in a single layer for better caching
RUN apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y --no-install-recommends \
        automake \
        git \
        libjpeg-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        unzip \
        wget \
        zlib1g-dev \
        zip && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install PHP extensions
# GD doit être configuré explicitement pour JPEG et WebP : sans cela il ne gère
# que PNG/GIF, et imagejpeg()/imagewebp() n'existent tout simplement pas, ce qui
# fait échouer l'enregistrement des flyers (voir ImageDriver2::redimensionner).
RUN docker-php-ext-configure gd --with-jpeg --with-webp && \
    docker-php-ext-install pdo pdo_mysql mysqli gd zip

# Configure PHP for better performance
RUN { \
        echo 'memory_limit=256M'; \
        echo 'upload_max_filesize=32M'; \
        echo 'post_max_size=32M'; \
        echo 'max_execution_time=300'; \
        echo 'date.timezone=UTC'; \
    } > /usr/local/etc/php/conf.d/99-custom.ini

# Prépare les répertoires inscriptibles au démarrage (voir le script pour le détail)
COPY ./docker/php/docker-entrypoint.sh /usr/local/bin/ladecadanse-entrypoint.sh
RUN chmod +x /usr/local/bin/ladecadanse-entrypoint.sh
ENTRYPOINT ["ladecadanse-entrypoint.sh"]
CMD ["apache2-foreground"]

# Development stage
FROM base AS development

# Aligne www-data sur l'utilisateur de l'hôte : le code est monté en bind mount et
# les fichiers créés par Apache doivent rester exploitables côté hôte (et
# inversement). Sans cela, sur un hôte Linux, www-data (uid 33) ne peut pas écrire
# dans des répertoires possédés par l'utilisateur hôte (uid 1000 typiquement).
# -o autorise un uid/gid déjà utilisé dans l'image, pour ne pas échouer au build.
ARG UID=1000
ARG GID=1000
RUN if [ "$GID" != "$(id -g www-data)" ]; then groupmod -o -g "$GID" www-data; fi && \
    if [ "$UID" != "$(id -u www-data)" ]; then usermod -o -u "$UID" www-data; fi && \
    chown -R www-data:www-data /var/www

# Install Xdebug for development
RUN pecl install xdebug-3.4.2 && \
    docker-php-ext-enable xdebug && \
    printf "xdebug.mode=debug\nxdebug.client_host=host.docker.internal\nxdebug.client_port=9000\n" > /usr/local/etc/php/conf.d/99-xdebug.ini

# Production stage
FROM base AS production

# Enable Apache mod_rewrite and other modules
RUN a2enmod rewrite headers deflate

# Set production environment
ENV APACHE_DOCUMENT_ROOT=/var/www/html
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data

# Copy Apache configuration
COPY ./docker/apache/ladecadanse.conf /etc/apache2/sites-available/000-default.conf
RUN a2ensite 000-default.conf

# Apache démarre en root (bind sur le port 80, écriture du pid et des logs dans
# /var/run/apache2 et /var/log/apache2) puis déprivilégie lui-même ses workers vers
# APACHE_RUN_USER. Il ne faut donc pas basculer le conteneur sur un compte non
# privilégié, sous peine d'empêcher son démarrage.
#
# Un chown du code au build serait par ailleurs sans effet : /var/www/html est
# recouvert par le bind mount au runtime. C'est l'entrypoint qui s'en charge.

# Health check — wget est fourni par le stage de base, contrairement à curl.
# 127.0.0.1 plutôt que localhost : Apache n'écoute qu'en IPv4, et localhost fait
# d'abord échouer une tentative sur ::1 à chaque sonde.
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://127.0.0.1/ || exit 1
