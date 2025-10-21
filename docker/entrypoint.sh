#!/bin/bash
set -e

# Entrypoint script for PC PhotoWall
# Ensures Composer dependencies are installed before starting Apache

echo "Starting PC PhotoWall entrypoint..."

# Check if vendor directory exists and has dependencies
if [ ! -d "/var/www/html/vendor" ] || [ ! -f "/var/www/html/vendor/autoload.php" ]; then
    echo "Composer dependencies not found. Attempting to install..."

    # Check if composer.json exists
    if [ ! -f "/var/www/html/composer.json" ]; then
        echo "ERROR: composer.json not found in /var/www/html/"
        echo "Cannot install dependencies without composer.json"
        exit 1
    fi

    # Check if Composer is available
    if command -v composer >/dev/null 2>&1; then
        echo "Installing dependencies with Composer..."
        cd /var/www/html
        composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
        echo "Composer dependencies installed successfully."
    else
        echo "ERROR: Composer not found in container"
        echo "Please either:"
        echo "  1. Install dependencies locally: cd app && composer install"
        echo "  2. Rebuild Docker image: docker-compose build --no-cache"
        exit 1
    fi
else
    echo "Composer dependencies already installed."
fi

# Ensure required directories exist with proper permissions
mkdir -p /var/www/html/uploads /var/www/html/data /var/www/html/logs
chown -R www-data:www-data /var/www/html/uploads /var/www/html/data /var/www/html/logs
chmod -R 755 /var/www/html/uploads /var/www/html/data /var/www/html/logs

echo "Starting Apache..."
exec apache2-foreground
