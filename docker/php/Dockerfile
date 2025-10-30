# Base stage with common dependencies
FROM php:8.3-apache AS base

# Install system dependencies in a single layer for better caching
RUN apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y --no-install-recommends \
        automake \
        git \
        libpng-dev \
        libzip-dev \
        unzip \
        wget \
        zlib1g-dev \
        zip && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli gd zip

# Configure PHP for better performance
RUN { \
        echo 'memory_limit=256M'; \
        echo 'upload_max_filesize=32M'; \
        echo 'post_max_size=32M'; \
        echo 'max_execution_time=300'; \
        echo 'date.timezone=UTC'; \
    } > /usr/local/etc/php/conf.d/99-custom.ini

# Development stage
FROM base AS development

# Install Xdebug for development
RUN pecl install xdebug-3.4.2 && \
    docker-php-ext-enable xdebug && \
    printf "xdebug.mode=debug\nxdebug.client_host=host.docker.internal\nxdebug.client_port=9000\n" > /usr/local/etc/php/conf.d/99-xdebug.ini

# Production stage
FROM base AS production

# Enable Apache mod_rewrite and other modules
RUN a2enmod rewrite headers deflate

# Set production environment
ENV APACHE_DOCUMENT_ROOT /var/www/html
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data

# Copy Apache configuration
COPY ./docker/apache/ladecadanse.conf /etc/apache2/sites-available/000-default.conf
RUN a2ensite 000-default.conf

# Create non-root user for security
RUN groupadd -r appuser && useradd -r -g appuser appuser

# Set proper permissions
RUN chown -R appuser:appuser /var/www/html && \
    chmod -R 755 /var/www/html

# Switch to non-root user
USER appuser

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1
