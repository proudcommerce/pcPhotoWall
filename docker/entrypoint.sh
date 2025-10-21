#!/bin/bash
set -e

# Entrypoint script for PC PhotoWall
# Ensures Composer dependencies are installed before starting Apache

echo "Starting PC PhotoWall entrypoint..."

# Check if vendor directory exists and has dependencies
if [ ! -d "/var/www/html/vendor" ] || [ ! -f "/var/www/html/vendor/autoload.php" ]; then
    echo "Composer dependencies not found. Installing..."
    
    # Check if composer.json exists
    if [ ! -f "/var/www/html/composer.json" ]; then
        echo "ERROR: composer.json not found in /var/www/html/"
        exit 1
    fi
    
    # Install dependencies
    cd /var/www/html
    /usr/bin/composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
    
    # Run post-install scripts if they exist
    if [ -f "/var/www/html/composer.json" ]; then
        /usr/bin/composer run-script post-install-cmd --no-interaction || true
    fi
    
    echo "Composer dependencies installed successfully."
else
    echo "Composer dependencies already installed."
fi

# Ensure required directories exist with proper permissions
mkdir -p /var/www/html/uploads /var/www/html/data /var/www/html/logs
chown -R www-data:www-data /var/www/html/uploads /var/www/html/data /var/www/html/logs
chmod -R 755 /var/www/html/uploads /var/www/html/data /var/www/html/logs

echo "Starting Apache..."
exec apache2-foreground
