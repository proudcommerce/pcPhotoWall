# Multi-stage Dockerfile for PC PhotoWall
# Stage 1: Builder - Install dependencies and prepare application
FROM php:8.4-apache AS builder

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libxpm-dev \
    libheif-dev \
    imagemagick \
    libmagickwand-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mysqli gd exif zip

# Install ImageMagick extension
RUN pecl install imagick \
    && docker-php-ext-enable imagick

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (for better layer caching)
COPY app/composer.json app/composer.lock* ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application files
COPY app/ ./

# Remove .env file if present (should use mounted .env at runtime)
RUN rm -f .env

# Run composer scripts if needed
RUN composer dump-autoload --optimize

# Stage 2: Production - Minimal runtime image
FROM php:8.4-apache AS production

# Install only runtime dependencies (no build tools)
RUN apt-get update && apt-get install -y \
    libzip5 \
    libpng16-16t64 \
    libjpeg62-turbo \
    libfreetype6 \
    libwebp7 \
    libxpm4 \
    libheif1 \
    imagemagick \
    libmagickwand-7.q16hdri-10 \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Copy PHP extensions from builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Configure PHP settings
RUN echo 'upload_max_filesize = 10G' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'post_max_size = 10G' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'max_file_uploads = 20' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo 'memory_limit = 256M' >> /usr/local/etc/php/conf.d/uploads.ini

# Enable Apache modules
RUN a2enmod rewrite headers

# Copy Apache VirtualHost configuration
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy application and dependencies from builder
COPY --from=builder --chown=www-data:www-data /var/www/html ./

# Create required directories with proper permissions
RUN mkdir -p uploads data logs \
    && chown -R www-data:www-data uploads data logs \
    && chmod -R 755 uploads data logs

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start Apache in foreground
CMD ["apache2-foreground"]
